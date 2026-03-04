<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\Api\IpApiGeoResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

describe('IpApiGeoResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new IpApiGeoResolver();
        Cache::flush();
    });

    describe('identifier', function (): void {
        it('returns ip_api', function (): void {
            expect($this->resolver->identifier())->toBe('ip_api');
        });
    });

    describe('country', function (): void {
        it('returns country code from API', function (): void {
            Http::fake([
                'ip-api.com/*' => Http::response([
                    'status' => 'success',
                    'countryCode' => 'US',
                    'regionName' => 'California',
                    'city' => 'San Francisco',
                    'lat' => 37.774_9,
                    'lon' => -122.419_4,
                ]),
            ]);

            expect($this->resolver->country('8.8.8.8'))->toBe('US');

            Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'ip-api.com/json/8.8.8.8'));
        });

        it('returns null on API failure', function (): void {
            Http::fake([
                'ip-api.com/*' => Http::response([
                    'status' => 'fail',
                    'message' => 'reserved range',
                ]),
            ]);

            expect($this->resolver->country('127.0.0.1'))->toBeNull();
        });

        it('caches results', function (): void {
            Http::fake([
                'ip-api.com/*' => Http::response([
                    'status' => 'success',
                    'countryCode' => 'US',
                ]),
            ]);

            // First call
            $this->resolver->country('8.8.8.8');

            // Second call (should use cache)
            $this->resolver->country('8.8.8.8');

            // Only one HTTP request should have been made
            Http::assertSentCount(1);
        });
    });

    describe('region', function (): void {
        it('returns region name from API', function (): void {
            Http::fake([
                'ip-api.com/*' => Http::response([
                    'status' => 'success',
                    'regionName' => 'California',
                ]),
            ]);

            expect($this->resolver->region('8.8.8.8'))->toBe('California');
        });
    });

    describe('city', function (): void {
        it('returns city from API', function (): void {
            Http::fake([
                'ip-api.com/*' => Http::response([
                    'status' => 'success',
                    'city' => 'San Francisco',
                ]),
            ]);

            expect($this->resolver->city('8.8.8.8'))->toBe('San Francisco');
        });
    });

    describe('coordinates', function (): void {
        it('returns coordinates from API', function (): void {
            Http::fake([
                'ip-api.com/*' => Http::response([
                    'status' => 'success',
                    'lat' => 37.774_9,
                    'lon' => -122.419_4,
                ]),
            ]);

            $coords = $this->resolver->coordinates('8.8.8.8');

            expect($coords)->not->toBeNull();
            expect($coords->latitude)->toBe(37.774_9);
            expect($coords->longitude)->toBe(-122.419_4);
        });

        it('returns null when coordinates are missing', function (): void {
            Http::fake([
                'ip-api.com/*' => Http::response([
                    'status' => 'success',
                    'countryCode' => 'US',
                ]),
            ]);

            expect($this->resolver->coordinates('8.8.8.8'))->toBeNull();
        });
    });
});
