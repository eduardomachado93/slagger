<?php

declare(strict_types=1);

namespace Slagger\Parser;

final class PathParser
{
    /**
     * Regex matching FastRoute / Slim placeholders:
     * e.g. {id} or {id:[0-9]+} or {year:\d{4}}
     */
    private const PLACEHOLDER_REGEX = '~\{([a-zA-Z0-9_]+)(?::((?:[^{}]+|\{[0-9,]+\})))?\}~';

    /**
     * Transforms a Slim route pattern into an OpenAPI-compatible path.
     * Example: '/users/{id:[0-9]+}' -> '/users/{id}'
     *
     * @param string $pattern Slim route pattern
     * @return string OpenAPI-compatible path
     */
    public function toOpenApiPath(string $pattern): string
    {
        $converted = preg_replace(self::PLACEHOLDER_REGEX, '{$1}', $pattern);

        return $converted ?? $pattern;
    }

    /**
     * Extracts OpenAPI path parameter specifications from a Slim route pattern.
     *
     * @param string $pattern Slim route pattern
     * @return list<array{name: string, in: string, required: bool, schema: array<string, mixed>}>
     */
    public function extractParameters(string $pattern): array
    {
        $parameters = [];

        if (preg_match_all(self::PLACEHOLDER_REGEX, $pattern, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $name = $match[1];
                $regexConstraint = $match[2] ?? null;

                $schema = [
                    'type' => 'string',
                ];

                if ($regexConstraint !== null && $regexConstraint !== '') {
                    if ($this->isIntegerPattern($regexConstraint)) {
                        $schema['type'] = 'integer';
                    } else {
                        $schema['pattern'] = $regexConstraint;
                    }
                }

                $parameters[] = [
                    'name' => $name,
                    'in' => 'path',
                    'required' => true,
                    'schema' => $schema,
                ];
            }
        }

        return $parameters;
    }

    /**
     * Parses a Slim route pattern and returns both the converted path and its parameters.
     *
     * @param string $pattern Slim route pattern
     * @return array{path: string, parameters: list<array{name: string, in: string, required: bool, schema: array<string, mixed>}>}
     */
    public function parse(string $pattern): array
    {
        return [
            'path' => $this->toOpenApiPath($pattern),
            'parameters' => $this->extractParameters($pattern),
        ];
    }

    /**
     * Checks if a route regex constraint indicates an integer type.
     */
    private function isIntegerPattern(string $pattern): bool
    {
        return $pattern === '[0-9]+'
            || $pattern === '\d+'
            || $pattern === '[0-9]'
            || $pattern === '\d'
            || (bool) preg_match('~^(\\\\d|\[0-9\])\{\d+(?:,\d*)?\}$~', $pattern);
    }
}
