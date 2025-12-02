<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\IpMatcher;
use Cline\Suspend\Resolvers\Contracts\IpResolver;
use Cline\Suspend\Strategies\IpAddressStrategy;
use Illuminate\Http\Request;

describe('IpAddressStrategy', function (): void {
    beforeEach(function (): void {
        $this->ipResolver = Mockery::mock(IpResolver::class);
        $this->ipMatcher = new IpMatcher();
        $this->strategy = new IpAddressStrategy($this->ipResolver, $this->ipMatcher);
    });

    describe('identifier', function (): void {
        test('returns ip_address as strategy identifier', function (): void {
            // Act
            $identifier = $this->strategy->identifier();

            // Assert
            expect($identifier)->toBe('ip_address');
        });
    });

    describe('Happy Paths', function (): void {
        test('matches request when client IP equals suspended IP exactly', function (): void {
            // Arrange
            $request = createRequestWithIp('192.168.1.100');
            $metadata = ['ip' => '192.168.1.100'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('192.168.1.100');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request when client IP is within suspended CIDR range', function (): void {
            // Arrange
            $request = createRequestWithIp('192.168.1.50');
            $metadata = ['ip' => '192.168.1.0/24'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('192.168.1.50');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches IPv6 address exactly', function (): void {
            // Arrange
            $request = createRequestWithIp('2001:0db8::1');
            $metadata = ['ip' => '2001:0db8::1'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('2001:0db8::1');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches IPv6 address within CIDR range', function (): void {
            // Arrange
            $request = createRequestWithIp('2001:0db8::ff');
            $metadata = ['ip' => '2001:0db8::/32'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('2001:0db8::ff');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns false when metadata does not contain ip key', function (): void {
            // Arrange
            $request = createRequestWithIp('192.168.1.1');
            $metadata = ['country' => 'US'];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when metadata ip is null', function (): void {
            // Arrange
            $request = createRequestWithIp('192.168.1.1');
            $metadata = ['ip' => null];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when IP resolver returns null', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['ip' => '192.168.1.1'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn(null);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when client IP does not match suspended IP', function (): void {
            // Arrange
            $request = createRequestWithIp('10.0.0.1');
            $metadata = ['ip' => '192.168.1.1'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('10.0.0.1');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when client IP is outside CIDR range', function (): void {
            // Arrange
            $request = createRequestWithIp('10.0.0.1');
            $metadata = ['ip' => '192.168.1.0/24'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('10.0.0.1');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('matches request when IP is at start of CIDR range', function (): void {
            // Arrange
            $request = createRequestWithIp('192.168.1.0');
            $metadata = ['ip' => '192.168.1.0/24'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('192.168.1.0');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches request when IP is at end of CIDR range', function (): void {
            // Arrange
            $request = createRequestWithIp('192.168.1.255');
            $metadata = ['ip' => '192.168.1.0/24'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('192.168.1.255');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false for IP just outside CIDR range boundary', function (): void {
            // Arrange
            $request = createRequestWithIp('192.168.2.0');
            $metadata = ['ip' => '192.168.1.0/24'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('192.168.2.0');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles /32 CIDR notation for single IP correctly', function (): void {
            // Arrange
            $request = createRequestWithIp('192.168.1.1');
            $metadata = ['ip' => '192.168.1.1/32'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('192.168.1.1');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles very large CIDR blocks like /8', function (): void {
            // Arrange
            $request = createRequestWithIp('10.255.255.255');
            $metadata = ['ip' => '10.0.0.0/8'];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('10.255.255.255');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles empty metadata array', function (): void {
            // Arrange
            $request = createRequestWithIp('192.168.1.1');
            $metadata = [];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });
    });
});
