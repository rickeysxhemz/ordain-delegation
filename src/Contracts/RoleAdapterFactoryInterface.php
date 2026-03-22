<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

use Illuminate\Support\Collection;

/**
 * Factory for creating role adapters from underlying models.
 */
interface RoleAdapterFactoryInterface
{
    /**
     * Create a role adapter from an underlying model.
     */
    public function fromModel(mixed $model): RoleInterface;

    /**
     * Create a collection of role adapters from a collection of models.
     *
     * @return Collection<int, RoleInterface>
     */
    public function collection(Collection $models): Collection;
}
