<?php

declare(strict_types=1);

namespace EDT\JsonApi\ApiDocumentation;

use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\MappingAttribute;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use EDT\Parsing\Utilities\DocblockTagParser;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionProperty;
use Webmozart\Assert\Assert;
use function array_key_exists;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use RuntimeException;
use Throwable;
use UnexpectedValueException;
use function is_array;
use function is_string;
use function strlen;

/**
 * Map Doctrine or native types to OpenAPI types.
 *
 * TODO: abstract this class away from the doctrine parts and move doctrine parts into separate class in separate package (service or subclass)
 */
class AttributeTypeResolver
{
    /**
     * @var array<class-string, ReflectionClass<object>>
     */
    private array $classReflectionCache = [];

    private AnnotationReader $annotationReader;

    public function __construct()
    {
        $this->annotationReader = new AnnotationReader();
    }

    /**
     * Return a valid `cebe\OpenApi` type declaration.
     *
     * @param class-string $rootEntityClass
     * @param non-empty-list<non-empty-string> $propertyPath
     *
     * @return array{type: non-empty-string, format?: non-empty-string, description?: string}
     *
     * @throws ReflectionException
     */
    public function resolveTypeFromEntityClass(
        string $rootEntityClass,
        array $propertyPath
    ): array {
        $propertyReflection = $this->getPropertyReflection($rootEntityClass, $propertyPath);

        $id = $this->annotationReader->getPropertyAnnotation($propertyReflection, Id::class);
        if ($id instanceof Id) {
            return [
                'type'        => 'string',
                'format'      => 'uuid',
                'description' => $this->formatDescriptionFromDocblock($propertyReflection),
            ];
        }

        $column = $this->annotationReader->getPropertyAnnotation($propertyReflection, Column::class);
        if ($column instanceof Column) {
            $dqlTypeMapping = $this->mapDqlType($column);
            $dqlTypeMapping['description'] = $this->formatDescriptionFromDocblock($propertyReflection);

            return $dqlTypeMapping;
        }

        return ['type' => 'unresolved'];
    }

    /**
     * @param class-string<object>             $entityClass
     * @param non-empty-list<non-empty-string> $propertyPath
     *
     * @throws ReflectionException
     */
    private function getPropertyReflection(string $entityClass, array $propertyPath): ReflectionProperty
    {
        $propertyName = array_shift($propertyPath);
        if (array_key_exists($entityClass, $this->classReflectionCache)) {
            $entityReflection = $this->classReflectionCache[$entityClass];
        } else {
            $entityReflection = new ReflectionClass($entityClass);
            $this->classReflectionCache[$entityClass] = $entityReflection;
        }

        if (!$entityReflection->hasProperty($propertyName)) {
            throw new UnexpectedValueException("Non-existent property '$propertyName' on entity '$entityClass'.");
        }

        $propertyReflection = $entityReflection->getProperty($propertyName);
        if ([] === $propertyPath) {
            return $propertyReflection;
        }

        $mapping = $this->annotationReader->getPropertyAnnotation($propertyReflection, MappingAttribute::class);
        if (!$mapping instanceof OneToMany
            && !$mapping instanceof ManyToOne
            && !$mapping instanceof ManyToMany
            && !$mapping instanceof OneToOne
        ) {
            throw new InvalidArgumentException("No mapping annotation found for property '$propertyName' in entity class '$entityClass'.");
        }

        Assert::classExists($mapping->targetEntity);

        return $this->getPropertyReflection($mapping->targetEntity, $propertyPath);
    }

    /**
     * Map a native type from a type reflection.
     */
    private function mapNativeType(ReflectionNamedType $reflectionType): string
    {
        $nativeType = $reflectionType->getName();

        return match ($nativeType) {
            'int' => 'number',
            /*
             * Arrays can be either arrays or hashmaps in PHP. This is currently not properly
             * handled and all arrays are assumed to be just arrays.
             *
             * TODO @improve T24976
             */
            //'array' => $nativeType,
            default => $nativeType,
        };
    }

    /**
     * @return array{type: non-empty-string, format?: non-empty-string}
     */
    private function mapDqlType(Column $column): array
    {
        $format = null;
        $dqlType = $column->type;

        switch ($dqlType) {
            case 'string':
            case 'text':
                $type = 'string';
                break;

            case 'integer':
                $type = 'integer';
                $format = 'int32';
                break;

            case 'boolean':
                $type = 'boolean';
                break;

            case 'datetime':
                $type = 'string';
                $format = 'iso8601';
                break;

            default:
                $type = 'unknown: '.(is_string($dqlType) ? $dqlType : 'non-string');
        }

        $result = ['type' => $type];
        if (null !== $format) {
            $result['format'] = $format;
        }

        return $result;
    }

    /**
     * @return array{type: string} valid `cebe\OpenApi` type declaration
     *
     * @throws ReflectionException
     */
    public function resolveReturnTypeFromCallable(callable $callable): array
    {
        $functionReflection = $this->reflectReturnOfCallable($callable);
        $returnType = $this->getReturnType($functionReflection);

        if (!$returnType->isBuiltin()) {
            throw new InvalidArgumentException('Custom read callback does not return a builtin type.');
        }

        return ['type' => $this->mapNativeType($returnType)];
    }

    /**
     * @param ReflectionMethod|ReflectionFunction $reflection
     *
     * @throws InvalidArgumentException if there is no return type hint or if it could not be determined
     *
     */
    public function getReturnType(ReflectionMethod|ReflectionFunction $reflection): ReflectionNamedType
    {
        if (!$reflection->hasReturnType()) {
            // OpenAPI and JSON do not support void/mixed types

            throw new InvalidArgumentException('Custom read callback without declared return type detected.');
        }

        $returnType = $reflection->getReturnType();
        if (!$returnType instanceof ReflectionNamedType) {
            // OpenAPI and JSON do not support compound types on attributes
            // see: https://spec.openapis.org/oas/v3.1.0.html#data-types

            throw new InvalidArgumentException('Custom read callback does not return a builtin type.');
        }

        return $returnType;
    }

    /**
     * @param callable(object): mixed $customReadCallback
     *
     * @throws ReflectionException
     */
    private function reflectReturnOfCallable(callable $customReadCallback): ReflectionMethod|ReflectionFunction
    {
        if (is_array($customReadCallback)) {
            [$class, $method] = $customReadCallback;
            Assert::object($class);
            Assert::stringNotEmpty($method);

            return (new ReflectionClass($class))->getMethod($method);
        }

        if (is_string($customReadCallback)) {
            return new ReflectionFunction($customReadCallback);
        }

        return new ReflectionFunction($customReadCallback(...));
    }

    /**
     * Combine the summary and description of a docblock to a CommonMark string
     *
     * This combines the summary (first line) and description (following lines except
     * any annotations) from a docblock into a CommonMark string which can
     * be used to fuel schema descriptions.
     */
    private function formatDescriptionFromDocblock(ReflectionProperty $reflectionProperty): string
    {
        $docblock = DocblockTagParser::createDocblock($reflectionProperty);
        if (null === $docblock) {
            return '';
        }

        $result = $docblock->getSummary();

        $description = (string) $docblock->getDescription();
        if (0 < strlen($description)) {
            $result .= "\n\n$description";
        }

        return $result;
    }
}
