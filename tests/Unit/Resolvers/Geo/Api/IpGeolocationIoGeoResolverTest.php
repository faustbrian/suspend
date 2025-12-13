<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\Api\IpGeolocationIoGeoResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

describe('IpGeolocationIoGeoResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new IpGeolocationIoGeoResolver('test-api-key');
        Cache::flush();
    });

    describe('identifier', function (): void {
        it('returns ipgeolocation_io', function (): void {
            expect($this->resolver->identifier())->toBe('ipgeolocation_io');
        });
    });

    describe('country', function (): void {
        it('returns country code from API', function (): void {
            Http::fake([
                'api.ipgeolocation.io/*' => Http::response([
                    'country_code2' => 'US',
                    'state_prov' => 'California',
                    'city' => 'San Francisco',
                    'latitude' => '37.7749',
                    'longitude' => '-122.4194',
                ]),
            ]);

            expect($this->resolver->country('8.8.8.8'))->toBe('US');

            Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'api.ipgeolocation.io/ipgeo') && str_contains((string) $request->url(), 'apiKey=test-api-key') && str_contains((string) $request->url(), 'ip=8.8.8.8'));
        });

        it('returns null on API failure', function (): void {
            Http::fake([
                'api.ipgeolocation.io/*' => Http::response([
                    'message' => 'Invalid API key',
                ]),
            ]);

            expect($this->resolver->country('127.0.0.1'))->toBeNull();
        });

        it('returns null on unsuccessful response', function (): void {
            Http::fake([
                'api.ipgeolocation.io/*' => Http::response(null, 500),
            ]);

            expect($this->resolver->country('8.8.8.8'))->toBeNull();
        });

        it('caches results', function (): void {
            Http::fake([
                'api.ipgeolocation.io/*' => Http::response([
                    'country_code2' => 'US',
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
                'api.ipgeolocation.io/*' => Http::response([
                    'state_prov' => 'California',
                ]),
            ]);

            expect($this->resolver->region('8.8.8.8'))->toBe('California');
        });
    });

    describe('city', function (): void {
        it('returns city from API', function (): void {
            Http::fake([
                'api.ipgeolocation.io/*' => Http::response([
                    'city' => 'San Francisco',
                ]),
            ]);

            expect($this->resolver->city('8.8.8.8'))->toBe('San Francisco');
        });
    });

    describe('coordinates', function (): void {
        it('returns coordinates from API', function (): void {
            Http::fake([
                'api.ipgeolocation.io/*' => Http::response([
                    'latitude' => '37.7749',
                    'longitude' => '-122.4194',
                ]),
            ]);

            $coords = $this->resolver->coordinates('8.8.8.8');

            expect($coords)->not->toBeNull();
            expect($coords->latitude)->toBe(37.774_9);
            expect($coords->longitude)->toBe(-122.419_4);
        });

        it('returns null when coordinates are missing', function (): void {
            Http::fake([
                'api.ipgeolocation.io/*' => Http::response([
                    'country_code2' => 'US',
                ]),
            ]);

            expect($this->resolver->coordinates('8.8.8.8'))->toBeNull();
        });
    });
});
