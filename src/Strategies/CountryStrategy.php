<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Strategies;

use Cline\Suspend\Contracts\Strategy;
use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Resolvers\Contracts\IpResolver;
use Illuminate\Http\Request;

use function array_map;
use function in_array;
use function is_array;
use function mb_strtoupper;

/**
 * Strategy that matches based on geographic country.
 *
 * Uses the configured IP and geo resolvers to determine the client's country
 * from their IP address, then compares against a list of suspended countries.
 * Country codes are normalized to uppercase for case-insensitive matching.
 *
 * Metadata format:
 * - countries: array<string> (required) - ISO 3166-1 alpha-2 country codes (e.g., ['US', 'CA', 'GB'])
 *
 * ```php
 * // Suspend users from specific countries
 * $suspension = Suspension::create([
 *     'strategy' => 'country',
 *     'strategy_metadata' => ['countries' => ['RU', 'CN']],
 * ]);
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CountryStrategy implements Strategy
{
    /**
     * Create a new country strategy.
     *
     * @param IpResolver  $ipResolver  Resolver for extracting the client's IP address from the request
     * @param GeoResolver $geoResolver Resolver for performing geolocation lookups to determine country from IP
     */
    public function __construct(
        private IpResolver $ipResolver,
        private GeoResolver $geoResolver,
    ) {}

    /**
     * Determine if the strategy matches the current request.
     *
     * Extracts the client IP address, performs geolocation lookup to determine
     * their country, and checks if it matches any country in the suspension's
     * country list. All country codes are normalized to uppercase for comparison.
     *
     * @param  Request              $request  HTTP request containing the client's IP address
     * @param  array<string, mixed> $metadata Strategy metadata containing the 'countries' array
     * @return bool                 True if the client's country is in the suspended countries list, false otherwise
     */
    public function matches(Request $request, array $metadata = []): bool
    {
        $blockedCountries = $metadata['countries'] ?? null;

        if (!is_array($blockedCountries) || $blockedCountries === []) {
            return false;
        }

        $clientIp = $this->ipResolver->resolve($request);

        if ($clientIp === null) {
            return false;
        }

        $country = $this->geoResolver->country($clientIp);

        if ($country === null) {
            return false;
        }

        // Normalize to uppercase for comparison
        $country = mb_strtoupper($country);

        /** @var list<uppercase-string> $blockedCountries */
        // @phpstan-ignore argument.type
        $blockedCountries = array_map(mb_strtoupper(...), $blockedCountries);

        return in_array($country, $blockedCountries, true);
    }

    /**
     * Get the unique identifier for this strategy type.
     *
     * @return string Strategy identifier used for registration and lookup
     */
    public function identifier(): string
    {
        return 'country';
    }
}
