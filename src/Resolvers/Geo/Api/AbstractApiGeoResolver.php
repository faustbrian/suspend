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
 * Geo resolver using AbstractAPI.
 *
 * Free tier: 20,000 requests/month
 * Paid tiers available with higher limits.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://www.abstractapi.com/api/ip-geolocation-api
 *
 * @psalm-immutable
 */
final readonly class AbstractApiGeoResolver implements GeoResolver
{
    /**
     * AbstractAPI geolocation service base URL.
     */
    private const string BASE_URL = 'https://ipgeolocation.abstractapi.com/v1';

    /**
     * Create a new AbstractAPI geo resolver.
     *
     * @param string $apiKey   AbstractAPI key obtained from https://www.abstractapi.com/api/ip-geolocation-api.
     *                         Required for all requests. Free tier provides 20,000 requests/month.
     * @param int    $cacheTtl Cache time-to-live in seconds for API responses. Defaults to 3,600 seconds (1 hour).
     *                         Longer TTL reduces API calls but may return stale data for dynamic IP addresses.
     *                         IP geolocation data changes infrequently, so caching is recommended.
     */
    public function __construct(
        private string $apiKey,
        private int $cacheTtl = 3_600,
    ) {}

    /**
     * Resolve the country code for an IP address.
     *
     * Extracts the 'country_code' field from the AbstractAPI response.
     * Returns null if the API call fails or the field is not present.
     *
     * @param  string      $ip The IP address to look up
     * @return null|string The two-letter country code, or null if unavailable
     */
    public function country(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['country_code'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Resolve the region/state for an IP address.
     *
     * Extracts the 'region' field from the AbstractAPI response.
     * Returns null if the API call fails or the field is not present.
     *
     * @param  string      $ip The IP address to look up
     * @return null|string The region name, or null if unavailable
     */
    public function region(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['region'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Resolve the city for an IP address.
     *
     * Extracts the 'city' field from the AbstractAPI response.
     * Returns null if the API call fails or the field is not present.
     *
     * @param  string      $ip The IP address to look up
     * @return null|string The city name, or null if unavailable
     */
    public function city(string $ip): ?string
    {
        $data = $this->lookup($ip);
        $value = $data['city'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Resolve geographic coordinates for an IP address.
     *
     * Extracts latitude and longitude from the AbstractAPI response and validates
     * that both values are numeric before constructing a Coordinates object.
     *
     * @param  string           $ip The IP address to look up
     * @return null|Coordinates The coordinates, or null if unavailable or invalid
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
     * Get the resolver identifier.
     *
     * @return string The identifier 'abstractapi'
     */
    public function identifier(): string
    {
        return 'abstractapi';
    }

    /**
     * Perform the API lookup with caching.
     *
     * Executes an HTTP GET request to the AbstractAPI endpoint and caches the result
     * using Laravel's cache system. Returns an empty array on API errors, rate limits,
     * or invalid responses to prevent exceptions from bubbling up.
     *
     * @param  string               $ip The IP address to look up
     * @return array<string, mixed> The API response data, or empty array on failure
     */
    private function lookup(string $ip): array
    {
        $cacheKey = 'suspend:geo:abstractapi:'.$ip;

        // @phpstan-ignore-next-line Cache::remember correctly returns array from closure
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($ip): array {
            /** @var Response $response */
            $response = Http::get(self::BASE_URL, [
                'api_key' => $this->apiKey,
                'ip_address' => $ip,
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
