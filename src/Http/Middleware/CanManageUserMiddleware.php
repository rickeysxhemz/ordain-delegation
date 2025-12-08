<?php

declare(strict_types=1);

namespace Ordain\Delegation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if the authenticated user can manage a target user.
 */
final readonly class CanManageUserMiddleware
{
    public function __construct(
        private DelegationServiceInterface $delegation,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     * @param  string  $routeParameter  The route parameter name containing the target user
     */
    public function handle(Request $request, Closure $next, string $routeParameter = 'user'): Response
    {
        $delegator = $request->user();

        if ($delegator === null) {
            abort(401, 'Unauthenticated.');
        }

        if (! $delegator instanceof DelegatableUserInterface) {
            abort(403, 'User model does not support delegation.');
        }

        $target = $request->route($routeParameter);

        if (! $target instanceof DelegatableUserInterface) {
            abort(404, 'Target user not found or does not support delegation.');
        }

        if (! $this->delegation->canManageUser($delegator, $target)) {
            abort(403, 'You are not authorized to manage this user.');
        }

        return $next($request);
    }
}
