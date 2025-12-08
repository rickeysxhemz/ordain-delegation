# Customization

The package is designed for extensibility through interfaces.

## Custom Role Repository

Implement `RoleRepositoryInterface` to integrate with your role system:

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;

final readonly class CustomRoleRepository implements RoleRepositoryInterface
{
    public function findById(int|string $id): ?RoleInterface
    {
        // Your implementation
    }

    public function findByName(string $name, ?string $guard = null): ?RoleInterface
    {
        // Your implementation
    }

    public function all(?string $guard = null): Collection
    {
        // Your implementation
    }
}
```

Register in a service provider:

```php
$this->app->scoped(
    RoleRepositoryInterface::class,
    CustomRoleRepository::class,
);
```

## Custom Permission Repository

Implement `PermissionRepositoryInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use Ordain\Delegation\Contracts\Repositories\PermissionRepositoryInterface;

final readonly class CustomPermissionRepository implements PermissionRepositoryInterface
{
    // Implement required methods
}
```

## Custom Audit Logger

Implement `DelegationAuditInterface` for custom audit logging:

```php
<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

final readonly class SlackDelegationAudit implements DelegationAuditInterface
{
    public function __construct(
        private SlackClient $slack,
    ) {}

    public function logRoleAssigned(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->slack->send("Role assigned: {$role->getRoleName()}");
    }

    public function logRoleRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->slack->send("Role revoked: {$role->getRoleName()}");
    }

    public function logPermissionGranted(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        // Implementation
    }

    public function logPermissionRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        // Implementation
    }

    public function logScopeUpdated(
        DelegatableUserInterface $admin,
        DelegatableUserInterface $user,
        DelegationScope $scope,
    ): void {
        // Implementation
    }

    public function logUnauthorizedAttempt(
        DelegatableUserInterface $delegator,
        string $action,
        array $context = [],
    ): void {
        // Implementation
    }
}
```

Configure in `config/permission-delegation.php`:

```php
'audit' => [
    'enabled' => true,
    'driver' => App\Services\Audit\SlackDelegationAudit::class,
],
```

## Custom Role/Permission Adapters

Create adapters for non-Spatie role systems:

```php
<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Models\CustomRole;
use Ordain\Delegation\Contracts\RoleInterface;

final readonly class CustomRoleAdapter implements RoleInterface
{
    public function __construct(
        private CustomRole $role,
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

    public function getModel(): CustomRole
    {
        return $this->role;
    }
}
```

## Exception Handling

Handle delegation exceptions in your exception handler:

```php
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;

public function render($request, Throwable $e): Response
{
    if ($e instanceof UnauthorizedDelegationException) {
        return response()->json([
            'message' => $e->getMessage(),
            'action' => $e->getAttemptedAction(),
            'context' => $e->getContext(),
        ], 403);
    }

    return parent::render($request, $e);
}
```