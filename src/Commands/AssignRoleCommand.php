<?php

declare(strict_types=1);

namespace Ordain\Delegation\Commands;

use Illuminate\Console\Command;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;

final class AssignRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delegation:assign
                            {delegator : The user ID performing the delegation}
                            {target : The user ID receiving the role}
                            {role : The role name or ID to assign}
                            {--by-id : Treat role argument as ID instead of name}
                            {--force : SECURITY: Bypass authorization checks (still audited)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a role to a user through delegation';

    /**
     * Execute the console command.
     */
    public function handle(
        DelegationServiceInterface $delegationService,
        RoleRepositoryInterface $roleRepository,
        DelegationAuditInterface $audit,
    ): int {
        /** @var string $delegatorId */
        $delegatorId = $this->argument('delegator');
        /** @var string $targetId */
        $targetId = $this->argument('target');
        /** @var string $roleArg */
        $roleArg = $this->argument('role');
        $byId = (bool) $this->option('by-id');
        $force = (bool) $this->option('force');

        /** @var string $userModel */
        $userModel = config('permission-delegation.user_model', 'App\\Models\\User');

        // Find delegator
        $delegator = $userModel::find($delegatorId);
        if ($delegator === null) {
            $this->error("Delegator user with ID {$delegatorId} not found.");

            return self::FAILURE;
        }

        if (! $delegator instanceof DelegatableUserInterface) {
            $this->error('Delegator does not implement DelegatableUserInterface.');

            return self::FAILURE;
        }

        // Find target
        $target = $userModel::find($targetId);
        if ($target === null) {
            $this->error("Target user with ID {$targetId} not found.");

            return self::FAILURE;
        }

        if (! $target instanceof DelegatableUserInterface) {
            $this->error('Target does not implement DelegatableUserInterface.');

            return self::FAILURE;
        }

        // Find role
        $role = $byId
            ? $roleRepository->findById($roleArg)
            : $roleRepository->findByName($roleArg);

        if ($role === null) {
            $identifier = $byId ? "ID {$roleArg}" : "name '{$roleArg}'";
            $this->error("Role with {$identifier} not found.");

            return self::FAILURE;
        }

        // Confirmation
        if (! $force && ! $this->confirm(
            "Assign role '{$role->getRoleName()}' to user #{$targetId} as delegated by user #{$delegatorId}?",
        )) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            if ($force) {
                // SECURITY: Forced assignment bypasses delegation checks
                // This should only be used by system administrators via CLI
                $this->warn('⚠️  SECURITY: Bypassing delegation authorization checks (--force)');

                // Direct role assignment bypassing delegation checks
                $roleRepository->assignToUser($target, $role);

                // Still log the action for audit trail - this is critical for security
                $audit->logRoleAssigned($delegator, $target, $role);

                $this->info("Role '{$role->getRoleName()}' assigned to user #{$targetId} (forced, audit logged).");
            } else {
                // Normal delegation flow (includes audit logging)
                $delegationService->delegateRole($delegator, $target, $role);
                $this->info("Role '{$role->getRoleName()}' successfully delegated to user #{$targetId}.");
            }

            return self::SUCCESS;
        } catch (UnauthorizedDelegationException $e) {
            $this->error('Delegation failed: '.$e->getMessage());

            if ($this->option('verbose')) {
                /** @var array<string, mixed> $context */
                $context = $e->getContext();
                $this->table(['Context Key', 'Value'], collect($context)->map(
                    fn (mixed $value, string $key): array => [$key, is_array($value) ? json_encode($value) : (string) $value],
                )->toArray());
            }

            $this->newLine();
            $this->line('Use --force to bypass authorization checks.');

            return self::FAILURE;
        }
    }
}
