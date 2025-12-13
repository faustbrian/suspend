<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Contracts;

use Illuminate\Http\Request;

/**
 * Contract for suspension resolution strategies.
 *
 * Strategies encapsulate the decision-making logic for determining whether
 * a suspension applies to a given context. This pattern enables sophisticated
 * suspension mechanisms beyond simple record lookups.
 *
 * Common strategy implementations include:
 * - Simple: Always matches if suspension record exists
 * - IP-based: Matches against IP addresses or CIDR ranges
 * - Country-based: Matches against geographic location
 * - Time-based: Matches only during specific time windows
 * - Conditional: Evaluate based on custom closure logic
 *
 * Strategies receive the current request context and optional metadata that
 * can influence decision-making. The separation of concerns between suspension
 * definition and resolution strategy allows for powerful, reusable logic.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Strategy
{
    /**
     * Determine if this strategy matches for the given request context.
     *
     * This method contains the core logic for determining if a suspension
     * applies. The implementation should be deterministic: the same context
     * and metadata should always produce the same result.
     *
     * @param  Request              $request  The current HTTP request for context extraction
     * @param  array<string, mixed> $metadata Strategy-specific configuration from the suspension record
     * @return bool                 True if the suspension applies, false otherwise
     */
    public function matches(Request $request, array $metadata = []): bool;

    /**
     * Get the unique identifier for this strategy.
     *
     * Used for storing and retrieving strategy configuration in suspension
     * records. Should be a short, descriptive string like 'ip', 'country',
     * 'time_window', etc.
     *
     * @return string The strategy identifier
     */
    public function identifier(): string;
}
