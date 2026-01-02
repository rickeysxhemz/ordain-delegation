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

    /**
     * @param  string|null  $userModelClass  The user model class (for table resolution)
     * @param  string|null  $userTable  Explicit table name (takes precedence over model resolution)
     */
    public function __construct(?string $userModelClass = null, ?string $userTable = null)
    {
        // Use explicit table name if provided (avoids model instantiation)
        if ($userTable !== null) {
            $this->userTable = $userTable;

            return;
        }

        // Fallback to model resolution
        $modelClass = $userModelClass ?? 'App\\Models\\User';

        if (class_exists($modelClass) && is_subclass_of($modelClass, Model::class)) {
            $this->userTable = (new $modelClass)->getTable();
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
