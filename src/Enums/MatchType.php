<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Enums;

/**
 * Built-in context match types for suspensions.
 *
 * These represent the standard types of contextual data that can be
 * matched for suspensions. Context-based suspensions use these match types
 * to evaluate runtime data (from requests, models, etc.) against suspension
 * criteria without requiring direct entity relationships.
 *
 * Custom matchers can extend beyond these built-in types by registering
 * additional match types through the MatcherRegistry.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum MatchType: string
{
    /**
     * Match by email address.
     *
     * Supports wildcard patterns like *@domain.com for domain-wide blocks
     * and user@* for specific user across multiple domains.
     */
    case Email = 'email';

    /**
     * Match by phone number.
     *
     * Phone numbers are normalized to E.164 international format before
     * matching to ensure consistency across different input formats.
     */
    case Phone = 'phone';

    /**
     * Match by IP address.
     *
     * Supports both individual IPs (192.168.1.1) and CIDR notation ranges
     * (192.168.1.0/24) for network-wide blocks.
     */
    case Ip = 'ip';

    /**
     * Match by domain name.
     *
     * Supports subdomain matching patterns, allowing blocks of specific
     * domains or entire domain hierarchies.
     */
    case Domain = 'domain';

    /**
     * Match by country code.
     *
     * Uses ISO 3166-1 alpha-2 country codes (US, GB, DE) for geographic
     * access restrictions and compliance requirements.
     */
    case Country = 'country';

    /**
     * Match by device fingerprint.
     *
     * Identifies devices using browser/device fingerprinting techniques
     * for tracking repeat offenders across sessions and accounts.
     */
    case Fingerprint = 'fingerprint';

    /**
     * Match by regular expression pattern.
     *
     * Provides maximum flexibility for complex matching scenarios using
     * PCRE regex patterns. Use with caution due to performance implications.
     */
    case Regex = 'regex';

    /**
     * Match by exact string equality.
     *
     * Simple case-sensitive string comparison for scenarios requiring
     * precise matches without pattern matching overhead.
     */
    case Exact = 'exact';
}
