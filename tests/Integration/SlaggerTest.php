<?php

declare(strict_types=1);

namespace Slagger\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slagger\Slagger;
use Slagger\Tests\Fixtures\UserController;
use Slagger\Ui\SwaggerUiAction;
use Slim\Factory\AppFactory;

final class SlaggerTest extends TestCase
{
    private Psr17Factory $psr17Factory;

    protected function setUp(): void
    {
        $this->psr17Factory = new Psr17Factory();
        AppFactory::setResponseFactory($this->psr17Factory);
    }

    public function testGeneratesValidOpenApi3Specification(): void
    {
        $app = AppFactory::create();

        // 1. Controller routes with annotations
        $app->get('/users/{id:[0-9]+}', [UserController::class, 'get']);
        $app->post('/users', [UserController::class, 'create']);
        $app->delete('/users/{id:[0-9]+}', [UserController::class, 'delete']);

        // 2. Closure route (should gracefully fallback without crashing)
        $app->get('/health', function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            return $response;
        });

        // 3. Route with HEAD and OPTIONS (should be filtered out)
        $app->map(['GET', 'HEAD', 'OPTIONS'], '/ping', function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            return $response;
        });

        $slagger = new Slagger(
            app: $app,
            title: 'User Management API',
            version: '1.2.3',
            description: 'Demonstration API testing Slagger generator'
        );

        $spec = $slagger->generate();

        // Basic Info Assertion
        $this->assertSame('3.0.0', $spec['openapi']);
        $this->assertSame('User Management API', $spec['info']['title']);
        $this->assertSame('1.2.3', $spec['info']['version']);
        $this->assertSame('Demonstration API testing Slagger generator', $spec['info']['description']);

        // Assert Path Transformation
        $this->assertArrayHasKey('/users/{id}', $spec['paths']);
        $this->assertArrayHasKey('/users', $spec['paths']);
        $this->assertArrayHasKey('/health', $spec['paths']);
        $this->assertArrayHasKey('/ping', $spec['paths']);

        // Assert GET /users/{id}
        /** @var array<string, mixed> $getUserOp */
        $getUserOp = $spec['paths']['/users/{id}']['get'];
        $this->assertSame('GET /users/{id}', $getUserOp['summary']);
        $this->assertSame(['Users'], $getUserOp['tags']);

        // Assert Path Parameters
        $this->assertArrayHasKey('parameters', $getUserOp);
        /** @var list<array<string, mixed>> $pathParams */
        $pathParams = $getUserOp['parameters'];
        $this->assertCount(1, $pathParams);
        $this->assertSame('id', $pathParams[0]['name']);
        $this->assertSame('path', $pathParams[0]['in']);
        $this->assertTrue($pathParams[0]['required']);
        $this->assertSame('integer', $pathParams[0]['schema']['type']);

        // Assert Responses on GET /users/{id}
        $this->assertArrayHasKey('responses', $getUserOp);
        $this->assertArrayHasKey('200', $getUserOp['responses']);
        $this->assertArrayHasKey('404', $getUserOp['responses']);
        $this->assertSame('User found', $getUserOp['responses']['200']['description']);
        $this->assertSame(
            '#/components/schemas/CreateUserDto',
            $getUserOp['responses']['200']['content']['application/json']['schema']['$ref']
        );

        // Assert POST /users Request Body and Responses
        /** @var array<string, mixed> $postUserOp */
        $postUserOp = $spec['paths']['/users']['post'];
        $this->assertArrayHasKey('requestBody', $postUserOp);
        $this->assertSame('User creation payload', $postUserOp['requestBody']['description']);
        $this->assertSame(
            '#/components/schemas/CreateUserDto',
            $postUserOp['requestBody']['content']['application/json']['schema']['$ref']
        );
        $this->assertArrayHasKey('201', $postUserOp['responses']);
        $this->assertArrayHasKey('400', $postUserOp['responses']);

        // Assert DELETE /users/{id} 204 response
        /** @var array<string, mixed> $deleteUserOp */
        $deleteUserOp = $spec['paths']['/users/{id}']['delete'];
        $this->assertArrayHasKey('204', $deleteUserOp['responses']);
        $this->assertSame('User deleted', $deleteUserOp['responses']['204']['description']);

        // Assert Closure fallback behavior
        /** @var array<string, mixed> $healthOp */
        $healthOp = $spec['paths']['/health']['get'];
        $this->assertSame('GET /health', $healthOp['summary']);
        $this->assertArrayHasKey('responses', $healthOp);
        $this->assertArrayHasKey('200', $healthOp['responses']);
        $this->assertSame('OK', $healthOp['responses']['200']['description']);

        // Assert HEAD and OPTIONS are ignored
        $this->assertArrayHasKey('get', $spec['paths']['/ping']);
        $this->assertArrayNotHasKey('head', $spec['paths']['/ping']);
        $this->assertArrayNotHasKey('options', $spec['paths']['/ping']);

        // Assert Components and Schema Generation
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('schemas', $spec['components']);
        $this->assertArrayHasKey('CreateUserDto', $spec['components']['schemas']);

        /** @var array<string, mixed> $dtoSchema */
        $dtoSchema = $spec['components']['schemas']['CreateUserDto'];
        $this->assertSame('object', $dtoSchema['type']);
        $this->assertArrayHasKey('name', $dtoSchema['properties']);
        $this->assertArrayHasKey('email', $dtoSchema['properties']);
        $this->assertArrayHasKey('age', $dtoSchema['properties']);
        $this->assertSame(['name', 'email'], $dtoSchema['required']);

        // Validate complete OpenAPI 3.0 specification using cebe/php-openapi
        $openApi = $slagger->toOpenApi();
        $this->assertTrue($openApi->validate(), 'OpenAPI specification validation failed: ' . implode(', ', $openApi->getErrors()));
        $this->assertEmpty($openApi->getErrors());
    }

    public function testSwaggerUiActionRendersHtmlResponse(): void
    {
        $action = new SwaggerUiAction('/api/openapi.json');
        $request = $this->psr17Factory->createServerRequest('GET', '/docs');
        $response = $this->psr17Factory->createResponse();

        $resultResponse = $action($request, $response);

        $this->assertSame(200, $resultResponse->getStatusCode());
        $this->assertStringContainsString('text/html', $resultResponse->getHeaderLine('Content-Type'));

        $html = (string) $resultResponse->getBody();
        $this->assertStringContainsString('https://unpkg.com/swagger-ui-dist@5/swagger-ui.css', $html);
        $this->assertStringContainsString('https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js', $html);
        $this->assertStringContainsString('/api/openapi.json', $html);
    }

    public function testSlaggerInvokableServesJson(): void
    {
        $app = AppFactory::create();
        $app->get('/users', [UserController::class, 'create']);

        $slagger = new Slagger($app);
        $request = $this->psr17Factory->createServerRequest('GET', '/openapi.json');
        $response = $this->psr17Factory->createResponse();

        $jsonResponse = $slagger($request, $response);

        $this->assertSame(200, $jsonResponse->getStatusCode());
        $this->assertStringContainsString('application/json', $jsonResponse->getHeaderLine('Content-Type'));

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $jsonResponse->getBody(), true);
        $this->assertIsArray($decoded);
        $this->assertSame('3.0.0', $decoded['openapi']);
    }

    public function testSlaggerRegisterMountsEndpoints(): void
    {
        $app = AppFactory::create();
        $slagger = new Slagger($app);

        $slagger->register('/spec.json', '/documentation');

        $routes = $app->getRouteCollector()->getRoutes();
        $patterns = array_map(static fn($r) => $r->getPattern(), $routes);

        $this->assertContains('/spec.json', $patterns);
        $this->assertContains('/documentation', $patterns);
    }
}
