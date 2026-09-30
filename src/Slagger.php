<?php

declare(strict_types=1);

namespace Slagger;

use cebe\openapi\spec\OpenApi;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slagger\Attributes\ApiResponse;
use Slagger\Attributes\RequestBody;
use Slagger\Attributes\Tag;
use Slagger\Extractor\RouteExtractor;
use Slagger\Inspector\HandlerInspector;
use Slagger\Parser\PathParser;
use Slagger\Schema\DtoSchemaGenerator;
use Slagger\Ui\SwaggerUiAction;
use Slim\App;

final class Slagger
{
    /**
     * Supported HTTP methods for OpenAPI operations.
     */
    private const SUPPORTED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    private readonly RouteExtractor $routeExtractor;
    private readonly HandlerInspector $handlerInspector;
    private readonly PathParser $pathParser;
    private readonly DtoSchemaGenerator $dtoSchemaGenerator;

    /**
     * @param App<ContainerInterface|null> $app Slim application instance
     * @param string $title API Documentation title
     * @param string $version API Documentation version
     * @param string $description Optional API description
     * @param RouteExtractor|null $routeExtractor Custom route extractor
     * @param HandlerInspector|null $handlerInspector Custom handler inspector
     * @param PathParser|null $pathParser Custom path parser
     * @param DtoSchemaGenerator|null $dtoSchemaGenerator Custom schema generator
     */
    public function __construct(
        private readonly App $app,
        private readonly string $title = 'API Documentation',
        private readonly string $version = '1.0.0',
        private readonly string $description = '',
        ?RouteExtractor $routeExtractor = null,
        ?HandlerInspector $handlerInspector = null,
        ?PathParser $pathParser = null,
        ?DtoSchemaGenerator $dtoSchemaGenerator = null,
    ) {
        $this->routeExtractor = $routeExtractor ?? new RouteExtractor();
        $this->handlerInspector = $handlerInspector ?? new HandlerInspector();
        $this->pathParser = $pathParser ?? new PathParser();
        $this->dtoSchemaGenerator = $dtoSchemaGenerator ?? new DtoSchemaGenerator();
    }

    /**
     * Compiles Slim 4 routes and attributes into an OpenAPI 3.0 specification array.
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
            ],
            'paths' => [],
        ];

        if ($this->description !== '') {
            $spec['info']['description'] = $this->description;
        }

        /** @var array<string, array<string, mixed>> $schemas */
        $schemas = [];
        $routes = $this->routeExtractor->extract($this->app);

        foreach ($routes as $route) {
            $methods = $route['methods'];
            $pattern = $route['pattern'];
            $callable = $route['callable'];

            // Filter out HEAD, OPTIONS, and unsupported HTTP methods
            $validMethods = [];
            foreach ($methods as $method) {
                $upper = strtoupper($method);
                if ($upper === 'HEAD' || $upper === 'OPTIONS') {
                    continue;
                }
                if (in_array($upper, self::SUPPORTED_METHODS, true)) {
                    $validMethods[] = $upper;
                }
            }

            if (empty($validMethods)) {
                continue;
            }

            $openApiPath = $this->pathParser->toOpenApiPath($pattern);
            $pathParameters = $this->pathParser->extractParameters($pattern);
            $reflectionMethod = $this->handlerInspector->resolve($callable);

            foreach ($validMethods as $httpMethod) {
                $operation = $this->buildOperation(
                    $httpMethod,
                    $openApiPath,
                    $pathParameters,
                    $reflectionMethod,
                    $schemas
                );

                $spec['paths'][$openApiPath][strtolower($httpMethod)] = $operation;
            }
        }

        if (!empty($schemas)) {
            $spec['components'] = [
                'schemas' => $schemas,
            ];
        }

