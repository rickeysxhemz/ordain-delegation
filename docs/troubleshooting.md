# Troubleshooting

Common issues and their solutions.

## Authorization Issues

### "User not authorized" errors

**Symptoms:** Users get 403 errors or `UnauthorizedDelegationException` when trying to delegate.

**Checklist:**

1. **Check `can_manage_users` flag:**
   ```php
   $user->can_manage_users; // Must be true
   ```

2. **Verify hierarchy relationship:**
   ```php
   // Target must be created by delegator
   $target->created_by_user_id === $delegator->id;
   ```

3. **Check assignable roles:**
   ```php
   $scope = Delegation::getDelegationScope($user);
   in_array($role->id, $scope->assignableRoleIds); // Must be true
   ```

4. **Verify root admin configuration:**
   ```php
   // config/permission-delegation.php
   'root_admin' => [
       'enabled' => true,
       'role' => 'root-admin', // Check this matches your role name
   ],
   ```

**Solution:**

```php
// Debug authorization
$delegator = auth()->user();
$target = User::find($targetId);
$role = Role::findByName('editor');

dump([
    'delegator_can_manage' => $delegator->can_manage_users,
    'target_created_by_delegator' => $target->created_by_user_id === $delegator->id,
    'role_in_scope' => in_array($role->id, Delegation::getDelegationScope($delegator)->assignableRoleIds),
    'is_root_admin' => $delegator->hasRole(config('permission-delegation.root_admin.role')),
]);
```

---

### Root admin not bypassing checks

**Symptoms:** Users with root admin role still get authorization errors.

**Checklist:**

1. **Verify role name matches:**
   ```php
   config('permission-delegation.root_admin.role'); // e.g., 'root-admin'
   $user->hasRole('root-admin'); // Must be true
   ```

2. **Check if bypass is enabled:**
   ```php
   config('permission-delegation.root_admin.enabled'); // Must be true
   ```

3. **Verify role exists:**
   ```php
   Role::findByName('root-admin'); // Must not be null
   ```

**Solution:**

```bash
# Create the root admin role if missing
php artisan tinker
>>> Spatie\Permission\Models\Role::create(['name' => 'root-admin'])

# Assign to user
>>> $user = User::find(1);
>>> $user->assignRole('root-admin');
```

---

## Database Issues

### Migration errors

**Symptoms:** Errors when running migrations.

**Common issues:**

1. **Users table doesn't exist yet:**
   ```
   SQLSTATE[42S02]: Base table or view not found
   ```

   **Solution:** Run package migrations after your users table migration:
   ```bash
   php artisan migrate  # Creates users table first
   php artisan vendor:publish --tag=delegation-migrations
   php artisan migrate  # Then run delegation migrations
   ```

2. **Column already exists:**
   ```
   SQLSTATE[42S21]: Column already exists: 'can_manage_users'
   ```

   **Solution:** Check if you've already added these columns manually. Either:
   - Remove them from your migration, or
   - Skip the package migration for that column

3. **Foreign key constraint fails:**
   ```
   Cannot add foreign key constraint
   ```

   **Solution:** Ensure the `id` column types match:
   ```php
   // If users.id is bigInteger unsigned:
   $table->foreignId('created_by_user_id')->nullable()->constrained('users');
   ```

---

### Audit logs not saving

**Symptoms:** No records in `delegation_audit_logs` table.

**Checklist:**

1. **Check audit is enabled:**
   ```php
   config('permission-delegation.audit.enabled'); // Must be true
   ```

2. **Verify driver:**
   ```php
   config('permission-delegation.audit.driver'); // 'database'
   ```

3. **Check table exists:**
   ```sql
   SHOW TABLES LIKE 'delegation_audit_logs';
   ```

4. **Look for exceptions:**
   ```php
   // Temporarily enable exception logging
   try {
       Delegation::delegateRole($delegator, $target, $role);
   } catch (\Exception $e) {
       logger()->error('Audit failed', ['error' => $e->getMessage()]);
   }
   ```

---

## Cache Issues

### Stale data after scope changes

**Symptoms:** Authorization checks return outdated results.

**Solution:**

```php
// Clear cache after scope changes
Delegation::forgetCache($user);

// Or clear all delegation caches
php artisan delegation:cache-reset
```

**For Redis/Memcached with tags:**
```php
Cache::tags(['delegation'])->flush();
```

---

### Cache not working at all

**Symptoms:** Every request hits the database.

**Checklist:**

1. **Check cache is enabled:**
   ```php
   config('permission-delegation.cache.enabled'); // Must be true
   ```

2. **Verify Laravel cache is configured:**
   ```php
   config('cache.default'); // Should be 'redis', 'memcached', etc.
   ```

3. **Test cache manually:**
   ```php
   Cache::put('test', 'value', 60);
   Cache::get('test'); // Should return 'value'
   ```

---

## Model Issues

### User model not implementing interface

**Error:**
```
Argument must be of type DelegatableUserInterface
```

