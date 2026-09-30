<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Slagger\Slagger;
use Slagger\Tests\Fixtures\UserController;
use Slim\Factory\AppFactory;

$psr17Factory = new Psr17Factory();
AppFactory::setResponseFactory($psr17Factory);
$app = AppFactory::create();

// Rotas de exemplo usando as fixtures de teste
$app->get('/users/{id:[0-9]+}', [UserController::class, 'get']);
$app->post('/users', [UserController::class, 'create']);
$app->delete('/users/{id:[0-9]+}', [UserController::class, 'delete']);

$app->get('/health', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));
    return $response->withHeader('Content-Type', 'application/json');
});

// Inicializa o Slagger
$slagger = new Slagger(
    app: $app,
    title: 'Demo API',
    version: '1.0.0',
    description: 'Testando o Swagger UI gerado pelo Slagger'
);

// Registra /openapi.json e /docs
$slagger->register(
    jsonPath: '/openapi.json',
    uiPath: '/docs'
);

$app->run();