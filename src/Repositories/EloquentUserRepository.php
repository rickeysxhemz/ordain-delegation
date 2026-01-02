<?php

declare(strict_types=1);

namespace Ordain\Delegation\Repositories;

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\Repositories\UserRepositoryInterface;

/**
 * Eloquent implementation of the user repository.
 *
 * Provides user lookup operations for delegation commands.
 */
final readonly class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * @param  class-string  $userModelClass
     */
    public function __construct(
        private string $userModelClass,
    ) {}

    public function findById(int|string $id): ?DelegatableUserInterface
    {
        /** @var DelegatableUserInterface|null $user */
        $user = $this->userModelClass::find($id);

        if ($user instanceof DelegatableUserInterface) {
            return $user;
        }

        return null;
    }

    /**
     * @return Collection<int, int|string>
     */
    public function getAllIds(): Collection
    {
        return $this->userModelClass::query()
            ->select('id')
            ->get()
            ->pluck('id');
    }

    public function count(): int
    {
        return $this->userModelClass::query()->count();
    }
}
