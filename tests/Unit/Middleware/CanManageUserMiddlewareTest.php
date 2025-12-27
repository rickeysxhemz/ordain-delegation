<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Http\Middleware\CanManageUserMiddleware;
use Ordain\Delegation\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class);

beforeEach(function (): void {
    $this->delegation = Mockery::mock(DelegationServiceInterface::class);
    $this->middleware = new CanManageUserMiddleware($this->delegation);
    $this->request = Mockery::mock(Request::class);
    $this->next = fn () => response('OK');
});

describe('CanManageUserMiddleware', function (): void {
    it('aborts with 401 when user is not authenticated', function (): void {
        $this->request->shouldReceive('user')->andReturn(null);

        $this->middleware->handle($this->request, $this->next);
    })->throws(HttpException::class, 'Unauthenticated.');

    it('aborts with 403 when user does not implement DelegatableUserInterface', function (): void {
        $user = new stdClass;
        $this->request->shouldReceive('user')->andReturn($user);

        $this->middleware->handle($this->request, $this->next);
    })->throws(HttpException::class, 'User model does not support delegation.');

    it('aborts with 404 when target user not found in route', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $this->request->shouldReceive('user')->andReturn($user);
        $this->request->shouldReceive('route')->with('user')->andReturn(null);

        $this->middleware->handle($this->request, $this->next);
    })->throws(HttpException::class, 'Target user not found or does not support delegation.');

    it('aborts with 404 when target does not implement DelegatableUserInterface', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $target = new stdClass;

        $this->request->shouldReceive('user')->andReturn($user);
        $this->request->shouldReceive('route')->with('user')->andReturn($target);

        $this->middleware->handle($this->request, $this->next);
    })->throws(HttpException::class, 'Target user not found or does not support delegation.');

    it('aborts with 403 when user cannot manage target', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $target = Mockery::mock(DelegatableUserInterface::class);

        $this->request->shouldReceive('user')->andReturn($user);
        $this->request->shouldReceive('route')->with('user')->andReturn($target);
        $this->delegation->shouldReceive('canManageUser')->with($user, $target)->andReturn(false);

        $this->middleware->handle($this->request, $this->next);
    })->throws(HttpException::class, 'You are not authorized to manage this user.');

    it('allows request when user can manage target', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $target = Mockery::mock(DelegatableUserInterface::class);

        $this->request->shouldReceive('user')->andReturn($user);
        $this->request->shouldReceive('route')->with('user')->andReturn($target);
        $this->delegation->shouldReceive('canManageUser')->with($user, $target)->andReturn(true);

        $response = $this->middleware->handle($this->request, $this->next);

        expect($response->getContent())->toBe('OK');
    });

    it('uses custom route parameter', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $target = Mockery::mock(DelegatableUserInterface::class);

        $this->request->shouldReceive('user')->andReturn($user);
        $this->request->shouldReceive('route')->with('targetUser')->andReturn($target);
        $this->delegation->shouldReceive('canManageUser')->with($user, $target)->andReturn(true);

        $response = $this->middleware->handle($this->request, $this->next, 'targetUser');

        expect($response->getContent())->toBe('OK');
    });
});
