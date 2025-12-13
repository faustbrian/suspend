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
use function count;
use function explode;
use function is_array;
use function is_numeric;
use function is_string;

/**
 * Resolves geographic information using the ipinfo.io API service.
 *
 * Provides IP geolocation through ipinfo.io's REST API with automatic caching
 * for improved performance. The free tier offers unlimited requests but only
 * provides country-level data. Paid tiers unlock detailed geolocation including
 * city, region, and precise coordinates. Uses token-based authentication.
 *
 * Free tier: Country-level data only (unlimited requests)
 * Paid tiers: Full geolocation data
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://ipinfo.io/developers
 *
 * @psalm-immutable
 */
final readonly class IpInfoGeoResolver implements GeoResolver
{
    /**
     * API endpoint for ipinfo.io geolocation service.
     *
     * All requests are made over HTTPS to this base URL with the IP address
     * appended to the path. Authentication token is passed via Bearer header.
     */
    private const string BASE_URL = 'https://ipinfo.io';

    /**
     * Create a new IPInfo geo resolver instance.
     *
     * @param null|string $token    API token for paid tier access with detailed geolocation data.
     *                              When null, uses the free tier which provides country-level data
     *                              only with unlimited requests. Paid tier tokens unlock city, region,
     *                              and coordinate information. Token is sent via Bearer authentication.
     * @param int         $cacheTtl Cache time-to-live in seconds for storing API responses.
     *                              Defaults to 3600 seconds (1 hour) to reduce API calls and
     *                              improve performance. Cached data is keyed by IP address.
     */
    public function __construct(
        private ?string $token = null,
        private int $cacheTtl = 3_600,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function country(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['country'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * {@inheritDoc}
     */
    public function region(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['region'] ?? null;

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

        if (!array_key_exists('loc', $data)) {
            return null;
        }

        $loc = $data['loc'];

        if (!is_string($loc)) {
            return null;
        }

        // IPInfo returns "lat,lon" as a string
        $parts = explode(',', $loc);

        if (count($parts) !== 2) {
            return null;
        }

        if (!is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return null;
        }

        return new Coordinates((float) $parts[0], (float) $parts[1]);
    }

    /**
     * {@inheritDoc}
     */
    public function identifier(): string
    {
        return 'ipinfo';
    }

    /**
     * Performs IP geolocation lookup with automatic caching.
     *
     * Makes HTTPS GET request to ipinfo.io API with optional Bearer token
     * authentication and caches the result to minimize API calls. Constructs
     * request with Accept header and conditionally includes Bearer token for
     * paid tier access.
     *
     * @param  string               $ip The IP address to geolocate (IPv4 or IPv6)
     * @return array<string, mixed> Associative array of geolocation data from ipinfo.io,
     *                              or empty array if lookup fails. Response includes country,
     *                              region, city, and a special 'loc' field containing coordinates
     *                              as a comma-separated string (e.g., "37.7749,-122.4194").
     */
    private function lookup(string $ip): array
    {
        $cacheKey = 'suspend:geo:ipinfo:'.$ip;

        // @phpstan-ignore-next-line Cache::remember correctly returns array from closure
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($ip): array {
            $request = Http::accept('application/json');

            if ($this->token !== null) {
                $request = $request->withToken($this->token);
            }

            /** @var Response $response */
            $response = $request->get(self::BASE_URL.('/'.$ip));

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
