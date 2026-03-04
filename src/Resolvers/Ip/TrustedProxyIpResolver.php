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

use function array_map;
use function array_reverse;
use function count;
use function explode;
use function in_array;
use function is_string;

/**
 * IP resolver that respects a configured list of trusted proxies.
 *
 * Walks the X-Forwarded-For chain from right to left, stopping at the
 * first IP that is not in the trusted proxies list.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TrustedProxyIpResolver implements IpResolver
{
    /**
     * Create a new trusted proxy IP resolver.
     *
     * @param list<string> $trustedProxies Array of IP addresses representing trusted proxies
     *                                     (e.g., ['10.0.0.1', '192.168.1.1']). The resolver
     *                                     will walk the X-Forwarded-For chain from right to
     *                                     left, skipping IPs in this list until it finds the
     *                                     first non-trusted IP which represents the real client.
     */
    public function __construct(
        private array $trustedProxies = [],
    ) {}

    /**
     * Resolves the client IP address by walking the proxy chain.
     *
     * Parses the X-Forwarded-For header and walks through the IP chain from
     * right to left (most recent proxy first), skipping any IPs that are in
     * the trusted proxies list. Returns the first IP that is not trusted,
     * which represents the actual client. If all IPs are trusted, returns
     * the leftmost (original) IP in the chain.
     *
     * The algorithm adds the direct connection IP (REMOTE_ADDR) to the chain
     * before processing to ensure proper validation of the entire proxy path.
     *
     * @param  Request     $request The current HTTP request
     * @return null|string The client IP address or null if unavailable
     */
    public function resolve(Request $request): ?string
    {
        $forwardedFor = $request->header('X-Forwarded-For');

        if (!is_string($forwardedFor) || $forwardedFor === '') {
            return $request->ip();
        }

        $ips = array_map(trim(...), explode(',', $forwardedFor));

        // Add the direct connection IP to the chain
        $directIp = $request->server('REMOTE_ADDR');

        if (is_string($directIp)) {
            $ips[] = $directIp;
        }

        // Walk from right to left (most recent proxy first)
        $ips = array_reverse($ips);

        foreach ($ips as $ip) {
            // Skip trusted proxies
            if (in_array($ip, $this->trustedProxies, true)) {
                continue;
            }

            // First non-trusted IP is the client
            return $ip;
        }

        // All IPs were trusted, return the leftmost (original)
        $lastIp = $ips[count($ips) - 1] ?? null;

        return is_string($lastIp) ? $lastIp : $request->ip();
    }

    /**
     * Returns the unique identifier for this resolver.
     *
     * @return string The identifier 'trusted_proxy'
     */
    public function identifier(): string
    {
        return 'trusted_proxy';
    }
}
