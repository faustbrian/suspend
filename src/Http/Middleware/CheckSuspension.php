<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Http\Middleware;

use Cline\Suspend\Conductors\CheckConductor;
use Cline\Suspend\Database\Models\Suspension;
use Cline\Suspend\Exceptions\SuspendedException;
use Cline\Suspend\SuspendManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function config;
use function is_object;
use function method_exists;

/**
 * Middleware to check for active suspensions on incoming requests.
 *
 * Examines incoming HTTP requests against configured suspension types
 * and throws a SuspendedException if any active suspension matches.
 * Supports multiple check types that can be configured globally or
 * specified per-route.
 *
 * Available check types:
 * - `ip` - Check for IP address suspensions using the configured IP resolver
 * - `country` - Check for country-based suspensions using GeoIP resolution
 * - `user` - Check for authenticated user suspensions (future enhancement)
 *
 * Usage in routes:
 * ```php
 * // Use default checks from config
 * Route::middleware('suspend')->group(function () { ... });
 *
 * // Specify checks explicitly
 * Route::middleware('suspend:ip,country')->group(function () { ... });
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CheckSuspension
{
    /**
     * Create a new middleware instance.
     *
     * @param SuspendManager $manager The suspension manager service for checking suspensions
     */
    public function __construct(
        private SuspendManager $manager,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Performs the configured suspension checks on the request. If any active
     * suspension matches, throws a SuspendedException with the HTTP response
     * configured in the package settings. If no checks are specified, uses
     * the default checks from configuration.
     *
     * @param Request                    $request   The incoming HTTP request to check
     * @param Closure(Request): Response $next      The next middleware in the pipeline
     * @param string                     ...$checks Optional check types to perform (ip, country, user)
     *
     * @throws SuspendedException When an active suspension matches the request
     *
     * @return Response The HTTP response from the next middleware
     */
    public function handle(Request $request, Closure $next, string ...$checks): Response
    {
        // Default to configured checks if none specified
        if ($checks === []) {
            $checks = $this->getDefaultChecks();
        }

        // Build the check conductor
        $conductor = $this->manager->check();

        foreach ($checks as $check) {
            match ($check) {
                'ip' => $this->addIpCheck($conductor, $request),
                'country' => $this->addCountryCheck($conductor, $request),
                'user' => $this->addUserCheck($request),
                default => null, // Ignore unknown checks
            };
        }

        // Check for matching suspension
        $suspension = $conductor->first();

        if ($suspension instanceof Suspension) {
            throw SuspendedException::fromSuspension($suspension);
        }

        return $next($request);
    }

    /**
     * Get default checks from configuration.
     *
     * Reads the configured default check types from the package configuration.
     * This allows applications to set site-wide defaults while still allowing
     * route-specific overrides via middleware parameters.
     *
     * @return list<string> Array of check type identifiers (e.g., ['ip', 'country'])
     */
    private function getDefaultChecks(): array
    {
        $checks = [];

        if (config('suspend.middleware.check_ip', true)) {
            $checks[] = 'ip';
        }

        if (config('suspend.middleware.check_country', false)) {
            $checks[] = 'country';
        }

        return $checks;
    }

    /**
     * Add IP address check to the conductor.
     *
     * Resolves the client IP address from the request using the configured
     * IP resolver and adds it to the suspension check conductor.
     *
     * @param CheckConductor $conductor The check conductor to add the IP constraint to
     * @param Request        $request   The incoming HTTP request
     */
    private function addIpCheck(CheckConductor $conductor, Request $request): void
    {
        $ip = $this->manager->getIpResolver()->resolve($request);

        if ($ip === null) {
            return;
        }

        $conductor->ip($ip);
    }

    /**
     * Add country-based check to the conductor.
     *
     * Resolves the client IP address, performs GeoIP lookup using the
     * configured geo resolver, and adds the country code to the suspension
     * check conductor. Requires GeoIP functionality to be configured.
     *
     * @param CheckConductor $conductor The check conductor to add the country constraint to
     * @param Request        $request   The incoming HTTP request
     */
    private function addCountryCheck(CheckConductor $conductor, Request $request): void
    {
        $ip = $this->manager->getIpResolver()->resolve($request);

        if ($ip === null) {
            return;
        }

        $country = $this->manager->getGeoResolver()->country($ip);

        if ($country === null) {
            return;
        }

        $conductor->country($country);
    }

    /**
     * Add authenticated user check to the conductor.
     *
     * Placeholder for future enhancement to check user-specific suspensions.
     * Currently not implemented - user suspensions should be handled through
     * model traits and scopes on the User model itself.
     *
     * @param Request $request The incoming HTTP request
     */
    private function addUserCheck(Request $request): void
    {
        $user = $request->user();

        if ($user === null || !is_object($user) || !method_exists($user, 'isSuspended')) {
            return;
        }

        // User check is handled separately through the model
        // This is a placeholder for future enhancement
    }
}
