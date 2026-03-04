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
 * IP resolver for Fastly CDN.
 *
 * Reads the Fastly-Client-IP header set by Fastly to get the
 * real client IP address.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://developer.fastly.com/reference/http/http-headers/Fastly-Client-IP/
 *
 * @psalm-immutable
 */
final readonly class FastlyIpResolver implements IpResolver
{
    /**
     * The HTTP header name used by Fastly to pass the real client IP.
     *
     * Fastly automatically adds this header to all requests proxied through
     * their CDN, containing the original client IP address.
     */
    private const string HEADER = 'Fastly-Client-IP';

    /**
     * Resolves the client IP address from Fastly headers.
     *
     * Reads the Fastly-Client-IP header set by Fastly's edge servers,
     * which contains the original client IP address before proxying through
     * their CDN. Falls back to Laravel's standard IP resolution if the header
     * is missing or the request is not proxied through Fastly.
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
     * @return string The identifier 'fastly'
     */
    public function identifier(): string
    {
        return 'fastly';
    }
}
