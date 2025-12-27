<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Ordain\Delegation\Services\Audit\AuditContext;

describe('AuditContext', function (): void {
    it('creates default context with CLI values', function (): void {
        $context = new AuditContext;

        expect($context->ipAddress)->toBe('cli')
            ->and($context->userAgent)->toBe('cli');
    });

    it('creates context with custom values', function (): void {
        $context = new AuditContext(
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
        );

        expect($context->ipAddress)->toBe('192.168.1.1')
            ->and($context->userAgent)->toBe('Mozilla/5.0');
    });
});

describe('fromRequest', function (): void {
    it('returns CLI context when request is null', function (): void {
        $context = AuditContext::fromRequest(null);

        expect($context->ipAddress)->toBe('cli')
            ->and($context->userAgent)->toBe('cli');
    });

    it('extracts IP and user agent from request', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('10.0.0.1');
        $request->shouldReceive('userAgent')->andReturn('TestBrowser/1.0');

        $context = AuditContext::fromRequest($request);

        expect($context->ipAddress)->toBe('10.0.0.1')
            ->and($context->userAgent)->toBe('TestBrowser/1.0');
    });

    it('uses CLI identifier when IP is null', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn(null);
        $request->shouldReceive('userAgent')->andReturn('TestBrowser/1.0');

        $context = AuditContext::fromRequest($request);

        expect($context->ipAddress)->toBe('cli');
    });

    it('sanitizes user agent with control characters', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('10.0.0.1');
        $request->shouldReceive('userAgent')->andReturn("Test\x00Browser\x1F/1.0");

        $context = AuditContext::fromRequest($request);

        expect($context->userAgent)->toBe('TestBrowser/1.0');
    });

    it('uses CLI identifier when user agent is null', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('10.0.0.1');
        $request->shouldReceive('userAgent')->andReturn(null);

        $context = AuditContext::fromRequest($request);

        expect($context->userAgent)->toBe('cli');
    });

    it('uses CLI identifier when user agent is empty', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('10.0.0.1');
        $request->shouldReceive('userAgent')->andReturn('');

        $context = AuditContext::fromRequest($request);

        expect($context->userAgent)->toBe('cli');
    });

    it('truncates long user agents to 500 characters', function (): void {
        $longUserAgent = str_repeat('A', 600);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('10.0.0.1');
        $request->shouldReceive('userAgent')->andReturn($longUserAgent);

        $context = AuditContext::fromRequest($request);

        expect(mb_strlen($context->userAgent))->toBe(500);
    });
});

describe('forCli', function (): void {
    it('creates CLI context', function (): void {
        $context = AuditContext::forCli();

        expect($context->ipAddress)->toBe('cli')
            ->and($context->userAgent)->toBe('cli');
    });
});

describe('custom', function (): void {
    it('creates context with custom values and sanitizes user agent', function (): void {
        $context = AuditContext::custom('192.168.1.100', "Custom\x00Agent");

        expect($context->ipAddress)->toBe('192.168.1.100')
            ->and($context->userAgent)->toBe('CustomAgent');
    });
});
