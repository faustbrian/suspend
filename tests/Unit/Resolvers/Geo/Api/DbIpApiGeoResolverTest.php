<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\Api\DbIpApiGeoResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

describe('DbIpApiGeoResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new DbIpApiGeoResolver('test-api-key');
        Cache::flush();
    });

    describe('identifier', function (): void {
        it('returns dbip_api', function (): void {
            expect($this->resolver->identifier())->toBe('dbip_api');
        });
    });

    describe('country', function (): void {
        it('returns country code from API with paid tier', function (): void {
            Http::fake([
                'api.db-ip.com/*' => Http::response([
                    'countryCode' => 'US',
                    'stateProv' => 'California',
                    'city' => 'San Francisco',
                    'latitude' => 37.774_9,
                    'longitude' => -122.419_4,
                ]),
            ]);

            expect($this->resolver->country('8.8.8.8'))->toBe('US');

            Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'api.db-ip.com/v2/test-api-key/8.8.8.8'));
        });

        it('returns null on API failure', function (): void {
            Http::fake([
                'api.db-ip.com/*' => Http::response([
                    'error' => 'Invalid API key',
                ]),
            ]);

            expect($this->resolver->country('127.0.0.1'))->toBeNull();
        });

        it('returns null on unsuccessful response', function (): void {
            Http::fake([
                'api.db-ip.com/*' => Http::response(null, 500),
            ]);

            expect($this->resolver->country('8.8.8.8'))->toBeNull();
        });

        it('caches results', function (): void {
            Http::fake([
                'api.db-ip.com/*' => Http::response([
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
        it('returns region from API', function (): void {
            Http::fake([
                'api.db-ip.com/*' => Http::response([
                    'stateProv' => 'California',
                ]),
            ]);

            expect($this->resolver->region('8.8.8.8'))->toBe('California');
        });
    });

    describe('city', function (): void {
        it('returns city from API', function (): void {
            Http::fake([
                'api.db-ip.com/*' => Http::response([
                    'city' => 'San Francisco',
                ]),
            ]);

            expect($this->resolver->city('8.8.8.8'))->toBe('San Francisco');
        });
    });

    describe('coordinates', function (): void {
        it('returns coordinates from API', function (): void {
            Http::fake([
                'api.db-ip.com/*' => Http::response([
                    'latitude' => 37.774_9,
                    'longitude' => -122.419_4,
                ]),
            ]);

            $coords = $this->resolver->coordinates('8.8.8.8');

            expect($coords)->not->toBeNull();
            expect($coords->latitude)->toBe(37.774_9);
            expect($coords->longitude)->toBe(-122.419_4);
        });

        it('returns null when coordinates are missing', function (): void {
            Http::fake([
                'api.db-ip.com/*' => Http::response([
                    'countryCode' => 'US',
                ]),
            ]);

            expect($this->resolver->coordinates('8.8.8.8'))->toBeNull();
        });
    });

    describe('free tier', function (): void {
        beforeEach(function (): void {
            $this->resolver = new DbIpApiGeoResolver();
            Cache::flush();
        });

        it('uses free API endpoint', function (): void {
            Http::fake([
                'api.db-ip.com/*' => Http::response([
                    'countryCode' => 'US',
                ]),
            ]);

            expect($this->resolver->country('8.8.8.8'))->toBe('US');

            Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'api.db-ip.com/v2/free/8.8.8.8'));
        });
    });
});
