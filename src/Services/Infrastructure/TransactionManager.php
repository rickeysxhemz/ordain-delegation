<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\TransactionManagerInterface;

/**
 * Laravel database transaction manager.
 */
final readonly class TransactionManager implements TransactionManagerInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $userTable = 'users',
    ) {}

    public function transaction(callable $callback): mixed
    {
        return $this->connection->transaction(fn () => $callback());
    }

    public function lockUserForUpdate(DelegatableUserInterface $user): void
    {
        $this->connection->table($this->userTable)
            ->where('id', $user->getDelegatableIdentifier())
            ->lockForUpdate()
            ->first();
    }
}
