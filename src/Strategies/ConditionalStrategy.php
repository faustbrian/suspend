<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Strategies;

use Cline\Suspend\Contracts\Strategy;
use Closure;
use Illuminate\Http\Request;

/**
 * Strategy that evaluates a custom closure condition.
 *
 * Allows for flexible suspension logic using closures. The closure
 * receives the request and metadata and should return a boolean.
 * This enables custom business logic for complex suspension scenarios
 * that don't fit the built-in strategy patterns.
 *
 * ```php
 * $strategy = new ConditionalStrategy(
 *     fn(Request $request, array $metadata) => $request->user()?->isAdmin() === false
 * );
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ConditionalStrategy implements Strategy
{
    /**
     * Create a new conditional strategy.
     *
     * @param Closure(Request, array<string, mixed>): bool $condition Closure that evaluates whether the suspension
     *                                                                matches the current request. Receives the HTTP
     *                                                                request and strategy metadata, and returns true
     *                                                                if the suspension should apply.
     */
    public function __construct(
        private Closure $condition,
    ) {}

    /**
     * Determine if the strategy matches the current request.
     *
     * Executes the configured closure with the request and metadata
     * to determine if the suspension condition is met.
     *
     * @param  Request              $request  HTTP request to evaluate against the condition
     * @param  array<string, mixed> $metadata Strategy metadata passed to the closure
     * @return bool                 True if the closure returns a truthy value, false otherwise
     */
    public function matches(Request $request, array $metadata = []): bool
    {
        return (bool) ($this->condition)($request, $metadata);
    }

    /**
     * Get the unique identifier for this strategy type.
     *
     * @return string Strategy identifier used for registration and lookup
     */
    public function identifier(): string
    {
        return 'conditional';
    }
}
