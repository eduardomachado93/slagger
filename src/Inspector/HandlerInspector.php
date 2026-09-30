<?php

declare(strict_types=1);

namespace Slagger\Inspector;

use Closure;
use ReflectionMethod;

final class HandlerInspector
{
    /**
     * Resolves a route callable into a ReflectionMethod instance.
     * Supported formats:
     * - [Class::class, 'method'] or [$object, 'method']
     * - 'Class:method'
     * - 'Class::method'
     * - Invokable class name string 'Class' or invokable object
     *
     * Returns null if the callable is an anonymous Closure or cannot be resolved.
     *
     * @param mixed $callable Route handler callable
     * @return ReflectionMethod|null Resolved ReflectionMethod or null
     */
    public function resolve(mixed $callable): ?ReflectionMethod
    {
        if ($this->isClosure($callable)) {
            return null;
        }

        // Array format: [ClassNameOrInstance, 'methodName']
        if (is_array($callable) && count($callable) >= 2) {
            $target = $callable[0];
            $method = (string) $callable[1];

            $className = is_object($target) ? get_class($target) : (is_string($target) ? $target : null);
            if ($className !== null && class_exists($className) && method_exists($className, $method)) {
                return new ReflectionMethod($className, $method);
            }

            return null;
        }

        // String formats
        if (is_string($callable)) {
            // 'Class:method' (Slim standard notation)
            if (str_contains($callable, ':') && !str_contains($callable, '::')) {
                [$className, $method] = explode(':', $callable, 2);
                if (class_exists($className) && method_exists($className, $method)) {
                    return new ReflectionMethod($className, $method);
                }

                return null;
            }

            // 'Class::method' (Static callable notation)
            if (str_contains($callable, '::')) {
                [$className, $method] = explode('::', $callable, 2);
                if (class_exists($className) && method_exists($className, $method)) {
                    return new ReflectionMethod($className, $method);
                }

                return null;
            }

            // Invokable class name
            if (class_exists($callable) && method_exists($callable, '__invoke')) {
                return new ReflectionMethod($callable, '__invoke');
            }

            return null;
        }

        // Invokable object
        if (is_object($callable) && method_exists($callable, '__invoke')) {
            return new ReflectionMethod($callable, '__invoke');
        }

        return null;
    }

    /**
     * Determines whether the given handler callable is an anonymous Closure.
     *
     * @param mixed $callable Route handler callable
     */
    public function isClosure(mixed $callable): bool
    {
        return $callable instanceof Closure;
    }
}
