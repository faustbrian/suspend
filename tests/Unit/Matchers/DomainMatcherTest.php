<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Matchers\DomainMatcher;

describe('DomainMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new DomainMatcher();
    });

    describe('type', function (): void {
        it('returns domain', function (): void {
            expect($this->matcher->type())->toBe('domain');
        });
    });

    describe('normalize', function (): void {
        it('lowercases domain names', function (): void {
            expect($this->matcher->normalize('EXAMPLE.COM'))->toBe('example.com');
        });

        it('trims whitespace', function (): void {
            expect($this->matcher->normalize('  example.com  '))->toBe('example.com');
        });

        it('removes trailing dot', function (): void {
            expect($this->matcher->normalize('example.com.'))->toBe('example.com');
        });

        it('removes protocol', function (): void {
            expect($this->matcher->normalize('http://example.com'))->toBe('example.com');
            expect($this->matcher->normalize('https://example.com'))->toBe('example.com');
        });

        it('removes path', function (): void {
            expect($this->matcher->normalize('example.com/path/to/page'))->toBe('example.com');
        });

        it('handles complex URLs', function (): void {
            expect($this->matcher->normalize('https://SUB.EXAMPLE.COM/path'))->toBe('sub.example.com');
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
                    return '  EXAMPLE.COM.  ';
                }
            };

            expect($this->matcher->normalize($stringable))->toBe('example.com');
        });

        it('returns empty string for non-stringable objects', function (): void {
            $object = new stdClass();

            expect($this->matcher->normalize($object))->toBe('');
        });

        it('returns empty string for arrays', function (): void {
            expect($this->matcher->normalize(['example.com']))->toBe('');
        });
    });

    describe('matches', function (): void {
        it('matches exact domains', function (): void {
            expect($this->matcher->matches('example.com', 'example.com'))->toBeTrue();
        });

        it('matches case-insensitively', function (): void {
            expect($this->matcher->matches('example.com', 'EXAMPLE.COM'))->toBeTrue();
        });

        it('does not match different domains', function (): void {
            expect($this->matcher->matches('example.com', 'other.com'))->toBeFalse();
        });

        it('matches wildcard subdomain patterns', function (): void {
            expect($this->matcher->matches('*.example.com', 'sub.example.com'))->toBeTrue();
            expect($this->matcher->matches('*.example.com', 'mail.example.com'))->toBeTrue();
            expect($this->matcher->matches('*.example.com', 'example.com'))->toBeTrue();
        });

        it('does not match wildcard pattern with different base domain', function (): void {
            expect($this->matcher->matches('*.example.com', 'sub.other.com'))->toBeFalse();
        });

        it('matches subdomains as part of domain', function (): void {
            expect($this->matcher->matches('example.com', 'sub.example.com'))->toBeTrue();
            expect($this->matcher->matches('example.com', 'mail.sub.example.com'))->toBeTrue();
        });

        it('does not match parent domain from subdomain', function (): void {
            expect($this->matcher->matches('sub.example.com', 'example.com'))->toBeFalse();
        });

        it('matches wildcard in middle of pattern', function (): void {
            expect($this->matcher->matches('mail.*.com', 'mail.example.com'))->toBeTrue();
            expect($this->matcher->matches('mail.*.com', 'mail.test.com'))->toBeTrue();
        });
    });

    describe('validate', function (): void {
        it('validates correct domain names', function (): void {
            expect($this->matcher->validate('example.com'))->toBeTrue();
            expect($this->matcher->validate('sub.example.com'))->toBeTrue();
            expect($this->matcher->validate('mail.sub.example.org'))->toBeTrue();
        });

        it('rejects invalid domains', function (): void {
            expect($this->matcher->validate('not-a-domain'))->toBeFalse();
            expect($this->matcher->validate('.com'))->toBeFalse();
            expect($this->matcher->validate('example.'))->toBeFalse();
        });

        it('validates wildcard patterns', function (): void {
            expect($this->matcher->validate('*.example.com'))->toBeTrue();
            expect($this->matcher->validate('mail.*.com'))->toBeTrue();
        });

        it('rejects invalid wildcard patterns', function (): void {
            expect($this->matcher->validate('*'))->toBeFalse();
            expect($this->matcher->validate('*.'))->toBeFalse();
        });

        it('validates domains with hyphens', function (): void {
            expect($this->matcher->validate('my-domain.com'))->toBeTrue();
            expect($this->matcher->validate('sub-domain.example.org'))->toBeTrue();
        });
    });

    describe('extract', function (): void {
        it('extracts root domain from subdomain', function (): void {
            expect($this->matcher->extract('sub.example.com'))->toBe('example.com');
            expect($this->matcher->extract('mail.sub.example.com'))->toBe('example.com');
        });

        it('returns domain for root domains', function (): void {
            expect($this->matcher->extract('example.com'))->toBe('example.com');
        });

        it('returns null for invalid domains', function (): void {
            expect($this->matcher->extract('invalid'))->toBeNull();
        });

        it('handles URLs in extraction', function (): void {
            expect($this->matcher->extract('https://sub.example.com/path'))->toBe('example.com');
        });
    });
});
