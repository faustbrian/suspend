<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Resolvers\Ip;

use Cline\Suspend\Resolvers\Contracts\IpResolver;
use Illuminate\Http\Request;

use function is_string;

/**
 * IP resolver for Akamai CDN.
 *
 * Reads the True-Client-IP header set by Akamai to get the
 * real client IP address.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://techdocs.akamai.com/property-mgr/docs/true-client-ip
 *
 * @psalm-immutable
 */
final readonly class AkamaiIpResolver implements IpResolver
{
    /**
     * The HTTP header name used by Akamai to pass the real client IP.
     *
     * This header must be enabled in your Akamai Property Manager configuration
     * to be available in requests.
     */
    private const string HEADER = 'True-Client-IP';

    /**
     * Resolves the client IP address from Akamai headers.
     *
     * Reads the True-Client-IP header set by Akamai's edge servers,
     * which contains the original client IP address before proxying.
     * Falls back to Laravel's standard IP resolution if the header is missing.
     *
     * @param  Request     $request The current HTTP request
     * @return null|string The client IP address or null if unavailable
     */
    public function resolve(Request $request): ?string
    {
        $ip = $request->header(self::HEADER);

        if (is_string($ip) && $ip !== '') {
            return $ip;
        }

        // Fallback to standard resolution
        return $request->ip();
    }

    /**
     * Returns the unique identifier for this resolver.
     *
     * @return string The identifier 'akamai'
     */
    public function identifier(): string
    {
        return 'akamai';
    }
}
