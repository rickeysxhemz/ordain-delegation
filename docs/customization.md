# Customization

Extend and customize the package for your specific needs.

## Architecture Overview

The package uses interfaces throughout, making it easy to swap implementations:

```
┌─────────────────────────────────────────────────────────────┐
│                    Your Application                          │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                  Contracts (Interfaces)                      │
│  DelegationServiceInterface, RoleRepositoryInterface, etc.  │
└─────────────────────────┬───────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          ▼               ▼               ▼
┌─────────────────┐ ┌───────────┐ ┌─────────────────┐
│  Default Impl   │ │  Spatie   │ │ Your Custom     │
│  (Eloquent)     │ │  Adapters │ │ Implementation  │
└─────────────────┘ └───────────┘ └─────────────────┘
```

## Custom Role Repository

Replace the Spatie role repository with your own implementation:

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Contracts\DelegatableUserInterface;

final readonly class CustomRoleRepository implements RoleRepositoryInterface
{
    public function findById(int|string $id): ?RoleInterface
    {
        $role = Role::find($id);

        return $role ? new CustomRoleAdapter($role) : null;
    }

    public function findByIds(array $ids): Collection
    {
        return Role::whereIn('id', $ids)
            ->get()
            ->map(fn (Role $role) => new CustomRoleAdapter($role));
    }

    public function findByName(string $name, ?string $guard = null): ?RoleInterface
    {
        $role = Role::query()
            ->where('name', $name)
            ->when($guard, fn ($q) => $q->where('guard_name', $guard))
            ->first();

        return $role ? new CustomRoleAdapter($role) : null;
    }

    public function findByNames(array $names, ?string $guard = null): Collection
    {
        return Role::query()
            ->whereIn('name', $names)
            ->when($guard, fn ($q) => $q->where('guard_name', $guard))
            ->get()
            ->map(fn (Role $role) => new CustomRoleAdapter($role));
    }

    public function all(?string $guard = null): Collection
    {
        return Role::query()
            ->when($guard, fn ($q) => $q->where('guard_name', $guard))
            ->get()
            ->map(fn (Role $role) => new CustomRoleAdapter($role));
    }

    public function allLazy(?string $guard = null): LazyCollection
    {
        return Role::query()
            ->when($guard, fn ($q) => $q->where('guard_name', $guard))
            ->cursor()
            ->map(fn (Role $role) => new CustomRoleAdapter($role));
    }

    public function getUserRoles(DelegatableUserInterface $user): Collection
    {
        return $user->roles
            ->map(fn (Role $role) => new CustomRoleAdapter($role));
    }

    public function assignToUser(DelegatableUserInterface $user, RoleInterface $role): void
    {
        $user->roles()->attach($role->getRoleIdentifier());
    }

    public function removeFromUser(DelegatableUserInterface $user, RoleInterface $role): void
    {
        $user->roles()->detach($role->getRoleIdentifier());
    }

    public function userHasRole(DelegatableUserInterface $user, RoleInterface $role): bool
    {
        return $user->roles()->where('id', $role->getRoleIdentifier())->exists();
    }

    public function userHasRoleByName(DelegatableUserInterface $user, string $roleName): bool
    {
        return $user->roles()->where('name', $roleName)->exists();
    }

    public function syncUserRoles(DelegatableUserInterface $user, array $roleIds): void
    {
        $user->roles()->sync($roleIds);
    }
}
```

### Register Custom Repository

```php
// AppServiceProvider.php
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;

public function register(): void
{
    $this->app->scoped(
        RoleRepositoryInterface::class,
        CustomRoleRepository::class
    );
}
```

## Custom Role Adapter

If you're not using Spatie, create an adapter for your role model:

```php
<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Models\Role;
use Ordain\Delegation\Contracts\RoleInterface;

final readonly class CustomRoleAdapter implements RoleInterface
{
    public function __construct(
        private Role $role,
    ) {}

    public function getRoleIdentifier(): int|string
    {
        return $this->role->id;
    }

    public function getRoleName(): string
    {
        return $this->role->name;
    }

    public function getRoleGuard(): string
    {
        return $this->role->guard ?? 'web';
    }

    public function getModel(): Role
    {
        return $this->role;
    }

    public static function fromModel(Role $role): self
    {
        return new self($role);
    }
}
```

## Custom Audit Driver

Create a custom audit driver for specialized logging:

```php
<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

