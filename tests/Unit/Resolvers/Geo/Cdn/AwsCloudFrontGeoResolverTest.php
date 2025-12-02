<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Geo\Cdn\AwsCloudFrontGeoResolver;
use Cline\Suspend\Support\Coordinates;

describe('AwsCloudFrontGeoResolver', function (): void {
    describe('identifier', function (): void {
        it('returns aws_cloudfront', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->identifier())->toBe('aws_cloudfront');
        });
    });

    describe('country', function (): void {
        it('extracts country from CloudFront-Viewer-Country header', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Country' => 'US',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBe('US');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Country' => '',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->country('203.0.113.50'))->toBeNull();
        });
    });

    describe('region', function (): void {
        it('extracts region from CloudFront-Viewer-Country-Region-Name header', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Country-Region-Name' => 'California',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBe('California');
        });

        it('falls back to CloudFront-Viewer-Country-Region header', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Country-Region' => 'CA',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBe('CA');
        });

        it('prefers region name over region code', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Country-Region-Name' => 'California',
                'CloudFront-Viewer-Country-Region' => 'CA',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBe('California');
        });

        it('returns null when both headers are missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBeNull();
        });

        it('returns null when both headers are empty', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Country-Region-Name' => '',
                'CloudFront-Viewer-Country-Region' => '',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->region('203.0.113.50'))->toBeNull();
        });
    });

    describe('city', function (): void {
        it('extracts city from CloudFront-Viewer-City header', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-City' => 'San Francisco',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBe('San Francisco');
        });

        it('returns null when header is missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBeNull();
        });

        it('returns null when header is empty', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-City' => '',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->city('203.0.113.50'))->toBeNull();
        });
    });

    describe('coordinates', function (): void {
        it('extracts coordinates from CloudFront-Viewer-Latitude and CloudFront-Viewer-Longitude headers', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Latitude' => '37.7749',
                'CloudFront-Viewer-Longitude' => '-122.4194',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            $coords = $resolver->coordinates('203.0.113.50');

            expect($coords)->toBeInstanceOf(Coordinates::class)
                ->and($coords->latitude)->toBe(37.774_9)
                ->and($coords->longitude)->toBe(-122.419_4);
        });

        it('returns null when latitude header is missing', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Longitude' => '-122.4194',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when longitude header is missing', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Latitude' => '37.7749',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when both headers are missing', function (): void {
            $request = createRequestWithHeaders([]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when latitude header is empty', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Latitude' => '',
                'CloudFront-Viewer-Longitude' => '-122.4194',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });

        it('returns null when longitude header is empty', function (): void {
            $request = createRequestWithHeaders([
                'CloudFront-Viewer-Latitude' => '37.7749',
                'CloudFront-Viewer-Longitude' => '',
            ]);
            $resolver = new AwsCloudFrontGeoResolver($request);

            expect($resolver->coordinates('203.0.113.50'))->toBeNull();
        });
    });
});
