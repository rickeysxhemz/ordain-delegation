<?php

declare(strict_types=1);

namespace Ordain\Delegation\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Trait to add delegation capabilities to a User model.
 *
 * Add this trait to your User model and implement DelegatableUserInterface.
 *
 * IMPORTANT: You must manually configure your User model's $fillable or $guarded
 * to include/exclude the delegation fields. This trait does NOT auto-add them
 * to prevent mass assignment vulnerabilities.
 *
 * Required fields to consider for $fillable (if using whitelist approach):
 * - 'can_manage_users' (boolean)
 * - 'max_manageable_users' (integer, nullable)
 * - 'created_by_user_id' (foreign key, nullable)
 *
 * SECURITY WARNING: The 'can_manage_users' field controls privilege escalation.
 * Only add it to $fillable if you have proper validation in place.
 * Consider using $guarded or explicit assignment instead.
 *
 * @mixin Model
 */
trait HasDelegation
{
    /**
     * Delegation field casts.
     *
     * @var array<string, string>
     */
    private static array $delegationCasts = [
        'can_manage_users' => 'boolean',
        'max_manageable_users' => 'integer',
    ];

    /**
     * Boot the trait.
     */
    public static function bootHasDelegation(): void
    {
        // Validate that sensitive fields are properly guarded
        static::creating(static function (Model $model): void {
            // Prevent self-referential creator (circular reference)
            if ($model->created_by_user_id !== null
                && $model->exists
                && $model->created_by_user_id === $model->getKey()) {
                throw new InvalidArgumentException('A user cannot be their own creator.');
            }
        });

        static::updating(static function (Model $model): void {
            // Prevent circular creator reference on update
            if ($model->isDirty('created_by_user_id')
                && $model->created_by_user_id === $model->getKey()) {
                throw new InvalidArgumentException('A user cannot be their own creator.');
            }
        });
    }

    /**
     * Initialize the trait.
     *
     * Note: This method intentionally does NOT modify $fillable to prevent
     * mass assignment vulnerabilities. Configure your model's $fillable
     * or $guarded manually with appropriate validation.
     */
    public function initializeHasDelegation(): void
    {
        // Merge casts efficiently using static array to avoid per-instance allocation
        if (! isset($this->casts['can_manage_users'])) {
            $this->mergeCasts(self::$delegationCasts);
        }
    }

    /**
     * Get the user's unique identifier for delegation.
     */
    public function getDelegatableIdentifier(): int|string
    {
        return $this->getKey();
    }

    /**
     * Check if user can manage other users.
     */
    public function canManageUsers(): bool
    {
        return (bool) ($this->can_manage_users ?? false);
    }

    /**
     * Get maximum number of users this user can create.
     */
    public function getMaxManageableUsers(): ?int
    {
        $max = $this->max_manageable_users;

        return $max !== null ? (int) $max : null;
    }

    /**
     * Get the user who created this user (relationship).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(static::class, 'created_by_user_id');
    }

    /**
     * Get the creator user instance.
     *
     * This method provides a type-safe way to access the creator
     * without relying on dynamic property access.
     */
    public function getCreator(): ?DelegatableUserInterface
    {
        /** @var DelegatableUserInterface|null $creator */
        $creator = $this->creator;

        return $creator;
    }

