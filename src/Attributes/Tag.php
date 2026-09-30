<?php

declare(strict_types=1);

namespace Slagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Tag
{
    /**
     * @param string $name Tag name
     * @param string $description Tag description
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
    ) {
    }
}
