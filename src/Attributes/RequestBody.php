<?php

declare(strict_types=1);

namespace Slagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class RequestBody
{
    /**
     * @param class-string $dtoClass Fully qualified class name of DTO representing request body
     * @param string $description Request body description
     */
    public function __construct(
        public readonly string $dtoClass,
        public readonly string $description = '',
    ) {
    }
}
