<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization;

use Illuminate\Pipeline\Pipeline;
use Ordain\Delegation\Contracts\AuthorizationPipelineInterface;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Services\Authorization\Pipes\CheckHierarchyPipe;
use Ordain\Delegation\Services\Authorization\Pipes\CheckRoleInScopePipe;
use Ordain\Delegation\Services\Authorization\Pipes\CheckRootAdminPipe;
use Ordain\Delegation\Services\Authorization\Pipes\CheckUserManagementPipe;

/**
 * Processes authorization checks through a series of pipes.
 */
final readonly class AuthorizationPipeline implements AuthorizationPipelineInterface
{
    /** @var array<class-string> */
    private array $pipes;

    public function __construct(
        private Pipeline $pipeline,
        private RootAdminResolverInterface $rootAdminResolver,
        private DelegationRepositoryInterface $delegationRepository,
    ) {
        $this->pipes = [
            CheckRootAdminPipe::class,
            CheckUserManagementPipe::class,
            CheckHierarchyPipe::class,
            CheckRoleInScopePipe::class,
        ];
    }

    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool {
        $context = AuthorizationContext::forRoleAssignment($delegator, $role, $target);

        return $this->process($context)->isGranted();
    }

    public function canAssignPermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): bool {
        $context = AuthorizationContext::forPermissionAssignment($delegator, $permission, $target);

        return $this->process($context)->isGranted();
    }

    public function canManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): bool {
        $context = AuthorizationContext::forUserManagement($delegator, $target);

        return $this->process($context)->isGranted();
    }

    private function process(AuthorizationContext $context): AuthorizationContext
    {
        $pipes = $this->buildPipes();

        /** @var AuthorizationContext */
        return $this->pipeline
            ->send($context)
            ->through($pipes)
            ->then(fn (AuthorizationContext $ctx): AuthorizationContext => $ctx->isDenied() ? $ctx : $ctx->deny('No authorization granted'));
    }

    /**
     * @return array<object>
     */
    private function buildPipes(): array
    {
        return [
            new CheckRootAdminPipe($this->rootAdminResolver),
            new CheckUserManagementPipe,
            new CheckHierarchyPipe,
            new CheckRoleInScopePipe($this->delegationRepository),
        ];
    }
}
