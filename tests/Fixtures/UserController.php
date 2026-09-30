<?php

declare(strict_types=1);

namespace Slagger\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slagger\Attributes\ApiResponse;
use Slagger\Attributes\RequestBody;
use Slagger\Attributes\Tag;

#[Tag(name: 'Users', description: 'User operations')]
final class UserController
{
    #[Tag(name: 'Users')]
    #[ApiResponse(status: 200, dtoClass: CreateUserDto::class, description: 'User found')]
    #[ApiResponse(status: 404, description: 'User not found')]
    public function get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response;
    }

    #[Tag(name: 'Users')]
    #[RequestBody(dtoClass: CreateUserDto::class, description: 'User creation payload')]
    #[ApiResponse(status: 201, dtoClass: CreateUserDto::class, description: 'User created successfully')]
    #[ApiResponse(status: 400, description: 'Invalid input data')]
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response;
    }

    #[Tag(name: 'Users')]
    #[ApiResponse(status: 204, description: 'User deleted')]
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response;
    }
}
