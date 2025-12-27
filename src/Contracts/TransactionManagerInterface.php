<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Abstraction for database transaction operations.
 */
interface TransactionManagerInterface
{
    /**
     * Execute callback within a database transaction.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function transaction(callable $callback): mixed;

    /**
     * Acquire pessimistic lock on user record for quota operations.
     */
    public function lockUserForUpdate(DelegatableUserInterface $user): void;
}
