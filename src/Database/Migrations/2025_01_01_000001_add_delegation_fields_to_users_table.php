<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $usersTable */
        $usersTable = config('permission-delegation.user_model', 'App\\Models\\User');
        $tableName = (new $usersTable)->getTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'can_manage_users')) {
                $table->boolean('can_manage_users')->default(false)->after('id');
            }

            if (! Schema::hasColumn($tableName, 'max_manageable_users')) {
                $table->unsignedInteger('max_manageable_users')->nullable()->after('can_manage_users');
            }

            if (! Schema::hasColumn($tableName, 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('max_manageable_users');
                $table->foreign('created_by_user_id')
                    ->references('id')
                    ->on($tableName)
                    ->nullOnDelete();
            }

            // Add indexes
            if (! Schema::hasIndex($tableName, $tableName.'_can_manage_users_index')) {
                $table->index('can_manage_users', $tableName.'_can_manage_users_index');
            }

            if (! Schema::hasIndex($tableName, $tableName.'_created_by_user_id_index')) {
                $table->index('created_by_user_id', $tableName.'_created_by_user_id_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $usersTable */
        $usersTable = config('permission-delegation.user_model', 'App\\Models\\User');
        $tableName = (new $usersTable)->getTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'created_by_user_id')) {
                $table->dropForeign(['created_by_user_id']);
            }

            $columns = ['can_manage_users', 'max_manageable_users', 'created_by_user_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
