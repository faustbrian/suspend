<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Strategies\ConditionalStrategy;
use Illuminate\Http\Request;

describe('ConditionalStrategy', function (): void {
    describe('identifier', function (): void {
        test('returns conditional as strategy identifier', function (): void {
            // Arrange
            $strategy = new ConditionalStrategy(fn (): true => true);

            // Act
            $identifier = $strategy->identifier();

            // Assert
            expect($identifier)->toBe('conditional');
        });
    });

    describe('Happy Paths', function (): void {
        test('matches request when closure returns true', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(fn (): true => true);

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request based on request method in closure', function (): void {
            // Arrange
            $request = Request::create('/api/test', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $strategy = new ConditionalStrategy(
                fn (Request $req): bool => $req->method() === 'POST',
            );

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request based on request path in closure', function (): void {
            // Arrange
            $request = Request::create('/admin/dashboard', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(
                fn (Request $req): bool => str_starts_with($req->path(), 'admin/'),
            );

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request using metadata in closure', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['user_id' => 123, 'role' => 'admin'];
            $strategy = new ConditionalStrategy(
                fn (Request $req, array $meta): bool => $meta['role'] === 'admin',
            );

            // Act
            $result = $strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request with complex conditional logic', function (): void {
            // Arrange
            $request = Request::create('/api/users', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $metadata = ['attempts' => 5, 'ip' => '192.168.1.1'];
            $strategy = new ConditionalStrategy(
                fn (Request $req, array $meta): bool => $req->method() === 'POST'
                    && $meta['attempts'] > 3
                    && str_starts_with((string) $meta['ip'], '192.168.'),
            );

            // Act
            $result = $strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request when closure accesses request headers', function (): void {
            // Arrange
            $request = createRequestWithHeaders([
                'User-Agent' => 'BadBot/1.0',
            ]);
            $strategy = new ConditionalStrategy(
                fn (Request $req): bool => str_contains($req->header('User-Agent', ''), 'BadBot'),
            );

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request when closure uses external variables', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $suspendedUserId = 42;
            $metadata = ['user_id' => 42];
            $strategy = new ConditionalStrategy(
                fn (Request $req, array $meta): bool => $meta['user_id'] === $suspendedUserId,
            );

            // Act
            $result = $strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns false when closure returns false', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(fn (): false => false);

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when request method does not match condition', function (): void {
            // Arrange
            $request = Request::create('/api/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(
                fn (Request $req): bool => $req->method() === 'POST',
            );

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when metadata does not satisfy condition', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['role' => 'user'];
            $strategy = new ConditionalStrategy(
                fn (Request $req, array $meta): bool => ($meta['role'] ?? null) === 'admin',
            );

            // Act
            $result = $strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when required metadata key is missing', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [];
            $strategy = new ConditionalStrategy(
                fn (Request $req, array $meta): bool => ($meta['required_key'] ?? null) === 'value',
            );

            // Act
            $result = $strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('converts truthy values to boolean true', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(fn (): int => 1);

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('converts falsy values to boolean false', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(fn (): int => 0);

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles closure returning null as false', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(fn (): null => null);

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles closure returning string as truthy', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(fn (): string => 'yes');

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles closure returning empty string as falsy', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(fn (): string => '');

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles closure returning empty array as falsy', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(fn (): array => []);

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles closure returning non-empty array as truthy', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(fn (): array => ['value']);

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles closure with no parameters', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(fn (): true => true);

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles closure that only uses request parameter', function (): void {
            // Arrange
            $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(
                fn (Request $req): bool => $req->path() === 'test',
            );

            // Act
            $result = $strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles closure that only uses metadata parameter', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['flag' => true];
            $strategy = new ConditionalStrategy(
                fn (Request $req, array $meta): mixed => $meta['flag'] ?? false,
            );

            // Act
            $result = $strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles empty metadata array in closure', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $strategy = new ConditionalStrategy(
                fn (Request $req, array $meta): bool => $meta === [],
            );

            // Act
            $result = $strategy->matches($request, []);

            // Assert
            expect($result)->toBeTrue();
        });
    });
});
