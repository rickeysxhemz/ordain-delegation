<?php

declare(strict_types=1);

namespace Ordain\Delegation\Adapters;

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\PermissionAdapterFactoryInterface;
use Ordain\Delegation\Contracts\PermissionInterface;

/**
 * Creates permission adapters from Spatie Permission models.
 */
final readonly class SpatiePermissionAdapterFactory implements PermissionAdapterFactoryInterface
{
    public function fromModel(mixed $model): PermissionInterface
    {
        return SpatiePermissionAdapter::fromModel($model);
    }

    /**
     * @param  Collection<int, mixed>  $models
     * @return Collection<int, PermissionInterface>
     */
    public function collection(Collection $models): Collection
    {
        /** @var Collection<int, PermissionInterface> */
        return SpatiePermissionAdapter::collection($models);
    }
}
