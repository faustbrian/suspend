<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Database\ModelRegistry;
use Cline\Suspend\SuspendManager;

describe('SuspendServiceProvider', function (): void {
    describe('custom tables configuration', function (): void {
        it('configures custom table names when provided', function (): void {
            // Set custom tables config
            config(['suspend.tables' => [
                'suspensions' => 'custom_suspensions',
            ]]);

            // Re-boot the service provider to apply config
            $registry = resolve(ModelRegistry::class);
            $registry->setTables(['suspensions' => 'custom_suspensions']);

            expect($registry->table('suspensions'))->toBe('custom_suspensions');
        });
    });

    describe('manager registration', function (): void {
        it('registers SuspendManager as singleton', function (): void {
            $manager1 = resolve(SuspendManager::class);
            $manager2 = resolve(SuspendManager::class);

            expect($manager1)->toBe($manager2);
        });

        it('registers suspend alias', function (): void {
            $manager = resolve('suspend');

            expect($manager)->toBeInstanceOf(SuspendManager::class);
        });
    });
});
