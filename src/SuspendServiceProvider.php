<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend;

use Cline\Suspend\Database\ModelRegistry;
use Cline\Suspend\Database\Models\Suspension;
use Cline\Suspend\Http\Middleware\CheckSuspension;
use Cline\Suspend\Matchers\CountryMatcher;
use Cline\Suspend\Matchers\DomainMatcher;
use Cline\Suspend\Matchers\EmailMatcher;
use Cline\Suspend\Matchers\ExactMatcher;
use Cline\Suspend\Matchers\FingerprintMatcher;
use Cline\Suspend\Matchers\GlobMatcher;
use Cline\Suspend\Matchers\IpMatcher;
use Cline\Suspend\Matchers\PhoneMatcher;
use Cline\Suspend\Matchers\RegexMatcher;
use Cline\Suspend\Resolvers\Contracts\GeoResolver;
use Cline\Suspend\Resolvers\Contracts\IpResolver;
use Cline\Suspend\Strategies\CountryStrategy;
use Cline\Suspend\Strategies\IpAddressStrategy;
use Cline\Suspend\Strategies\SimpleStrategy;
use Cline\Suspend\Strategies\TimeWindowStrategy;
use Cline\VariableKeys\Enums\PrimaryKeyType;
use Cline\VariableKeys\Facades\VariableKeys;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function config;

/**
 * Service provider for the Suspend package.
 *
 * Registers all package services including the manager, resolvers, matchers,
 * and strategies. Also publishes configuration files and migrations, and
 * registers the middleware for route-level suspension checking.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SuspendServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     *
     * Sets up the package name, configuration file, and database migrations
     * for publishing and loading.
     *
     * @param Package $package Package configuration instance
     */
    #[Override()]
    public function configurePackage(Package $package): void
    {
        $package
            ->name('suspend')
            ->hasConfigFile()
            ->hasMigration('create_suspend_tables');
    }

    /**
     * Register package services.
     *
     * Registers all core services as singletons in the container including
     * the model registry, IP and geo resolvers (from configuration), the main
     * suspend manager, and all built-in matchers and strategies. Also creates
     * a facade alias for convenient access.
     */
    #[Override()]
    public function registeringPackage(): void
    {
        // Register ModelRegistry as singleton
        $this->app->singleton(ModelRegistry::class);

        // Register IP resolver
        $this->app->singleton(function (Container $app): IpResolver {
            /** @var string $resolverClass */
            $resolverClass = config('suspend.ip_resolver');

            /** @var IpResolver */
            return $app->make($resolverClass);
        });

        // Register Geo resolver
        $this->app->singleton(function (Container $app): GeoResolver {
            /** @var string $resolverClass */
            $resolverClass = config('suspend.geo_resolver');

            /** @var GeoResolver */
            return $app->make($resolverClass);
        });

        // Register the manager
        $this->app->singleton(function (Container $app): SuspendManager {
            /** @var IpResolver $ipResolver */
            $ipResolver = $app->make(IpResolver::class);

            /** @var GeoResolver $geoResolver */
            $geoResolver = $app->make(GeoResolver::class);

            $manager = new SuspendManager($ipResolver, $geoResolver);

            $this->registerMatchers($manager);
            $this->registerStrategies($manager, $app);

            return $manager;
        });

        // Alias for facade
        $this->app->alias(SuspendManager::class, 'suspend');
    }

    /**
     * Boot package services.
     *
     * Applies custom table configuration if provided, registers the Suspension model
     * with VariableKeys for primary key configuration, and registers the
     * 'suspended' middleware alias for convenient route usage.
     */
    #[Override()]
    public function bootingPackage(): void
    {
        // Configure custom tables if set
        /** @var array<string, string> $tables */
        $tables = config('suspend.tables', []);

        if (!empty($tables)) {
            /** @var ModelRegistry $registry */
            $registry = $this->app->make(ModelRegistry::class);
            $registry->setTables($tables);
        }

        // Register Suspension model with VariableKeys
        VariableKeys::map([
            Suspension::class => [
                'primary_key_type' => PrimaryKeyType::from(config('suspend.primary_key_type', 'id')),
            ],
        ]);

        // Register middleware alias for route usage
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('suspended', CheckSuspension::class);
    }

    /**
     * Register built-in matchers.
     *
     * Registers all default matcher implementations for email, phone, IP,
     * domain, country, fingerprint, regex, glob, and exact matching.
     *
     * @param SuspendManager $manager Manager instance to register matchers with
     */
    private function registerMatchers(SuspendManager $manager): void
    {
        $manager->registerMatcher(
            new EmailMatcher(),
        );
        $manager->registerMatcher(
            new PhoneMatcher(),
        );
        $manager->registerMatcher(
            new IpMatcher(),
        );
        $manager->registerMatcher(
            new DomainMatcher(),
        );
        $manager->registerMatcher(
            new CountryMatcher(),
        );
        $manager->registerMatcher(
            new FingerprintMatcher(),
        );
        $manager->registerMatcher(
            new RegexMatcher(),
        );
        $manager->registerMatcher(
            new GlobMatcher(),
        );
        $manager->registerMatcher(
            new ExactMatcher(),
        );
    }

    /**
     * Register built-in strategies.
     *
     * Registers all default strategy implementations including simple,
     * time window, IP address, and country-based strategies with their
     * required dependencies resolved from the container.
     *
     * @param SuspendManager $manager Manager instance to register strategies with
     * @param Container      $app     Container instance for resolving dependencies
     */
    private function registerStrategies(SuspendManager $manager, Container $app): void
    {
        $manager->registerStrategy(
            new SimpleStrategy(),
        );
        $manager->registerStrategy(
            new TimeWindowStrategy(),
        );

        /** @var IpResolver $ipResolver */
        $ipResolver = $app->make(IpResolver::class);
        $manager->registerStrategy(
            new IpAddressStrategy(
                $ipResolver,
                new IpMatcher(),
            ),
        );

        /** @var IpResolver $ipResolverForCountry */
        $ipResolverForCountry = $app->make(IpResolver::class);

        /** @var GeoResolver $geoResolver */
        $geoResolver = $app->make(GeoResolver::class);
        $manager->registerStrategy(
            new CountryStrategy(
                $ipResolverForCountry,
                $geoResolver,
            ),
        );
    }
}
