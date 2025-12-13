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
 * Contract for extracting client IP addresses from requests.
 *
 * Different hosting environments and CDN providers expose the real client
 * IP in different ways. This contract abstracts the IP extraction logic
 * to support various deployment scenarios:
 *
 * - Standard: Uses Laravel's $request->ip()
 * - Cloudflare: Reads CF-Connecting-IP header
 * - AWS API Gateway: Parses X-Forwarded-For chain
 * - Fastly/Akamai: Provider-specific headers
 *
 * Implementations should handle edge cases like missing headers, malformed
 * values, and IPv4/IPv6 differences gracefully.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface IpResolver
{
    /**
     * Extract the client IP address from the request.
     *
     * Implementations should return the real client IP, handling any
     * proxy or CDN headers specific to their environment. Returns null
     * if the IP cannot be determined.
     *
     * @param  Request     $request The incoming HTTP request
     * @return null|string The client IP address, or null if unavailable
     */
    public function resolve(Request $request): ?string;

    /**
     * Get the resolver identifier.
     *
     * Used for configuration and debugging. Should be a short,
     * descriptive string like 'standard', 'cloudflare', 'aws'.
     *
     * @return string The resolver identifier
     */
    public function identifier(): string;
}
