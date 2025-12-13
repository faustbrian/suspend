<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\Cdn\CloudflareGeoResolver;
use Cline\Suspend\Support\Coordinates;

describe('CloudflareGeoResolver', function (): void {
    describe('identifier', function (): void {
        it('returns cloudflare', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->identifier())->toBe('cloudflare');
        });
    });

    describe('country', function (): void {
        it('extracts country from CF-IPCountry header', function (): void {
            $request = createRequestWithHeaders([
                'CF-IPCountry' => 'US',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBe('US');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'CF-IPCountry' => '',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is XX (unknown)', function (): void {
            $request = createRequestWithHeaders([
                'CF-IPCountry' => 'XX',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });
    });

    describe('region', function (): void {
        it('extracts region from CF-Region header', function (): void {
            $request = createRequestWithHeaders([
                'CF-Region' => 'CA',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBe('CA');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'CF-Region' => '',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBeNull();
        });
    });

    describe('city', function (): void {
        it('extracts city from CF-IPCity header', function (): void {
            $request = createRequestWithHeaders([
                'CF-IPCity' => 'San Francisco',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBe('San Francisco');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'CF-IPCity' => '',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBeNull();
        });
    });

    describe('coordinates', function (): void {
        it('extracts coordinates from CF-IPLatitude and CF-IPLongitude headers', function (): void {
            $request = createRequestWithHeaders([
                'CF-IPLatitude' => '37.7749',
                'CF-IPLongitude' => '-122.4194',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            $coords = $resolver->coordinates('203.0.113.50');

            expect($coords)->toBeInstanceOf(Coordinates::class)
                ->and($coords->latitude)->toBe(37.774_9)
                ->and($coords->longitude)->toBe(-122.419_4);
        });

        it('returns null when latitude header is missing', function (): void {
            $request = createRequestWithHeaders([
                'CF-IPLongitude' => '-122.4194',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when longitude header is missing', function (): void {
            $request = createRequestWithHeaders([
                'CF-IPLatitude' => '37.7749',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when both headers are missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when latitude header is empty', function (): void {
            $request = createRequestWithHeaders([
                'CF-IPLatitude' => '',
                'CF-IPLongitude' => '-122.4194',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when longitude header is empty', function (): void {
            $request = createRequestWithHeaders([
                'CF-IPLatitude' => '37.7749',
                'CF-IPLongitude' => '',
            ]);
            $resolver = new CloudflareGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });
    });
});