**Solution:**

```php
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Traits\HasDelegation;

class User extends Authenticatable implements DelegatableUserInterface
{
    use HasDelegation;
}
```

---

### Relationships not loading

**Symptoms:** `$user->assignableRoles` returns empty.

**Checklist:**

1. **Check pivot tables exist:**
   ```sql
   SHOW TABLES LIKE 'user_assignable_roles';
   SHOW TABLES LIKE 'user_assignable_permissions';
   ```

2. **Verify table names in config:**
   ```php
   config('permission-delegation.tables');
   ```

3. **Check data exists:**
   ```sql
   SELECT * FROM user_assignable_roles WHERE user_id = 1;
   ```

---

## Middleware Issues

### Middleware not being applied

**Symptoms:** Routes accessible without authorization checks.

**Checklist:**

1. **Verify middleware alias registration:**
   ```php
   // Check in Kernel.php or bootstrap/app.php
   'can.delegate' => CanDelegateMiddleware::class,
   ```

2. **Check route middleware is applied:**
   ```bash
   php artisan route:list --columns=uri,middleware
   ```

3. **Ensure service provider loaded:**
   ```php
   // config/app.php or bootstrap/providers.php
   Ordain\Delegation\Providers\DelegationServiceProvider::class,
   ```

---

### Middleware returning wrong status code

**Symptoms:** Getting 500 instead of 403.

**Solution:** Check for exceptions in the middleware:

```php
// Custom error handling
Route::middleware('can.delegate')->group(function () {
    // routes
})->missing(function () {
    return response()->json(['error' => 'Not found'], 404);
});
```

---

## Performance Issues

### Slow authorization checks

**Symptoms:** Page loads are slow, many database queries.

**Solutions:**

1. **Enable caching:**
   ```php
   'cache' => [
       'enabled' => true,
       'ttl' => 3600,
   ],
   ```

2. **Use eager loading:**
   ```php
   $users = User::with(['assignableRoles', 'assignablePermissions'])->get();
   ```

3. **Batch role lookups:**
   ```php
   // Instead of checking one by one
   $roles = Role::whereIn('id', $roleIds)->get();
   ```

---

### N+1 query problems

**Symptoms:** Many similar queries in debug bar.

**Solution:**

```php
// Bad: N+1 queries
foreach ($users as $user) {
    $user->assignableRoles; // Query per user
}

// Good: Eager load
$users = User::with('assignableRoles')->get();
foreach ($users as $user) {
    $user->assignableRoles; // No additional queries
}
```

---

## Event Issues

### Events not firing

**Symptoms:** Listeners not being called.

**Checklist:**

1. **Check events are enabled:**
   ```php
   config('permission-delegation.events.enabled'); // Must be true
   ```

2. **Verify listener registration:**
   ```php
   // EventServiceProvider.php
   protected $listen = [
       RoleDelegated::class => [YourListener::class],
   ];
   ```

3. **Check for exceptions:**
   ```php
   Event::listen(RoleDelegated::class, function ($event) {
       logger()->info('Event fired', ['event' => $event]);
   });
   ```

---

## Common Error Messages

### "Role not found"

**Cause:** Role doesn't exist or wrong model configured.

**Solution:**
```php
// Check role exists
Role::findByName('editor'); // Must not be null

// Verify model in config
config('permission-delegation.models.role'); // Must match your Role model
```

---

### "Target user not in hierarchy"

**Cause:** Delegator didn't create the target user.

**Solution:**
```php
// Verify relationship
$target->created_by_user_id === $delegator->id;

// Or make delegator the creator
$target->update(['created_by_user_id' => $delegator->id]);
```

---

### "Quota exceeded"

**Cause:** User has reached their creation limit.

**Solution:**
```php
// Check current quota
$remaining = Delegation::getRemainingQuota($user);

// Increase quota
$scope = Delegation::getDelegationScope($user);
$newScope = $scope->withMaxUsers(20);
Delegation::setDelegationScope($user, $newScope);
```

---

## Debugging Tools

### Health Check

```bash
php artisan delegation:health
```

### Show User Scope

```bash
php artisan delegation:show {user_id}
```

### Clear Cache

```bash
php artisan delegation:cache-reset
```

### Debug Mode

```php
// Add to a controller temporarily
dd([
    'user' => auth()->user(),
    'can_manage_users' => auth()->user()->can_manage_users,
    'scope' => Delegation::getDelegationScope(auth()->user())->toArray(),
    'assignable_roles' => Delegation::getAssignableRoles(auth()->user())->toArray(),
]);
```

---

## Getting Help

If you can't resolve your issue:

1. **Check the [GitHub Issues](https://github.com/rickeysxhemz/ordain-delegation/issues)** for similar problems
2. **Run the health check:** `php artisan delegation:health --json`
3. **Collect debug information:**
   - PHP version
   - Laravel version
   - Package version
   - Error message and stack trace
   - Relevant configuration
4. **Open a new issue** with the collected information