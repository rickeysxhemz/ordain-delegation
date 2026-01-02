# Events

The package dispatches events for all delegation actions, allowing you to react to changes in your application.

## Configuration

Events are enabled by default. To disable:

```php
// config/permission-delegation.php
'events' => [
    'enabled' => false,
],
```

## Available Events

| Event | Dispatched When |
|-------|-----------------|
| `RoleDelegated` | A role is assigned to a user |
| `RoleRevoked` | A role is removed from a user |
| `PermissionGranted` | A permission is granted to a user |
| `PermissionRevoked` | A permission is removed from a user |
| `DelegationScopeUpdated` | A user's delegation scope changes |
| `UnauthorizedDelegationAttempted` | An unauthorized delegation is attempted |

## Event Properties

### RoleDelegated

```php
use Ordain\Delegation\Events\RoleDelegated;

class RoleDelegated
{
    public function __construct(
        public readonly DelegatableUserInterface $delegator,
        public readonly DelegatableUserInterface $target,
        public readonly RoleInterface $role,
    ) {}
}
```

### RoleRevoked

```php
use Ordain\Delegation\Events\RoleRevoked;

class RoleRevoked
{
    public function __construct(
        public readonly DelegatableUserInterface $delegator,
        public readonly DelegatableUserInterface $target,
        public readonly RoleInterface $role,
    ) {}
}
```

### PermissionGranted

```php
use Ordain\Delegation\Events\PermissionGranted;

class PermissionGranted
{
    public function __construct(
        public readonly DelegatableUserInterface $delegator,
        public readonly DelegatableUserInterface $target,
        public readonly PermissionInterface $permission,
    ) {}
}
```

### PermissionRevoked

```php
use Ordain\Delegation\Events\PermissionRevoked;

class PermissionRevoked
{
    public function __construct(
        public readonly DelegatableUserInterface $delegator,
        public readonly DelegatableUserInterface $target,
        public readonly PermissionInterface $permission,
    ) {}
}
```

### DelegationScopeUpdated

```php
use Ordain\Delegation\Events\DelegationScopeUpdated;

class DelegationScopeUpdated
{
    public function __construct(
        public readonly DelegatableUserInterface $admin,
        public readonly DelegatableUserInterface $user,
        public readonly DelegationScope $oldScope,
        public readonly DelegationScope $newScope,
    ) {}
}
```

### UnauthorizedDelegationAttempted

```php
use Ordain\Delegation\Events\UnauthorizedDelegationAttempted;

class UnauthorizedDelegationAttempted
{
    public function __construct(
        public readonly DelegatableUserInterface $delegator,
        public readonly string $action,
        public readonly string $reason,
        public readonly array $context = [],
    ) {}
}
```

## Creating Listeners

### Basic Listener

```php
<?php

namespace App\Listeners;

use Ordain\Delegation\Events\RoleDelegated;

class SendRoleAssignmentNotification
{
    public function handle(RoleDelegated $event): void
    {
        $event->target->notify(new RoleAssignedNotification(
            role: $event->role->getRoleName(),
            assignedBy: $event->delegator->name,
        ));
    }
}
```

### Queued Listener

```php
<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Ordain\Delegation\Events\RoleDelegated;

class LogRoleAssignment implements ShouldQueue
{
    public string $queue = 'audit';

    public function handle(RoleDelegated $event): void
    {
        activity()
            ->causedBy($event->delegator)
            ->performedOn($event->target)
            ->withProperties([
                'role' => $event->role->getRoleName(),
                'action' => 'role_assigned',
            ])
            ->log('Role assigned');
    }
}
```

### Conditional Listener

