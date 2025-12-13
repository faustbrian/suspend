<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\Cdn\FastlyGeoResolver;
use Cline\Suspend\Support\Coordinates;

describe('FastlyGeoResolver', function (): void {
    describe('identifier', function (): void {
        it('returns fastly', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->identifier())->toBe('fastly');
        });
    });

    describe('country', function (): void {
        it('extracts country from Fastly-Geo-Country-Code header', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Country-Code' => 'US',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBe('US');
        });

        it('falls back to X-Geo-Country header', function (): void {
            $request = createRequestWithHeaders([
                'X-Geo-Country' => 'US',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBe('US');
        });

        it('prefers Fastly-Geo-Country-Code over X-Geo-Country', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Country-Code' => 'US',
                'X-Geo-Country' => 'CA',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBe('US');
        });

        it('returns null when both headers are missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });

        it('returns null when both headers are empty', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Country-Code' => '',
                'X-Geo-Country' => '',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });
    });

    describe('region', function (): void {
        it('extracts region from Fastly-Geo-Region header', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Region' => 'CA',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBe('CA');
        });

        it('falls back to X-Geo-Region header', function (): void {
            $request = createRequestWithHeaders([
                'X-Geo-Region' => 'CA',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBe('CA');
        });

        it('prefers Fastly-Geo-Region over X-Geo-Region', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Region' => 'CA',
                'X-Geo-Region' => 'NY',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBe('CA');
        });

        it('returns null when both headers are missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBeNull();
        });

        it('returns null when both headers are empty', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Region' => '',
                'X-Geo-Region' => '',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBeNull();
        });
    });

    describe('city', function (): void {
        it('extracts city from Fastly-Geo-City header', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-City' => 'San Francisco',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBe('San Francisco');
        });

        it('falls back to X-Geo-City header', function (): void {
            $request = createRequestWithHeaders([
                'X-Geo-City' => 'San Francisco',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBe('San Francisco');
        });

        it('prefers Fastly-Geo-City over X-Geo-City', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-City' => 'San Francisco',
                'X-Geo-City' => 'Los Angeles',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBe('San Francisco');
        });

        it('returns null when both headers are missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBeNull();
        });

        it('returns null when both headers are empty', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-City' => '',
                'X-Geo-City' => '',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBeNull();
        });
    });

    describe('coordinates', function (): void {
        it('extracts coordinates from Fastly-Geo-Latitude and Fastly-Geo-Longitude headers', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Latitude' => '37.7749',
                'Fastly-Geo-Longitude' => '-122.4194',
            ]);
            $resolver = new FastlyGeoResolver($request);

            $coords = $resolver->coordinates('203.0.113.50');

            expect($coords)->toBeInstanceOf(Coordinates::class)
                ->and($coords->latitude)->toBe(37.774_9)
                ->and($coords->longitude)->toBe(-122.419_4);
        });

        it('falls back to X-Geo-Latitude and X-Geo-Longitude headers', function (): void {
            $request = createRequestWithHeaders([
                'X-Geo-Latitude' => '37.7749',
                'X-Geo-Longitude' => '-122.4194',
            ]);
            $resolver = new FastlyGeoResolver($request);

            $coords = $resolver->coordinates('203.0.113.50');

            expect($coords)->toBeInstanceOf(Coordinates::class)
                ->and($coords->latitude)->toBe(37.774_9)
                ->and($coords->longitude)->toBe(-122.419_4);
        });

        it('prefers Fastly-Geo headers over X-Geo headers', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Latitude' => '37.7749',
                'Fastly-Geo-Longitude' => '-122.4194',
                'X-Geo-Latitude' => '40.7128',
                'X-Geo-Longitude' => '-74.0060',
            ]);
            $resolver = new FastlyGeoResolver($request);

            $coords = $resolver->coordinates('203.0.113.50');

            expect($coords)->toBeInstanceOf(Coordinates::class)
                ->and($coords->latitude)->toBe(37.774_9)
                ->and($coords->longitude)->toBe(-122.419_4);
        });

        it('returns null when latitude header is missing', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Longitude' => '-122.4194',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when longitude header is missing', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Latitude' => '37.7749',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when both headers are missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when latitude header is empty', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Latitude' => '',
                'Fastly-Geo-Longitude' => '-122.4194',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when longitude header is empty', function (): void {
            $request = createRequestWithHeaders([
                'Fastly-Geo-Latitude' => '37.7749',
                'Fastly-Geo-Longitude' => '',
            ]);
            $resolver = new FastlyGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });
    });
});
