<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ordain\Delegation\Routing\RouteMacros;

describe('RouteMacros', function (): void {
    beforeEach(function (): void {
        RouteMacros::register();
    });

    it('registers canDelegate macro on Router', function (): void {
        expect(Route::hasMacro('canDelegate'))->toBeTrue();
    });

    it('registers canAssignRole macro on Router', function (): void {
        expect(Route::hasMacro('canAssignRole'))->toBeTrue();
    });

    it('registers canManageUser macro on Router', function (): void {
        expect(Route::hasMacro('canManageUser'))->toBeTrue();
    });

    it('register method registers all macros', function (): void {
        // Clear and re-register
        RouteMacros::register();

        expect(Route::hasMacro('canDelegate'))->toBeTrue()
            ->and(Route::hasMacro('canAssignRole'))->toBeTrue()
            ->and(Route::hasMacro('canManageUser'))->toBeTrue();
    });
});
