<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Http\Request;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * Create a test user.
 *
 * @param array<string, mixed> $attributes
 */
function createUser(array $attributes = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ], $attributes));
}

/**
 * Create a mock request with the given IP.
 */
function createRequestWithIp(string $ip): Request
{
    $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
    $request->server->set('REMOTE_ADDR', $ip);

    return $request;
}

/**
 * Create a mock request with headers.
 *
 * @param array<string, string> $headers
 */
function createRequestWithHeaders(array $headers): Request
{
    $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);

    foreach ($headers as $key => $value) {
        $request->headers->set($key, $value);
    }

    return $request;
}
