<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Database\ModelRegistry;
use Cline\Suspend\Database\Models\Suspension;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;

describe('ModelRegistry', function (): void {
    beforeEach(function (): void {
        $this->registry = resolve(ModelRegistry::class);
        $this->registry->reset();
        Relation::morphMap([], false); // Clear morph map
    });

    describe('Happy Paths', function (): void {
        test('registers suspension model class in internal registry', function (): void {
            // Arrange
            $modelClass = Suspension::class;

            // Act
            $this->registry->setSuspensionModel($modelClass);

            // Assert
            expect($this->registry->classname(Suspension::class))->toBe($modelClass);
        });

        test('registers custom table names and resolves them correctly', function (): void {
            // Arrange
            $tableMap = [
                'suspensions' => 'custom_suspensions_table',
                'other_table' => 'custom_other_table',
            ];

            // Act
            $this->registry->setTables($tableMap);

            // Assert
            expect($this->registry->table('suspensions'))->toBe('custom_suspensions_table')
                ->and($this->registry->table('other_table'))->toBe('custom_other_table');
        });

        test('resolves table name to original when not registered', function (): void {
            // Arrange
            $unregisteredTable = 'some_unregistered_table';

            // Act
            $result = $this->registry->table($unregisteredTable);

            // Assert
            expect($result)->toBe($unregisteredTable);
        });

        test('resolves model classname to original when not registered', function (): void {
            // Arrange
            $unregisteredClass = Suspension::class;

            // Act
            $result = $this->registry->classname($unregisteredClass);

            // Assert
            expect($result)->toBe(Suspension::class);
        });

        test('creates suspension instance with default model class', function (): void {
            // Arrange & Act
            $suspension = $this->registry->suspension();

            // Assert
            expect($suspension)->toBeInstanceOf(Suspension::class);
        });

        test('creates suspension instance with initial attributes', function (): void {
            // Arrange
            $attributes = [
                'reason' => 'Test suspension',
                'match_type' => 'email',
                'match_value' => 'test@example.com',
            ];

            // Act
            $suspension = $this->registry->suspension($attributes);

            // Assert
            expect($suspension->reason)->toBe('Test suspension')
                ->and($suspension->match_type)->toBe('email')
                ->and($suspension->match_value)->toBe('test@example.com');
        });

        test('creates query builder for default table name', function (): void {
            // Arrange & Act
            $query = $this->registry->query('suspensions');

            // Assert
            expect($query)->toBeInstanceOf(Builder::class)
                ->and($query->from)->toBe('suspensions');
        });

        test('creates query builder for custom table name', function (): void {
            // Arrange
            $this->registry->setTables(['suspensions' => 'custom_suspensions']);

            // Act
            $query = $this->registry->query('suspensions');

            // Assert
            expect($query)->toBeInstanceOf(Builder::class)
                ->and($query->from)->toBe('custom_suspensions');
        });

        test('updates morph map with default suspension model', function (): void {
            // Arrange & Act
            $this->registry->updateMorphMap();

            // Assert
            $morphMap = Relation::morphMap();
            expect($morphMap)->toContain(Suspension::class);
        });

        test('updates morph map with suspension model after registration', function (): void {
            // Arrange
            $this->registry->setSuspensionModel(Suspension::class);

            // Act
            $this->registry->updateMorphMap();

            // Assert
            $morphMap = Relation::morphMap();
            expect($morphMap)->toContain(Suspension::class);
        });

        test('updates morph map with explicit class array', function (): void {
            // Arrange
            $classNames = [Suspension::class];

            // Act
            $this->registry->updateMorphMap($classNames);

            // Assert
            $morphMap = Relation::morphMap();
            expect($morphMap)->toContain(Suspension::class);
        });

        test('resets all custom model registrations to defaults', function (): void {
            // Arrange
            $this->registry->setSuspensionModel(Suspension::class);

            // Act
            $this->registry->reset();

            // Assert
            expect($this->registry->classname(Suspension::class))->toBe(Suspension::class);
        });

        test('resets all custom table registrations to defaults', function (): void {
            // Arrange
            $this->registry->setTables(['suspensions' => 'custom_suspensions']);

            // Act
            $this->registry->reset();

            // Assert
            expect($this->registry->table('suspensions'))->toBe('suspensions');
        });

        test('merges multiple table registrations without overwriting all', function (): void {
            // Arrange
            $this->registry->setTables(['table1' => 'custom_table1']);

            // Act
            $this->registry->setTables(['table2' => 'custom_table2']);

            // Assert
            expect($this->registry->table('table1'))->toBe('custom_table1')
                ->and($this->registry->table('table2'))->toBe('custom_table2');
        });

        test('creates query builder with correct connection and grammar', function (): void {
            // Arrange
            $suspension = new Suspension();

            // Act
            $query = $this->registry->query('suspensions');

            // Assert
            expect($query->getConnection())->toBe($suspension->getConnection())
                ->and($query->getGrammar())->not->toBeNull();
        });

        test('setSuspensionModel updates morph map automatically', function (): void {
            // Arrange
            Relation::morphMap([], false); // Clear morph map

            // Act
            $this->registry->setSuspensionModel(Suspension::class);

            // Assert
            $morphMap = Relation::morphMap();
            expect($morphMap)->toContain(Suspension::class);
        });

        test('setTables triggers morph map update', function (): void {
            // Arrange
            Relation::morphMap([], false); // Clear morph map
            $this->registry->setSuspensionModel(Suspension::class);

            // Act
            $this->registry->setTables(['suspensions' => 'custom_table']);

            // Assert
            $morphMap = Relation::morphMap();
            expect($morphMap)->toContain(Suspension::class);
        });
    });

    describe('Sad Paths', function (): void {
        test('filters non-existent classes from morph map registration', function (): void {
            // Arrange
            $nonExistentClass = 'App\\Models\\NonExistentSuspension';

            // Act
            $this->registry->setSuspensionModel($nonExistentClass);
            $this->registry->updateMorphMap();

            // Assert - morph map should filter out non-existent classes
            $morphMap = Relation::morphMap();
            expect($morphMap)->not->toContain($nonExistentClass);
        });

        test('stores non-existent class in registry but filters from morph map', function (): void {
            // Arrange
            $nonExistentClass = 'App\\Models\\NonExistentSuspension';

            // Act
            $this->registry->setSuspensionModel($nonExistentClass);

            // Assert
            expect($this->registry->classname(Suspension::class))->toBe($nonExistentClass);

            // But morph map should filter it
            $morphMap = Relation::morphMap();
            expect($morphMap)->not->toContain($nonExistentClass);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty table map registration gracefully', function (): void {
            // Arrange
            $emptyMap = [];

            // Act
            $this->registry->setTables($emptyMap);

            // Assert
            expect($this->registry->table('suspensions'))->toBe('suspensions');
        });

        test('overwrites existing table registration with same key', function (): void {
            // Arrange
            $this->registry->setTables(['suspensions' => 'first_custom_table']);

            // Act
            $this->registry->setTables(['suspensions' => 'second_custom_table']);

            // Assert
            expect($this->registry->table('suspensions'))->toBe('second_custom_table');
        });

        test('creates suspension with empty attributes array', function (): void {
            // Arrange
            $emptyAttributes = [];

            // Act
            $suspension = $this->registry->suspension($emptyAttributes);

            // Assert
            expect($suspension)->toBeInstanceOf(Suspension::class)
                ->and($suspension->getAttributes())->toBeArray();
        });

        test('handles multiple reset calls consecutively', function (): void {
            // Arrange
            $this->registry->setSuspensionModel(Suspension::class);
            $this->registry->setTables(['suspensions' => 'custom_table']);

            // Act
            $this->registry->reset();
            $this->registry->reset();
            $this->registry->reset();

            // Assert
            expect($this->registry->classname(Suspension::class))->toBe(Suspension::class)
                ->and($this->registry->table('suspensions'))->toBe('suspensions');
        });

        test('filters out non-existent classes from explicit morph map array', function (): void {
            // Arrange
            $classNames = [
                Suspension::class,            // exists
                'App\\Models\\NonExistent',   // does not exist
            ];

            // Act
            $this->registry->updateMorphMap($classNames);

            // Assert
            $morphMap = Relation::morphMap();
            expect($morphMap)->toContain(Suspension::class)
                ->and($morphMap)->not->toContain('App\\Models\\NonExistent');
        });

        test('handles table name with special characters', function (): void {
            // Arrange
            $specialTableName = 'prefix_suspensions_2024';

            // Act
            $this->registry->setTables(['suspensions' => $specialTableName]);

            // Assert
            expect($this->registry->table('suspensions'))->toBe($specialTableName);
        });

        test('creates query builder after model registration', function (): void {
            // Arrange
            $this->registry->setSuspensionModel(Suspension::class);

            // Act
            $query = $this->registry->query('suspensions');

            // Assert
            expect($query)->toBeInstanceOf(Builder::class)
                ->and($query->from)->toBe('suspensions');
        });

        test('suspension instance respects model registration', function (): void {
            // Arrange
            $this->registry->setSuspensionModel(Suspension::class);
            $attributes = ['reason' => 'Model registration test'];

            // Act
            $suspension = $this->registry->suspension($attributes);

            // Assert
            expect($suspension)->toBeInstanceOf(Suspension::class)
                ->and($suspension->reason)->toBe('Model registration test');
        });

        test('handles null passed to updateMorphMap', function (): void {
            // Arrange
            $this->registry->setSuspensionModel(Suspension::class);

            // Act
            $this->registry->updateMorphMap(null);

            // Assert
            $morphMap = Relation::morphMap();
            expect($morphMap)->toContain(Suspension::class);
        });

        test('query builder uses suspension model connection', function (): void {
            // Arrange & Act
            $query = $this->registry->query('test_table');

            // Assert
            expect($query->from)->toBe('test_table')
                ->and($query->getConnection())->not->toBeNull();
        });

        test('handles unicode characters in table names', function (): void {
            // Arrange
            $unicodeTableName = 'suspensions_café_2024';

            // Act
            $this->registry->setTables(['suspensions' => $unicodeTableName]);

            // Assert
            expect($this->registry->table('suspensions'))->toBe($unicodeTableName);
        });

        test('table resolution with numeric table names', function (): void {
            // Arrange
            $numericTable = '123_suspensions';

            // Act
            $this->registry->setTables(['suspensions' => $numericTable]);

            // Assert
            expect($this->registry->table('suspensions'))->toBe($numericTable);
        });

        test('creates multiple suspension instances independently', function (): void {
            // Arrange
            $attributes1 = ['reason' => 'First suspension'];
            $attributes2 = ['reason' => 'Second suspension'];

            // Act
            $suspension1 = $this->registry->suspension($attributes1);
            $suspension2 = $this->registry->suspension($attributes2);

            // Assert
            expect($suspension1->reason)->toBe('First suspension')
                ->and($suspension2->reason)->toBe('Second suspension')
                ->and($suspension1)->not->toBe($suspension2);
        });

        test('query method creates new builder instance each time', function (): void {
            // Arrange & Act
            $query1 = $this->registry->query('suspensions');
            $query2 = $this->registry->query('suspensions');

            // Assert
            expect($query1)->not->toBe($query2)
                ->and($query1)->toBeInstanceOf(Builder::class)
                ->and($query2)->toBeInstanceOf(Builder::class);
        });
    });

    describe('Regressions', function (): void {
        test('prevents morph map pollution after reset', function (): void {
            // Arrange
            $this->registry->setSuspensionModel(Suspension::class);
            $this->registry->updateMorphMap();

            $initialMorphMap = Relation::morphMap();

            // Act
            $this->registry->reset();
            $this->registry->updateMorphMap();

            // Assert
            $finalMorphMap = Relation::morphMap();
            expect($finalMorphMap)->toContain(Suspension::class);
        });

        test('maintains singleton behavior across multiple app() calls', function (): void {
            // Arrange
            $registry1 = resolve(ModelRegistry::class);
            $registry1->setTables(['suspensions' => 'custom_table']);

            // Act
            $registry2 = resolve(ModelRegistry::class);

            // Assert - both should reference the same instance
            expect($registry2->table('suspensions'))->toBe('custom_table')
                ->and($registry1)->toBe($registry2);
        });

        test('setSuspensionModel followed by setTables maintains both registrations', function (): void {
            // Arrange
            $this->registry->setSuspensionModel(Suspension::class);

            // Act
            $this->registry->setTables(['suspensions' => 'custom_table']);

            // Assert
            expect($this->registry->classname(Suspension::class))->toBe(Suspension::class)
                ->and($this->registry->table('suspensions'))->toBe('custom_table');
        });

        test('table map merge preserves existing entries when adding new ones', function (): void {
            // Arrange
            $this->registry->setTables([
                'table1' => 'custom1',
                'table2' => 'custom2',
            ]);

            // Act
            $this->registry->setTables(['table3' => 'custom3']);

            // Assert
            expect($this->registry->table('table1'))->toBe('custom1')
                ->and($this->registry->table('table2'))->toBe('custom2')
                ->and($this->registry->table('table3'))->toBe('custom3');
        });
    });
});
