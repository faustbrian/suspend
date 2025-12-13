<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Resolvers\Geo\Cdn;

use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Support\Coordinates;
use Illuminate\Http\Request;

use function is_string;

/**
 * Resolves geographic information using Cloudflare request headers.
 *
 * Extracts geolocation data from Cloudflare headers that are automatically added
 * to all proxied requests when the site is behind Cloudflare CDN. This provides
 * zero-latency geo resolution since data is already present in HTTP headers,
 * eliminating the need for external API calls. This is the fastest and most
 * cost-effective option for Cloudflare users.
 *
 * The CF-IPCountry header is available on all Cloudflare plans including Free.
 * Advanced headers like city, region, and coordinates require Enterprise plan.
 * Returns 'XX' country code for unknown/invalid IPs which is filtered to null.
 *
 * Available headers:
 * - CF-IPCountry: Two-letter country code (all plans)
 * - CF-Region: Region/state code (Enterprise only)
 * - CF-IPCity: City name (Enterprise only)
 * - CF-IPContinent: Continent code (Enterprise only)
 * - CF-IPLatitude/CF-IPLongitude: Coordinates (Enterprise only)
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://developers.cloudflare.com/fundamentals/reference/http-request-headers/
 *
 * @psalm-immutable
 */
final readonly class CloudflareGeoResolver implements GeoResolver
{
    /**
     * Create a new Cloudflare geo resolver instance.
     *
     * @param Request $request The current HTTP request instance containing Cloudflare headers.
     *                         This request object is used to extract CF-* headers which contain
     *                         geographic information provided by Cloudflare CDN for proxied requests.
     *                         Typically injected via Laravel's service container.
     */
    public function __construct(
        private Request $request,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function country(string $ip): ?string
    {
        $country = $this->request->header('CF-IPCountry');

        if (!is_string($country) || $country === '' || $country === 'XX') {
            return null;
        }

        return $country;
    }

    /**
     * {@inheritDoc}
     */
    public function region(string $ip): ?string
    {
        // Cloudflare provides region via CF-Region header (Enterprise)
        $region = $this->request->header('CF-Region');

        if (!is_string($region) || $region === '') {
            return null;
        }

        return $region;
    }

    /**
     * {@inheritDoc}
     */
    public function city(string $ip): ?string
    {
        // City is only available on Enterprise plans
        $city = $this->request->header('CF-IPCity');

        if (!is_string($city) || $city === '') {
            return null;
        }

        return $city;
    }

    /**
     * {@inheritDoc}
     */
    public function coordinates(string $ip): ?Coordinates
    {
        // Coordinates are only available on Enterprise plans
        $lat = $this->request->header('CF-IPLatitude');
        $lon = $this->request->header('CF-IPLongitude');

        if (!is_string($lat) || !is_string($lon) || $lat === '' || $lon === '') {
            return null;
        }

        return new Coordinates((float) $lat, (float) $lon);
    }

    /**
     * {@inheritDoc}
     */
    public function identifier(): string
    {
        return 'cloudflare';
    }
}
