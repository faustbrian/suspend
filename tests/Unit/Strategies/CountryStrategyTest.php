<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Resolvers\Contracts\IpResolver;
use Cline\Suspend\Strategies\CountryStrategy;
use Illuminate\Http\Request;

describe('CountryStrategy', function (): void {
    beforeEach(function (): void {
        $this->ipResolver = Mockery::mock(IpResolver::class);
        $this->geoResolver = Mockery::mock(GeoResolver::class);
        $this->strategy = new CountryStrategy($this->ipResolver, $this->geoResolver);
    });

    describe('identifier', function (): void {
        test('returns country as strategy identifier', function (): void {
            // Act
            $identifier = $this->strategy->identifier();

            // Assert
            expect($identifier)->toBe('country');
        });
    });

    describe('Happy Paths', function (): void {
        test('matches request when client country is in blocked list', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => ['US', 'CA', 'GB']];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('1.2.3.4');

            $this->geoResolver->shouldReceive('country')
                ->once()
                ->with('1.2.3.4')
                ->andReturn('US');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches with case-insensitive country code comparison', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => ['us', 'ca']];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('1.2.3.4');

            $this->geoResolver->shouldReceive('country')
                ->once()
                ->with('1.2.3.4')
                ->andReturn('US');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches when resolver returns lowercase country code', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => ['GB', 'FR']];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('1.2.3.4');

            $this->geoResolver->shouldReceive('country')
                ->once()
                ->with('1.2.3.4')
                ->andReturn('gb');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('matches when country is only item in blocked list', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => ['RU']];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('1.2.3.4');

            $this->geoResolver->shouldReceive('country')
                ->once()
                ->with('1.2.3.4')
                ->andReturn('RU');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns false when metadata does not contain countries key', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['ip' => '1.2.3.4'];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when countries metadata is not an array', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => 'US'];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when countries array is empty', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => []];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when IP resolver returns null', function (): void {
            // Arrange
            $request = Request::create('/', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $metadata = ['countries' => ['US']];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn(null);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when geo resolver returns null', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => ['US']];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('1.2.3.4');

            $this->geoResolver->shouldReceive('country')
                ->once()
                ->with('1.2.3.4')
                ->andReturn(null);

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when client country is not in blocked list', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => ['US', 'CA', 'GB']];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('1.2.3.4');

            $this->geoResolver->shouldReceive('country')
                ->once()
                ->with('1.2.3.4')
                ->andReturn('FR');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles very large list of blocked countries', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $blockedCountries = array_map(fn ($i): string => sprintf('C%02d', $i), range(1, 100));
            $blockedCountries[] = 'XX';
            $metadata = ['countries' => $blockedCountries];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('1.2.3.4');

            $this->geoResolver->shouldReceive('country')
                ->once()
                ->with('1.2.3.4')
                ->andReturn('XX');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles mixed case country codes in metadata', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => ['Us', 'cA', 'GB']];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('1.2.3.4');

            $this->geoResolver->shouldReceive('country')
                ->once()
                ->with('1.2.3.4')
                ->andReturn('ca');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles unicode country codes with proper case normalization', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => ['US', 'DE']];

            $this->ipResolver->shouldReceive('resolve')
                ->once()
                ->with($request)
                ->andReturn('1.2.3.4');

            $this->geoResolver->shouldReceive('country')
                ->once()
                ->with('1.2.3.4')
                ->andReturn('de');

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles empty metadata array', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = [];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false for null country value in metadata', function (): void {
            // Arrange
            $request = createRequestWithIp('1.2.3.4');
            $metadata = ['countries' => null];

            // Act
            $result = $this->strategy->matches($request, $metadata);

            // Assert
            expect($result)->toBeFalse();
        });
    });
});
