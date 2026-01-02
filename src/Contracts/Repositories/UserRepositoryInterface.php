<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts\Repositories;

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\DelegatableUserInterface;

/**
 * Repository interface for user operations.
 *
 * Provides abstraction for user lookup operations used by delegation commands.
 */
interface UserRepositoryInterface
{
    /**
     * Find a user by their identifier.
     */
    public function findById(int|string $id): ?DelegatableUserInterface;

    /**
     * Get all user IDs.
     *
     * @return Collection<int, int|string>
     */
    public function getAllIds(): Collection;

    /**
     * Get the total count of users.
     */
    public function count(): int;
}
