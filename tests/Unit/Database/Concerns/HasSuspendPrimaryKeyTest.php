<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Database\Models\Suspension;
use Illuminate\Support\Str;

describe('HasSuspendPrimaryKey', function (): void {
    describe('with ID primary key type', function (): void {
        beforeEach(function (): void {
            config(['suspend.primary_key_type' => 'id']);
            $this->suspension = new Suspension();
        });

        describe('Happy Paths', function (): void {
            test('initializes with default incrementing integer key type', function (): void {
                // Arrange & Act
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Assert
                expect($this->suspension->getKeyType())->toBe('int')
                    ->and($this->suspension->getIncrementing())->toBeTrue();
            });

            test('returns null when generating new unique ID for auto-increment', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act
                $result = $this->suspension->newUniqueId();

                // Assert
                expect($result)->toBeNull();
            });

            test('returns empty array for unique ID columns with auto-increment', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act
                $result = $this->suspension->uniqueIds();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toBeEmpty();
            });

            test('validates numeric values as valid unique IDs', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId(123))->toBeTrue()
                    ->and($this->suspension->isValidUniqueId('456'))->toBeTrue()
                    ->and($this->suspension->isValidUniqueId(0))->toBeTrue();
            });

            test('uses ID type behavior based on config', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act
                $uniqueId = $this->suspension->newUniqueId();
                $uniqueIds = $this->suspension->uniqueIds();

                // Assert
                expect($uniqueId)->toBeNull()
                    ->and($uniqueIds)->toBeEmpty();
            });
        });

        describe('Sad Paths', function (): void {
            test('rejects non-numeric values as invalid unique IDs', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId('abc'))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId('uuid-string'))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId(null))->toBeFalse();
            });
        });

        describe('Edge Cases', function (): void {
            test('validates negative numbers as valid numeric IDs', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId(-1))->toBeTrue()
                    ->and($this->suspension->isValidUniqueId('-999'))->toBeTrue();
            });

            test('validates float values as numeric for ID validation', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId(123.45))->toBeTrue()
                    ->and($this->suspension->isValidUniqueId('678.90'))->toBeTrue();
            });
        });
    });

    describe('with UUID primary key type', function (): void {
        beforeEach(function (): void {
            config(['suspend.primary_key_type' => 'uuid']);
            $this->suspension = new Suspension();
        });

        describe('Happy Paths', function (): void {
            test('initializes with string key type and non-incrementing behavior', function (): void {
                // Arrange & Act
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Assert
                expect($this->suspension->getKeyType())->toBe('string')
                    ->and($this->suspension->getIncrementing())->toBeFalse();
            });

            test('generates valid UUID when creating new unique ID', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act
                $result = $this->suspension->newUniqueId();

                // Assert
                expect($result)->toBeString()
                    ->and($result)->not->toBeEmpty()
                    ->and(Str::isUuid($result))->toBeTrue();
            });

            test('returns primary key column in unique IDs array', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act
                $result = $this->suspension->uniqueIds();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->not->toBeEmpty()
                    ->and($result)->toContain($this->suspension->getKeyName());
            });

            test('validates properly formatted UUID strings', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();
                $validUuid = '550e8400-e29b-41d4-a716-446655440000';

                // Act & Assert
                expect($this->suspension->isValidUniqueId($validUuid))->toBeTrue()
                    ->and($this->suspension->isValidUniqueId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'))->toBeTrue();
            });

            test('uses UUID type behavior based on config', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act
                $uniqueId = $this->suspension->newUniqueId();

                // Assert
                expect($uniqueId)->toBeString()
                    ->and(Str::isUuid($uniqueId))->toBeTrue();
            });
        });

        describe('Sad Paths', function (): void {
            test('rejects invalid UUID format strings', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId('not-a-uuid'))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId('12345'))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId('550e8400-e29b-41d4-a716'))->toBeFalse();
            });

            test('rejects numeric values as invalid UUID', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId(123))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId(0))->toBeFalse();
            });

            test('rejects null and empty values as invalid UUID', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId(null))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId(''))->toBeFalse();
            });
        });

        describe('Edge Cases', function (): void {
            test('rejects UUIDs with incorrect case variations', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId('550E8400-E29B-41D4-A716-446655440000'))->toBeTrue()
                    ->and($this->suspension->isValidUniqueId('550e8400-E29B-41d4-A716-446655440000'))->toBeTrue();
            });

            test('rejects UUID without hyphens', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId('550e8400e29b41d4a716446655440000'))->toBeFalse();
            });

            test('rejects ULID format when configured for UUID', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();
                $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

                // Act & Assert
                expect($this->suspension->isValidUniqueId($ulid))->toBeFalse();
            });
        });
    });

    describe('with ULID primary key type', function (): void {
        beforeEach(function (): void {
            config(['suspend.primary_key_type' => 'ulid']);
            $this->suspension = new Suspension();
        });

        describe('Happy Paths', function (): void {
            test('initializes with string key type and non-incrementing behavior', function (): void {
                // Arrange & Act
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Assert
                expect($this->suspension->getKeyType())->toBe('string')
                    ->and($this->suspension->getIncrementing())->toBeFalse();
            });

            test('generates valid ULID when creating new unique ID', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act
                $result = $this->suspension->newUniqueId();

                // Assert
                expect($result)->toBeString()
                    ->and($result)->not->toBeEmpty()
                    ->and(Str::isUlid($result))->toBeTrue()
                    ->and(mb_strlen($result))->toBe(26);
            });

            test('returns primary key column in unique IDs array', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act
                $result = $this->suspension->uniqueIds();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->not->toBeEmpty()
                    ->and($result)->toContain($this->suspension->getKeyName());
            });

            test('validates properly formatted ULID strings', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();
                $validUlid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

                // Act & Assert
                expect($this->suspension->isValidUniqueId($validUlid))->toBeTrue()
                    ->and($this->suspension->isValidUniqueId('01BX5ZZKBKACTAV9WEVGEMMVRZ'))->toBeTrue();
            });

            test('uses ULID type behavior based on config', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act
                $uniqueId = $this->suspension->newUniqueId();

                // Assert
                expect($uniqueId)->toBeString()
                    ->and(Str::isUlid($uniqueId))->toBeTrue();
            });
        });

        describe('Sad Paths', function (): void {
            test('rejects invalid ULID format strings', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId('not-a-ulid'))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId('12345'))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId('01ARZ3NDEK'))->toBeFalse();
            });

            test('rejects numeric values as invalid ULID', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId(123))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId(0))->toBeFalse();
            });

            test('rejects null and empty values as invalid ULID', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId(null))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId(''))->toBeFalse();
            });
        });

        describe('Edge Cases', function (): void {
            test('rejects ULID with incorrect length', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId('01ARZ3NDEKTSV4RRFFQ69G5FA'))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId('01ARZ3NDEKTSV4RRFFQ69G5FAVX'))->toBeFalse();
            });

            test('accepts lowercase ULID format', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId('01arz3ndektsv4rrffq69g5fav'))->toBeTrue();
            });

            test('rejects UUID format when configured for ULID', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();
                $uuid = '550e8400-e29b-41d4-a716-446655440000';

                // Act & Assert
                expect($this->suspension->isValidUniqueId($uuid))->toBeFalse();
            });

            test('rejects ULID with invalid characters', function (): void {
                // Arrange
                $this->suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($this->suspension->isValidUniqueId('01ARZ3NDEKTSV4RRFFQ69G5F@V'))->toBeFalse()
                    ->and($this->suspension->isValidUniqueId('01ARZ3NDEKTSV4RRFFQ69G5F-V'))->toBeFalse();
            });
        });
    });

    describe('with invalid primary key type configuration', function (): void {
        describe('Happy Paths', function (): void {
            test('falls back to ID type when config value is invalid string', function (): void {
                // Arrange
                config(['suspend.primary_key_type' => 'invalid']);
                $suspension = new Suspension();

                // Act
                $suspension->initializeHasSuspendPrimaryKey();

                $uniqueId = $suspension->newUniqueId();
                $uniqueIds = $suspension->uniqueIds();

                // Assert - should behave like ID type
                expect($suspension->getKeyType())->toBe('int')
                    ->and($suspension->getIncrementing())->toBeTrue()
                    ->and($uniqueId)->toBeNull()
                    ->and($uniqueIds)->toBeEmpty();
            });

            test('initializes with default behavior when invalid config is provided', function (): void {
                // Arrange
                config(['suspend.primary_key_type' => 'invalid']);
                $suspension = new Suspension();

                // Act
                $suspension->initializeHasSuspendPrimaryKey();

                // Assert
                expect($suspension->getKeyType())->toBe('int')
                    ->and($suspension->getIncrementing())->toBeTrue();
            });

            test('returns null for new unique ID when invalid config is provided', function (): void {
                // Arrange
                config(['suspend.primary_key_type' => 'invalid']);
                $suspension = new Suspension();
                $suspension->initializeHasSuspendPrimaryKey();

                // Act
                $result = $suspension->newUniqueId();

                // Assert
                expect($result)->toBeNull();
            });

            test('returns empty array for unique IDs when invalid config is provided', function (): void {
                // Arrange
                config(['suspend.primary_key_type' => 'invalid']);
                $suspension = new Suspension();
                $suspension->initializeHasSuspendPrimaryKey();

                // Act
                $result = $suspension->uniqueIds();

                // Assert
                expect($result)->toBeArray()
                    ->and($result)->toBeEmpty();
            });

            test('validates numeric values when invalid config is provided', function (): void {
                // Arrange
                config(['suspend.primary_key_type' => 'invalid']);
                $suspension = new Suspension();
                $suspension->initializeHasSuspendPrimaryKey();

                // Act & Assert
                expect($suspension->isValidUniqueId(123))->toBeTrue()
                    ->and($suspension->isValidUniqueId('456'))->toBeTrue()
                    ->and($suspension->isValidUniqueId('abc'))->toBeFalse();
            });
        });

        describe('Edge Cases', function (): void {
            test('uses default config value when key is missing', function (): void {
                // Arrange
                // Ensure suspend config exists but without primary_key_type
                config(['suspend' => []]);
                $suspension = new Suspension();

                // Act
                $suspension->initializeHasSuspendPrimaryKey();

                // Assert
                expect($suspension->getKeyType())->toBe('int')
                    ->and($suspension->getIncrementing())->toBeTrue();
            });

            test('handles case-insensitive config values correctly', function (): void {
                // Arrange
                config(['suspend.primary_key_type' => 'UUID']);
                $suspension = new Suspension();

                // Act
                $suspension->initializeHasSuspendPrimaryKey();

                $uniqueId = $suspension->newUniqueId();

                // Assert - should not match and fall back to ID
                expect($suspension->getKeyType())->toBe('int')
                    ->and($suspension->getIncrementing())->toBeTrue()
                    ->and($uniqueId)->toBeNull();
            });

            test('handles whitespace in config values correctly', function (): void {
                // Arrange
                config(['suspend.primary_key_type' => ' uuid ']);
                $suspension = new Suspension();

                // Act
                $suspension->initializeHasSuspendPrimaryKey();

                $uniqueId = $suspension->newUniqueId();

                // Assert - should not match and fall back to ID
                expect($suspension->getKeyType())->toBe('int')
                    ->and($suspension->getIncrementing())->toBeTrue()
                    ->and($uniqueId)->toBeNull();
            });
        });
    });
});
