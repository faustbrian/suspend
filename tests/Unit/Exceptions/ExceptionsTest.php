<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Exceptions\InvalidMatcherValueException;
use Cline\Suspend\Exceptions\InvalidTimeFormatException;
use Cline\Suspend\Exceptions\MissingDependencyException;
use Cline\Suspend\Exceptions\MissingLatitudeKeyException;
use Cline\Suspend\Exceptions\MissingLongitudeKeyException;
use Cline\Suspend\Exceptions\MissingStrategyMetadataException;
use Cline\Suspend\Exceptions\UnknownMatcherTypeException;
use Cline\Suspend\Exceptions\UnknownStrategyException;

describe('Exceptions', function (): void {
    describe('InvalidMatcherValueException', function (): void {
        describe('Happy Paths', function (): void {
            test('creates exception with string value containing type and value in message', function (): void {
                // Arrange & Act
                $exception = InvalidMatcherValueException::forValue('email', 'invalid-email');

                // Assert
                expect($exception->getMessage())->toContain('email')
                    ->and($exception->getMessage())->toContain('invalid-email')
                    ->and($exception->getMessage())->toContain('Invalid value for matcher type');
            });

            test('creates exception with integer value showing type in message', function (): void {
                // Arrange & Act
                $exception = InvalidMatcherValueException::forValue('email', 123);

                // Assert
                expect($exception->getMessage())->toContain('email')
                    ->and($exception->getMessage())->toContain('integer')
                    ->and($exception->getMessage())->not->toContain('123');
            });

            test('creates exception with array value showing type in message', function (): void {
                // Arrange & Act
                $exception = InvalidMatcherValueException::forValue('ip', ['invalid']);

                // Assert
                expect($exception->getMessage())->toContain('ip')
                    ->and($exception->getMessage())->toContain('array');
            });

            test('creates exception with boolean value showing type in message', function (): void {
                // Arrange & Act
                $exception = InvalidMatcherValueException::forValue('phone', true);

                // Assert
                expect($exception->getMessage())->toContain('phone')
                    ->and($exception->getMessage())->toContain('boolean');
            });
        });

        describe('Edge Cases', function (): void {
            test('handles null value showing type in message', function (): void {
                // Arrange & Act
                $exception = InvalidMatcherValueException::forValue('email', null);

                // Assert
                expect($exception->getMessage())->toContain('email')
                    ->and($exception->getMessage())->toContain('NULL');
            });

            test('handles empty string value', function (): void {
                // Arrange & Act
                $exception = InvalidMatcherValueException::forValue('email', '');

                // Assert
                expect($exception->getMessage())->toContain('email')
                    ->and($exception->getMessage())->toContain("Invalid value for matcher type 'email':");
            });

            test('handles object value showing type in message', function (): void {
                // Arrange & Act
                $exception = InvalidMatcherValueException::forValue('custom', new stdClass());

                // Assert
                expect($exception->getMessage())->toContain('custom')
                    ->and($exception->getMessage())->toContain('object');
            });
        });
    });

    describe('InvalidTimeFormatException', function (): void {
        describe('Happy Paths', function (): void {
            test('creates exception with invalid time string in message', function (): void {
                // Arrange & Act
                $exception = InvalidTimeFormatException::forTime('25:00');

                // Assert
                expect($exception->getMessage())->toContain('Invalid time format')
                    ->and($exception->getMessage())->toContain('25:00');
            });

            test('creates exception with malformed time string in message', function (): void {
                // Arrange & Act
                $exception = InvalidTimeFormatException::forTime('not-a-time');

                // Assert
                expect($exception->getMessage())->toContain('Invalid time format')
                    ->and($exception->getMessage())->toContain('not-a-time');
            });
        });

        describe('Edge Cases', function (): void {
            test('handles empty time string', function (): void {
                // Arrange & Act
                $exception = InvalidTimeFormatException::forTime('');

                // Assert
                expect($exception->getMessage())->toContain('Invalid time format')
                    ->and($exception->getMessage())->toContain('');
            });

            test('handles time string with special characters', function (): void {
                // Arrange & Act
                $exception = InvalidTimeFormatException::forTime('12:00@#$');

                // Assert
                expect($exception->getMessage())->toContain('Invalid time format')
                    ->and($exception->getMessage())->toContain('12:00@#$');
            });
        });
    });

    describe('MissingDependencyException', function (): void {
        describe('Happy Paths', function (): void {
            test('creates exception with package name and feature description in message', function (): void {
                // Arrange & Act
                $exception = MissingDependencyException::package('geoip2/geoip2', 'GeoIP country resolution');

                // Assert
                expect($exception->getMessage())->toContain('geoip2/geoip2')
                    ->and($exception->getMessage())->toContain('GeoIP country resolution')
                    ->and($exception->getMessage())->toContain('composer require')
                    ->and($exception->getMessage())->toContain('requires the');
            });

            test('creates exception with installation instructions', function (): void {
                // Arrange & Act
                $exception = MissingDependencyException::package('vendor/package', 'Feature X');

                // Assert
                expect($exception->getMessage())->toContain('Install it with: composer require vendor/package');
            });
        });

        describe('Edge Cases', function (): void {
            test('handles package with unusual naming', function (): void {
                // Arrange & Act
                $exception = MissingDependencyException::package('my-vendor/my-cool-package', 'Cool Feature');

                // Assert
                expect($exception->getMessage())->toContain('my-vendor/my-cool-package')
                    ->and($exception->getMessage())->toContain('Cool Feature');
            });

            test('handles feature description with special characters', function (): void {
                // Arrange & Act
                $exception = MissingDependencyException::package('vendor/package', 'Feature (Beta)');

                // Assert
                expect($exception->getMessage())->toContain('Feature (Beta)');
            });
        });
    });

    describe('MissingLatitudeKeyException', function (): void {
        describe('Happy Paths', function (): void {
            test('creates exception with descriptive message about required keys', function (): void {
                // Arrange & Act
                $exception = MissingLatitudeKeyException::create();

                // Assert
                expect($exception->getMessage())->toContain('latitude')
                    ->and($exception->getMessage())->toContain('lat')
                    ->and($exception->getMessage())->toContain('Array must contain');
            });

            test('creates exception mentioning both latitude and lat keys', function (): void {
                // Arrange & Act
                $exception = MissingLatitudeKeyException::create();

                // Assert
                expect($exception->getMessage())->toContain('"latitude"')
                    ->and($exception->getMessage())->toContain('"lat"');
            });
        });
    });

    describe('MissingLongitudeKeyException', function (): void {
        describe('Happy Paths', function (): void {
            test('creates exception with descriptive message about required keys', function (): void {
                // Arrange & Act
                $exception = MissingLongitudeKeyException::create();

                // Assert
                expect($exception->getMessage())->toContain('longitude')
                    ->and($exception->getMessage())->toContain('lon')
                    ->and($exception->getMessage())->toContain('lng')
                    ->and($exception->getMessage())->toContain('Array must contain');
            });

            test('creates exception mentioning all three acceptable longitude keys', function (): void {
                // Arrange & Act
                $exception = MissingLongitudeKeyException::create();

                // Assert
                expect($exception->getMessage())->toContain('"longitude"')
                    ->and($exception->getMessage())->toContain('"lon"')
                    ->and($exception->getMessage())->toContain('"lng"');
            });
        });
    });

    describe('MissingStrategyMetadataException', function (): void {
        describe('Happy Paths', function (): void {
            test('creates exception with strategy identifier and field name in message', function (): void {
                // Arrange & Act
                $exception = MissingStrategyMetadataException::forField('time_window', 'start_time');

                // Assert
                expect($exception->getMessage())->toContain('time_window')
                    ->and($exception->getMessage())->toContain('start_time')
                    ->and($exception->getMessage())->toContain('requires metadata field');
            });

            test('creates exception showing which strategy requires metadata', function (): void {
                // Arrange & Act
                $exception = MissingStrategyMetadataException::forField('geofence', 'coordinates');

                // Assert
                expect($exception->getMessage())->toContain("Strategy 'geofence'")
                    ->and($exception->getMessage())->toContain('coordinates');
            });
        });

        describe('Edge Cases', function (): void {
            test('handles strategy names with special characters', function (): void {
                // Arrange & Act
                $exception = MissingStrategyMetadataException::forField('custom-strategy_v2', 'field_name');

                // Assert
                expect($exception->getMessage())->toContain('custom-strategy_v2')
                    ->and($exception->getMessage())->toContain('field_name');
            });

            test('handles nested field names with dots', function (): void {
                // Arrange & Act
                $exception = MissingStrategyMetadataException::forField('strategy', 'config.nested.field');

                // Assert
                expect($exception->getMessage())->toContain('config.nested.field');
            });
        });
    });

    describe('UnknownMatcherTypeException', function (): void {
        describe('Happy Paths', function (): void {
            test('creates exception with unregistered matcher type in message', function (): void {
                // Arrange & Act
                $exception = UnknownMatcherTypeException::forType('custom_matcher');

                // Assert
                expect($exception->getMessage())->toContain('custom_matcher')
                    ->and($exception->getMessage())->toContain('No matcher registered for type');
            });

            test('creates exception indicating registration is required', function (): void {
                // Arrange & Act
                $exception = UnknownMatcherTypeException::forType('unknown');

                // Assert
                expect($exception->getMessage())->toContain('No matcher registered')
                    ->and($exception->getMessage())->toContain('unknown');
            });
        });

        describe('Edge Cases', function (): void {
            test('handles empty string matcher type', function (): void {
                // Arrange & Act
                $exception = UnknownMatcherTypeException::forType('');

                // Assert
                expect($exception->getMessage())->toContain('No matcher registered for type:');
            });

            test('handles matcher type with special characters', function (): void {
                // Arrange & Act
                $exception = UnknownMatcherTypeException::forType('custom-matcher_v2');

                // Assert
                expect($exception->getMessage())->toContain('custom-matcher_v2');
            });
        });
    });

    describe('UnknownStrategyException', function (): void {
        describe('Happy Paths', function (): void {
            test('creates exception with unregistered strategy identifier in message', function (): void {
                // Arrange & Act
                $exception = UnknownStrategyException::forIdentifier('custom_strategy');

                // Assert
                expect($exception->getMessage())->toContain('custom_strategy')
                    ->and($exception->getMessage())->toContain('No strategy registered with identifier');
            });

            test('creates exception indicating registration is required', function (): void {
                // Arrange & Act
                $exception = UnknownStrategyException::forIdentifier('unknown');

                // Assert
                expect($exception->getMessage())->toContain('No strategy registered')
                    ->and($exception->getMessage())->toContain('unknown');
            });
        });

        describe('Edge Cases', function (): void {
            test('handles empty string strategy identifier', function (): void {
                // Arrange & Act
                $exception = UnknownStrategyException::forIdentifier('');

                // Assert
                expect($exception->getMessage())->toContain('No strategy registered with identifier:');
            });

            test('handles strategy identifier with special characters', function (): void {
                // Arrange & Act
                $exception = UnknownStrategyException::forIdentifier('custom-strategy_v2');

                // Assert
                expect($exception->getMessage())->toContain('custom-strategy_v2');
            });
        });
    });
});
