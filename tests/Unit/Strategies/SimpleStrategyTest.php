<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Strategies\SimpleStrategy;
use Illuminate\Http\Request;

describe('SimpleStrategy', function (): void {
    beforeEach(function (): void {
        $this->strategy = new SimpleStrategy();
    });

    describe('identifier', function (): void {
        test('returns simple as strategy identifier', function (): void {
            // Act
            $identifier = $this->strategy->identifier();

            // Assert
            expect($identifier)->toBe('simple');
        });
    });

    describe('Happy Paths', function (): void {
        test('matches request with empty metadata', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);

            // Act
            $result = $this->strategy->matches($request, []);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request with arbitrary metadata', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'reason' => 'Test suspension',
                'user_id' => 123,
                'expires_at' => now()->addDay(),
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches POST request with data', function (): void {
            // Arrange
            $request = Request::create('/api/test', Symfony\Component\HttpFoundation\Request::METHOD_POST, ['data' => 'value']);

            // Act
            $result = $this->strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request with custom headers', function (): void {
            // Arrange
            $request = createRequestWithHeaders([
                'X-Custom-Header' => 'test-value',
                'Authorization' => 'Bearer token',
            ]);

            // Act
            $result = $this->strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('matches request regardless of IP address', function (): void {
            // Arrange
            $request = createRequestWithIp('192.168.1.1');

            // Act
            $result = $this->strategy->matches($request);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request with null metadata values', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [
                'ip' => null,
                'country' => null,
                'fingerprint' => null,
            ];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches multiple consecutive requests consistently', function (): void {
            // Arrange
            $request1 = Request::create('/page1', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request2 = Request::create('/page2', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request3 = createRequestWithIp('10.0.0.1');

            // Act
            $result1 = $this->strategy->matches($request1);
            $result2 = $this->strategy->matches($request2);
            $result3 = $this->strategy->matches($request3);

            // Assert
            expect($result1)->toBeTrue()
                ->and($result2)->toBeTrue()
                ->and($result3)->toBeTrue();
        });

        test('matches request with very large metadata array', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = array_fill(0, 1_000, 'value');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });
    });
});
