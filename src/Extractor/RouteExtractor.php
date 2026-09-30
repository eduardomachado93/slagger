<?php

declare(strict_types=1);

namespace Slagger\Extractor;

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorInterface;

final class RouteExtractor
{
    /**
     * Extracts route definitions from a Slim App or RouteCollectorInterface.
     *
     * @param App<ContainerInterface|null>|RouteCollectorInterface $source Slim App or RouteCollector instance
     * @return list<array{methods: list<string>, pattern: string, callable: mixed}>
     */
    public function extract(App|RouteCollectorInterface $source): array
    {
        $collector = $source instanceof App ? $source->getRouteCollector() : $source;
        $routes = $collector->getRoutes();

        $extracted = [];
        foreach ($routes as $route) {
            $extracted[] = [
                'methods' => array_values($route->getMethods()),
                'pattern' => $route->getPattern(),
                'callable' => $route->getCallable(),
            ];
        }

        return $extracted;
    }
}
