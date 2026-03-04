<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\CountryMatcher;

describe('CountryMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new CountryMatcher();
    });

    describe('type', function (): void {
        it('returns country', function (): void {
            expect($this->matcher->type())->toBe('country');
        });
    });

    describe('normalize', function (): void {
        it('uppercases country codes', function (): void {
            expect($this->matcher->normalize('us'))->toBe('US');
            expect($this->matcher->normalize('gb'))->toBe('GB');
        });

        it('trims whitespace', function (): void {
            expect($this->matcher->normalize('  US  '))->toBe('US');
        });

        it('handles mixed case input', function (): void {
            expect($this->matcher->normalize('uS'))->toBe('US');
            expect($this->matcher->normalize('Gb'))->toBe('GB');
        });

        it('handles scalar values by converting to string', function (): void {
            expect($this->matcher->normalize(123))->toBe('123');
            expect($this->matcher->normalize(45.67))->toBe('45.67');
            expect($this->matcher->normalize(true))->toBe('1');
            expect($this->matcher->normalize(false))->toBe('');
        });

        it('handles stringable objects', function (): void {
            $stringable = new class() implements Stringable
            {
                public function __toString(): string
                {
                    return '  us  ';
                }
            };

            expect($this->matcher->normalize($stringable))->toBe('US');
        });

        it('returns empty string for non-stringable objects', function (): void {
            $object = new stdClass();

            expect($this->matcher->normalize($object))->toBe('');
        });

        it('returns empty string for arrays', function (): void {
            expect($this->matcher->normalize(['US']))->toBe('');
        });
    });

    describe('matches', function (): void {
        it('matches exact country codes', function (): void {
            expect($this->matcher->matches('US', 'US'))->toBeTrue();
            expect($this->matcher->matches('GB', 'GB'))->toBeTrue();
        });

        it('matches case-insensitively', function (): void {
            expect($this->matcher->matches('US', 'us'))->toBeTrue();
            expect($this->matcher->matches('us', 'US'))->toBeTrue();
        });

        it('does not match different country codes', function (): void {
            expect($this->matcher->matches('US', 'GB'))->toBeFalse();
            expect($this->matcher->matches('DE', 'FR'))->toBeFalse();
        });

        it('handles whitespace in matching', function (): void {
            expect($this->matcher->matches('US', '  US  '))->toBeTrue();
        });
    });

    describe('validate', function (): void {
        it('validates common ISO 3166-1 alpha-2 codes', function (): void {
            expect($this->matcher->validate('US'))->toBeTrue();
            expect($this->matcher->validate('GB'))->toBeTrue();
            expect($this->matcher->validate('DE'))->toBeTrue();
            expect($this->matcher->validate('FR'))->toBeTrue();
            expect($this->matcher->validate('JP'))->toBeTrue();
            expect($this->matcher->validate('CA'))->toBeTrue();
        });

        it('validates lowercase codes', function (): void {
            expect($this->matcher->validate('us'))->toBeTrue();
            expect($this->matcher->validate('gb'))->toBeTrue();
        });

        it('rejects invalid country codes', function (): void {
            expect($this->matcher->validate('USA'))->toBeFalse();
            expect($this->matcher->validate('U'))->toBeFalse();
            expect($this->matcher->validate('XX'))->toBeFalse();
        });

        it('rejects non-letter characters', function (): void {
            expect($this->matcher->validate('U1'))->toBeFalse();
            expect($this->matcher->validate('12'))->toBeFalse();
        });

        it('rejects empty strings', function (): void {
            expect($this->matcher->validate(''))->toBeFalse();
        });

        it('validates all common country codes', function (): void {
            $commonCodes = ['AD', 'AE', 'AF', 'AU', 'BR', 'CA', 'CH', 'CN', 'ES', 'IN', 'IT', 'MX', 'NL', 'RU', 'SE', 'ZA'];

            foreach ($commonCodes as $code) {
                expect($this->matcher->validate($code))->toBeTrue();
            }
        });
    });

    describe('extract', function (): void {
        it('returns null for country codes', function (): void {
            expect($this->matcher->extract('US'))->toBeNull();
            expect($this->matcher->extract('GB'))->toBeNull();
        });
    });
});
