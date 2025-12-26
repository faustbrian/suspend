<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\RegexMatcher;

describe('RegexMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new RegexMatcher();
    });

    describe('type', function (): void {
        it('returns regex', function (): void {
            expect($this->matcher->type())->toBe('regex');
        });
    });

    describe('normalize', function (): void {
        it('trims whitespace', function (): void {
            expect($this->matcher->normalize('  /test/  '))->toBe('/test/');
        });

        it('preserves regex pattern', function (): void {
            expect($this->matcher->normalize('/^test$/i'))->toBe('/^test$/i');
        });

        it('preserves complex patterns', function (): void {
            expect($this->matcher->normalize('/[a-z]+@[a-z]+\\.com/'))->toBe('/[a-z]+@[a-z]+\\.com/');
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
                    return '  /test/i  ';
                }
            };

            expect($this->matcher->normalize($stringable))->toBe('/test/i');
        });

        it('returns empty string for non-stringable objects', function (): void {
            $object = new stdClass();

            expect($this->matcher->normalize($object))->toBe('');
        });

        it('returns empty string for arrays', function (): void {
            expect($this->matcher->normalize(['/test/']))->toBe('');
        });
    });

    describe('matches', function (): void {
        it('matches simple patterns', function (): void {
            expect($this->matcher->matches('/test/', 'test'))->toBeTrue();
            expect($this->matcher->matches('/hello/', 'hello world'))->toBeTrue();
        });

        it('does not match non-matching patterns', function (): void {
            expect($this->matcher->matches('/test/', 'example'))->toBeFalse();
            expect($this->matcher->matches('/^hello$/', 'hello world'))->toBeFalse();
        });

        it('matches with anchors', function (): void {
            expect($this->matcher->matches('/^test$/', 'test'))->toBeTrue();
            expect($this->matcher->matches('/^test$/', 'testing'))->toBeFalse();
        });

        it('matches case-insensitively with flag', function (): void {
            expect($this->matcher->matches('/test/i', 'TEST'))->toBeTrue();
            expect($this->matcher->matches('/test/i', 'TeSt'))->toBeTrue();
        });

        it('matches case-sensitively without flag', function (): void {
            expect($this->matcher->matches('/test/', 'test'))->toBeTrue();
            expect($this->matcher->matches('/test/', 'TEST'))->toBeFalse();
        });

        it('matches character classes', function (): void {
            expect($this->matcher->matches('/\d+/', '12345'))->toBeTrue();
            expect($this->matcher->matches('/[a-z]+/', 'hello'))->toBeTrue();
            expect($this->matcher->matches('/[a-z]+/', '12345'))->toBeFalse();
        });

        it('matches email patterns', function (): void {
            expect($this->matcher->matches('/[a-z]+@[a-z]+\\.com/', 'test@example.com'))->toBeTrue();
            expect($this->matcher->matches('/[a-z]+@[a-z]+\\.com/', 'invalid-email'))->toBeFalse();
        });

        it('matches phone number patterns', function (): void {
            expect($this->matcher->matches('/\\d{3}-\\d{3}-\\d{4}/', '555-123-4567'))->toBeTrue();
            expect($this->matcher->matches('/\\d{3}-\\d{3}-\\d{4}/', '555.123.4567'))->toBeFalse();
        });

        it('returns false for invalid patterns', function (): void {
            expect($this->matcher->matches('/[invalid/', 'test'))->toBeFalse();
        });

        it('matches against scalar check values by converting to string', function (): void {
            expect($this->matcher->matches('/^\d+$/', 12_345))->toBeTrue();
            expect($this->matcher->matches('/^\d+\.\d+$/', 123.45))->toBeTrue();
            expect($this->matcher->matches('/^1$/', true))->toBeTrue();
            expect($this->matcher->matches('/^$/', false))->toBeTrue();
        });

        it('matches against stringable objects', function (): void {
            $stringable = new class() implements Stringable
            {
                public function __toString(): string
                {
                    return 'test@example.com';
                }
            };

            expect($this->matcher->matches('/[a-z]+@[a-z]+\\.com/', $stringable))->toBeTrue();
        });

        it('returns false when matching against non-stringable objects', function (): void {
            $object = new stdClass();

            expect($this->matcher->matches('/test/', $object))->toBeFalse();
        });

        it('returns false when matching against arrays', function (): void {
            expect($this->matcher->matches('/test/', ['test']))->toBeFalse();
        });
    });

    describe('validate', function (): void {
        it('validates correct regex patterns', function (): void {
            expect($this->matcher->validate('/test/'))->toBeTrue();
            expect($this->matcher->validate('/^test$/'))->toBeTrue();
            expect($this->matcher->validate('/[a-z]+/i'))->toBeTrue();
        });

        it('validates patterns with escaped characters', function (): void {
            expect($this->matcher->validate('/test\\.com/'))->toBeTrue();
            expect($this->matcher->validate('/\\d+/'))->toBeTrue();
        });

        it('validates patterns with character classes', function (): void {
            expect($this->matcher->validate('/[a-z0-9]+/'))->toBeTrue();
            expect($this->matcher->validate('/[^a-z]+/'))->toBeTrue();
        });

        it('rejects invalid regex patterns', function (): void {
            expect($this->matcher->validate('/[invalid/'))->toBeFalse();
            expect($this->matcher->validate('/(?P<name/'))->toBeFalse();
        });

        it('rejects empty patterns', function (): void {
            expect($this->matcher->validate(''))->toBeFalse();
        });

        it('validates patterns with flags', function (): void {
            expect($this->matcher->validate('/test/i'))->toBeTrue();
            expect($this->matcher->validate('/test/m'))->toBeTrue();
            expect($this->matcher->validate('/test/s'))->toBeTrue();
            expect($this->matcher->validate('/test/ims'))->toBeTrue();
        });

        it('validates complex patterns', function (): void {
            expect($this->matcher->validate('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/'))->toBeTrue();
            expect($this->matcher->validate('/^\\+?[1-9]\\d{1,14}$/'))->toBeTrue();
        });
    });

    describe('extract', function (): void {
        it('returns null for regex patterns', function (): void {
            expect($this->matcher->extract('/test/'))->toBeNull();
            expect($this->matcher->extract('/[a-z]+/i'))->toBeNull();
        });
    });
});
