<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\Cdn\AkamaiGeoResolver;
use Cline\Suspend\Support\Coordinates;

describe('AkamaiGeoResolver', function (): void {
    describe('identifier', function (): void {
        it('returns akamai', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->identifier())->toBe('akamai');
        });
    });

    describe('country', function (): void {
        it('extracts country from X-Akamai-Edgescape header', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263,country_code=US,region_code=CA,city=SANJOSE',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBe('US');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => '',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });

        it('returns null when country_code is not present in header', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263,region_code=CA',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });
    });

    describe('region', function (): void {
        it('extracts region from X-Akamai-Edgescape header', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263,country_code=US,region_code=CA,city=SANJOSE',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBe('CA');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBeNull();
        });

        it('returns null when region_code is not present in header', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263,country_code=US',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBeNull();
        });
    });

    describe('city', function (): void {
        it('extracts city from X-Akamai-Edgescape header', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263,country_code=US,region_code=CA,city=SANJOSE',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBe('SANJOSE');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBeNull();
        });

        it('returns null when city is not present in header', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263,country_code=US,region_code=CA',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBeNull();
        });
    });

    describe('coordinates', function (): void {
        it('extracts coordinates from X-Akamai-Edgescape header', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263,country_code=US,lat=37.3394,long=-121.8950',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            $coords = $resolver->coordinates('203.0.113.50');

            expect($coords)->toBeInstanceOf(Coordinates::class)
                ->and($coords->latitude)->toBe(37.339_4)
                ->and($coords->longitude)->toBe(-121.895_0);
        });

        it('handles coordinates with spaces in header', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263, country_code=US, lat = 37.3394 , long = -121.8950',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            $coords = $resolver->coordinates('203.0.113.50');

            expect($coords)->toBeInstanceOf(Coordinates::class)
                ->and($coords->latitude)->toBe(37.339_4)
                ->and($coords->longitude)->toBe(-121.895_0);
        });

        it('returns null when latitude is missing', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263,country_code=US,long=-121.8950',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when longitude is missing', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263,country_code=US,lat=37.3394',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when both coordinates are missing', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'georegion=263,country_code=US',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });
    });

    describe('EdgeScape parsing', function (): void {
        it('handles malformed pairs without equals sign', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'invalid_pair,country_code=US,another_invalid',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBe('US');
        });

        it('handles pairs with multiple equals signs', function (): void {
            $request = createRequestWithHeaders([
                'X-Akamai-Edgescape' => 'country_code=US,value=a=b=c',
            ]);
            $resolver = new AkamaiGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBe('US');
        });
    });
});
