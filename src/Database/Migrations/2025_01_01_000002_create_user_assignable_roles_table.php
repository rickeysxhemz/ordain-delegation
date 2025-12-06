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
        $tableName = config('permission-delegation.tables.user_assignable_roles', 'user_assignable_roles');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            // Get table names dynamically
            $usersTable = config('permission-delegation.user_model', 'App\\Models\\User');
            $usersTableName = (new $usersTable)->getTable();

            $rolesTable = config('permission.table_names.roles', 'roles');

            // Foreign keys
            $table->foreign('user_id')
                ->references('id')
                ->on($usersTableName)
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on($rolesTable)
                ->cascadeOnDelete();

            // Unique constraint to prevent duplicates
            $table->unique(['user_id', 'role_id']);

            // Indexes
            $table->index('user_id');
            $table->index('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('permission-delegation.tables.user_assignable_roles', 'user_assignable_roles');

        Schema::dropIfExists($tableName);
    }
};