<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Http\Middleware\CanDelegateMiddleware;
use Ordain\Delegation\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class);

beforeEach(function (): void {
    $this->delegation = Mockery::mock(DelegationServiceInterface::class);
    $this->middleware = new CanDelegateMiddleware($this->delegation);
    $this->request = Mockery::mock(Request::class);
    $this->next = fn () => response('OK');
});

describe('CanDelegateMiddleware', function (): void {
    it('aborts with 401 when user is not authenticated', function (): void {
        $this->request->shouldReceive('user')->andReturn(null);

        $this->middleware->handle($this->request, $this->next);
    })->throws(HttpException::class, 'Unauthenticated.');

    it('aborts with 403 when user does not implement DelegatableUserInterface', function (): void {
        $user = new stdClass;
        $this->request->shouldReceive('user')->andReturn($user);

        $this->middleware->handle($this->request, $this->next);
    })->throws(HttpException::class, 'User model does not support delegation.');

    it('aborts with 403 when user cannot create users', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $this->request->shouldReceive('user')->andReturn($user);
        $this->delegation->shouldReceive('canCreateUsers')->with($user)->andReturn(false);

        $this->middleware->handle($this->request, $this->next);
    })->throws(HttpException::class, 'You are not authorized to manage users.');

    it('allows request when user can create users', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $this->request->shouldReceive('user')->andReturn($user);
        $this->delegation->shouldReceive('canCreateUsers')->with($user)->andReturn(true);

        $response = $this->middleware->handle($this->request, $this->next);

        expect($response->getContent())->toBe('OK');
    });
});
