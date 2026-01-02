<?php

declare(strict_types=1);

namespace Ordain\Delegation\Commands;

use Illuminate\Console\Command;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\UserRepositoryInterface;

final class ShowDelegationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delegation:show
                            {user : The user ID to show delegation scope for}
                            {--guard= : The guard to use for finding the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the delegation scope for a user';

    /**
     * Execute the console command.
     */
    public function handle(
        DelegationServiceInterface $delegationService,
        UserRepositoryInterface $userRepository,
    ): int {
        /** @var string $userId */
        $userId = $this->argument('user');

        $user = $userRepository->findById($userId);

        if ($user === null) {
            $this->error("User with ID {$userId} not found.");
            $this->line('Make sure your User model uses the HasDelegation trait.');

            return self::FAILURE;
        }

        $scope = $delegationService->getDelegationScope($user);

        $this->info("Delegation Scope for User #{$userId}");
        $this->newLine();

        // User Management
        $this->table(
            ['Setting', 'Value'],
            [
                ['Can Manage Users', $scope->canManageUsers ? '<fg=green>Yes</>' : '<fg=red>No</>'],
                ['Max Manageable Users', $scope->maxManageableUsers ?? '<fg=yellow>Unlimited</>'],
                ['Created Users Count', $delegationService->getCreatedUsersCount($user)],
                ['Remaining Quota', $delegationService->getRemainingUserQuota($user) ?? '<fg=yellow>Unlimited</>'],
            ],
        );

        $this->newLine();

        // Assignable Roles
        $this->info('Assignable Roles:');
        $assignableRoles = $delegationService->getAssignableRoles($user);

        if ($assignableRoles->isEmpty()) {
            $this->line('  <fg=yellow>No roles can be assigned by this user.</>');
        } else {
            $roleData = $assignableRoles->map(fn ($role): array => [
                $role->getRoleIdentifier(),
                $role->getRoleName(),
                $role->getRoleGuard(),
            ])->toArray();

            $this->table(['ID', 'Name', 'Guard'], $roleData);
        }

        $this->newLine();

        // Assignable Permissions
        $this->info('Assignable Permissions:');
        $assignablePermissions = $delegationService->getAssignablePermissions($user);

        if ($assignablePermissions->isEmpty()) {
            $this->line('  <fg=yellow>No permissions can be granted by this user.</>');
        } else {
            $permissionData = $assignablePermissions->map(fn ($permission): array => [
                $permission->getPermissionIdentifier(),
                $permission->getPermissionName(),
                $permission->getPermissionGuard(),
            ])->toArray();

            $this->table(['ID', 'Name', 'Guard'], $permissionData);
        }

        $this->newLine();

        // Creator Info
        $this->info('Creator Information:');
        $creator = $user->creator;

        if ($creator === null) {
            $this->line('  <fg=yellow>No creator (top-level user)</>');
        } else {
            $this->line("  Created by: User #{$creator->getDelegatableIdentifier()}");
        }

        // Created Users
        $createdUsers = $user->createdUsers()->count();
        $this->line("  Users created by this user: {$createdUsers}");

        return self::SUCCESS;
    }
}
