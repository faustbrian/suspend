<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Ip\TrustedProxyIpResolver;

describe('TrustedProxyIpResolver', function (): void {
    describe('identifier', function (): void {
        it('returns trusted_proxy', function (): void {
            $resolver = new TrustedProxyIpResolver();

            expect($resolver->identifier())->toBe('trusted_proxy');
        });
    });

    describe('resolve', function (): void {
        it('extracts client IP from X-Forwarded-For header walking right to left', function (): void {
            // With no trusted proxies, returns the first non-trusted IP from right
            // Chain: 203.0.113.50 -> no REMOTE_ADDR added (127.0.0.1 by default)
            // Walking right to left: 127.0.0.1 (not trusted), returns it
            $resolver = new TrustedProxyIpResolver();
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '203.0.113.50',
            ]);
            $request->server->set('REMOTE_ADDR', '10.0.0.1');

            // Walking right to left: 10.0.0.1 is not trusted, so returns it
            expect($resolver->resolve($request))->toBe('10.0.0.1');
        });

        it('returns first non-trusted IP walking right to left', function (): void {
            // Chain: 203.0.113.50, 198.51.100.1, 192.0.2.1 + REMOTE_ADDR (10.0.0.1)
            // Walking right to left: 10.0.0.1 is not trusted, returns it
            $resolver = new TrustedProxyIpResolver();
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '203.0.113.50, 198.51.100.1, 192.0.2.1',
            ]);
            $request->server->set('REMOTE_ADDR', '10.0.0.1');

            expect($resolver->resolve($request))->toBe('10.0.0.1');
        });

        it('skips trusted proxies when walking the chain', function (): void {
            // Chain: 203.0.113.50, 198.51.100.1, 192.0.2.1 + REMOTE_ADDR (10.0.0.1)
            // Trusted: 10.0.0.1, 192.0.2.1, 198.51.100.1
            // Walking right to left: 10.0.0.1 (trusted), 192.0.2.1 (trusted), 198.51.100.1 (trusted), 203.0.113.50 (not trusted)
            $resolver = new TrustedProxyIpResolver(['10.0.0.1', '192.0.2.1', '198.51.100.1']);
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '203.0.113.50, 198.51.100.1, 192.0.2.1',
            ]);
            $request->server->set('REMOTE_ADDR', '10.0.0.1');

            expect($resolver->resolve($request))->toBe('203.0.113.50');
        });

        it('returns first non-trusted IP when some proxies are trusted', function (): void {
            // Chain: 203.0.113.50, 198.51.100.1 + REMOTE_ADDR (10.0.0.1)
            // Trusted: 10.0.0.1
            // Walking right to left: 10.0.0.1 (trusted), 198.51.100.1 (not trusted) -> returns
            $resolver = new TrustedProxyIpResolver(['10.0.0.1']);
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '203.0.113.50, 198.51.100.1',
            ]);
            $request->server->set('REMOTE_ADDR', '10.0.0.1');

            expect($resolver->resolve($request))->toBe('198.51.100.1');
        });

        it('includes REMOTE_ADDR in the chain for evaluation', function (): void {
            // Chain: 198.51.100.1 + REMOTE_ADDR (203.0.113.50)
            // Trusted: 198.51.100.1
            // Walking right to left: 203.0.113.50 (not trusted) -> returns
            $resolver = new TrustedProxyIpResolver(['198.51.100.1']);
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '198.51.100.1',
            ]);
            $request->server->set('REMOTE_ADDR', '203.0.113.50');

            expect($resolver->resolve($request))->toBe('203.0.113.50');
        });

        it('returns leftmost IP when all IPs are trusted', function (): void {
            // Chain: 203.0.113.50, 198.51.100.1, 192.0.2.1 + REMOTE_ADDR (127.0.0.1 default)
            // All X-Forwarded-For IPs trusted, but not 127.0.0.1
            // Walking right to left: 127.0.0.1 (not trusted) -> returns
            $resolver = new TrustedProxyIpResolver(['203.0.113.50', '198.51.100.1', '192.0.2.1', '10.0.0.1']);
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '203.0.113.50, 198.51.100.1, 192.0.2.1',
            ]);
            $request->server->set('REMOTE_ADDR', '10.0.0.1');

            // All IPs including REMOTE_ADDR are trusted, so returns the leftmost (203.0.113.50)
            expect($resolver->resolve($request))->toBe('203.0.113.50');
        });

        it('falls back to request IP when header is missing', function (): void {
            $resolver = new TrustedProxyIpResolver();
            $request = createRequestWithIp('192.168.1.1');

            expect($resolver->resolve($request))->toBe('192.168.1.1');
        });

        it('falls back when header is empty', function (): void {
            $resolver = new TrustedProxyIpResolver();
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '',
            ]);
            $request->server->set('REMOTE_ADDR', '192.168.1.1');

            expect($resolver->resolve($request))->toBe('192.168.1.1');
        });

        it('trims whitespace from IPs in the chain', function (): void {
            // Chain: 203.0.113.50, 198.51.100.1 + REMOTE_ADDR
            // Walking right to left with trusted REMOTE_ADDR
            $resolver = new TrustedProxyIpResolver(['10.0.0.1']);
            $request = createRequestWithHeaders([
                'X-Forwarded-For' => '  203.0.113.50  ,  198.51.100.1  ',
            ]);
            $request->server->set('REMOTE_ADDR', '10.0.0.1');

            // Walking right to left: 10.0.0.1 (trusted), 198.51.100.1 (not trusted) -> returns
            expect($resolver->resolve($request))->toBe('198.51.100.1');
        });
    });
});
