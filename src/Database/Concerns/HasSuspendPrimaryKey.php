<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Database\Concerns;

use Cline\Suspend\Enums\PrimaryKeyType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

use function array_values;
use function config;
use function is_numeric;

/**
 * Configures model primary key based on package configuration.
 *
 * Dynamically applies HasUuids or HasUlids trait behavior based on
 * the configured primary key type, maintaining compatibility with
 * auto-incrementing IDs as the default.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait HasSuspendPrimaryKey
{
    use HasUlids {
        HasUlids::newUniqueId as ulidUniqueId;
        HasUlids::uniqueIds as ulidUniqueIds;
        HasUlids::isValidUniqueId as ulidIsValidUniqueId;
    }
    use HasUuids {
        HasUuids::newUniqueId as uuidUniqueId;
        HasUuids::uniqueIds as uuidUniqueIds;
        HasUuids::isValidUniqueId as uuidIsValidUniqueId;
    }

    /**
     * Initialize the trait for an instance.
     *
     * Configures the model's key type and incrementing behavior based on
     * the configured primary key type (ID, UUID, or ULID).
     */
    public function initializeHasSuspendPrimaryKey(): void
    {
        $type = $this->getPrimaryKeyType();

        if ($type === PrimaryKeyType::ID) {
            return;
        }

        $this->keyType = 'string';
        $this->incrementing = false;
    }

    /**
     * Generate a new unique ID for the model.
     *
     * @return null|string Generated UUID or ULID, or null for auto-incrementing IDs
     */
    public function newUniqueId(): ?string
    {
        return match ($this->getPrimaryKeyType()) {
            PrimaryKeyType::UUID => $this->uuidUniqueId(),
            PrimaryKeyType::ULID => $this->ulidUniqueId(),
            PrimaryKeyType::ID => null,
        };
    }

    /**
     * Get the columns that should receive unique IDs.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        $type = $this->getPrimaryKeyType();

        if ($type === PrimaryKeyType::ID) {
            return [];
        }

        /** @var array<int|string, string> $ids */
        $ids = match ($type) {
            PrimaryKeyType::UUID => $this->uuidUniqueIds(),
            PrimaryKeyType::ULID => $this->ulidUniqueIds(),
        };

        return array_values($ids);
    }

    /**
     * Determine if the given value is a valid unique identifier.
     *
     * @param  mixed $value Value to validate
     * @return bool  True if the value is valid for the configured primary key type
     */
    public function isValidUniqueId(mixed $value): bool
    {
        return match ($this->getPrimaryKeyType()) {
            PrimaryKeyType::UUID => $this->uuidIsValidUniqueId($value),
            PrimaryKeyType::ULID => $this->ulidIsValidUniqueId($value),
            PrimaryKeyType::ID => is_numeric($value),
        };
    }

    /**
     * Get the configured primary key type.
     *
     * @return PrimaryKeyType The configured primary key type from config or default to ID
     */
    private function getPrimaryKeyType(): PrimaryKeyType
    {
        /** @var int|string $configValue */
        $configValue = config('suspend.primary_key_type', 'id');

        return PrimaryKeyType::tryFrom($configValue) ?? PrimaryKeyType::ID;
    }
}
