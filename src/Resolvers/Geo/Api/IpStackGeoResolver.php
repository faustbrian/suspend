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
 * Resolves geographic information using the ipstack.com API service.
 *
 * Provides IP geolocation through ipstack.com's REST API with automatic caching
 * for improved performance and reduced API usage. The service offers a limited
 * free tier with 100 requests per month. Paid tiers provide higher limits and
 * HTTPS support. Note that free tier uses HTTP only.
 *
 * Free tier: 100 requests/month
 * Paid tiers available with higher limits.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://ipstack.com/documentation
 *
 * @psalm-immutable
 */
final readonly class IpStackGeoResolver implements GeoResolver
{
    /**
     * API endpoint for ipstack.com geolocation service.
     *
     * Uses HTTP protocol (free tier) with IP address appended to the path
     * and access_key as query parameter. HTTPS is only available on paid plans.
     */
    private const string BASE_URL = 'http://api.ipstack.com';

    /**
     * Create a new IPStack geo resolver instance.
     *
     * @param string $apiKey   API access key obtained from ipstack.com dashboard. Required for all
     *                         requests including free tier. The key authenticates requests and
     *                         tracks usage against monthly quotas. Free tier provides 100 requests
     *                         per month over HTTP. Paid tiers offer higher limits and HTTPS access.
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
        return 'ipstack';
    }

    /**
     * Performs IP geolocation lookup with automatic caching.
     *
     * Makes HTTP GET request to ipstack.com API with the configured access key
     * and caches the result to minimize API calls. Returns empty array on API
     * errors, invalid API keys, or when monthly quota is exceeded.
     *
     * @param  string               $ip The IP address to geolocate (IPv4 or IPv6)
     * @return array<string, mixed> Associative array of geolocation data from ipstack.com,
     *                              or empty array if lookup fails. Response includes country_code,
     *                              region_name, city, latitude, longitude, and additional metadata
     *                              fields such as timezone, currency, and connection information.
     */
    private function lookup(string $ip): array
    {
        $cacheKey = 'suspend:geo:ipstack:'.$ip;

        // @phpstan-ignore-next-line Cache::remember correctly returns array from closure
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($ip): array {
            /** @var Response $response */
            $response = Http::get(self::BASE_URL.('/'.$ip), [
                'access_key' => $this->apiKey,
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
