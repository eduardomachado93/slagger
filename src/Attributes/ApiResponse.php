<?php

declare(strict_types=1);

namespace Slagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ApiResponse
{
    /**
     * @param int $status HTTP status code (e.g. 200, 201, 400)
     * @param class-string|null $dtoClass Fully qualified class name of DTO representing response body
     * @param string $description Response description
     */
    public function __construct(
        public readonly int $status,
        public readonly ?string $dtoClass = null,
        public readonly string $description = 'OK',
    ) {
    }
}
