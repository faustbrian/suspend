<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\GlobMatcher;

describe('GlobMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new GlobMatcher();
    });

    describe('type', function (): void {
        it('returns glob', function (): void {
            expect($this->matcher->type())->toBe('glob');
        });
    });

    describe('normalize', function (): void {
        it('trims whitespace', function (): void {
            expect($this->matcher->normalize('  pattern*  '))->toBe('pattern*');
        });

        it('converts to string', function (): void {
            expect($this->matcher->normalize(123))->toBe('123');
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
                    return '  spam@*  ';
                }
            };

            expect($this->matcher->normalize($stringable))->toBe('spam@*');
        });

        it('returns empty string for non-stringable objects', function (): void {
            $object = new stdClass();

            expect($this->matcher->normalize($object))->toBe('');
        });

        it('returns empty string for arrays', function (): void {
            expect($this->matcher->normalize(['spam@*']))->toBe('');
        });
    });

    describe('matches - wildcards', function (): void {
        it('matches asterisk for any characters', function (): void {
            expect($this->matcher->matches('spam@*', 'spam@example.com'))->toBeTrue();
            expect($this->matcher->matches('spam@*', 'spam@test.org'))->toBeTrue();
            expect($this->matcher->matches('spam@*', 'legit@example.com'))->toBeFalse();
        });

        it('matches asterisk in middle', function (): void {
            expect($this->matcher->matches('192.168.*.*', '192.168.1.1'))->toBeTrue();
            expect($this->matcher->matches('192.168.*.*', '192.168.255.100'))->toBeTrue();
            expect($this->matcher->matches('192.168.*.*', '10.0.0.1'))->toBeFalse();
        });

        it('matches asterisk at start', function (): void {
            expect($this->matcher->matches('*.example.com', 'mail.example.com'))->toBeTrue();
            expect($this->matcher->matches('*.example.com', 'sub.example.com'))->toBeTrue();
            expect($this->matcher->matches('*.example.com', 'example.org'))->toBeFalse();
        });

        it('matches question mark for single character', function (): void {
            expect($this->matcher->matches('user?@example.com', 'user1@example.com'))->toBeTrue();
            expect($this->matcher->matches('user?@example.com', 'userA@example.com'))->toBeTrue();
            expect($this->matcher->matches('user?@example.com', 'user12@example.com'))->toBeFalse();
        });

        it('matches multiple question marks', function (): void {
            expect($this->matcher->matches('10.0.0.???', '10.0.0.100'))->toBeTrue();
            expect($this->matcher->matches('10.0.0.???', '10.0.0.1'))->toBeFalse();
        });
    });

    describe('matches - character classes', function (): void {
        it('matches character set', function (): void {
            expect($this->matcher->matches('user[123]@example.com', 'user1@example.com'))->toBeTrue();
            expect($this->matcher->matches('user[123]@example.com', 'user2@example.com'))->toBeTrue();
            expect($this->matcher->matches('user[123]@example.com', 'user4@example.com'))->toBeFalse();
        });

        it('matches negated character set', function (): void {
            expect($this->matcher->matches('user[!0]@example.com', 'user1@example.com'))->toBeTrue();
            expect($this->matcher->matches('user[!0]@example.com', 'user0@example.com'))->toBeFalse();
        });
    });

    describe('matches - case insensitive', function (): void {
        it('matches case insensitively', function (): void {
            expect($this->matcher->matches('SPAM@*', 'spam@example.com'))->toBeTrue();
            expect($this->matcher->matches('spam@*', 'SPAM@EXAMPLE.COM'))->toBeTrue();
            expect($this->matcher->matches('*.EXAMPLE.COM', 'mail.example.com'))->toBeTrue();
        });
    });

    describe('matches - exact', function (): void {
        it('matches exact string without wildcards', function (): void {
            expect($this->matcher->matches('exact@example.com', 'exact@example.com'))->toBeTrue();
            expect($this->matcher->matches('exact@example.com', 'other@example.com'))->toBeFalse();
        });
    });

    describe('validate', function (): void {
        it('validates patterns with wildcards', function (): void {
            expect($this->matcher->validate('spam@*'))->toBeTrue();
            expect($this->matcher->validate('*.example.com'))->toBeTrue();
            expect($this->matcher->validate('192.168.*.*'))->toBeTrue();
        });

        it('validates plain strings', function (): void {
            expect($this->matcher->validate('exact@example.com'))->toBeTrue();
        });

        it('rejects empty patterns', function (): void {
            expect($this->matcher->validate(''))->toBeFalse();
            expect($this->matcher->validate('   '))->toBeFalse();
        });
    });

    describe('extract', function (): void {
        it('returns null for glob patterns', function (): void {
            expect($this->matcher->extract('spam@*'))->toBeNull();
        });
    });
});