```php
<?php

namespace App\Listeners;

use Ordain\Delegation\Events\RoleDelegated;

class AlertOnAdminAssignment
{
    public function handle(RoleDelegated $event): void
    {
        // Only alert for admin role assignments
        if ($event->role->getRoleName() !== 'admin') {
            return;
        }

        // Send alert to security team
        SecurityAlert::dispatch([
            'type' => 'admin_role_assigned',
            'target' => $event->target->id,
            'assigned_by' => $event->delegator->id,
        ]);
    }

    public function shouldQueue(RoleDelegated $event): bool
    {
        return $event->role->getRoleName() === 'admin';
    }
}
```

## Registering Listeners

### Using EventServiceProvider

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Ordain\Delegation\Events\RoleDelegated;
use Ordain\Delegation\Events\RoleRevoked;
use Ordain\Delegation\Events\PermissionGranted;
use Ordain\Delegation\Events\DelegationScopeUpdated;
use Ordain\Delegation\Events\UnauthorizedDelegationAttempted;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RoleDelegated::class => [
            \App\Listeners\SendRoleAssignmentNotification::class,
            \App\Listeners\LogRoleAssignment::class,
        ],
        RoleRevoked::class => [
            \App\Listeners\SendRoleRevokedNotification::class,
        ],
        PermissionGranted::class => [
            \App\Listeners\LogPermissionGrant::class,
        ],
        DelegationScopeUpdated::class => [
            \App\Listeners\NotifyScopeChange::class,
        ],
        UnauthorizedDelegationAttempted::class => [
            \App\Listeners\LogSecurityIncident::class,
            \App\Listeners\AlertSecurityTeam::class,
        ],
    ];
}
```

### Using Event Discovery

Laravel can auto-discover listeners:

```php
// App\Listeners\HandleRoleDelegated.php
<?php

namespace App\Listeners;

use Ordain\Delegation\Events\RoleDelegated;

class HandleRoleDelegated
{
    // Method name matches event class
    public function handle(RoleDelegated $event): void
    {
        // Handle event
    }
}
```

### Using Closures

```php
// In a service provider boot method
use Illuminate\Support\Facades\Event;
use Ordain\Delegation\Events\RoleDelegated;

Event::listen(RoleDelegated::class, function (RoleDelegated $event) {
    logger()->info('Role delegated', [
        'role' => $event->role->getRoleName(),
        'target' => $event->target->id,
    ]);
});
```

## Event Subscriber

Group related listeners in a subscriber:

```php
<?php

namespace App\Listeners;

use Illuminate\Events\Dispatcher;
use Ordain\Delegation\Events\RoleDelegated;
use Ordain\Delegation\Events\RoleRevoked;
use Ordain\Delegation\Events\PermissionGranted;
use Ordain\Delegation\Events\PermissionRevoked;
use Ordain\Delegation\Events\DelegationScopeUpdated;
use Ordain\Delegation\Events\UnauthorizedDelegationAttempted;

class DelegationEventSubscriber
{
    public function handleRoleDelegated(RoleDelegated $event): void
    {
        $this->logActivity('role_assigned', $event->delegator, $event->target, [
            'role' => $event->role->getRoleName(),
        ]);
    }

    public function handleRoleRevoked(RoleRevoked $event): void
    {
        $this->logActivity('role_revoked', $event->delegator, $event->target, [
            'role' => $event->role->getRoleName(),
        ]);
    }

    public function handlePermissionGranted(PermissionGranted $event): void
    {
        $this->logActivity('permission_granted', $event->delegator, $event->target, [
            'permission' => $event->permission->getPermissionName(),
        ]);
    }

    public function handlePermissionRevoked(PermissionRevoked $event): void
    {
        $this->logActivity('permission_revoked', $event->delegator, $event->target, [
            'permission' => $event->permission->getPermissionName(),
        ]);
    }

    public function handleScopeUpdated(DelegationScopeUpdated $event): void
    {
        $this->logActivity('scope_updated', $event->admin, $event->user, [
            'old_scope' => $event->oldScope->toArray(),
            'new_scope' => $event->newScope->toArray(),
        ]);
    }

