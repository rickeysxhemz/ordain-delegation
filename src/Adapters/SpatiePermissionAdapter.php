<?php

declare(strict_types=1);

namespace Ordain\Delegation\Adapters;

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\PermissionInterface;
use Spatie\Permission\Contracts\Permission as SpatiePermissionContract;

/**
 * Adapter to make Spatie Permission models compatible with PermissionInterface.
 *
 * This adapter wraps Spatie's Permission model to implement our package's
 * PermissionInterface, enabling loose coupling between the packages.
 */
final readonly class SpatiePermissionAdapter implements PermissionInterface
{
    public function __construct(
        private SpatiePermissionContract $permission,
    ) {}

    public function getPermissionIdentifier(): int|string
    {
        /** @var int|string */
        return $this->permission->getKey();
    }

    public function getPermissionName(): string
    {
        /** @var string */
        return $this->permission->name;
    }

    public function getPermissionGuard(): string
    {
        /** @var string */
        return $this->permission->guard_name;
    }

    /**
     * Get the underlying Spatie Permission model.
     */
    public function getModel(): SpatiePermissionContract
    {
        return $this->permission;
    }

    /**
     * Create adapter from a Spatie Permission model.
     */
    public static function fromModel(SpatiePermissionContract $permission): self
    {
        return new self($permission);
    }

    /**
     * Create a collection of adapters from Spatie Permission models.
     *
     * @param  Collection<int, SpatiePermissionContract>  $permissions
     * @return Collection<int, self>
     */
    public static function collection(Collection $permissions): Collection
    {
        return $permissions->map(fn (SpatiePermissionContract $permission): self => new self($permission));
    }

    /**
     * Check if two permission adapters represent the same permission.
     */
    public function equals(PermissionInterface $other): bool
    {
        return $this->getPermissionIdentifier() === $other->getPermissionIdentifier()
            && $this->getPermissionGuard() === $other->getPermissionGuard();
    }
}
