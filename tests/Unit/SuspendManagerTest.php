<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\EmailMatcher;
use Cline\Suspend\Resolvers\Geo\NullGeoResolver;
use Cline\Suspend\Resolvers\Ip\StandardIpResolver;
use Cline\Suspend\Strategies\SimpleStrategy;
use Cline\Suspend\SuspendManager;

describe('SuspendManager', function (): void {
    beforeEach(function (): void {
        $this->ipResolver = new StandardIpResolver();
        $this->geoResolver = new NullGeoResolver();
        $this->manager = new SuspendManager($this->ipResolver, $this->geoResolver);
    });

    describe('matchers', function (): void {
        it('registers a matcher', function (): void {
            $matcher = new EmailMatcher();

            $result = $this->manager->registerMatcher($matcher);

            expect($result)->toBe($this->manager);
            expect($this->manager->getMatcher('email'))->toBe($matcher);
        });

        it('returns null for unregistered matcher', function (): void {
            expect($this->manager->getMatcher('nonexistent'))->toBeNull();
        });

        it('returns all registered matchers', function (): void {
            $matcher = new EmailMatcher();
            $this->manager->registerMatcher($matcher);

            $matchers = $this->manager->getMatchers();

            expect($matchers)->toBeArray();
            expect($matchers)->toHaveKey('email');
            expect($matchers['email'])->toBe($matcher);
        });
    });

    describe('strategies', function (): void {
        it('registers a strategy', function (): void {
            $strategy = new SimpleStrategy();

            $result = $this->manager->registerStrategy($strategy);

            expect($result)->toBe($this->manager);
            expect($this->manager->getStrategy('simple'))->toBe($strategy);
        });

        it('returns null for unregistered strategy', function (): void {
            expect($this->manager->getStrategy('nonexistent'))->toBeNull();
        });

        it('returns all registered strategies', function (): void {
            $strategy = new SimpleStrategy();
            $this->manager->registerStrategy($strategy);

            $strategies = $this->manager->getStrategies();

            expect($strategies)->toBeArray();
            expect($strategies)->toHaveKey('simple');
            expect($strategies['simple'])->toBe($strategy);
        });
    });

    describe('resolvers', function (): void {
        it('returns the IP resolver', function (): void {
            expect($this->manager->getIpResolver())->toBe($this->ipResolver);
        });

        it('returns the geo resolver', function (): void {
            expect($this->manager->getGeoResolver())->toBe($this->geoResolver);
        });
    });
});
