<?php

declare(strict_types=1);

namespace Ordain\Delegation\Adapters;

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\RoleAdapterFactoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Creates role adapters from Spatie Role models.
 */
final readonly class SpatieRoleAdapterFactory implements RoleAdapterFactoryInterface
{
    public function fromModel(mixed $model): RoleInterface
    {
        return SpatieRoleAdapter::fromModel($model);
    }

    /**
     * @param  Collection<int, mixed>  $models
     * @return Collection<int, RoleInterface>
     */
    public function collection(Collection $models): Collection
    {
        /** @var Collection<int, RoleInterface> */
        return SpatieRoleAdapter::collection($models);
    }
}