    public function handleUnauthorizedAttempt(UnauthorizedDelegationAttempted $event): void
    {
        Log::channel('security')->warning('Unauthorized delegation attempt', [
            'delegator_id' => $event->delegator->getDelegatableIdentifier(),
            'action' => $event->action,
            'reason' => $event->reason,
            'context' => $event->context,
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            RoleDelegated::class => 'handleRoleDelegated',
            RoleRevoked::class => 'handleRoleRevoked',
            PermissionGranted::class => 'handlePermissionGranted',
            PermissionRevoked::class => 'handlePermissionRevoked',
            DelegationScopeUpdated::class => 'handleScopeUpdated',
            UnauthorizedDelegationAttempted::class => 'handleUnauthorizedAttempt',
        ];
    }

    private function logActivity(string $action, $delegator, $target, array $properties): void
    {
        activity('delegation')
            ->causedBy($delegator)
            ->performedOn($target)
            ->withProperties($properties)
            ->log($action);
    }
}
```

Register the subscriber:

```php
// EventServiceProvider.php
protected $subscribe = [
    \App\Listeners\DelegationEventSubscriber::class,
];
```

## Common Use Cases

### Send Notifications

```php
public function handle(RoleDelegated $event): void
{
    // Email notification
    Mail::to($event->target)->send(new RoleAssignedMail($event->role));

    // Slack notification
    Notification::route('slack', config('services.slack.webhook'))
        ->notify(new RoleAssignedSlackNotification($event));

    // Database notification
    $event->target->notify(new RoleAssignedNotification($event->role));
}
```

### Audit Logging

```php
public function handle(RoleDelegated $event): void
{
    AuditLog::create([
        'action' => 'role_assigned',
        'actor_id' => $event->delegator->id,
        'subject_id' => $event->target->id,
        'subject_type' => get_class($event->target),
        'properties' => [
            'role_id' => $event->role->getRoleIdentifier(),
            'role_name' => $event->role->getRoleName(),
        ],
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
}
```

### Security Monitoring

```php
public function handle(UnauthorizedDelegationAttempted $event): void
{
    // Log to security channel
    Log::channel('security')->warning('Unauthorized delegation attempt', [
        'user_id' => $event->delegator->id,
        'action' => $event->action,
        'reason' => $event->reason,
        'ip' => request()->ip(),
    ]);

    // Increment rate limiter
    RateLimiter::hit('delegation-violations:' . $event->delegator->id);

    // Alert if threshold exceeded
    if (RateLimiter::tooManyAttempts('delegation-violations:' . $event->delegator->id, 5)) {
        SecurityTeam::alert($event);
    }
}
```

### Sync with External Systems

```php
public function handle(RoleDelegated $event): void
{
    // Sync to identity provider
    Http::post(config('services.idp.url') . '/users/' . $event->target->id . '/roles', [
        'role' => $event->role->getRoleName(),
    ]);

    // Update search index
    $event->target->searchable();

    // Clear CDN cache
    Cdn::purge('/api/users/' . $event->target->id);
}
```

## Testing Events

### Assert Events Dispatched

```php
use Illuminate\Support\Facades\Event;
use Ordain\Delegation\Events\RoleDelegated;

public function test_role_delegation_dispatches_event(): void
{
    Event::fake();

    Delegation::delegateRole($delegator, $target, $role);

    Event::assertDispatched(RoleDelegated::class, function ($event) use ($target, $role) {
        return $event->target->id === $target->id
            && $event->role->getRoleName() === $role->name;
    });
}
```

### Assert Listeners Called

```php
public function test_notification_sent_on_role_delegation(): void
{
    Notification::fake();

    Delegation::delegateRole($delegator, $target, $role);

    Notification::assertSentTo($target, RoleAssignedNotification::class);
}
```

## Next Steps

- [Commands](commands.md) - Artisan console commands
- [Customization](customization.md) - Extend the package
- [API Reference](api-reference.md) - Complete method reference