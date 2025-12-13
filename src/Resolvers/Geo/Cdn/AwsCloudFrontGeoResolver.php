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
 * Resolves geographic information using AWS CloudFront request headers.
 *
 * Extracts geolocation data from CloudFront viewer headers that are added to
 * requests when configured in the CloudFront distribution settings. This provides
 * zero-latency geo resolution since data is already present in HTTP headers,
 * eliminating the need for external API calls. Ideal for applications behind AWS
 * CloudFront CDN infrastructure.
 *
 * IMPORTANT: Headers must be explicitly whitelisted in the CloudFront distribution
 * configuration for them to be forwarded to the origin. Each header must be added
 * to the cache behavior's whitelist individually.
 *
 * Available headers:
 * - CloudFront-Viewer-Country: Two-letter country code
 * - CloudFront-Viewer-Country-Name: Full country name
 * - CloudFront-Viewer-Country-Region: Region/state code
 * - CloudFront-Viewer-Country-Region-Name: Region/state name
 * - CloudFront-Viewer-City: City name
 * - CloudFront-Viewer-Latitude/Longitude: Coordinates
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/adding-cloudfront-headers.html
 *
 * @psalm-immutable
 */
final readonly class AwsCloudFrontGeoResolver implements GeoResolver
{
    /**
     * Create a new AWS CloudFront geo resolver instance.
     *
     * @param Request $request The current HTTP request instance containing CloudFront viewer headers.
     *                         This request object is used to extract CloudFront-Viewer-* headers
     *                         which contain geographic information provided by AWS CloudFront CDN.
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
        $country = $this->request->header('CloudFront-Viewer-Country');

        if (!is_string($country) || $country === '') {
            return null;
        }

        return $country;
    }

    /**
     * {@inheritDoc}
     */
    public function region(string $ip): ?string
    {
        // Try full name first, then code
        $region = $this->request->header('CloudFront-Viewer-Country-Region-Name')
            ?? $this->request->header('CloudFront-Viewer-Country-Region');

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
        $city = $this->request->header('CloudFront-Viewer-City');

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
        $lat = $this->request->header('CloudFront-Viewer-Latitude');
        $lon = $this->request->header('CloudFront-Viewer-Longitude');

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
        return 'aws_cloudfront';
    }
}
