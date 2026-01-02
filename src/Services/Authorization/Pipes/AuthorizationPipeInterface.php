<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization\Pipes;

use Closure;
use Ordain\Delegation\Services\Authorization\AuthorizationContext;

/**
 * Contract for authorization pipeline pipes.
 *
 * Each pipe inspects the authorization context and either:
 * - Grants access (sets result to true and stops pipeline)
 * - Denies access (sets result to false and stops pipeline)
 * - Passes to next pipe (continues evaluation)
 */
interface AuthorizationPipeInterface
{
    /**
     * @param  Closure(AuthorizationContext): AuthorizationContext  $next
     */
    public function handle(AuthorizationContext $context, Closure $next): AuthorizationContext;
}
