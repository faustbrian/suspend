<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Database;

use Cline\Suspend\Database\Models\Suspension;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;

use function array_filter;
use function array_key_exists;
use function array_merge;
use function assert;
use function class_exists;

/**
 * Central registry for Suspend model and table configuration.
 *
 * This registry pattern allows customization of model classes and table names
 * across the entire Suspend system. All model instantiation and database queries
 * should use this registry to respect custom configurations.
 *
 * Bound as singleton in the service container for Octane compatibility.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Singleton()]
final class ModelRegistry
{
    /**
     * Registry mapping default model classes to custom implementations.
     *
     * @var array<class-string|string, class-string>
     */
    private array $models = [];

    /**
     * Registry mapping logical table names to physical database table names.
     *
     * @var array<string, string>
     */
    private array $tables = [];

    /**
     * Register a custom model class for suspensions.
     *
     * @param class-string $model Fully-qualified class name of the custom Suspension model
     */
    public function setSuspensionModel(string $model): void
    {
        $this->models[Suspension::class] = $model;

        $this->updateMorphMap([$model]);
    }

    /**
     * Register custom table names for Suspend's database tables.
     *
     * @param array<string, string> $map Associative array of logical name => physical table name
     */
    public function setTables(array $map): void
    {
        $this->tables = array_merge($this->tables, $map);

        $this->updateMorphMap();
    }

    /**
     * Resolve a logical table name to its physical database table name.
     *
     * @param  string $table Logical table name (e.g., 'suspensions')
     * @return string Physical database table name
     */
    public function table(string $table): string
    {
        if (array_key_exists($table, $this->tables)) {
            return $this->tables[$table];
        }

        return $table;
    }

    /**
     * Resolve a model class to its registered custom implementation.
     *
     * @param  class-string $model Default model class name
     * @return class-string Registered custom class or the original class
     */
    public function classname(string $model): string
    {
        if (array_key_exists($model, $this->models)) {
            return $this->models[$model];
        }

        return $model;
    }

    /**
     * Update Eloquent's morph map with Suspend model classes.
     *
     * @param null|array<int, class-string> $classNames Optional array of model classes to register
     */
    public function updateMorphMap(?array $classNames = null): void
    {
        if (null === $classNames) {
            $classNames = [
                $this->classname(Suspension::class),
            ];
        }

        /** @var array<string, class-string<Model>> $morphMap */
        $morphMap = array_filter($classNames, class_exists(...));
        Relation::morphMap($morphMap);
    }

    /**
     * Create a new instance of the registered Suspension model.
     *
     * @param array<string, mixed> $attributes Initial attribute values
     */
    public function suspension(array $attributes = []): Suspension
    {
        $model = $this->make(Suspension::class, $attributes);
        assert($model instanceof Suspension);

        return $model;
    }

    /**
     * Create a new query builder for a Suspend table.
     *
     * @param  string  $table Logical table name
     * @return Builder Configured query builder instance
     */
    public function query(string $table): Builder
    {
        $model = $this->suspension();
        $connection = $model->getConnection();
        $grammar = $connection->getQueryGrammar();
        $processor = $connection->getPostProcessor();

        $query = new Builder($connection, $grammar, $processor);

        return $query->from($this->table($table));
    }

    /**
     * Reset all registry settings to defaults.
     *
     * Clears all custom model and table registrations, reverting to default
     * configuration. Useful for testing or Octane request cleanup.
     */
    public function reset(): void
    {
        $this->models = [];
        $this->tables = [];
    }

    /**
     * Instantiate a model class respecting custom registrations.
     *
     * @param  class-string         $model      Default model class name
     * @param  array<string, mixed> $attributes Initial attribute values
     * @return Model                New model instance
     */
    private function make(string $model, array $attributes = []): Model
    {
        $modelClass = $this->classname($model);

        $instance = new $modelClass($attributes);
        assert($instance instanceof Model);

        return $instance;
    }
}
