<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

use Illuminate\Support\Collection;

/**
 * Factory for creating permission adapters from underlying models.
 */
interface PermissionAdapterFactoryInterface
{
    /**
     * Create a permission adapter from an underlying model.
     */
    public function fromModel(mixed $model): PermissionInterface;

    /**
     * Create a collection of permission adapters from a collection of models.
     *
     * @return Collection<int, PermissionInterface>
     */
    public function collection(Collection $models): Collection;
}
