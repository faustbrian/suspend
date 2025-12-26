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

/**
 * Standard IP resolver using Laravel's request IP method.
 *
 * Uses Laravel's built-in IP detection which respects trusted proxies
 * configured in the application. Suitable for most deployments.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class StandardIpResolver implements IpResolver
{
    /**
     * Resolves the client IP address using Laravel's standard method.
     *
     * Uses Laravel's built-in IP detection which automatically respects
     * the trusted proxies configuration defined in your application's
     * TrustProxies middleware. This is the recommended resolver for most
     * deployments that don't require CDN-specific header parsing.
     *
     * @param  Request     $request The current HTTP request
     * @return null|string The client IP address or null if unavailable
     */
    public function resolve(Request $request): ?string
    {
        return $request->ip();
    }

    /**
     * Returns the unique identifier for this resolver.
     *
     * @return string The identifier 'standard'
     */
    public function identifier(): string
    {
        return 'standard';
    }
}
