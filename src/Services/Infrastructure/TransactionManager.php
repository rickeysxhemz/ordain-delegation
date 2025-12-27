<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\TransactionManagerInterface;

/**
 * Laravel database transaction manager.
 */
final readonly class TransactionManager implements TransactionManagerInterface
{
    private string $userTable;

    public function __construct(?string $userModelClass = null)
    {
        $modelClass = $userModelClass ?? 'App\\Models\\User';

        if (class_exists($modelClass)) {
            /** @var Model $model */
            $model = new $modelClass;
            $this->userTable = $model->getTable();
        } else {
            $this->userTable = 'users';
        }
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction(fn () => $callback());
    }

    public function lockUserForUpdate(DelegatableUserInterface $user): void
    {
        DB::table($this->userTable)
            ->where('id', $user->getDelegatableIdentifier())
            ->lockForUpdate()
            ->first();
    }
}
