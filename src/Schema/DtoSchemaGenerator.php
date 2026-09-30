<?php

declare(strict_types=1);

namespace Slagger\Schema;

use DateTimeInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

final class DtoSchemaGenerator
{
    /**
     * Generates an OpenAPI 3.0 schema array for a given DTO class.
     *
     * @param class-string|string $dtoClass Fully qualified class name
     * @param list<string> $visited Stack to prevent circular references
     * @return array<string, mixed> OpenAPI schema array
     */
    public function generate(string $dtoClass, array $visited = []): array
    {
        if (!class_exists($dtoClass)) {
            throw new InvalidArgumentException(sprintf('Class "%s" does not exist.', $dtoClass));
        }

        if (in_array($dtoClass, $visited, true)) {
            return [
                'type' => 'object',
                'description' => sprintf('Circular reference to %s', $this->getSchemaName($dtoClass)),
            ];
        }

        $visited[] = $dtoClass;
        $reflection = new ReflectionClass($dtoClass);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $propertiesSchema = [];
        $required = [];

        foreach ($properties as $property) {
            $propName = $property->getName();
            $propType = $property->getType();

            $propSchema = $this->resolveTypeSchema($propType, $visited);

            // Handle default value if defined and not null
            if ($property->hasDefaultValue()) {
                $defaultValue = $property->getDefaultValue();
                if ($defaultValue !== null) {
                    $propSchema['default'] = $defaultValue;
                }
            }

            // Determine if field is required:
            // A property is required if it does not allow null and does not have a default value
            $isNullable = $this->isNullable($propType);
            if ($isNullable) {
                $propSchema['nullable'] = true;
            } elseif (!$property->hasDefaultValue()) {
                $required[] = $propName;
            }

            $propertiesSchema[$propName] = $propSchema;
        }

        $schema = [
            'type' => 'object',
            'properties' => $propertiesSchema,
        ];

        if (count($required) > 0) {
            $schema['required'] = array_values($required);
        }

        return $schema;
    }

    /**
     * Extracts a clean OpenAPI schema name from a fully qualified DTO class name.
     *
     * @param class-string|string $dtoClass
     */
    public function getSchemaName(string $dtoClass): string
    {
        if (class_exists($dtoClass)) {
            return (new ReflectionClass($dtoClass))->getShortName();
        }

        $parts = explode('\\', $dtoClass);

        return end($parts);
    }

    /**
     * Resolves a ReflectionType into an OpenAPI schema property array.
     *
     * @param list<string> $visited
     * @return array<string, mixed>
     */
    private function resolveTypeSchema(?ReflectionType $type, array $visited): array
    {
        if ($type === null) {
            return ['type' => 'string'];
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->resolveNamedType($type, $visited);
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $subType) {
                if ($subType instanceof ReflectionNamedType && $subType->getName() !== 'null') {
                    return $this->resolveNamedType($subType, $visited);
                }
            }
        }

        return ['type' => 'string'];
    }

    /**
     * Resolves a ReflectionNamedType into an OpenAPI schema property array.
     *
     * @param list<string> $visited
     * @return array<string, mixed>
     */
    private function resolveNamedType(ReflectionNamedType $type, array $visited): array
    {
        $typeName = $type->getName();

        return match ($typeName) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'string' => ['type' => 'string'],
            'array' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'object' => ['type' => 'object'],
            'mixed' => ['type' => 'string'],
            default => $this->resolveClassType($typeName, $visited),
        };
    }

    /**
     * Resolves a class type into an OpenAPI schema.
     *
     * @param list<string> $visited
     * @return array<string, mixed>
     */
    private function resolveClassType(string $className, array $visited): array
    {
        if (is_subclass_of($className, DateTimeInterface::class) || $className === DateTimeInterface::class) {
            return [
                'type' => 'string',
                'format' => 'date-time',
            ];
        }

        if (class_exists($className)) {
            return $this->generate($className, $visited);
        }

        return ['type' => 'string'];
    }

    /**
     * Determines whether a type allows null.
     */
    private function isNullable(?ReflectionType $type): bool
    {
        if ($type === null) {
            return true;
        }

        return $type->allowsNull();
    }
}
