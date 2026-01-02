<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Builders;

use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

/**
 * Fluent builder for constructing DelegationScope objects.
 */
final class DelegationScopeBuilder
{
    private bool $canManageUsers = false;

    private ?int $maxManageableUsers = null;

    /** @var array<int|string> */
    private array $assignableRoleIds = [];

    /** @var array<int|string> */
    private array $assignablePermissionIds = [];

    public static function create(): self
    {
        return new self;
    }

    /**
     * Start from an existing scope.
     */
    public static function from(DelegationScope $scope): self
    {
        return (new self)
            ->userManagement($scope->canManageUsers)
            ->maxUsers($scope->maxManageableUsers)
            ->withRoles($scope->assignableRoleIds)
            ->withPermissions($scope->assignablePermissionIds);
    }

    /**
     * Enable or disable user management.
     */
    public function userManagement(bool $enabled = true): self
    {
        $this->canManageUsers = $enabled;

        return $this;
    }

    /**
     * Alias for userManagement(true).
     */
    public function allowUserManagement(): self
    {
        return $this->userManagement(true);
    }

    /**
     * Alias for userManagement(false).
     */
    public function denyUserManagement(): self
    {
        return $this->userManagement(false);
    }

    /**
     * Set maximum manageable users.
     */
    public function maxUsers(?int $max): self
    {
        $this->maxManageableUsers = $max;

        return $this;
    }

    /**
     * Set unlimited user management.
     */
    public function unlimited(): self
    {
        $this->canManageUsers = true;
        $this->maxManageableUsers = null;

        return $this;
    }

    /**
     * Set limited user management with a maximum count.
     */
    public function limited(int $maxUsers): self
    {
        $this->canManageUsers = true;
        $this->maxManageableUsers = $maxUsers;

        return $this;
    }

    /**
     * Set assignable role IDs.
     *
     * @param  array<int|string>  $roleIds
     */
    public function withRoles(array $roleIds): self
    {
        $this->assignableRoleIds = $roleIds;

        return $this;
    }

    /**
     * Add role IDs to the assignable list.
     *
     * @param  array<int|string>|int|string  $roleIds
     */
    public function addRoles(array|int|string $roleIds): self
    {
        $roleIds = is_array($roleIds) ? $roleIds : [$roleIds];
        $this->assignableRoleIds = array_merge($this->assignableRoleIds, $roleIds);

        return $this;
    }

    /**
     * Add a single role ID.
     */
    public function addRole(int|string $roleId): self
    {
        $this->assignableRoleIds[] = $roleId;

        return $this;
    }

    /**
     * Set assignable permission IDs.
     *
     * @param  array<int|string>  $permissionIds
     */
    public function withPermissions(array $permissionIds): self
    {
        $this->assignablePermissionIds = $permissionIds;

        return $this;
    }

    /**
     * Add permission IDs to the assignable list.
     *
     * @param  array<int|string>|int|string  $permissionIds
     */
    public function addPermissions(array|int|string $permissionIds): self
    {
        $permissionIds = is_array($permissionIds) ? $permissionIds : [$permissionIds];
        $this->assignablePermissionIds = array_merge($this->assignablePermissionIds, $permissionIds);

        return $this;
    }

    /**
     * Add a single permission ID.
     */
    public function addPermission(int|string $permissionId): self
    {
        $this->assignablePermissionIds[] = $permissionId;

        return $this;
    }

    /**
     * Build the DelegationScope object.
     */
    public function build(): DelegationScope
    {
        return new DelegationScope(
            canManageUsers: $this->canManageUsers,
            maxManageableUsers: $this->maxManageableUsers,
            assignableRoleIds: $this->assignableRoleIds,
            assignablePermissionIds: $this->assignablePermissionIds,
        );
    }
}
