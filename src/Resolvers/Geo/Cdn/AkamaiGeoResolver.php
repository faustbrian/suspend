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

use function explode;
use function is_string;
use function mb_trim;
use function str_contains;

/**
 * Resolves geographic information using Akamai EdgeScape request headers.
 *
 * Extracts geolocation data from the X-Akamai-Edgescape header that Akamai CDN
 * adds to requests when EdgeScape is enabled. This provides zero-latency geo
 * resolution since data is already present in HTTP headers, eliminating the need
 * for external API calls. Ideal for applications behind Akamai CDN infrastructure.
 *
 * The header contains comma-separated key=value pairs with geographic and network
 * information. Data is parsed once per request and cached in memory for subsequent
 * lookups within the same request lifecycle.
 *
 * Example header value:
 * georegion=263,country_code=US,region_code=CA,city=SANJOSE,lat=37.3394,long=-121.8950
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://techdocs.akamai.com/edgescape/docs/edgescape-headers
 */
final class AkamaiGeoResolver implements GeoResolver
{
    /**
     * Cached parsed EdgeScape header data for the current request.
     *
     * Stores parsed key-value pairs from the X-Akamai-Edgescape header to avoid
     * re-parsing on subsequent method calls within the same request. Initialized
     * to null and populated on first access via parseEdgeScape method.
     *
     * @var null|array<string, string>
     */
    private ?array $edgeScapeData = null;

    /**
     * Create a new Akamai geo resolver instance.
     *
     * @param Request $request The current HTTP request instance containing Akamai EdgeScape headers.
     *                         This request object is used to extract the X-Akamai-Edgescape header
     *                         which contains all geographic information provided by Akamai CDN.
     *                         Typically injected via Laravel's service container.
     */
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function country(string $ip): ?string
    {
        return $this->getData('country_code');
    }

    /**
     * {@inheritDoc}
     */
    public function region(string $ip): ?string
    {
        return $this->getData('region_code');
    }

    /**
     * {@inheritDoc}
     */
    public function city(string $ip): ?string
    {
        return $this->getData('city');
    }

    /**
     * {@inheritDoc}
     */
    public function coordinates(string $ip): ?Coordinates
    {
        $lat = $this->getData('lat');
        $lon = $this->getData('long');

        if ($lat === null || $lon === null) {
            return null;
        }

        return new Coordinates((float) $lat, (float) $lon);
    }

    /**
     * {@inheritDoc}
     */
    public function identifier(): string
    {
        return 'akamai';
    }

    /**
     * Parses the X-Akamai-Edgescape header into key-value pairs.
     *
     * Extracts and parses the comma-separated key=value pairs from the
     * X-Akamai-Edgescape header. Skips malformed pairs that don't contain
     * an equals sign. Trims whitespace from keys and values to ensure clean
     * data for lookups.
     *
     * @return array<string, string> Associative array of EdgeScape data with keys like
     *                               country_code, region_code, city, lat, and long.
     *                               Returns empty array if header is missing or invalid.
     */
    private function parseEdgeScape(): array
    {
        $header = $this->request->header('X-Akamai-Edgescape');

        if (!is_string($header) || $header === '') {
            return [];
        }

        $data = [];
        $pairs = explode(',', $header);

        foreach ($pairs as $pair) {
            if (!str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $data[mb_trim($key)] = mb_trim($value);
        }

        return $data;
    }

    /**
     * Retrieves a value from the parsed EdgeScape data.
     *
     * Lazily parses the EdgeScape header on first access and caches the result
     * for subsequent calls. Returns null if the requested key doesn't exist in
     * the EdgeScape data.
     *
     * @param  string      $key The EdgeScape field name to retrieve (e.g., 'country_code', 'city', 'lat')
     * @return null|string The field value if present, null otherwise
     */
    private function getData(string $key): ?string
    {
        if ($this->edgeScapeData === null) {
            $this->edgeScapeData = $this->parseEdgeScape();
        }

        return $this->edgeScapeData[$key] ?? null;
    }
}
