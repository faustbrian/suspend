<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Resolvers\Contracts;

use Illuminate\Http\Request;

/**
 * Contract for extracting device fingerprints from requests.
 *
 * Device fingerprinting provides an additional layer of identification
 * beyond IP addresses, useful for detecting ban evasion or tracking
 * devices across sessions.
 *
 * Common fingerprinting approaches:
 * - FingerprintJS: Client-side fingerprinting library
 * - Custom headers: Application-specific device IDs
 * - User-Agent parsing: Browser/device identification
 *
 * Implementations should handle missing fingerprint data gracefully
 * and return null when fingerprinting is not available.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface DeviceResolver
{
    /**
     * Extract the device fingerprint from the request.
     *
     * Implementations should extract or compute a device identifier
     * from available request data. Returns null if fingerprinting
     * is not possible for this request.
     *
     * @param  Request     $request The incoming HTTP request
     * @return null|string The device fingerprint, or null if unavailable
     */
    public function resolve(Request $request): ?string;

    /**
     * Get the resolver identifier.
     *
     * Used for configuration and debugging. Should be a short,
     * descriptive string like 'fingerprintjs', 'user_agent'.
     *
     * @return string The resolver identifier
     */
    public function identifier(): string;
}
