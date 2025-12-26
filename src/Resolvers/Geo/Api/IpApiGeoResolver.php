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

use function is_array;
use function is_numeric;
use function is_string;
use function sprintf;

/**
 * Resolves geographic information using the ip-api.com API service.
 *
 * Provides IP geolocation through ip-api.com's REST API with automatic caching.
 * The service offers a free tier with rate limiting (45 requests/minute) for
 * non-commercial use, and a Pro tier with unlimited requests for commercial
 * applications. Note that the free tier uses HTTP while Pro tier uses HTTPS.
 *
 * Free tier: 45 requests/minute (non-commercial use only)
 * Pro tier: Unlimited requests, commercial use allowed
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://ip-api.com/docs/
 *
 * @psalm-immutable
 */
final readonly class IpApiGeoResolver implements GeoResolver
{
    /**
     * API endpoint for free tier geolocation lookups.
     *
     * Uses HTTP protocol. Limited to 45 requests per minute and restricted
     * to non-commercial use. No API key required.
     */
    private const string BASE_URL = 'http://ip-api.com/json';

    /**
     * API endpoint for Pro tier geolocation lookups.
     *
     * Uses HTTPS protocol for secure communications. Requires valid API key
     * and supports unlimited requests for commercial applications.
     */
    private const string PRO_BASE_URL = 'https://pro.ip-api.com/json';

    /**
     * Create a new IP-API geo resolver instance.
     *
     * @param null|string $apiKey   API key for Pro tier access with unlimited requests and HTTPS.
     *                              When null, uses the free tier endpoint which is limited to
     *                              45 requests/minute and non-commercial use only. Pro tier is
     *                              recommended for production applications.
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

        $countryCode = $data['countryCode'] ?? null;

        return is_string($countryCode) ? $countryCode : null;
    }

    /**
     * {@inheritDoc}
     */
    public function region(string $ip): ?string
    {
        $data = $this->lookup($ip);

        $regionName = $data['regionName'] ?? null;

        return is_string($regionName) ? $regionName : null;
    }

    /**
     * {@inheritDoc}
     */
    public function city(string $ip): ?string
    {
        $data = $this->lookup($ip);

        $city = $data['city'] ?? null;

        return is_string($city) ? $city : null;
    }

    /**
     * {@inheritDoc}
     */
    public function coordinates(string $ip): ?Coordinates
    {
        $data = $this->lookup($ip);

        $lat = $data['lat'] ?? null;
        $lon = $data['lon'] ?? null;

        if (!is_numeric($lat) || !is_numeric($lon)) {
            return null;
        }

        return new Coordinates((float) $lat, (float) $lon);
    }

    /**
     * {@inheritDoc}
     */
    public function identifier(): string
    {
        return 'ip_api';
    }

    /**
     * Performs IP geolocation lookup with automatic caching.
     *
     * Makes HTTP/HTTPS request to ip-api.com and caches the result to minimize
     * API calls. Automatically selects free or Pro tier endpoint based on whether
     * an API key is configured. Validates response status to ensure successful
     * lookups before returning data.
     *
     * @param  string               $ip The IP address to geolocate (IPv4 or IPv6)
     * @return array<string, mixed> Associative array of geolocation data from ip-api.com,
     *                              or empty array if lookup fails. Response includes countryCode,
     *                              regionName, city, lat, lon, and additional metadata fields.
     *                              Only returns data when API response status is 'success'.
     */
    private function lookup(string $ip): array
    {
        $cacheKey = 'suspend:geo:ip_api:'.$ip;

        // @phpstan-ignore-next-line Cache::remember correctly returns array from closure
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($ip): array {
            $baseUrl = $this->apiKey !== null ? self::PRO_BASE_URL : self::BASE_URL;
            $url = sprintf('%s/%s', $baseUrl, $ip);

            $query = [];

            if ($this->apiKey !== null) {
                $query['key'] = $this->apiKey;
            }

            /** @var Response $response */
            $response = Http::get($url, $query);

            if (!$response->successful()) {
                return [];
            }

            /** @var mixed $data */
            $data = $response->json();

            if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
                return [];
            }

            return $data;
        });
    }
}
