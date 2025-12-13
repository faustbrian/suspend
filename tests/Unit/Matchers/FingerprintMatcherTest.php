<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\FingerprintMatcher;

describe('FingerprintMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new FingerprintMatcher();
    });

    describe('type', function (): void {
        it('returns fingerprint', function (): void {
            expect($this->matcher->type())->toBe('fingerprint');
        });
    });

    describe('normalize', function (): void {
        it('trims whitespace', function (): void {
            expect($this->matcher->normalize('  abc123def456  '))->toBe('abc123def456');
        });

        it('preserves alphanumeric characters', function (): void {
            expect($this->matcher->normalize('abc123DEF456'))->toBe('abc123DEF456');
        });

        it('preserves hyphens and underscores', function (): void {
            expect($this->matcher->normalize('abc-123_def-456'))->toBe('abc-123_def-456');
        });

        it('handles scalar values by converting to string', function (): void {
            expect($this->matcher->normalize(12_345_678))->toBe('12345678');
            expect($this->matcher->normalize(12.345_678))->toBe('12.345678');
            expect($this->matcher->normalize(true))->toBe('1');
            expect($this->matcher->normalize(false))->toBe('');
        });

        it('handles stringable objects', function (): void {
            $stringable = new class() implements Stringable
            {
                public function __toString(): string
                {
                    return '  abc123def456  ';
                }
            };

            expect($this->matcher->normalize($stringable))->toBe('abc123def456');
        });

        it('returns empty string for non-stringable objects', function (): void {
            $object = new stdClass();

            expect($this->matcher->normalize($object))->toBe('');
        });

        it('returns empty string for arrays', function (): void {
            expect($this->matcher->normalize(['abc123def456']))->toBe('');
        });
    });

    describe('matches', function (): void {
        it('matches exact fingerprints', function (): void {
            expect($this->matcher->matches('abc123def456', 'abc123def456'))->toBeTrue();
        });

        it('matches case-sensitively', function (): void {
            expect($this->matcher->matches('abc123DEF456', 'abc123DEF456'))->toBeTrue();
        });

        it('does not match different fingerprints', function (): void {
            expect($this->matcher->matches('abc123def456', 'xyz789ghi012'))->toBeFalse();
        });

        it('does not match case-insensitively', function (): void {
            expect($this->matcher->matches('abc123def456', 'ABC123DEF456'))->toBeFalse();
        });

        it('trims whitespace before matching', function (): void {
            expect($this->matcher->matches('abc123def456', '  abc123def456  '))->toBeTrue();
        });

        it('matches fingerprints with hyphens', function (): void {
            expect($this->matcher->matches('abc-123-def-456', 'abc-123-def-456'))->toBeTrue();
        });

        it('matches fingerprints with underscores', function (): void {
            expect($this->matcher->matches('abc_123_def_456', 'abc_123_def_456'))->toBeTrue();
        });
    });

    describe('validate', function (): void {
        it('validates fingerprints with alphanumeric characters', function (): void {
            expect($this->matcher->validate('abc123def456'))->toBeTrue();
            expect($this->matcher->validate('ABC123DEF456'))->toBeTrue();
        });

        it('validates fingerprints with hyphens', function (): void {
            expect($this->matcher->validate('abc-123-def-456'))->toBeTrue();
        });

        it('validates fingerprints with underscores', function (): void {
            expect($this->matcher->validate('abc_123_def_456'))->toBeTrue();
        });

        it('validates minimum length fingerprints', function (): void {
            expect($this->matcher->validate('abc12345'))->toBeTrue();
        });

        it('validates maximum length fingerprints', function (): void {
            expect($this->matcher->validate(str_repeat('a', 128)))->toBeTrue();
        });

        it('rejects fingerprints too short', function (): void {
            expect($this->matcher->validate('abc123'))->toBeFalse();
            expect($this->matcher->validate('1234567'))->toBeFalse();
        });

        it('rejects fingerprints too long', function (): void {
            expect($this->matcher->validate(str_repeat('a', 129)))->toBeFalse();
        });

        it('rejects empty fingerprints', function (): void {
            expect($this->matcher->validate(''))->toBeFalse();
        });

        it('rejects fingerprints with special characters', function (): void {
            expect($this->matcher->validate('abc@123#def'))->toBeFalse();
            expect($this->matcher->validate('abc.123.def'))->toBeFalse();
            expect($this->matcher->validate('abc 123 def'))->toBeFalse();
        });

        it('validates common fingerprint formats', function (): void {
            expect($this->matcher->validate('1a2b3c4d5e6f7g8h'))->toBeTrue();
            expect($this->matcher->validate('fingerprint-12345678'))->toBeTrue();
            expect($this->matcher->validate('fp_1234567890abcdef'))->toBeTrue();
        });
    });

    describe('extract', function (): void {
        it('returns null for fingerprints', function (): void {
            expect($this->matcher->extract('abc123def456'))->toBeNull();
            expect($this->matcher->extract('fingerprint-12345678'))->toBeNull();
        });
    });
});
