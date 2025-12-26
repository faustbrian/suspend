<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\FingerprintMatcher;
use Cline\Suspend\Resolvers\Contracts\DeviceResolver;
use Cline\Suspend\Strategies\DeviceFingerprintStrategy;
use Illuminate\Http\Request;

describe('DeviceFingerprintStrategy', function (): void {
    beforeEach(function (): void {
        $this->deviceResolver = Mockery::mock(DeviceResolver::class);
        $this->matcher = new FingerprintMatcher();
        $this->strategy = new DeviceFingerprintStrategy($this->deviceResolver, $this->matcher);
    });

    describe('identifier', function (): void {
        test('returns device_fingerprint as strategy identifier', function (): void {
            // Act
            $identifier = $this->strategy->identifier();

            // Assert
            expect($identifier)->toBe('device_fingerprint');
        });
    });

    describe('Happy Paths', function (): void {
        test('matches request when client fingerprint equals suspended fingerprint', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'abc123def456ghi789';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request with FingerprintJS style hash', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'a1b2c3d4e5f6g7h8';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request with hyphenated fingerprint format', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'abc-123-def-456';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request with underscore-separated fingerprint', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'device_abc_123_xyz';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request with long alphanumeric fingerprint', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns false when metadata does not contain fingerprint key', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['ip' => '192.168.1.1'];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when metadata fingerprint is null', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['fingerprint' => null];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when device resolver returns null', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['fingerprint' => 'abc123'];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn(null);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when client fingerprint does not match suspended fingerprint', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['fingerprint' => 'abc123'];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('xyz789');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when fingerprints differ only in case', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['fingerprint' => 'ABC123'];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('abc123');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('matches when fingerprints differ only by surrounding whitespace', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['fingerprint' => 'abc123'];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn(' abc123 ');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles empty metadata array', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = [];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('matches fingerprint with maximum allowed length', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = str_repeat('a', 128);
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches fingerprint with minimum allowed length', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'abcd1234';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles fingerprint with all hyphens', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'a-b-c-1-2-3';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles fingerprint with all underscores', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'a_b_c_1_2_3';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles numeric-only fingerprint', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = '12345678';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles uppercase-only fingerprint', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'ABCDEFGH';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles lowercase-only fingerprint', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'abcdefgh';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles mixed case fingerprint', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $fingerprint = 'AbCdEfGh12345';
            $metadata = ['fingerprint' => $fingerprint];

            $this->deviceResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn($fingerprint);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });
    });
});
