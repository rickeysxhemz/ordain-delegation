<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing a user's delegation scope.
 *
 * Immutable object that encapsulates what a user can delegate.
 */
final readonly class DelegationScope
{
    /** @var array<int|string> Role IDs this user can assign */
    public array $assignableRoleIds;

    /** @var array<int|string> Permission IDs this user can grant */
    public array $assignablePermissionIds;

    /**
     * @param  bool  $canManageUsers  Whether the user can create/manage other users
     * @param  int|null  $maxManageableUsers  Maximum users this user can create (null = unlimited)
     * @param  array<int|string>  $assignableRoleIds  Role IDs this user can assign
     * @param  array<int|string>  $assignablePermissionIds  Permission IDs this user can grant
     */
    public function __construct(
        public bool $canManageUsers = false,
        public ?int $maxManageableUsers = null,
        array $assignableRoleIds = [],
        array $assignablePermissionIds = [],
    ) {
        if ($maxManageableUsers !== null && $maxManageableUsers < 0) {
            throw new InvalidArgumentException('Max manageable users cannot be negative.');
        }

        // Validate and normalize role IDs (filter invalid, remove duplicates)
        $this->assignableRoleIds = self::normalizeIds($assignableRoleIds, 'role');

        // Validate and normalize permission IDs (filter invalid, remove duplicates)
        $this->assignablePermissionIds = self::normalizeIds($assignablePermissionIds, 'permission');
    }

    /**
     * Create a scope with no delegation abilities.
     */
    public static function none(): self
    {
        return new self(
            canManageUsers: false,
            maxManageableUsers: null,
            assignableRoleIds: [],
            assignablePermissionIds: [],
        );
    }

    /**
     * Create a scope with unlimited delegation abilities.
     *
     * @param  array<int|string>  $roleIds
     * @param  array<int|string>  $permissionIds
     */
    public static function unlimited(array $roleIds = [], array $permissionIds = []): self
    {
        return new self(
            canManageUsers: true,
            maxManageableUsers: null,
            assignableRoleIds: $roleIds,
            assignablePermissionIds: $permissionIds,
        );
    }

    /**
     * Create a scope with limited user management.
     *
     * @param  array<int|string>  $roleIds
     * @param  array<int|string>  $permissionIds
     */
    public static function limited(
        int $maxUsers,
        array $roleIds = [],
        array $permissionIds = [],
    ): self {
        return new self(
            canManageUsers: true,
            maxManageableUsers: $maxUsers,
            assignableRoleIds: $roleIds,
            assignablePermissionIds: $permissionIds,
        );
    }

    /**
     * Create from array (from storage/serialization).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $roleIds = array_filter(
            $data['assignable_role_ids'] ?? [],
            static fn (mixed $id): bool => is_int($id) || is_string($id),
        );

        $permissionIds = array_filter(
            $data['assignable_permission_ids'] ?? [],
            static fn (mixed $id): bool => is_int($id) || is_string($id),
        );

        return new self(
            canManageUsers: (bool) ($data['can_manage_users'] ?? false),
            maxManageableUsers: isset($data['max_manageable_users']) ? (int) $data['max_manageable_users'] : null,
            assignableRoleIds: array_values($roleIds),
            assignablePermissionIds: array_values($permissionIds),
        );
    }

    /**
     * Normalize an array of IDs by filtering invalid values and removing duplicates.
     *
     * @param  array<mixed>  $ids
     * @return array<int|string>
     */
    private static function normalizeIds(array $ids, string $type): array
    {
        $normalized = [];
        $seen = [];

        foreach ($ids as $id) {
            // Skip non-integer/string values
            if (! is_int($id) && ! is_string($id)) {
                throw new InvalidArgumentException("Assignable {$type} IDs must be integers or strings.");
            }

            // Skip empty strings
            if ($id === '') {
                continue;
            }

            // Skip duplicates
            $key = is_int($id) ? "i:{$id}" : "s:{$id}";
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $id;
        }

        return $normalized;
    }

    /**
     * Check if scope allows managing users.
     */
    public function allowsUserManagement(): bool
    {
        return $this->canManageUsers;
    }

    /**
     * Check if scope has unlimited user creation.
     */
    public function hasUnlimitedUsers(): bool
    {
        return $this->canManageUsers && $this->maxManageableUsers === null;
    }

    /**
     * Check if a role ID is in the assignable list.
     */
    public function canAssignRoleId(int|string $roleId): bool
    {
        return in_array($roleId, $this->assignableRoleIds, true);
    }

    /**
     * Check if a permission ID is in the assignable list.
     */
    public function canAssignPermissionId(int|string $permissionId): bool
    {
        return in_array($permissionId, $this->assignablePermissionIds, true);
    }

    /**
     * Create a new scope with updated user management setting.
     */
    public function withUserManagement(bool $canManage): self
    {
        return new self(
            canManageUsers: $canManage,
            maxManageableUsers: $this->maxManageableUsers,
            assignableRoleIds: $this->assignableRoleIds,
            assignablePermissionIds: $this->assignablePermissionIds,
        );
    }

    /**
     * Create a new scope with updated max users.
     */
    public function withMaxUsers(?int $max): self
    {
        return new self(
            canManageUsers: $this->canManageUsers,
            maxManageableUsers: $max,
            assignableRoleIds: $this->assignableRoleIds,
            assignablePermissionIds: $this->assignablePermissionIds,
        );
    }

    /**
     * Create a new scope with updated assignable roles.
     *
     * @param  array<int|string>  $roleIds
     */
    public function withAssignableRoles(array $roleIds): self
    {
        return new self(
            canManageUsers: $this->canManageUsers,
            maxManageableUsers: $this->maxManageableUsers,
            assignableRoleIds: $roleIds,
            assignablePermissionIds: $this->assignablePermissionIds,
        );
    }

    /**
     * Create a new scope with updated assignable permissions.
     *
     * @param  array<int|string>  $permissionIds
     */
    public function withAssignablePermissions(array $permissionIds): self
    {
        return new self(
            canManageUsers: $this->canManageUsers,
            maxManageableUsers: $this->maxManageableUsers,
            assignableRoleIds: $this->assignableRoleIds,
            assignablePermissionIds: $permissionIds,
        );
    }

    /**
     * Convert to array for storage/serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'can_manage_users' => $this->canManageUsers,
            'max_manageable_users' => $this->maxManageableUsers,
            'assignable_role_ids' => $this->assignableRoleIds,
            'assignable_permission_ids' => $this->assignablePermissionIds,
        ];
    }

    /**
     * Check equality with another scope.
     */
    public function equals(self $other): bool
    {
        return $this->canManageUsers === $other->canManageUsers
            && $this->maxManageableUsers === $other->maxManageableUsers
            && $this->assignableRoleIds === $other->assignableRoleIds
            && $this->assignablePermissionIds === $other->assignablePermissionIds;
    }
}