    /**
     * Get users created by this user.
     */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(static::class, 'created_by_user_id');
    }

    /**
     * Get roles this user can assign to others.
     */
    public function assignableRoles(): BelongsToMany
    {
        /** @var string $roleModel */
        $roleModel = $this->getDelegationConfig('role_model');
        /** @var string $pivotTable */
        $pivotTable = $this->getDelegationConfig('user_assignable_roles');

        return $this->belongsToMany($roleModel, $pivotTable, 'user_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Get permissions this user can grant to others.
     */
    public function assignablePermissions(): BelongsToMany
    {
        /** @var string $permissionModel */
        $permissionModel = $this->getDelegationConfig('permission_model');
        /** @var string $pivotTable */
        $pivotTable = $this->getDelegationConfig('user_assignable_permissions');

        return $this->belongsToMany($permissionModel, $pivotTable, 'user_id', 'permission_id')
            ->withTimestamps();
    }

    /**
     * Get the count of users created by this user (cached per request).
     */
    public function getCreatedUsersCount(): int
    {
        // Use relationship count if already loaded, otherwise query
        if ($this->relationLoaded('createdUsers')) {
            return $this->createdUsers->count();
        }

        return $this->createdUsers()->count();
    }

    /**
     * Check if user has reached their user creation limit.
     */
    public function hasReachedUserLimit(): bool
    {
        $max = $this->getMaxManageableUsers();

        if ($max === null) {
            return false;
        }

        return $this->getCreatedUsersCount() >= $max;
    }

    /**
     * Get remaining user creation quota.
     */
    public function getRemainingUserQuota(): ?int
    {
        $max = $this->getMaxManageableUsers();

        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->getCreatedUsersCount());
    }

    /**
     * Check if this user can assign a specific role.
     */
    public function canAssignRole(int|string|RoleInterface $role): bool
    {
        $roleId = $role instanceof RoleInterface ? $role->getRoleIdentifier() : $role;

        return $this->assignableRoles()->where('id', $roleId)->exists();
    }

    /**
     * Check if this user can grant a specific permission.
     */
    public function canAssignPermission(int|string|PermissionInterface $permission): bool
    {
        $permissionId = $permission instanceof PermissionInterface ? $permission->getPermissionIdentifier() : $permission;

        return $this->assignablePermissions()->where('id', $permissionId)->exists();
    }

    /**
     * Sync assignable roles for this user.
     *
     * @param  array<int|string>  $roleIds
     */
    public function syncAssignableRoles(array $roleIds): void
    {
        $this->assignableRoles()->sync($roleIds);
    }

    /**
     * Sync assignable permissions for this user.
     *
     * @param  array<int|string>  $permissionIds
     */
    public function syncAssignablePermissions(array $permissionIds): void
    {
        $this->assignablePermissions()->sync($permissionIds);
    }

    /**
     * Set the creator of this user.
     */
    public function setCreator(DelegatableUserInterface $creator): void
    {
        $this->created_by_user_id = $creator->getDelegatableIdentifier();
        $this->save();
    }

    /**
     * Enable user management for this user.
     */
    public function enableUserManagement(?int $maxUsers = null): void
    {
        $this->update([
            'can_manage_users' => true,
            'max_manageable_users' => $maxUsers,
        ]);
    }

    /**
     * Disable user management for this user.
     */
    public function disableUserManagement(): void
    {
        $this->update([
            'can_manage_users' => false,
            'max_manageable_users' => null,
        ]);

        // Optionally clear assignable roles/permissions
        $this->assignableRoles()->detach();
        $this->assignablePermissions()->detach();
    }

    /**
     * Scope to get users who can manage other users.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCanManageUsers(Builder $query): Builder
    {
        return $query->where('can_manage_users', true);
    }

    /**
     * Scope to get users created by a specific user.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCreatedBy(Builder $query, int|string|DelegatableUserInterface $creator): Builder
    {
        $creatorId = $creator instanceof DelegatableUserInterface
            ? $creator->getDelegatableIdentifier()
            : $creator;

        return $query->where('created_by_user_id', $creatorId);
    }

    /**
     * Get delegation config value.
     */
    private function getDelegationConfig(string $key): mixed
    {
        return match ($key) {
            'role_model' => config('permission-delegation.role_model', 'Spatie\\Permission\\Models\\Role'),
            'permission_model' => config('permission-delegation.permission_model', 'Spatie\\Permission\\Models\\Permission'),
            'user_assignable_roles' => config('permission-delegation.tables.user_assignable_roles', 'user_assignable_roles'),
            'user_assignable_permissions' => config('permission-delegation.tables.user_assignable_permissions', 'user_assignable_permissions'),
            default => null,
        };
    }
}