final readonly class SlackAuditDriver implements DelegationAuditInterface
{
    public function __construct(
        private SlackClient $slack,
        private string $channel = '#security-alerts',
    ) {}

    public function logRoleAssigned(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->notify(
            "Role `{$role->getRoleName()}` assigned to user #{$target->getDelegatableIdentifier()} " .
            "by user #{$delegator->getDelegatableIdentifier()}"
        );
    }

    public function logRoleRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->notify(
            "Role `{$role->getRoleName()}` revoked from user #{$target->getDelegatableIdentifier()} " .
            "by user #{$delegator->getDelegatableIdentifier()}"
        );
    }

    public function logPermissionGranted(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        $this->notify(
            "Permission `{$permission->getPermissionName()}` granted to user #{$target->getDelegatableIdentifier()}"
        );
    }

    public function logPermissionRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        $this->notify(
            "Permission `{$permission->getPermissionName()}` revoked from user #{$target->getDelegatableIdentifier()}"
        );
    }

    public function logDelegationScopeChanged(
        DelegatableUserInterface $admin,
        DelegatableUserInterface $user,
        DelegationScope $scope,
    ): void {
        $this->notify(
            "Delegation scope updated for user #{$user->getDelegatableIdentifier()} " .
            "by admin #{$admin->getDelegatableIdentifier()}"
        );
    }

    public function logUnauthorizedAttempt(
        DelegatableUserInterface $delegator,
        string $action,
        array $context = [],
    ): void {
        $this->notify(
            ":warning: Unauthorized delegation attempt by user #{$delegator->getDelegatableIdentifier()}: {$action}",
            'warning'
        );
    }

    public function logUserCreated(
        DelegatableUserInterface $creator,
        DelegatableUserInterface $user,
    ): void {
        $this->notify(
            "User #{$user->getDelegatableIdentifier()} created by user #{$creator->getDelegatableIdentifier()}"
        );
    }

    private function notify(string $message, string $type = 'info'): void
    {
        $this->slack->send($this->channel, $message, $type);
    }
}
```

### Configure Custom Audit Driver

```php
// config/permission-delegation.php
'audit' => [
    'enabled' => true,
    'driver' => App\Services\Audit\SlackAuditDriver::class,
],
```

Or register in a service provider:

```php
use Ordain\Delegation\Contracts\DelegationAuditInterface;

$this->app->scoped(DelegationAuditInterface::class, function ($app) {
    return new SlackAuditDriver(
        slack: $app->make(SlackClient::class),
        channel: config('services.slack.delegation_channel'),
    );
});
```

## Custom Authorization Pipe

Add custom authorization logic:

```php
<?php

declare(strict_types=1);

namespace App\Services\Authorization\Pipes;

use Ordain\Delegation\Services\Authorization\AuthorizationContext;
use Ordain\Delegation\Services\Authorization\Pipes\AuthorizationPipeInterface;

final readonly class CheckDepartmentPipe implements AuthorizationPipeInterface
{
    public function handle(AuthorizationContext $context, callable $next): AuthorizationContext
    {
        // Skip if already granted (e.g., by root admin)
        if ($context->isGranted()) {
            return $next($context);
        }

        $delegator = $context->getDelegator();
        $target = $context->getTarget();

        // Skip if no target (e.g., checking if user can delegate in general)
        if ($target === null) {
            return $next($context);
        }

        // Check same department
        if ($delegator->department_id !== $target->department_id) {
            return $context->deny('Cannot delegate across departments');
        }

        return $next($context);
    }
}
```

### Register Custom Pipe

```php
// AppServiceProvider.php
use Ordain\Delegation\Contracts\AuthorizationPipelineInterface;
use Ordain\Delegation\Services\Authorization\AuthorizationPipeline;

