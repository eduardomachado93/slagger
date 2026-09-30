<?php

declare(strict_types=1);

namespace Slagger\Tests\Fixtures;

final class CreateUserDto
{
    public string $name;
    public string $email;
    public ?int $age = null;

    public function __construct(string $name = '', string $email = '', ?int $age = null)
    {
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
    }
}
