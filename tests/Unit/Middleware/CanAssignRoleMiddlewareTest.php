<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Http\Middleware\CanAssignRoleMiddleware;
use Ordain\Delegation\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class);

beforeEach(function (): void {
    $this->delegation = Mockery::mock(DelegationServiceInterface::class);
    $this->roleRepository = Mockery::mock(RoleRepositoryInterface::class);
    $this->middleware = new CanAssignRoleMiddleware($this->delegation, $this->roleRepository);
    $this->request = Mockery::mock(Request::class);
    $this->next = fn () => response('OK');
});

describe('CanAssignRoleMiddleware', function (): void {
    it('aborts with 401 when user is not authenticated', function (): void {
        $this->request->shouldReceive('user')->andReturn(null);

        $this->middleware->handle($this->request, $this->next, 'admin');
    })->throws(HttpException::class, 'Unauthenticated.');

    it('aborts with 403 when user does not implement DelegatableUserInterface', function (): void {
        $user = new stdClass;
        $this->request->shouldReceive('user')->andReturn($user);

        $this->middleware->handle($this->request, $this->next, 'admin');
    })->throws(HttpException::class, 'User model does not support delegation.');

    it('allows request when no role names provided', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $this->request->shouldReceive('user')->andReturn($user);

        $response = $this->middleware->handle($this->request, $this->next);

        expect($response->getContent())->toBe('OK');
    });

    it('aborts with 404 when role not found', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $this->request->shouldReceive('user')->andReturn($user);
        $this->roleRepository->shouldReceive('findByName')->with('admin')->andReturn(null);

        $this->middleware->handle($this->request, $this->next, 'admin');
    })->throws(HttpException::class, "Role 'admin' not found.");

    it('aborts with 403 when user cannot assign role', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $role = Mockery::mock(RoleInterface::class);

        $this->request->shouldReceive('user')->andReturn($user);
        $this->roleRepository->shouldReceive('findByName')->with('admin')->andReturn($role);
        $this->delegation->shouldReceive('canAssignRole')->with($user, $role)->andReturn(false);

        $this->middleware->handle($this->request, $this->next, 'admin');
    })->throws(HttpException::class, "You are not authorized to assign the 'admin' role.");

    it('allows request when user can assign role', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $role = Mockery::mock(RoleInterface::class);

        $this->request->shouldReceive('user')->andReturn($user);
        $this->roleRepository->shouldReceive('findByName')->with('admin')->andReturn($role);
        $this->delegation->shouldReceive('canAssignRole')->with($user, $role)->andReturn(true);

        $response = $this->middleware->handle($this->request, $this->next, 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('checks all provided roles', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $adminRole = Mockery::mock(RoleInterface::class);
        $editorRole = Mockery::mock(RoleInterface::class);

        $this->request->shouldReceive('user')->andReturn($user);
        $this->roleRepository->shouldReceive('findByName')->with('admin')->andReturn($adminRole);
        $this->roleRepository->shouldReceive('findByName')->with('editor')->andReturn($editorRole);
        $this->delegation->shouldReceive('canAssignRole')->with($user, $adminRole)->andReturn(true);
        $this->delegation->shouldReceive('canAssignRole')->with($user, $editorRole)->andReturn(true);

        $response = $this->middleware->handle($this->request, $this->next, 'admin', 'editor');

        expect($response->getContent())->toBe('OK');
    });

    it('aborts on first unauthorized role', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $adminRole = Mockery::mock(RoleInterface::class);
        $editorRole = Mockery::mock(RoleInterface::class);

        $this->request->shouldReceive('user')->andReturn($user);
        $this->roleRepository->shouldReceive('findByName')->with('admin')->andReturn($adminRole);
        $this->delegation->shouldReceive('canAssignRole')->with($user, $adminRole)->andReturn(false);

        $this->middleware->handle($this->request, $this->next, 'admin', 'editor');
    })->throws(HttpException::class, "You are not authorized to assign the 'admin' role.");
});
