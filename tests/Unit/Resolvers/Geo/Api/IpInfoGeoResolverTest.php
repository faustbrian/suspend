<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\Api\IpInfoGeoResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

describe('IpInfoGeoResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new IpInfoGeoResolver('test-token');
        Cache::flush();
    });

    describe('identifier', function (): void {
        it('returns ipinfo', function (): void {
            expect($this->resolver->identifier())->toBe('ipinfo');
        });
    });

    describe('country', function (): void {
        it('returns country code from API', function (): void {
            Http::fake([
                'ipinfo.io/*' => Http::response([
                    'country' => 'US',
                    'region' => 'California',
                    'city' => 'San Francisco',
                    'loc' => '37.7749,-122.4194',
                ]),
            ]);

            expect($this->resolver->country('8.8.8.8'))->toBe('US');

            Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'ipinfo.io/8.8.8.8') && $request->hasHeader('Authorization', 'Bearer test-token'));
        });

        it('returns null on API failure', function (): void {
            Http::fake([
                'ipinfo.io/*' => Http::response([
                    'error' => [
                        'title' => 'Invalid token',
                        'message' => 'The access token you provided is invalid.',
                    ],
                ]),
            ]);

            expect($this->resolver->country('127.0.0.1'))->toBeNull();
        });

        it('returns null on unsuccessful response', function (): void {
            Http::fake([
                'ipinfo.io/*' => Http::response(null, 500),
            ]);

            expect($this->resolver->country('8.8.8.8'))->toBeNull();
        });

        it('caches results', function (): void {
            Http::fake([
                'ipinfo.io/*' => Http::response([
                    'country' => 'US',
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
                'ipinfo.io/*' => Http::response([
                    'region' => 'California',
                ]),
            ]);

            expect($this->resolver->region('8.8.8.8'))->toBe('California');
        });
    });

    describe('city', function (): void {
        it('returns city from API', function (): void {
            Http::fake([
                'ipinfo.io/*' => Http::response([
                    'city' => 'San Francisco',
                ]),
            ]);

            expect($this->resolver->city('8.8.8.8'))->toBe('San Francisco');
        });
    });

    describe('coordinates', function (): void {
        it('returns coordinates from API', function (): void {
            Http::fake([
                'ipinfo.io/*' => Http::response([
                    'loc' => '37.7749,-122.4194',
                ]),
            ]);

            $coords = $this->resolver->coordinates('8.8.8.8');

            expect($coords)->not->toBeNull();
            expect($coords->latitude)->toBe(37.774_9);
            expect($coords->longitude)->toBe(-122.419_4);
        });

        it('returns null when loc is missing', function (): void {
            Http::fake([
                'ipinfo.io/*' => Http::response([
                    'country' => 'US',
                ]),
            ]);

            expect($this->resolver->coordinates('8.8.8.8'))->toBeNull();
        });

        it('returns null when loc format is invalid', function (): void {
            Http::fake([
                'ipinfo.io/*' => Http::response([
                    'loc' => 'invalid',
                ]),
            ]);

            expect($this->resolver->coordinates('8.8.8.8'))->toBeNull();
        });
    });

    describe('without token', function (): void {
        beforeEach(function (): void {
            $this->resolver = new IpInfoGeoResolver();
            Cache::flush();
        });

        it('works with free tier', function (): void {
            Http::fake([
                'ipinfo.io/*' => Http::response([
                    'country' => 'US',
                ]),
            ]);

            expect($this->resolver->country('8.8.8.8'))->toBe('US');

            Http::assertSent(fn ($request): bool => !$request->hasHeader('Authorization'));
        });
    });
});
