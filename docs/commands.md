# Artisan Commands

Console commands for managing delegation from the command line.

## Available Commands

| Command | Description |
|---------|-------------|
| `delegation:install` | Interactive installation wizard |
| `delegation:show` | Display a user's delegation scope |
| `delegation:assign` | Assign a role to a user |
| `delegation:cache-reset` | Clear delegation cache |
| `delegation:health` | Package health check |

## delegation:install

Interactive wizard for setting up the package.

```bash
php artisan delegation:install
```

### What It Does

1. Publishes the configuration file
2. Publishes database migrations
3. Runs migrations
4. Guides through initial setup
5. Optionally creates a root admin user

### Options

```bash
# Skip confirmation prompts
php artisan delegation:install --force

# Only publish config
php artisan delegation:install --config

# Only publish migrations
php artisan delegation:install --migrations
```

## delegation:show

Display a user's delegation scope and capabilities.

```bash
php artisan delegation:show {user}
```

### Arguments

| Argument | Description |
|----------|-------------|
| `user` | User ID or email address |

### Example Output

```
+---------------------------+----------------------------------------+
| Delegation Scope for User #5 (john@example.com)                   |
+---------------------------+----------------------------------------+
| Can Manage Users          | Yes                                    |
| Max Manageable Users      | 10                                     |
| Created Users Count       | 3                                      |
| Remaining Quota           | 7                                      |
| Created By                | Admin (ID: 1)                          |
+---------------------------+----------------------------------------+
| Assignable Roles                                                  |
+---------------------------+----------------------------------------+
| ID: 2                     | editor                                 |
| ID: 3                     | moderator                              |
+---------------------------+----------------------------------------+
| Assignable Permissions                                            |
+---------------------------+----------------------------------------+
| ID: 5                     | posts.create                           |
| ID: 6                     | posts.edit                             |
| ID: 7                     | posts.delete                           |
+---------------------------+----------------------------------------+
| Created Users                                                     |
+---------------------------+----------------------------------------+
| ID: 10                    | alice@example.com                      |
| ID: 11                    | bob@example.com                        |
| ID: 12                    | carol@example.com                      |
+---------------------------+----------------------------------------+
```

### Options

```bash
# Output as JSON
php artisan delegation:show 5 --json

# Include more details
php artisan delegation:show 5 --verbose
```

## delegation:assign

Assign a role to a user via CLI.

```bash
php artisan delegation:assign {delegator} {target} {role}
```

### Arguments

| Argument | Description |
|----------|-------------|
| `delegator` | ID of user performing the assignment |
| `target` | ID of user receiving the role |
| `role` | Role name or ID |

### Examples

```bash
# Assign 'editor' role
php artisan delegation:assign 1 5 editor

# Using role ID
php artisan delegation:assign 1 5 --by-id 3
```

### Options

| Option | Description |
|--------|-------------|
| `--by-id` | Treat role argument as ID instead of name |
| `--force` | Bypass authorization checks (requires confirmation) |

### Output

```
Assigning role 'editor' to user #5...

✓ Role assigned successfully.

Delegator: Admin (ID: 1)
Target: John Doe (ID: 5)
Role: editor (ID: 3)
```

### Error Handling

```bash
$ php artisan delegation:assign 2 5 admin

✗ Authorization failed.

Reason: Role 'admin' is not in delegator's assignable roles.

The delegator (ID: 2) can only assign: editor, moderator
```

## delegation:cache-reset

Clear delegation cache for one or all users.

```bash
php artisan delegation:cache-reset {user?}
```

### Arguments

| Argument | Description |
|----------|-------------|
| `user` | (Optional) User ID to clear cache for |

### Examples

```bash
# Clear cache for specific user
php artisan delegation:cache-reset 5

# Clear all delegation caches
php artisan delegation:cache-reset
```

### Output

```bash
$ php artisan delegation:cache-reset 5
✓ Cache cleared for user #5.

$ php artisan delegation:cache-reset
Clearing delegation cache for all users...
✓ Cleared cache for 47 users.
```

### Options

```bash
# Skip confirmation for clearing all
php artisan delegation:cache-reset --force
```

## delegation:health

Run health checks to verify package configuration.

```bash
php artisan delegation:health
```

### Example Output

```
Permission Delegation Health Check
==================================

Configuration
✓ Config file published
✓ User model configured
✓ Role model configured
✓ Permission model configured

Database
✓ Users table has delegation columns
✓ user_assignable_roles table exists
✓ user_assignable_permissions table exists
✓ delegation_audit_logs table exists

Models
✓ User model implements DelegatableUserInterface
✓ User model uses HasDelegation trait

Services
✓ DelegationService bound to container
✓ RoleRepository bound to container
✓ PermissionRepository bound to container
✓ AuditDriver configured correctly

Features
✓ Blade directives registered
✓ Route macros registered
✓ Middleware aliases registered

Root Admin
✓ Root admin role 'root-admin' exists
! Warning: No users have the root admin role

Cache
✓ Cache driver configured
✓ Cache is working

Overall: 17 passed, 1 warning, 0 failed
```

### Options

```bash
# Output as JSON (for CI/CD)
php artisan delegation:health --json

# Check specific component
php artisan delegation:health --only=database
php artisan delegation:health --only=config
php artisan delegation:health --only=models
```

### Exit Codes

| Code | Meaning |
|------|---------|
| 0 | All checks passed |
| 1 | Warnings present |
| 2 | Failures detected |

### CI/CD Integration

```yaml
# GitHub Actions example
- name: Check Delegation Health
  run: php artisan delegation:health --json
  continue-on-error: false
```

## Scheduling Commands

You might want to schedule some commands:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Clear expired cache entries nightly
    $schedule->command('delegation:cache-reset')
        ->daily()
        ->at('03:00');

    // Health check (log results)
    $schedule->command('delegation:health --json')
        ->hourly()
        ->appendOutputTo(storage_path('logs/delegation-health.log'));
}
```

## Creating Custom Commands

Extend the package with custom commands:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ordain\Delegation\Facades\Delegation;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

class SetupManagerCommand extends Command
{
    protected $signature = 'delegation:setup-manager
        {user : User ID to configure}
        {--max-users=10 : Maximum users they can create}
        {--roles=* : Role IDs they can assign}';

    protected $description = 'Configure a user as a manager with delegation rights';

    public function handle(): int
    {
        $userId = $this->argument('user');
        $user = User::findOrFail($userId);

        $scope = DelegationScope::builder()
            ->allowUserManagement()
            ->maxUsers((int) $this->option('max-users'))
            ->withRoles($this->option('roles'))
            ->build();

        Delegation::setDelegationScope($user, $scope);

        $this->info("✓ User #{$userId} configured as manager.");
        $this->table(
            ['Setting', 'Value'],
            [
                ['Can Manage Users', 'Yes'],
                ['Max Users', $this->option('max-users')],
                ['Assignable Roles', implode(', ', $this->option('roles'))],
            ]
        );

        return self::SUCCESS;
    }
}
```

## Next Steps

- [Customization](customization.md) - Extend the package
- [API Reference](api-reference.md) - Complete method reference
- [Testing](testing.md) - Test your implementation