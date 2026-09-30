<?php

declare(strict_types=1);

namespace Slagger\Ui;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class SwaggerUiAction implements RequestHandlerInterface
{
    /**
     * @param string $jsonUrl URL path to the OpenAPI JSON endpoint
     * @param ResponseFactoryInterface|null $responseFactory PSR-17 response factory for PSR-15 handling
     */
    public function __construct(
        private readonly string $jsonUrl = '/openapi.json',
        private readonly ?ResponseFactoryInterface $responseFactory = null,
    ) {
    }

    /**
     * Slim 4 callable handler invocation.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->renderHtml());

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * PSR-15 RequestHandlerInterface handling.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->responseFactory === null) {
            throw new RuntimeException('ResponseFactoryInterface is required to use handle().');
        }

        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write($this->renderHtml());

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Renders Swagger UI HTML referencing CDN assets from unpkg.
     */
    public function renderHtml(): string
    {
        $escapedJsonUrl = htmlspecialchars($this->jsonUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Swagger UI</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@5/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@5/favicon-16x16.png" sizes="16x16" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            background: #fafafa;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" charset="UTF-8"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js" charset="UTF-8"></script>
    <script>
    window.onload = function() {
        window.ui = SwaggerUIBundle({
            url: "{$escapedJsonUrl}",
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],
            layout: "StandaloneLayout"
        });
    };
    </script>
</body>
</html>
HTML;
    }
}
