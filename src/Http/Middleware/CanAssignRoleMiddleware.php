<?php

declare(strict_types=1);

namespace Ordain\Delegation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if the authenticated user can assign a specific role.
 *
 * Usage in routes:
 *   Route::middleware('can.assign.role:editor')->post('/users/{user}/roles', ...);
 *   Route::middleware('can.assign.role:admin,manager')->post('/users/{user}/promote', ...);
 */
final readonly class CanAssignRoleMiddleware
{
    public function __construct(
        private DelegationServiceInterface $delegation,
        private RoleRepositoryInterface $roleRepository,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roleNames  One or more role names (comma-separated in route definition)
     */
    public function handle(Request $request, Closure $next, string ...$roleNames): Response
    {
        $user = $request->user();

        if (! $user instanceof DelegatableUserInterface) {
            abort(403, 'User model does not support delegation.');
        }

        foreach ($roleNames as $roleName) {
            $role = $this->roleRepository->findByName($roleName);

            if ($role === null) {
                abort(404, "Role '{$roleName}' not found.");
            }

            if (! $this->delegation->canAssignRole($user, $role)) {
                abort(403, "You are not authorized to assign the '{$roleName}' role.");
            }
        }

        return $next($request);
    }
}
