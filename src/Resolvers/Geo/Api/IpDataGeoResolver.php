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
 * Resolves geographic information using the ipdata.co API service.
 *
 * Provides IP geolocation through ipdata.co's REST API with automatic caching
 * for improved performance and reduced API usage. The service offers a free
 * tier with 1,500 requests per day, suitable for small applications and
 * development environments.
 *
 * Free tier: 1,500 requests/day
 * Paid tiers available with higher limits.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.ipdata.co/
 *
 * @psalm-immutable
 */
final readonly class IpDataGeoResolver implements GeoResolver
{
    /**
     * API endpoint for ipdata.co geolocation service.
     *
     * All requests are made over HTTPS to this base URL with the IP address
     * appended to the path and API key passed as query parameter.
     */
    private const string BASE_URL = 'https://api.ipdata.co';

    /**
     * Create a new IPData geo resolver instance.
     *
     * @param string $apiKey   API key obtained from ipdata.co dashboard. Required for all
     *                         requests including free tier. The key authenticates requests and
     *                         tracks usage against daily quotas. Sign up at ipdata.co to obtain
     *                         your free API key with 1,500 daily requests.
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
        return 'ipdata';
    }

    /**
     * Performs IP geolocation lookup with automatic caching.
     *
     * Makes HTTPS GET request to ipdata.co API with the configured API key
     * and caches the result to minimize API calls. Returns empty array on API
     * errors, invalid API keys, or when daily quota is exceeded.
     *
     * @param  string               $ip The IP address to geolocate (IPv4 or IPv6)
     * @return array<string, mixed> Associative array of geolocation data from ipdata.co,
     *                              or empty array if lookup fails. Response includes country_code,
     *                              region, city, latitude, longitude, and extensive additional
     *                              metadata such as timezone, currency, and threat intelligence.
     */
    private function lookup(string $ip): array
    {
        $cacheKey = 'suspend:geo:ipdata:'.$ip;

        // @phpstan-ignore-next-line Cache::remember correctly returns array from closure
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($ip): array {
            /** @var Response $response */
            $response = Http::get(self::BASE_URL.('/'.$ip), [
                'api-key' => $this->apiKey,
            ]);

            if (!$response->successful()) {
                return [];
            }

            /** @var mixed $data */
            $data = $response->json();

            if (!is_array($data) || array_key_exists('message', $data)) {
                return [];
            }

            return $data;
        });
    }
}
