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
        $tableName = config('permission-delegation.tables.user_assignable_permissions', 'user_assignable_permissions');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            // Get table names dynamically
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $usersTable */
            $usersTable = config('permission-delegation.user_model', 'App\\Models\\User');
            $usersTableName = (new $usersTable)->getTable();

            $permissionsTable = config('permission.table_names.permissions', 'permissions');

            // Foreign keys
            $table->foreign('user_id')
                ->references('id')
                ->on($usersTableName)
                ->cascadeOnDelete();

            $table->foreign('permission_id')
                ->references('id')
                ->on($permissionsTable)
                ->cascadeOnDelete();

            // Unique constraint to prevent duplicates
            $table->unique(['user_id', 'permission_id']);

            // Indexes
            $table->index('user_id');
            $table->index('permission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('permission-delegation.tables.user_assignable_permissions', 'user_assignable_permissions');

        Schema::dropIfExists($tableName);
    }
};
