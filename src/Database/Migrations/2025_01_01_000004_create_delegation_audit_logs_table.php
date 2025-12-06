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
        $tableName = config('permission-delegation.tables.delegation_audit_logs', 'delegation_audit_logs');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('action', 50); // e.g., 'role_assigned', 'permission_granted'
            $table->unsignedBigInteger('performed_by_id');
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Get users table name dynamically
            $usersTable = config('permission-delegation.user_model', 'App\\Models\\User');
            $usersTableName = (new $usersTable)->getTable();

            // Foreign keys (nullable for target as some actions don't have targets)
            $table->foreign('performed_by_id')
                ->references('id')
                ->on($usersTableName)
                ->cascadeOnDelete();

            $table->foreign('target_user_id')
                ->references('id')
                ->on($usersTableName)
                ->nullOnDelete();

            // Indexes for common queries
            $table->index('action');
            $table->index('performed_by_id');
            $table->index('target_user_id');
            $table->index('created_at');

            // Composite index for filtering by action and user
            $table->index(['action', 'performed_by_id']);
            $table->index(['action', 'target_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('permission-delegation.tables.delegation_audit_logs', 'delegation_audit_logs');

        Schema::dropIfExists($tableName);
    }
};
