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

/**
 * Resolves geographic information using the IP2Location.io API service.
 *
 * Provides IP geolocation through IP2Location's REST API with automatic caching
 * for improved performance and reduced API usage. The service offers a generous
 * free tier with 30,000 queries per month, making it suitable for small to
 * medium-sized applications.
 *
 * Free tier: 30,000 queries/month
 * Paid tiers available with higher limits.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://www.ip2location.io/ip2location-documentation
 *
 * @psalm-immutable
 */
final readonly class Ip2LocationApiGeoResolver implements GeoResolver
{
    /**
     * API endpoint for IP2Location.io geolocation service.
     *
     * All requests are made over HTTPS to this base URL with API key
     * and IP address passed as query parameters.
     */
    private const string BASE_URL = 'https://api.ip2location.io';

    /**
     * Create a new IP2Location API geo resolver instance.
     *
     * @param string $apiKey   API key obtained from IP2Location.io dashboard. Required for all
     *                         requests including free tier. The key authenticates requests and
     *                         tracks usage against monthly quotas. Sign up at ip2location.io to
     *                         obtain your free API key with 30,000 monthly queries.
     * @param int    $cacheTtl Cache time-to-live in seconds for storing API responses.
     *                         Defaults to 3600 seconds (1 hour) to reduce API calls and
     *                         improve performance. Cached data is keyed by IP address.
     */
    public function __construct(
        private string $apiKey,
        private int $cacheTtl = 3_600,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function country(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['country_code'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * {@inheritDoc}
     */
    public function region(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['region_name'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * {@inheritDoc}
     */
    public function city(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['city_name'] ?? null;

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
        return 'ip2location_api';
    }

    /**
     * Performs IP geolocation lookup with automatic caching.
     *
     * Makes HTTP GET request to IP2Location.io API with the configured API key
     * and caches the result to minimize API calls. Returns empty array on API
     * errors, invalid API keys, or when monthly quota is exceeded.
     *
     * @param  string               $ip The IP address to geolocate (IPv4 or IPv6)
     * @return array<string, mixed> Associative array of geolocation data from IP2Location.io,
     *                              or empty array if lookup fails. Response includes country_code,
     *                              region_name, city_name, latitude, longitude, and additional
     *                              metadata fields depending on API plan.
     */
    private function lookup(string $ip): array
    {
        $cacheKey = 'suspend:geo:ip2location_api:'.$ip;

        // @phpstan-ignore-next-line Cache::remember correctly returns array from closure
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($ip): array {
            /** @var Response $response */
            $response = Http::get(self::BASE_URL, [
                'key' => $this->apiKey,
                'ip' => $ip,
            ]);

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
