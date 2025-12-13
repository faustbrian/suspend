<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Resolvers\Contracts;

use Cline\Suspend\Support\Coordinates;

/**
 * Contract for resolving geographic information from IP addresses.
 *
 * Geo resolvers provide location data for IP-based suspension checks.
 * Different implementations support various data sources:
 *
 * CDN-based (free, fast, header-based):
 * - Cloudflare: CF-IPCountry, CF-IPCity headers
 * - AWS CloudFront: CloudFront-Viewer-Country header
 * - Fastly, Akamai, Vercel: Provider-specific headers
 *
 * API-based (requires external calls):
 * - MaxMind GeoIP2, IPStack, IPInfo, IP-API
 * - IPGeolocation.io, IP2Location, AbstractAPI
 *
 * Local database (no external calls):
 * - MaxMind GeoLite2/GeoIP2 .mmdb files
 * - IP2Location .bin files
 *
 * Implementations should handle failures gracefully and return null
 * for unavailable data rather than throwing exceptions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface GeoResolver
{
    /**
     * Resolve the country code for an IP address.
     *
     * Returns the ISO 3166-1 alpha-2 country code (e.g., 'US', 'GB', 'DE').
     * This is the most commonly available and reliable geo data point.
     *
     * @param  string      $ip The IP address to look up
     * @return null|string The two-letter country code, or null if unavailable
     */
    public function country(string $ip): ?string;

    /**
     * Resolve the region/state for an IP address.
     *
     * Returns the region or state name (e.g., 'California', 'England').
     * Availability depends on the resolver and IP address.
     *
     * @param  string      $ip The IP address to look up
     * @return null|string The region name, or null if unavailable
     */
    public function region(string $ip): ?string;

    /**
     * Resolve the city for an IP address.
     *
     * Returns the city name (e.g., 'San Francisco', 'London').
     * City-level accuracy varies significantly by provider and region.
     *
     * @param  string      $ip The IP address to look up
     * @return null|string The city name, or null if unavailable
     */
    public function city(string $ip): ?string;

    /**
     * Resolve geographic coordinates for an IP address.
     *
     * Returns latitude/longitude coordinates for the IP location.
     * Accuracy varies from city-level to country centroid depending
     * on the provider and available data.
     *
     * @param  string           $ip The IP address to look up
     * @return null|Coordinates The coordinates, or null if unavailable
     */
    public function coordinates(string $ip): ?Coordinates;

    /**
     * Get the resolver identifier.
     *
     * Used for configuration, logging, and debugging. Should be a
     * descriptive string like 'cloudflare', 'maxmind_api', 'ip_api'.
     *
     * @return string The resolver identifier
     */
    public function identifier(): string;
}
