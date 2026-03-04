<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\Local\MaxMindLocalGeoResolver;
use Cline\Suspend\Support\Coordinates;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\City;

describe('MaxMindLocalGeoResolver', function (): void {
    beforeEach(function (): void {
        $this->mockReader = Mockery::mock(Reader::class);
        $this->mockReader->shouldReceive('close')->zeroOrMoreTimes();
    });

    describe('identifier', function (): void {
        it('returns maxmind_local', function (): void {
            $resolver = createResolverWithMockReader($this->mockReader);

            expect($resolver->identifier())->toBe('maxmind_local');
        });
    });

    describe('country', function (): void {
        it('returns country ISO code', function (): void {
            $cityModel = createCityModel(['country' => ['iso_code' => 'US']]);
            $this->mockReader->shouldReceive('city')->with('8.8.8.8')->andReturn($cityModel);

            $resolver = createResolverWithMockReader($this->mockReader);

            expect($resolver->country('8.8.8.8'))->toBe('US');
        });

        it('returns null when address not found', function (): void {
            $this->mockReader->shouldReceive('city')
                ->with('192.168.1.1')
                ->andThrow(
                    new AddressNotFoundException('Address not found'),
                );

            $resolver = createResolverWithMockReader($this->mockReader);

            expect($resolver->country('192.168.1.1'))->toBeNull();
        });
    });

    describe('region', function (): void {
        it('returns subdivision name', function (): void {
            $cityModel = createCityModel(['subdivisions' => [['names' => ['en' => 'California']]]]);
            $this->mockReader->shouldReceive('city')->with('8.8.8.8')->andReturn($cityModel);

            $resolver = createResolverWithMockReader($this->mockReader);

            expect($resolver->region('8.8.8.8'))->toBe('California');
        });

        it('returns null when address not found', function (): void {
            $this->mockReader->shouldReceive('city')
                ->with('192.168.1.1')
                ->andThrow(
                    new AddressNotFoundException('Address not found'),
                );

            $resolver = createResolverWithMockReader($this->mockReader);

            expect($resolver->region('192.168.1.1'))->toBeNull();
        });
    });

    describe('city', function (): void {
        it('returns city name', function (): void {
            $cityModel = createCityModel(['city' => ['names' => ['en' => 'Los Angeles']]]);
            $this->mockReader->shouldReceive('city')->with('8.8.8.8')->andReturn($cityModel);

            $resolver = createResolverWithMockReader($this->mockReader);

            expect($resolver->city('8.8.8.8'))->toBe('Los Angeles');
        });

        it('returns null when address not found', function (): void {
            $this->mockReader->shouldReceive('city')
                ->with('192.168.1.1')
                ->andThrow(
                    new AddressNotFoundException('Address not found'),
                );

            $resolver = createResolverWithMockReader($this->mockReader);

            expect($resolver->city('192.168.1.1'))->toBeNull();
        });
    });

    describe('coordinates', function (): void {
        it('returns coordinates when available', function (): void {
            $cityModel = createCityModel(['location' => ['latitude' => 34.052_2, 'longitude' => -118.243_7]]);
            $this->mockReader->shouldReceive('city')->with('8.8.8.8')->andReturn($cityModel);

            $resolver = createResolverWithMockReader($this->mockReader);
            $coords = $resolver->coordinates('8.8.8.8');

            expect($coords)->toBeInstanceOf(Coordinates::class);
            expect($coords->latitude)->toBe(34.052_2);
            expect($coords->longitude)->toBe(-118.243_7);
        });

        it('returns null when latitude is missing', function (): void {
            $cityModel = createCityModel(['location' => ['latitude' => null, 'longitude' => -118.243_7]]);
            $this->mockReader->shouldReceive('city')->with('8.8.8.8')->andReturn($cityModel);

            $resolver = createResolverWithMockReader($this->mockReader);

            expect($resolver->coordinates('8.8.8.8'))->toBeNull();
        });

        it('returns null when longitude is missing', function (): void {
            $cityModel = createCityModel(['location' => ['latitude' => 34.052_2, 'longitude' => null]]);
            $this->mockReader->shouldReceive('city')->with('8.8.8.8')->andReturn($cityModel);

            $resolver = createResolverWithMockReader($this->mockReader);

            expect($resolver->coordinates('8.8.8.8'))->toBeNull();
        });

        it('returns null when address not found', function (): void {
            $this->mockReader->shouldReceive('city')
                ->with('192.168.1.1')
                ->andThrow(
                    new AddressNotFoundException('Address not found'),
                );

            $resolver = createResolverWithMockReader($this->mockReader);

            expect($resolver->coordinates('192.168.1.1'))->toBeNull();
        });
    });
});

/**
 * Create a resolver with a mock reader injected via reflection.
 */
function createResolverWithMockReader(Reader $mockReader): MaxMindLocalGeoResolver
{
    $resolver = new MaxMindLocalGeoResolver('/fake/path.mmdb');

    $reflection = new ReflectionClass($resolver);
    $property = $reflection->getProperty('reader');
    $property->setValue($resolver, $mockReader);

    return $resolver;
}

/**
 * Create a City model with the given data.
 */
function createCityModel(array $data = []): City
{
    $raw = [
        'city' => $data['city'] ?? [],
        'country' => $data['country'] ?? [],
        'location' => $data['location'] ?? [],
        // City model requires at least one subdivision or it errors
        'subdivisions' => $data['subdivisions'] ?? [['names' => ['en' => 'Unknown']]],
        'traits' => ['ip_address' => '8.8.8.8'],
    ];

    return new City($raw, ['en']);
}
