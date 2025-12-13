<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Resolvers\Geo\Api;

use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Support\Coordinates;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function array_key_exists;
use function is_array;
use function is_numeric;
use function is_string;
use function sprintf;

/**
 * Resolves geographic information using the DB-IP API service.
 *
 * Provides IP geolocation through DB-IP's REST API with automatic caching
 * for improved performance and reduced API usage. Supports both free tier
 * (country-level data only) and paid tiers (full geolocation details).
 *
 * Free tier: 1,000 requests/day (country-level)
 * Paid tiers available with higher limits and more detail.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://db-ip.com/api/doc.php
 *
 * @psalm-immutable
 */
final readonly class DbIpApiGeoResolver implements GeoResolver
{
    /**
     * API endpoint for free tier geolocation lookups.
     *
     * Provides country-level geolocation data without requiring an API key.
     * Limited to 1,000 requests per day.
     */
    private const string FREE_BASE_URL = 'http://api.db-ip.com/v2/free';

    /**
     * API endpoint for paid tier geolocation lookups.
     *
     * Requires valid API key. Provides detailed geolocation data including
     * city, region, coordinates, and additional metadata based on plan level.
     */
    private const string BASE_URL = 'http://api.db-ip.com/v2';

    /**
     * Create a new DB-IP API geo resolver instance.
     *
     * @param null|string $apiKey   API key for paid tier access. When null, uses the free tier
     *                              endpoint which provides country-level data only. Paid tier
     *                              keys unlock access to detailed city, region, and coordinate
     *                              information based on subscription level.
     * @param int         $cacheTtl Cache time-to-live in seconds for storing API responses.
     *                              Defaults to 3600 seconds (1 hour) to reduce API calls and
     *                              improve performance. Cached data is keyed by IP address.
     */
    public function __construct(
        private ?string $apiKey = null,
        private int $cacheTtl = 3_600,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function country(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['countryCode'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * {@inheritDoc}
     */
    public function region(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['stateProv'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * {@inheritDoc}
     */
    public function city(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['city'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * {@inheritDoc}
     */
    public function coordinates(string $ip): ?Coordinates
    {
        $data = $this->lookup($ip);

        if (!isset($data['latitude'], $data['longitude'])) {
            return null;
        }

        $latitude = $data['latitude'];
        $longitude = $data['longitude'];

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        return new Coordinates((float) $latitude, (float) $longitude);
    }

    /**
     * {@inheritDoc}
     */
    public function identifier(): string
    {
        return 'dbip_api';
    }

    /**
     * Performs IP geolocation lookup with automatic caching.
     *
     * Makes HTTP GET request to DB-IP API and caches the result to minimize
     * API calls. Automatically selects free or paid tier endpoint based on
     * whether an API key is configured. Returns empty array on API errors
     * or when rate limits are exceeded.
     *
     * @param  string               $ip The IP address to geolocate (IPv4 or IPv6)
     * @return array<string, mixed> Associative array of geolocation data from DB-IP API,
     *                              or empty array if lookup fails. Response structure varies
     *                              by tier but commonly includes countryCode, stateProv, city,
     *                              latitude, and longitude fields.
     */
    private function lookup(string $ip): array
    {
        $cacheKey = 'suspend:geo:dbip_api:'.$ip;

        // @phpstan-ignore-next-line Cache::remember correctly returns array from closure
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($ip): array {
            $url = $this->apiKey !== null ? self::BASE_URL.sprintf('/%s/%s', $this->apiKey, $ip) : self::FREE_BASE_URL.('/'.$ip);

            /** @var Response $response */
            $response = Http::get($url);

            if (!$response->successful()) {
                return [];
            }

            /** @var mixed $data */
            $data = $response->json();

            if (!is_array($data) || array_key_exists('error', $data)) {
                return [];
            }

            return $data;
        });
    }
}
