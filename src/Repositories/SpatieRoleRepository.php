<?php

declare(strict_types=1);

namespace Ordain\Delegation\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Ordain\Delegation\Adapters\SpatieRoleAdapter;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Spatie\Permission\Contracts\Role as SpatieRoleContract;
use Spatie\Permission\Models\Role;

/**
 * Spatie Permission package implementation of the role repository.
 *
 * Uses the adapter pattern to wrap Spatie Role models with our RoleInterface.
 */
final readonly class SpatieRoleRepository implements RoleRepositoryInterface
{
    public function __construct(
        private string $roleModelClass = Role::class,
    ) {}

    public function findById(int|string $id): ?RoleInterface
    {
        /** @var SpatieRoleContract|null $role */
        $role = $this->roleModelClass::find($id);

        if ($role === null) {
            return null;
        }

        return SpatieRoleAdapter::fromModel($role);
    }

    /**
     * @param  array<int|string>  $ids
     * @return Collection<int, RoleInterface>
     */
    public function findByIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return SpatieRoleAdapter::collection(
            $this->roleModelClass::whereIn('id', $ids)->get(),
        );
    }

    public function findByName(string $name, ?string $guard = null): ?RoleInterface
    {
        $query = $this->roleModelClass::where('name', $name);

        if ($guard !== null) {
            $query->where('guard_name', $guard);
        }

        /** @var SpatieRoleContract|null $role */
        $role = $query->first();

        if ($role === null) {
            return null;
        }

        return SpatieRoleAdapter::fromModel($role);
    }

    /**
     * @return Collection<int, RoleInterface>
     */
    public function all(?string $guard = null, ?int $limit = 500): Collection
    {
        $query = $this->roleModelClass::query();

        if ($guard !== null) {
            $query->where('guard_name', $guard);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return SpatieRoleAdapter::collection($query->get());
    }

    /**
     * @return LazyCollection<int, RoleInterface>
     */
    public function allLazy(?string $guard = null): LazyCollection
    {
        $query = $this->roleModelClass::query();

        if ($guard !== null) {
            $query->where('guard_name', $guard);
        }

        return $query->lazy()->map(
            fn (SpatieRoleContract $role): RoleInterface => SpatieRoleAdapter::fromModel($role),
        );
    }

    /**
     * @return Collection<int, RoleInterface>
     */
    public function getUserRoles(DelegatableUserInterface $user): Collection
    {
        /** @var Collection<int, SpatieRoleContract> $roles */
        $roles = $user->roles()->get();

        return SpatieRoleAdapter::collection($roles);
    }

    public function assignToUser(DelegatableUserInterface $user, RoleInterface $role): void
    {
        $spatieRole = $this->resolveSpatieRole($role);

        /** @phpstan-ignore-next-line */
        $user->assignRole($spatieRole);
    }

    public function removeFromUser(DelegatableUserInterface $user, RoleInterface $role): void
    {
        $spatieRole = $this->resolveSpatieRole($role);

        /** @phpstan-ignore-next-line */
        $user->removeRole($spatieRole);
    }

    public function userHasRole(DelegatableUserInterface $user, RoleInterface $role): bool
    {
        /** @phpstan-ignore-next-line */
        return $user->hasRole($role->getRoleName());
    }

    public function userHasRoleByName(DelegatableUserInterface $user, string $roleName, ?string $guard = null): bool
    {
        /** @phpstan-ignore-next-line */
        $query = $user->roles()->where('name', $roleName);

        if ($guard !== null) {
            /** @phpstan-ignore-next-line */
            $query->where('guard_name', $guard);
        }

        return $query->exists();
    }

    /**
     * @param  array<string>  $names
     * @return Collection<int, RoleInterface>
     */
    public function findByNames(array $names, ?string $guard = null): Collection
    {
        if ($names === []) {
            return collect();
        }

        $query = $this->roleModelClass::whereIn('name', $names);

        if ($guard !== null) {
            $query->where('guard_name', $guard);
        }

        return SpatieRoleAdapter::collection($query->get());
    }

    /**
     * @param  array<int|string>  $roleIds
     */
    public function syncUserRoles(DelegatableUserInterface $user, array $roleIds): void
    {
        $roles = $this->roleModelClass::whereIn('id', $roleIds)->get();

        /** @phpstan-ignore-next-line */
        $user->syncRoles($roles);
    }

    /**
     * Resolve the underlying Spatie Role model from a RoleInterface.
     */
    private function resolveSpatieRole(RoleInterface $role): SpatieRoleContract
    {
        if ($role instanceof SpatieRoleAdapter) {
            return $role->getModel();
        }

        /** @var SpatieRoleContract */
        return $this->roleModelClass::findOrFail($role->getRoleIdentifier());
    }
}