public function register(): void
{
    $this->app->extend(AuthorizationPipelineInterface::class, function ($pipeline, $app) {
        // Add your pipe to the existing pipeline
        return new class($pipeline, $app->make(CheckDepartmentPipe::class)) implements AuthorizationPipelineInterface {
            public function __construct(
                private AuthorizationPipelineInterface $wrapped,
                private CheckDepartmentPipe $departmentPipe,
            ) {}

            public function canAssignRole($delegator, $role, $target): bool
            {
                // Run department check first
                $context = AuthorizationContext::forRoleAssignment($delegator, $role, $target);
                $context = $this->departmentPipe->handle($context, fn ($c) => $c);

                if ($context->isDenied()) {
                    return false;
                }

                return $this->wrapped->canAssignRole($delegator, $role, $target);
            }

            // Implement other methods similarly...
        };
    });
}
```

## Custom Event Dispatcher

Replace the event dispatcher:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Ordain\Delegation\Contracts\EventDispatcherInterface;

final readonly class CustomEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private EventBusInterface $eventBus,
    ) {}

    public function dispatch(object $event): void
    {
        // Log all events
        logger()->info('Delegation event', ['event' => get_class($event)]);

        // Dispatch to custom event bus
        $this->eventBus->publish($event);

        // Also dispatch via Laravel
        event($event);
    }
}
```

## Extending the Delegation Service

Wrap the service with additional functionality:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\RoleInterface;

final class ExtendedDelegationService implements DelegationServiceInterface
{
    public function __construct(
        private DelegationServiceInterface $wrapped,
        private MetricsService $metrics,
    ) {}

    public function delegateRole(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->metrics->increment('delegation.role_assigned');
        $this->metrics->timing('delegation.role_assigned', function () use ($delegator, $target, $role) {
            $this->wrapped->delegateRole($delegator, $target, $role);
        });
    }

    // Delegate all other methods to wrapped service
    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool {
        return $this->wrapped->canAssignRole($delegator, $role, $target);
    }

    // ... implement remaining interface methods
}
```

### Register Extended Service

```php
$this->app->extend(DelegationServiceInterface::class, function ($service, $app) {
    return new ExtendedDelegationService(
        wrapped: $service,
        metrics: $app->make(MetricsService::class),
    );
});
```

## Custom Blade Directives

Add your own delegation-related directives:

```php
// AppServiceProvider.php
use Illuminate\Support\Facades\Blade;

public function boot(): void
{
    Blade::directive('canDelegateInDepartment', function ($expression) {
        return "<?php if(app('delegation')->canCreateUsers(auth()->user()) && auth()->user()->department_id === {$expression}): ?>";
    });

    Blade::directive('endCanDelegateInDepartment', function () {
        return '<?php endif; ?>';
    });
}
```

## Custom Middleware

Create specialized middleware:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ordain\Delegation\Facades\Delegation;

class EnsureDelegationQuota
{
    public function handle(Request $request, Closure $next, int $minQuota = 1)
    {
        $user = $request->user();

        if (! $user || ! Delegation::canCreateUsers($user)) {
            abort(403, 'No delegation rights');
        }

        $remaining = Delegation::getRemainingQuota($user);

        if ($remaining !== null && $remaining < $minQuota) {
            abort(403, "Insufficient quota. Need {$minQuota}, have {$remaining}.");
        }

        return $next($request);
    }
}
```

## Exception Handling

Customize how delegation exceptions are handled:

```php
// app/Exceptions/Handler.php
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;
use Ordain\Delegation\Exceptions\DelegationException;

public function render($request, Throwable $e): Response
{
    if ($e instanceof UnauthorizedDelegationException) {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'delegation_unauthorized',
                'message' => $e->getMessage(),
                'action' => $e->getAttemptedAction(),
                'context' => $e->getContext(),
            ], 403);
        }

        return redirect()
            ->back()
            ->with('error', $e->getMessage());
    }

    if ($e instanceof DelegationException) {
        report($e); // Log the error

        return response()->json([
            'error' => 'delegation_error',
            'message' => 'A delegation error occurred.',
        ], 500);
    }

    return parent::render($request, $e);
}
```

## Next Steps

- [API Reference](api-reference.md) - Complete method reference
- [Testing](testing.md) - Test your implementation
- [Troubleshooting](troubleshooting.md) - Common issues