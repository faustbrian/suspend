<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Strategies;

use Cline\Suspend\Contracts\Strategy;
use Illuminate\Http\Request;

/**
 * Simple strategy that always matches.
 *
 * Used for basic suspensions without additional conditions. This is the
 * default strategy when no specific matching logic is needed. The suspension
 * record itself (context, expiration, revocation status) determines if the
 * suspension applies, rather than request-specific criteria.
 *
 * ```php
 * // Simple suspension for a user (no special conditions)
 * $suspension = Suspension::create([
 *     'context_type' => User::class,
 *     'context_id' => $user->id,
 *     'strategy' => 'simple',
 * ]);
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class SimpleStrategy implements Strategy
{
    /**
     * Determine if the strategy matches the current request.
     *
     * This strategy always returns true, delegating the suspension decision
     * to the suspension record's context, expiration, and revocation status.
     *
     * @param  Request              $request  HTTP request (not evaluated by this strategy)
     * @param  array<string, mixed> $metadata Strategy metadata (not used by this strategy)
     * @return bool                 Always returns true
     */
    public function matches(Request $request, array $metadata = []): bool
    {
        // Always matches - the suspension record determines applicability
        return true;
    }

    /**
     * Get the unique identifier for this strategy type.
     *
     * @return string Strategy identifier used for registration and lookup
     */
    public function identifier(): string
    {
        return 'simple';
    }
}
