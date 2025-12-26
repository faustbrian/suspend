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

use function explode;
use function is_string;
use function mb_trim;

/**
 * IP resolver for AWS API Gateway and ALB.
 *
 * AWS proxies append the client IP to the X-Forwarded-For header.
 * This resolver extracts the first (original client) IP from the chain.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.aws.amazon.com/elasticloadbalancing/latest/application/x-forwarded-headers.html
 *
 * @psalm-immutable
 */
final readonly class AwsApiGatewayIpResolver implements IpResolver
{
    /**
     * The HTTP header name used by AWS to pass forwarded IP addresses.
     *
     * AWS API Gateway and Application Load Balancer append client IPs to this
     * header as requests pass through their infrastructure.
     */
    private const string HEADER = 'X-Forwarded-For';

    /**
     * Resolves the client IP address from AWS forwarding headers.
     *
     * Parses the X-Forwarded-For header which contains a comma-separated
     * list of IP addresses in the format: client, proxy1, proxy2, ...
     * Extracts the first (leftmost) IP which represents the original client.
     * Falls back to Laravel's standard IP resolution if the header is missing.
     *
     * @param  Request     $request The current HTTP request
     * @return null|string The client IP address or null if unavailable
     */
    public function resolve(Request $request): ?string
    {
        $forwardedFor = $request->header(self::HEADER);

        if (is_string($forwardedFor) && $forwardedFor !== '') {
            // X-Forwarded-For format: client, proxy1, proxy2, ...
            $ips = explode(',', $forwardedFor);
            $clientIp = mb_trim($ips[0]);

            if ($clientIp !== '') {
                return $clientIp;
            }
        }

        // Fallback to standard resolution
        return $request->ip();
    }

    /**
     * Returns the unique identifier for this resolver.
     *
     * @return string The identifier 'aws'
     */
    public function identifier(): string
    {
        return 'aws';
    }
}
