<?php

declare(strict_types=1);

namespace Ordain\Delegation\Repositories;

use Illuminate\Support\Collection;
use Ordain\Delegation\Adapters\SpatiePermissionAdapter;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\PermissionRepositoryInterface;
use Spatie\Permission\Contracts\Permission as SpatiePermissionContract;
use Spatie\Permission\Models\Permission;

/**
 * Spatie Permission package implementation of the permission repository.
 *
 * Uses the adapter pattern to wrap Spatie Permission models with our PermissionInterface.
 */
final readonly class SpatiePermissionRepository implements PermissionRepositoryInterface
{
    public function __construct(
        private string $permissionModelClass = Permission::class,
    ) {}

    public function findById(int|string $id): ?PermissionInterface
    {
        /** @var SpatiePermissionContract|null $permission */
        $permission = $this->permissionModelClass::find($id);

        if ($permission === null) {
            return null;
        }

        return SpatiePermissionAdapter::fromModel($permission);
    }

    /**
     * @param  array<int|string>  $ids
     * @return Collection<int, PermissionInterface>
     */
    public function findByIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return SpatiePermissionAdapter::collection(
            $this->permissionModelClass::whereIn('id', $ids)->get(),
        );
    }

    public function findByName(string $name, ?string $guard = null): ?PermissionInterface
    {
        $query = $this->permissionModelClass::where('name', $name);

        if ($guard !== null) {
            $query->where('guard_name', $guard);
        }

        /** @var SpatiePermissionContract|null $permission */
        $permission = $query->first();

        if ($permission === null) {
            return null;
        }

        return SpatiePermissionAdapter::fromModel($permission);
    }

    /**
     * @return Collection<int, PermissionInterface>
     */
    public function all(?string $guard = null): Collection
    {
        $query = $this->permissionModelClass::query();

        if ($guard !== null) {
            $query->where('guard_name', $guard);
        }

        return SpatiePermissionAdapter::collection($query->get());
    }

    /**
     * @return Collection<int, PermissionInterface>
     */
    public function getUserPermissions(DelegatableUserInterface $user): Collection
    {
        /** @var Collection<int, SpatiePermissionContract> $permissions */
        $permissions = $user->permissions()->get();

        return SpatiePermissionAdapter::collection($permissions);
    }

    /**
     * @return Collection<int, PermissionInterface>
     */
    public function getAllUserPermissions(DelegatableUserInterface $user): Collection
    {
        /** @phpstan-ignore-next-line */
        $permissions = $user->getAllPermissions();

        return SpatiePermissionAdapter::collection($permissions);
    }

    public function assignToUser(DelegatableUserInterface $user, PermissionInterface $permission): void
    {
        $spatiePermission = $this->resolveSpatiePermission($permission);

        /** @phpstan-ignore-next-line */
        $user->givePermissionTo($spatiePermission);
    }

    public function removeFromUser(DelegatableUserInterface $user, PermissionInterface $permission): void
    {
        $spatiePermission = $this->resolveSpatiePermission($permission);

        /** @phpstan-ignore-next-line */
        $user->revokePermissionTo($spatiePermission);
    }

    public function userHasPermission(DelegatableUserInterface $user, PermissionInterface $permission): bool
    {
        /** @phpstan-ignore-next-line */
        return $user->hasPermissionTo($permission->getPermissionName());
    }

    /**
     * @param  array<int|string>  $permissionIds
     */
    public function syncUserPermissions(DelegatableUserInterface $user, array $permissionIds): void
    {
        $permissions = $this->permissionModelClass::whereIn('id', $permissionIds)->get();

        /** @phpstan-ignore-next-line */
        $user->syncPermissions($permissions);
    }

    /**
     * Resolve the underlying Spatie Permission model from a PermissionInterface.
     */
    private function resolveSpatiePermission(PermissionInterface $permission): SpatiePermissionContract
    {
        if ($permission instanceof SpatiePermissionAdapter) {
            return $permission->getModel();
        }

        /** @var SpatiePermissionContract */
        return $this->permissionModelClass::findOrFail($permission->getPermissionIdentifier());
    }
}
