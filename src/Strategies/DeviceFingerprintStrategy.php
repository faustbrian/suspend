<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Strategies;

use Cline\Suspend\Contracts\Strategy;
use Cline\Suspend\Matchers\FingerprintMatcher;
use Cline\Suspend\Resolvers\Contracts\DeviceResolver;
use Illuminate\Http\Request;

use function is_string;

/**
 * Strategy that matches based on device fingerprint.
 *
 * Uses the configured device resolver to extract the fingerprint from the
 * request and the fingerprint matcher for comparison. Device fingerprints
 * uniquely identify browsers/devices based on various attributes like
 * user agent, screen resolution, installed plugins, and other characteristics.
 *
 * Metadata format:
 * - fingerprint: string (required) - Device fingerprint hash to match
 *
 * ```php
 * // Suspend a specific device fingerprint
 * $suspension = Suspension::create([
 *     'strategy' => 'device_fingerprint',
 *     'strategy_metadata' => ['fingerprint' => 'abc123...'],
 * ]);
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class DeviceFingerprintStrategy implements Strategy
{
    /**
     * Create a new device fingerprint strategy.
     *
     * @param DeviceResolver     $deviceResolver Resolver for extracting device fingerprint from request headers and attributes
     * @param FingerprintMatcher $matcher        Matcher for comparing fingerprint hashes with support for fuzzy matching
     */
    public function __construct(
        private DeviceResolver $deviceResolver,
        private FingerprintMatcher $matcher,
    ) {}

    /**
     * Determine if the strategy matches the current request.
     *
     * Extracts the device fingerprint from the request and compares it
     * against the suspended fingerprint using the configured matcher.
     *
     * @param  Request              $request  HTTP request containing device fingerprint data
     * @param  array<string, mixed> $metadata Strategy metadata containing the 'fingerprint' string
     * @return bool                 True if the client's device fingerprint matches the suspended fingerprint, false otherwise
     */
    public function matches(Request $request, array $metadata = []): bool
    {
        $suspendedFingerprint = $metadata['fingerprint'] ?? null;

        if (!is_string($suspendedFingerprint)) {
            return false;
        }

        $clientFingerprint = $this->deviceResolver->resolve($request);

        if ($clientFingerprint === null) {
            return false;
        }

        return $this->matcher->matches($suspendedFingerprint, $clientFingerprint);
    }

    /**
     * Get the unique identifier for this strategy type.
     *
     * @return string Strategy identifier used for registration and lookup
     */
    public function identifier(): string
    {
        return 'device_fingerprint';
    }
}
