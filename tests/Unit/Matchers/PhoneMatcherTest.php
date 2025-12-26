<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\PhoneMatcher;

describe('PhoneMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new PhoneMatcher();
    });

    describe('type', function (): void {
        it('returns phone', function (): void {
            expect($this->matcher->type())->toBe('phone');
        });
    });

    describe('normalize', function (): void {
        it('removes formatting characters', function (): void {
            expect($this->matcher->normalize('(555) 123-4567'))->toBe('5551234567');
        });

        it('removes dots and spaces', function (): void {
            expect($this->matcher->normalize('555.123.4567'))->toBe('5551234567');
        });

        it('preserves leading plus sign', function (): void {
            expect($this->matcher->normalize('+1 (555) 123-4567'))->toBe('+15551234567');
        });

        it('removes all non-numeric except leading plus', function (): void {
            expect($this->matcher->normalize('+1-555-123-4567'))->toBe('+15551234567');
        });

        it('handles phone without plus', function (): void {
            expect($this->matcher->normalize('15551234567'))->toBe('15551234567');
        });

        it('handles scalar values by converting to string', function (): void {
            expect($this->matcher->normalize(5_551_234_567))->toBe('5551234567');
            expect($this->matcher->normalize(123.456))->toBe('123456');
            expect($this->matcher->normalize(true))->toBe('1');
            expect($this->matcher->normalize(false))->toBe('');
        });

        it('handles stringable objects', function (): void {
            $stringable = new class() implements Stringable
            {
                public function __toString(): string
                {
                    return '+1 (555) 123-4567';
                }
            };

            expect($this->matcher->normalize($stringable))->toBe('+15551234567');
        });

        it('returns empty string for non-stringable objects', function (): void {
            $object = new stdClass();

            expect($this->matcher->normalize($object))->toBe('');
        });

        it('returns empty string for arrays', function (): void {
            expect($this->matcher->normalize(['5551234567']))->toBe('');
        });
    });

    describe('matches', function (): void {
        it('matches exact phone numbers', function (): void {
            expect($this->matcher->matches('5551234567', '5551234567'))->toBeTrue();
        });

        it('matches phone numbers with different formatting', function (): void {
            expect($this->matcher->matches('5551234567', '(555) 123-4567'))->toBeTrue();
            expect($this->matcher->matches('(555) 123-4567', '555.123.4567'))->toBeTrue();
        });

        it('does not match different phone numbers', function (): void {
            expect($this->matcher->matches('5551234567', '5559876543'))->toBeFalse();
        });

        it('matches with or without plus prefix', function (): void {
            expect($this->matcher->matches('15551234567', '+15551234567'))->toBeTrue();
            expect($this->matcher->matches('+15551234567', '15551234567'))->toBeTrue();
        });
    });

    describe('validate', function (): void {
        it('validates phone numbers with correct length', function (): void {
            expect($this->matcher->validate('5551234567'))->toBeTrue();
            expect($this->matcher->validate('+15551234567'))->toBeTrue();
        });

        it('validates minimum 7 digit phone numbers', function (): void {
            expect($this->matcher->validate('5551234'))->toBeTrue();
        });

        it('validates maximum 15 digit phone numbers', function (): void {
            expect($this->matcher->validate('123456789012345'))->toBeTrue();
        });

        it('rejects phone numbers too short', function (): void {
            expect($this->matcher->validate('123456'))->toBeFalse();
        });

        it('rejects phone numbers too long', function (): void {
            expect($this->matcher->validate('1234567890123456'))->toBeFalse();
        });

        it('validates phone numbers with formatting', function (): void {
            expect($this->matcher->validate('(555) 123-4567'))->toBeTrue();
            expect($this->matcher->validate('+1-555-123-4567'))->toBeTrue();
        });
    });

    describe('extract', function (): void {
        it('extracts up to 3 digits as country code from E.164 format', function (): void {
            expect($this->matcher->extract('+441234567890'))->toBe('441');
        });

        it('returns null for phone without country code', function (): void {
            expect($this->matcher->extract('5551234567'))->toBeNull();
        });

        it('extracts country code with formatting', function (): void {
            expect($this->matcher->extract('+1 (555) 123-4567'))->toBe('155');
        });

        it('handles three digit country codes', function (): void {
            expect($this->matcher->extract('+123456789012'))->toBe('123');
        });

        it('returns null for phone with plus but no digits', function (): void {
            expect($this->matcher->extract('+'))->toBeNull();
        });

        it('returns null for malformed international format', function (): void {
            expect($this->matcher->extract('+abc'))->toBeNull();
        });
    });
});
