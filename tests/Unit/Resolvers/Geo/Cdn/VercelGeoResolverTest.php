<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\Cdn\VercelGeoResolver;
use Cline\Suspend\Support\Coordinates;

describe('VercelGeoResolver', function (): void {
    describe('identifier', function (): void {
        it('returns vercel', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->identifier())->toBe('vercel');
        });
    });

    describe('country', function (): void {
        it('extracts country from x-vercel-ip-country header', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-country' => 'US',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBe('US');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-country' => '',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });
    });

    describe('region', function (): void {
        it('extracts region from x-vercel-ip-country-region header', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-country-region' => 'CA',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBe('CA');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-country-region' => '',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBeNull();
        });
    });

    describe('city', function (): void {
        it('extracts city from x-vercel-ip-city header', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-city' => 'San Francisco',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBe('San Francisco');
        });

        it('decodes URL-encoded city names', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-city' => 'San%20Francisco',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBe('San Francisco');
        });

        it('handles cities with special characters', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-city' => 'S%C3%A3o%20Paulo',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBe('São Paulo');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-city' => '',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBeNull();
        });
    });

    describe('coordinates', function (): void {
        it('extracts coordinates from x-vercel-ip-latitude and x-vercel-ip-longitude headers', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-latitude' => '37.7749',
                'x-vercel-ip-longitude' => '-122.4194',
            ]);
            $resolver = new VercelGeoResolver($request);

            $coords = $resolver->coordinates('203.0.113.50');

            expect($coords)->toBeInstanceOf(Coordinates::class)
                ->and($coords->latitude)->toBe(37.774_9)
                ->and($coords->longitude)->toBe(-122.419_4);
        });

        it('returns null when latitude header is missing', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-longitude' => '-122.4194',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when longitude header is missing', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-latitude' => '37.7749',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when both headers are missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when latitude header is empty', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-latitude' => '',
                'x-vercel-ip-longitude' => '-122.4194',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when longitude header is empty', function (): void {
            $request = createRequestWithHeaders([
                'x-vercel-ip-latitude' => '37.7749',
                'x-vercel-ip-longitude' => '',
            ]);
            $resolver = new VercelGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });
    });
});
