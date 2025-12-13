<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Facades;

use Cline\Suspend\Conductors\CheckConductor;
use Cline\Suspend\Conductors\MatchConductor;
use Cline\Suspend\Conductors\SuspensionConductor;
use Cline\Suspend\Contracts\Strategy;
use Cline\Suspend\Matchers\Contracts\Matcher;
use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Resolvers\Contracts\IpResolver;
use Cline\Suspend\SuspendManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Override;

/**
 * Facade for the SuspendManager service.
 *
 * Provides a convenient static interface to the suspension management
 * system. Use this facade to check for active suspensions, create new
 * suspensions, match contextual data, and configure matchers and strategies.
 *
 * ```php
 * // Check for IP-based suspension
 * $suspension = Suspend::check()->ip('192.168.1.1')->first();
 *
 * // Create a suspension for a user
 * Suspend::for($user)->suspend('abuse', ['reason' => 'Spam detected']);
 *
 * // Match an email against suspended patterns
 * $matches = Suspend::match('email', 'user@example.com')->get();
 * ```
 *
 * @method static CheckConductor          check()                              Start building a suspension check query
 * @method static SuspensionConductor     for(Model $context)                  Create suspensions for a specific model context
 * @method static GeoResolver             getGeoResolver()                     Get the configured geographic location resolver
 * @method static IpResolver              getIpResolver()                      Get the configured IP address resolver
 * @method static null|Matcher            getMatcher(string $type)             Retrieve a registered matcher by type identifier
 * @method static array<string, Matcher>  getMatchers()                        Get all registered matchers indexed by type
 * @method static array<string, Strategy> getStrategies()                      Get all registered strategies indexed by identifier
 * @method static null|Strategy           getStrategy(string $identifier)      Retrieve a registered strategy by identifier
 * @method static MatchConductor          match(string $type, string $value)   Start building a contextual match query
 * @method static SuspendManager          registerMatcher(Matcher $matcher)    Register a custom matcher implementation
 * @method static SuspendManager          registerStrategy(Strategy $strategy) Register a custom suspension strategy
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see SuspendManager
 */
final class Suspend extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * Returns the service container binding that this facade proxies to.
     * All static method calls will be forwarded to the SuspendManager instance.
     *
     * @return string The fully qualified class name of the underlying service
     */
    #[Override()]
    protected static function getFacadeAccessor(): string
    {
        return SuspendManager::class;
    }
}
