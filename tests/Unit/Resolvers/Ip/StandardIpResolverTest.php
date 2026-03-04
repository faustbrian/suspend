<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Suspend\Resolvers\Ip\StandardIpResolver;

describe('StandardIpResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new StandardIpResolver();
    });

    describe('identifier', function (): void {
        it('returns standard', function (): void {
            expect($this->resolver->identifier())->toBe('standard');
        });
    });

    describe('resolve', function (): void {
        it('returns request IP using Laravel built-in method', function (): void {
            $request = createRequestWithIp('192.168.1.1');

            expect($this->resolver->resolve($request))->toBe('192.168.1.1');
        });

        it('respects trusted proxies configuration', function (): void {
            $request = createRequestWithIp('10.0.0.1');

            expect($this->resolver->resolve($request))->toBe('10.0.0.1');
        });
    });
});