        return $spec;
    }

    /**
     * Alias for generate().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->generate();
    }

    /**
     * Encodes the OpenAPI specification to a JSON string.
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return (string) json_encode($this->generate(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Builds and validates a cebe/php-openapi OpenApi instance.
     */
    public function toOpenApi(): OpenApi
    {
        $openApi = new OpenApi($this->generate());
        $openApi->validate();

        return $openApi;
    }

    /**
     * PSR-7 callable handler to serve the OpenAPI JSON specification.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->toJson());

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Registers documentation endpoints directly into the Slim application.
     *
     * @param string $jsonPath Path to serve OpenAPI JSON (default: /openapi.json)
     * @param string $uiPath Path to serve Swagger UI (default: /docs)
     */
    public function register(string $jsonPath = '/openapi.json', string $uiPath = '/docs'): void
    {
        $this->app->get($jsonPath, $this);
        $this->app->get($uiPath, new SwaggerUiAction($jsonPath));
    }

    /**
     * Builds an OpenAPI operation specification for a single route method.
     *
     * @param list<array{name: string, in: string, required: bool, schema: array<string, mixed>}> $pathParameters
     * @param array<string, array<string, mixed>> $schemas
     * @return array<string, mixed>
     */
    private function buildOperation(
        string $httpMethod,
        string $openApiPath,
        array $pathParameters,
        ?\ReflectionMethod $reflectionMethod,
        array &$schemas,
    ): array {
        $operation = [
            'summary' => sprintf('%s %s', $httpMethod, $openApiPath),
        ];

        // 1. Tags
        if ($reflectionMethod !== null) {
            $tags = $this->extractTags($reflectionMethod);
            if (!empty($tags)) {
                $operation['tags'] = $tags;
            }
        }

        // 2. Parameters
        if (!empty($pathParameters)) {
            $operation['parameters'] = $pathParameters;
        }

        // 3. Request Body
        if ($reflectionMethod !== null) {
            $requestBodyAttrs = $reflectionMethod->getAttributes(RequestBody::class);
            if (count($requestBodyAttrs) > 0) {
                /** @var RequestBody $requestBodyAttr */
                $requestBodyAttr = $requestBodyAttrs[0]->newInstance();
                $dtoClass = $requestBodyAttr->dtoClass;

                if (class_exists($dtoClass)) {
                    $schemaName = $this->dtoSchemaGenerator->getSchemaName($dtoClass);
                    $schemas[$schemaName] = $this->dtoSchemaGenerator->generate($dtoClass);

                    $operation['requestBody'] = [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/' . $schemaName,
                                ],
                            ],
                        ],
                    ];

                    if ($requestBodyAttr->description !== '') {
                        $operation['requestBody']['description'] = $requestBodyAttr->description;
                    }
                }
            }
        }

        // 4. Responses
        $responses = [];
        if ($reflectionMethod !== null) {
            $responseAttrs = $reflectionMethod->getAttributes(ApiResponse::class);
            foreach ($responseAttrs as $attr) {
                /** @var ApiResponse $apiResponse */
                $apiResponse = $attr->newInstance();
                $statusCode = (string) $apiResponse->status;

                $responseEntry = [
                    'description' => $apiResponse->description,
                ];

                if ($apiResponse->dtoClass !== null && class_exists($apiResponse->dtoClass)) {
                    $schemaName = $this->dtoSchemaGenerator->getSchemaName($apiResponse->dtoClass);
                    $schemas[$schemaName] = $this->dtoSchemaGenerator->generate($apiResponse->dtoClass);

                    $responseEntry['content'] = [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $schemaName,
                            ],
                        ],
                    ];
                }

                $responses[$statusCode] = $responseEntry;
            }
        }

        // Fallback response if no ApiResponse attributes exist (or Closure handler)
        if (empty($responses)) {
            $responses['200'] = [
                'description' => 'OK',
            ];
        }

        $operation['responses'] = $responses;

        return $operation;
    }

    /**
     * Extracts tags from both the declaring class and the method.
     *
     * @return list<string>
     */
    private function extractTags(\ReflectionMethod $reflectionMethod): array
    {
        $tags = [];

        // Class-level tags
        $class = $reflectionMethod->getDeclaringClass();
        $classTagAttrs = $class->getAttributes(Tag::class);
        foreach ($classTagAttrs as $attr) {
            /** @var Tag $tag */
            $tag = $attr->newInstance();
            if (!in_array($tag->name, $tags, true)) {
                $tags[] = $tag->name;
            }
        }

        // Method-level tags
        $methodTagAttrs = $reflectionMethod->getAttributes(Tag::class);
        foreach ($methodTagAttrs as $attr) {
            /** @var Tag $tag */
            $tag = $attr->newInstance();
            if (!in_array($tag->name, $tags, true)) {
                $tags[] = $tag->name;
            }
        }

        return $tags;
    }
}
