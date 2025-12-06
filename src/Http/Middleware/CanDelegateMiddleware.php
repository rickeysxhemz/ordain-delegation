<?php

declare(strict_types=1);

namespace Ordain\Delegation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if the authenticated user can delegate (manage other users).
 *
 * Usage in routes:
 *   Route::middleware('can.delegate')->group(function () {
 *       // Routes for user management
 *   });
 */
final readonly class CanDelegateMiddleware
{
    public function __construct(
        private DelegationServiceInterface $delegation,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof DelegatableUserInterface) {
            abort(403, 'User model does not support delegation.');
        }

        if (! $this->delegation->canCreateUsers($user)) {
            abort(403, 'You are not authorized to manage users.');
        }

        return $next($request);
    }
}
