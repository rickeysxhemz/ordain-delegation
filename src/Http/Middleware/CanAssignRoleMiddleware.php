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
 * Middleware to check if the authenticated user can assign specific roles.
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

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user instanceof DelegatableUserInterface) {
            abort(403, 'User model does not support delegation.');
        }

        if ($roleNames === []) {
            return $next($request);
        }

        // Batch fetch all roles in single query (N+1 optimization)
        $roles = $this->roleRepository->findByNames($roleNames);
        $foundRoleNames = $roles->map(fn ($role) => $role->getRoleName())->all();

        // Collect all authorization results first (constant-time operation)
        // This prevents timing attacks that could reveal role existence
        $authorized = true;
        foreach ($roles as $role) {
            if (! $this->delegation->canAssignRole($user, $role)) {
                $authorized = false;
                // Don't break early - continue checking all roles for constant timing
            }
        }

        // Check for missing roles
        $missingRoles = array_diff($roleNames, $foundRoleNames);

        // Single consolidated check at the end (prevents timing-based enumeration)
        if (! $authorized || $missingRoles !== []) {
            // Generic message - don't reveal which specific roles are missing or unauthorized
            abort(403, 'You are not authorized to assign the requested role.');
        }

        return $next($request);
    }
}
