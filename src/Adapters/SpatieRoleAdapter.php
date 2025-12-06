<?php

declare(strict_types=1);

namespace Ordain\Delegation\Adapters;

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\RoleInterface;
use Spatie\Permission\Contracts\Role as SpatieRoleContract;

/**
 * Adapter to make Spatie Role models compatible with RoleInterface.
 *
 * This adapter wraps Spatie's Role model to implement our package's
 * RoleInterface, enabling loose coupling between the packages.
 */
final readonly class SpatieRoleAdapter implements RoleInterface
{
    public function __construct(
        private SpatieRoleContract $role,
    ) {}

    /**
     * Create adapter from a Spatie Role model.
     */
    public static function fromModel(SpatieRoleContract $role): self
    {
        return new self($role);
    }

    /**
     * Create a collection of adapters from Spatie Role models.
     *
     * @param  Collection<int, SpatieRoleContract>  $roles
     * @return Collection<int, self>
     */
    public static function collection(Collection $roles): Collection
    {
        return $roles->map(fn (SpatieRoleContract $role): self => new self($role));
    }

    public function getRoleIdentifier(): int|string
    {
        /** @var int|string */
        return $this->role->getKey();
    }

    public function getRoleName(): string
    {
        /** @var string */
        return $this->role->name;
    }

    public function getRoleGuard(): string
    {
        /** @var string */
        return $this->role->guard_name;
    }

    /**
     * Get the underlying Spatie Role model.
     */
    public function getModel(): SpatieRoleContract
    {
        return $this->role;
    }

    /**
     * Check if two role adapters represent the same role.
     */
    public function equals(RoleInterface $other): bool
    {
        return $this->getRoleIdentifier() === $other->getRoleIdentifier()
            && $this->getRoleGuard() === $other->getRoleGuard();
    }
}
