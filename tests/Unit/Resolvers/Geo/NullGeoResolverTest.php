<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\NullGeoResolver;

describe('NullGeoResolver', function (): void {
    it('returns null for country', function (): void {
        $resolver = new NullGeoResolver();

        expect($resolver->country('1.2.3.4'))->toBeNull();
    });

    it('returns null for region', function (): void {
        $resolver = new NullGeoResolver();

        expect($resolver->region('1.2.3.4'))->toBeNull();
    });

    it('returns null for city', function (): void {
        $resolver = new NullGeoResolver();

        expect($resolver->city('1.2.3.4'))->toBeNull();
    });

    it('returns null for coordinates', function (): void {
        $resolver = new NullGeoResolver();

        expect($resolver->coordinates('1.2.3.4'))->toBeNull();
    });

    it('has null identifier', function (): void {
        $resolver = new NullGeoResolver();

        expect($resolver->identifier())->toBe('null');
    });
});
