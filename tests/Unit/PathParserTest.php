<?php

declare(strict_types=1);

namespace Slagger\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Slagger\Parser\PathParser;

final class PathParserTest extends TestCase
{
    private PathParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PathParser();
    }

    public function testConvertsRegexPathToOpenApiPath(): void
    {
        $this->assertSame('/users', $this->parser->toOpenApiPath('/users'));
        $this->assertSame('/users/{id}', $this->parser->toOpenApiPath('/users/{id:[0-9]+}'));
        $this->assertSame('/users/{id}', $this->parser->toOpenApiPath('/users/{id:\d+}'));
        $this->assertSame(
            '/users/{userId}/posts/{postId}',
            $this->parser->toOpenApiPath('/users/{userId:[0-9]+}/posts/{postId:[a-z0-9-]+}')
        );
    }

    public function testExtractsIntegerPathParameters(): void
    {
        $params = $this->parser->extractParameters('/users/{id:[0-9]+}');

        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]['name']);
        $this->assertSame('path', $params[0]['in']);
        $this->assertTrue($params[0]['required']);
        $this->assertSame('integer', $params[0]['schema']['type']);
    }

    public function testExtractsStringPathParametersWithoutRegex(): void
    {
        $params = $this->parser->extractParameters('/users/{username}');

        $this->assertCount(1, $params);
        $this->assertSame('username', $params[0]['name']);
        $this->assertSame('path', $params[0]['in']);
        $this->assertTrue($params[0]['required']);
        $this->assertSame('string', $params[0]['schema']['type']);
        $this->assertArrayNotHasKey('pattern', $params[0]['schema']);
    }

    public function testExtractsRegexPathParametersWithPattern(): void
    {
        $params = $this->parser->extractParameters('/articles/{slug:[a-z0-9_-]+}');

        $this->assertCount(1, $params);
        $this->assertSame('slug', $params[0]['name']);
        $this->assertSame('path', $params[0]['in']);
        $this->assertTrue($params[0]['required']);
        $this->assertSame('string', $params[0]['schema']['type']);
        $this->assertSame('[a-z0-9_-]+', $params[0]['schema']['pattern']);
    }

    public function testParseReturnsBothPathAndParameters(): void
    {
        $result = $this->parser->parse('/users/{userId:[0-9]+}/posts/{slug:[a-z-]+}');

        $this->assertSame('/users/{userId}/posts/{slug}', $result['path']);
        $this->assertCount(2, $result['parameters']);

        $this->assertSame('userId', $result['parameters'][0]['name']);
        $this->assertSame('integer', $result['parameters'][0]['schema']['type']);

        $this->assertSame('slug', $result['parameters'][1]['name']);
        $this->assertSame('string', $result['parameters'][1]['schema']['type']);
        $this->assertSame('[a-z-]+', $result['parameters'][1]['schema']['pattern']);
    }
}
