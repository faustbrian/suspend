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
 * IP resolver for Cloudflare-proxied requests.
 *
 * Reads the CF-Connecting-IP header set by Cloudflare to get the
 * real client IP address. Falls back to standard IP if header is missing.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://developers.cloudflare.com/fundamentals/reference/http-request-headers/
 *
 * @psalm-immutable
 */
final readonly class CloudflareIpResolver implements IpResolver
{
    /**
     * The HTTP header name used by Cloudflare to pass the real client IP.
     *
     * Cloudflare automatically adds this header to all proxied requests,
     * containing the original client IP address before entering their network.
     */
    private const string HEADER = 'CF-Connecting-IP';

    /**
     * Resolves the client IP address from Cloudflare headers.
     *
     * Reads the CF-Connecting-IP header set by Cloudflare's edge network,
     * which contains the original client IP address before proxying through
     * their CDN. Falls back to Laravel's standard IP resolution if the header
     * is missing or the request is not proxied through Cloudflare.
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
     * @return string The identifier 'cloudflare'
     */
    public function identifier(): string
    {
        return 'cloudflare';
    }
}
