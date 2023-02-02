<?php

declare(strict_types=1);

namespace EDT\Parsing\Utilities;

use Throwable;

class TagNameParseException extends ParseException
{
    /**
     * @param non-empty-string $propertyName
     * @param class-string $className
     * @param non-empty-string $message
     */
    protected function __construct(
        private readonly string $propertyName,
        string $className,
        string $message,
        int $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($className, $message, $code, $previous);
    }

    /**
     * @param non-empty-string $renderedProperty
     * @param class-string $className
     */
    public static function createForEmptyVariableName(string $renderedProperty, string $className): self
    {
        $message = "Empty property name parsed in $className from @property-read: '$renderedProperty', please check if you used a '$' directly in front of the property name, otherwise what you intended to set as property name might has been interpreted as description.";

        return new self($renderedProperty, $className, $message);
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }
}
