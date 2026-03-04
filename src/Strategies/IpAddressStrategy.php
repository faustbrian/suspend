<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Strategies;

use Cline\Suspend\Contracts\Strategy;
use Cline\Suspend\Matchers\IpMatcher;
use Cline\Suspend\Resolvers\Contracts\IpResolver;
use Illuminate\Http\Request;

use function is_string;

/**
 * Strategy that matches based on IP address.
 *
 * Uses the configured IP resolver to extract the client IP and
 * the IP matcher for comparison with support for CIDR notation.
 * Supports both exact IP matching and CIDR range matching for
 * suspending entire subnets.
 *
 * Metadata format:
 * - ip: string (required) - IP address (e.g., '192.168.1.1') or CIDR range (e.g., '10.0.0.0/8')
 *
 * ```php
 * // Suspend a single IP address
 * $suspension = Suspension::create([
 *     'strategy' => 'ip_address',
 *     'strategy_metadata' => ['ip' => '192.168.1.100'],
 * ]);
 *
 * // Suspend an entire subnet using CIDR notation
 * $suspension = Suspension::create([
 *     'strategy' => 'ip_address',
 *     'strategy_metadata' => ['ip' => '10.0.0.0/8'],
 * ]);
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class IpAddressStrategy implements Strategy
{
    /**
     * Create a new IP address strategy.
     *
     * @param IpResolver $ipResolver Resolver for extracting the client's IP address from the request
     * @param IpMatcher  $ipMatcher  Matcher for IP comparison supporting CIDR notation and range matching
     */
    public function __construct(
        private IpResolver $ipResolver,
        private IpMatcher $ipMatcher,
    ) {}

    /**
     * Determine if the strategy matches the current request.
     *
     * Extracts the client IP address from the request and compares it
     * against the suspended IP address or CIDR range using the IP matcher.
     *
     * @param  Request              $request  HTTP request containing the client's IP address
     * @param  array<string, mixed> $metadata Strategy metadata containing the 'ip' string
     * @return bool                 True if the client's IP matches the suspended IP or falls within the CIDR range, false otherwise
     */
    public function matches(Request $request, array $metadata = []): bool
    {
        $suspendedIp = $metadata['ip'] ?? null;

        if (!is_string($suspendedIp)) {
            return false;
        }

        $clientIp = $this->ipResolver->resolve($request);

        if ($clientIp === null) {
            return false;
        }

        return $this->ipMatcher->matches($suspendedIp, $clientIp);
    }

    /**
     * Get the unique identifier for this strategy type.
     *
     * @return string Strategy identifier used for registration and lookup
     */
    public function identifier(): string
    {
        return 'ip_address';
    }
}
