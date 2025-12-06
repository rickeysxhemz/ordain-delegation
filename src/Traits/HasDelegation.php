<?php

declare(strict_types=1);

namespace Ordain\Delegation\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Trait to add delegation capabilities to a User model.
 *
 * Add this trait to your User model and implement DelegatableUserInterface.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasDelegation
{
    /**
     * Boot the trait.
     */
    public static function bootHasDelegation(): void
    {
        // Can add model event listeners here if needed
    }

    /**
     * Initialize the trait.
     */
    public function initializeHasDelegation(): void
    {
        // Add default values to fillable if not already present
        $fillableFields = [
            'can_manage_users',
            'max_manageable_users',
            'created_by_user_id',
        ];

        foreach ($fillableFields as $field) {
            if (! in_array($field, $this->fillable, true)) {
                $this->fillable[] = $field;
            }
        }

        // Add casts
        $this->casts = array_merge($this->casts ?? [], [
            'can_manage_users' => 'boolean',
            'max_manageable_users' => 'integer',
        ]);
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
     * Get the user who created this user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(static::class, 'created_by_user_id');
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
        $roleModel = config('permission-delegation.role_model', 'Spatie\\Permission\\Models\\Role');
        $pivotTable = config('permission-delegation.tables.user_assignable_roles', 'user_assignable_roles');

        return $this->belongsToMany($roleModel, $pivotTable, 'user_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Get permissions this user can grant to others.
     */
    public function assignablePermissions(): BelongsToMany
    {
        $permissionModel = config('permission-delegation.permission_model', 'Spatie\\Permission\\Models\\Permission');
        $pivotTable = config('permission-delegation.tables.user_assignable_permissions', 'user_assignable_permissions');

        return $this->belongsToMany($permissionModel, $pivotTable, 'user_id', 'permission_id')
            ->withTimestamps();
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

        return $this->createdUsers()->count() >= $max;
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

        return max(0, $max - $this->createdUsers()->count());
    }

    /**
     * Check if this user can assign a specific role.
     *
     * @param  int|string|RoleInterface  $role
     */
    public function canAssignRole(mixed $role): bool
    {
        $roleId = is_object($role) ? $role->getRoleIdentifier() : $role;

        return $this->assignableRoles()->where('id', $roleId)->exists();
    }

    /**
     * Check if this user can grant a specific permission.
     *
     * @param  int|string|PermissionInterface  $permission
     */
    public function canAssignPermission(mixed $permission): bool
    {
        $permissionId = is_object($permission) ? $permission->getPermissionIdentifier() : $permission;

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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCanManageUsers($query)
    {
        return $query->where('can_manage_users', true);
    }

    /**
     * Scope to get users created by a specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int|string|DelegatableUserInterface  $creator
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCreatedBy($query, $creator)
    {
        $creatorId = $creator instanceof DelegatableUserInterface
            ? $creator->getDelegatableIdentifier()
            : $creator;

        return $query->where('created_by_user_id', $creatorId);
    }
}
