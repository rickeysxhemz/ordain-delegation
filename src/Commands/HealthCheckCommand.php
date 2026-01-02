<?php

declare(strict_types=1);

namespace Ordain\Delegation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class HealthCheckCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'delegation:health
                            {--fix : Attempt to fix issues where possible}';

    /**
     * @var string
     */
    protected $description = 'Check the health of the delegation package configuration';

    private int $issues = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->info('Delegation Package Health Check');
        $this->info('================================');
        $this->newLine();

        $this->checkConfiguration();
        $this->checkDatabaseSchema();
        $this->checkModels();
        $this->checkSpatieIntegration();

        $this->newLine();
        $this->displaySummary();

        return $this->issues > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkConfiguration(): void
    {
        $this->info('Configuration');
        $this->line('-------------');

        // Check if config is published
        $configPath = config_path('permission-delegation.php');
        if (file_exists($configPath)) {
            $this->logSuccess('Config file published');
        } else {
            $this->logWarning('Config file not published (using defaults)');
        }

        // Check user model
        $userModel = config('permission-delegation.user_model');
        if ($userModel && class_exists($userModel)) {
            $this->logSuccess("User model: {$userModel}");
        } else {
            $this->logError("User model not found: {$userModel}");
        }

        // Check role model
        $roleModel = config('permission-delegation.role_model');
        if ($roleModel && class_exists($roleModel)) {
            $this->logSuccess("Role model: {$roleModel}");
        } else {
            $this->logError("Role model not found: {$roleModel}");
        }

        // Check permission model
        $permissionModel = config('permission-delegation.permission_model');
        if ($permissionModel && class_exists($permissionModel)) {
            $this->logSuccess("Permission model: {$permissionModel}");
        } else {
            $this->logError("Permission model not found: {$permissionModel}");
        }

        $this->newLine();
    }

    private function checkDatabaseSchema(): void
    {
        $this->info('Database Schema');
        $this->line('----------------');

        // Check user table columns
        $userModel = config('permission-delegation.user_model');
        if ($userModel && class_exists($userModel)) {
            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $userModel;
            $userTable = $instance->getTable();

            $requiredColumns = ['can_manage_users', 'max_manageable_users', 'created_by_user_id'];

            foreach ($requiredColumns as $column) {
                if ($this->hasColumn($userTable, $column)) {
                    $this->logSuccess("Column '{$column}' exists in {$userTable}");
                } else {
                    $this->logError("Column '{$column}' missing in {$userTable}");
                }
            }
        }

        // Check pivot tables
        $tables = [
            'user_assignable_roles' => config('permission-delegation.tables.user_assignable_roles', 'user_assignable_roles'),
            'user_assignable_permissions' => config('permission-delegation.tables.user_assignable_permissions', 'user_assignable_permissions'),
            'delegation_audit_logs' => config('permission-delegation.tables.delegation_audit_logs', 'delegation_audit_logs'),
        ];

        foreach ($tables as $name => $tableName) {
            if ($this->tableExists($tableName)) {
                $this->logSuccess("Table '{$tableName}' exists");
            } else {
                $this->logError("Table '{$tableName}' missing");
            }
        }

        $this->newLine();
    }

    private function checkModels(): void
    {
        $this->info('Model Configuration');
        $this->line('--------------------');

        $userModel = config('permission-delegation.user_model');

        if (! $userModel || ! class_exists($userModel)) {
            $this->logError('Cannot check models - user model not found');

            return;
        }

        // Check if user model uses HasDelegation trait
        $traits = class_uses_recursive($userModel);
        $hasDelegationTrait = isset($traits['Ordain\Delegation\Traits\HasDelegation']);

        if ($hasDelegationTrait) {
            $this->logSuccess('User model uses HasDelegation trait');
        } else {
            $this->logError('User model does not use HasDelegation trait');
        }

        // Check if user model implements DelegatableUserInterface
        $implements = class_implements($userModel);
        $implementsInterface = isset($implements['Ordain\Delegation\Contracts\DelegatableUserInterface']);

        if ($implementsInterface) {
            $this->logSuccess('User model implements DelegatableUserInterface');
        } else {
            $this->logError('User model does not implement DelegatableUserInterface');
        }

        $this->newLine();
    }

    private function checkSpatieIntegration(): void
    {
        $this->info('Spatie Permission Integration');
        $this->line('------------------------------');

        // Check if spatie/laravel-permission is installed
        if (class_exists('Spatie\Permission\PermissionServiceProvider')) {
            $this->logSuccess('Spatie permission package is installed');
        } else {
            $this->logError('Spatie permission package is not installed');

            return;
        }

        // Check if roles table exists
        $rolesTable = config('permission.table_names.roles', 'roles');
        if ($this->tableExists($rolesTable)) {
            $roleCount = DB::table($rolesTable)->count();
            $this->logSuccess("Roles table exists ({$roleCount} roles)");
        } else {
            $this->logError("Roles table '{$rolesTable}' does not exist");
        }

        // Check if permissions table exists
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        if ($this->tableExists($permissionsTable)) {
            $permissionCount = DB::table($permissionsTable)->count();
            $this->logSuccess("Permissions table exists ({$permissionCount} permissions)");
        } else {
            $this->logError("Permissions table '{$permissionsTable}' does not exist");
        }

        $this->newLine();
    }

    private function displaySummary(): void
    {
        $this->info('Summary');
        $this->line('--------');

        if ($this->issues === 0 && $this->warnings === 0) {
            $this->logSuccess('All checks passed!');
        } else {
            if ($this->issues > 0) {
                $this->line("<fg=red>{$this->issues} issue(s) found</>");
            }
            if ($this->warnings > 0) {
                $this->line("<fg=yellow>{$this->warnings} warning(s) found</>");
            }

            if ($this->issues > 0) {
                $this->newLine();
                $this->line('Run the following to fix common issues:');
                $this->line('  <fg=cyan>php artisan delegation:install</>');
            }
        }
    }

    private function logSuccess(string $message): void
    {
        $this->line("  <fg=green>✓</> {$message}");
    }

    private function logWarning(string $message): void
    {
        $this->warnings++;
        $this->line("  <fg=yellow>⚠</> {$message}");
    }

    private function logError(string $message): void
    {
        $this->issues++;
        $this->line("  <fg=red>✗</> {$message}");
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (! $this->tableExists($table)) {
            return false;
        }

        return Schema::hasColumn($table, $column);
    }
}
