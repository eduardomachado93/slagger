<?php

declare(strict_types=1);

namespace Slagger\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Slagger\Schema\DtoSchemaGenerator;
use Slagger\Tests\Fixtures\CreateUserDto;

final class DtoSchemaGeneratorTest extends TestCase
{
    private DtoSchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new DtoSchemaGenerator();
    }

    public function testGeneratesSchemaForCreateUserDto(): void
    {
        $schema = $this->generator->generate(CreateUserDto::class);

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);

        /** @var array<string, array<string, mixed>> $properties */
        $properties = $schema['properties'];

        // Name property: required string
        $this->assertArrayHasKey('name', $properties);
        $this->assertSame('string', $properties['name']['type']);
        $this->assertArrayNotHasKey('nullable', $properties['name']);

        // Email property: required string
        $this->assertArrayHasKey('email', $properties);
        $this->assertSame('string', $properties['email']['type']);
        $this->assertArrayNotHasKey('nullable', $properties['email']);

        // Age property: nullable integer
        $this->assertArrayHasKey('age', $properties);
        $this->assertSame('integer', $properties['age']['type']);
        $this->assertTrue($properties['age']['nullable']);

        // Required array
        $this->assertArrayHasKey('required', $schema);
        $this->assertSame(['name', 'email'], $schema['required']);
    }

    public function testExtractsCorrectSchemaName(): void
    {
        $this->assertSame('CreateUserDto', $this->generator->getSchemaName(CreateUserDto::class));
    }

    public function testThrowsExceptionForNonExistentClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator->generate('NonExistent\\Class\\Name');
    }

    public function testGeneratesPrimitiveTypesCorrectly(): void
    {
        $dummyDto = new class {
            public string $title;
            public int $quantity;
            public float $price;
            public bool $active;
            /** @var array<string> */
            public array $tags;
            public ?string $description = null;
        };

        $schema = $this->generator->generate(get_class($dummyDto));

        $this->assertSame('object', $schema['type']);
        /** @var array<string, array<string, mixed>> $props */
        $props = $schema['properties'];

        $this->assertSame('string', $props['title']['type']);
        $this->assertSame('integer', $props['quantity']['type']);
        $this->assertSame('number', $props['price']['type']);
        $this->assertSame('boolean', $props['active']['type']);
        $this->assertSame('array', $props['tags']['type']);
        $this->assertSame('string', $props['description']['type']);
        $this->assertTrue($props['description']['nullable']);

        $this->assertSame(['title', 'quantity', 'price', 'active', 'tags'], $schema['required']);
    }
}
