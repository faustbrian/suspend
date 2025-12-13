<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\ChainResolver;
use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Resolvers\Geo\NullGeoResolver;
use Cline\Suspend\Support\Coordinates;

describe('ChainResolver', function (): void {
    it('returns first non-null country from chain', function (): void {
        $resolver1 = Mockery::mock(GeoResolver::class);
        $resolver1->shouldReceive('country')->with('1.2.3.4')->andReturn(null);

        $resolver2 = Mockery::mock(GeoResolver::class);
        $resolver2->shouldReceive('country')->with('1.2.3.4')->andReturn('US');

        $chain = new ChainResolver([$resolver1, $resolver2]);

        expect($chain->country('1.2.3.4'))->toBe('US');
    });

    it('returns null when all resolvers return null for country', function (): void {
        $resolver1 = new NullGeoResolver();
        $resolver2 = new NullGeoResolver();

        $chain = new ChainResolver([$resolver1, $resolver2]);

        expect($chain->country('1.2.3.4'))->toBeNull();
    });

    it('returns first non-null region from chain', function (): void {
        $resolver1 = Mockery::mock(GeoResolver::class);
        $resolver1->shouldReceive('region')->with('1.2.3.4')->andReturn(null);

        $resolver2 = Mockery::mock(GeoResolver::class);
        $resolver2->shouldReceive('region')->with('1.2.3.4')->andReturn('California');

        $chain = new ChainResolver([$resolver1, $resolver2]);

        expect($chain->region('1.2.3.4'))->toBe('California');
    });

    it('returns null when all resolvers return null for region', function (): void {
        $chain = new ChainResolver([new NullGeoResolver()]);

        expect($chain->region('1.2.3.4'))->toBeNull();
    });

    it('returns first non-null city from chain', function (): void {
        $resolver1 = Mockery::mock(GeoResolver::class);
        $resolver1->shouldReceive('city')->with('1.2.3.4')->andReturn(null);

        $resolver2 = Mockery::mock(GeoResolver::class);
        $resolver2->shouldReceive('city')->with('1.2.3.4')->andReturn('Los Angeles');

        $chain = new ChainResolver([$resolver1, $resolver2]);

        expect($chain->city('1.2.3.4'))->toBe('Los Angeles');
    });

    it('returns null when all resolvers return null for city', function (): void {
        $chain = new ChainResolver([new NullGeoResolver()]);

        expect($chain->city('1.2.3.4'))->toBeNull();
    });

    it('returns first non-null coordinates from chain', function (): void {
        $coords = new Coordinates(34.052_2, -118.243_7);

        $resolver1 = Mockery::mock(GeoResolver::class);
        $resolver1->shouldReceive('coordinates')->with('1.2.3.4')->andReturn(null);

        $resolver2 = Mockery::mock(GeoResolver::class);
        $resolver2->shouldReceive('coordinates')->with('1.2.3.4')->andReturn($coords);

        $chain = new ChainResolver([$resolver1, $resolver2]);

        expect($chain->coordinates('1.2.3.4'))->toBe($coords);
    });

    it('returns null when all resolvers return null for coordinates', function (): void {
        $chain = new ChainResolver([new NullGeoResolver()]);

        expect($chain->coordinates('1.2.3.4'))->toBeNull();
    });

    it('has chain identifier', function (): void {
        $chain = new ChainResolver([]);

        expect($chain->identifier())->toBe('chain');
    });

    it('returns null for empty chain', function (): void {
        $chain = new ChainResolver([]);

        expect($chain->country('1.2.3.4'))->toBeNull();
        expect($chain->region('1.2.3.4'))->toBeNull();
        expect($chain->city('1.2.3.4'))->toBeNull();
        expect($chain->coordinates('1.2.3.4'))->toBeNull();
    });
});
