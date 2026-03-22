<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;

final readonly class RootAdminResolver implements RootAdminResolverInterface
{
    /**
     * @param  array<string>  $roleIdentifiers
     */
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private bool $enabled = true,
        private array $roleIdentifiers = [],
        private ?string $guard = null,
    ) {}

    public function isRootAdmin(DelegatableUserInterface $user): bool
    {
        if (! $this->enabled || $this->roleIdentifiers === []) {
            return false;
        }

        foreach ($this->roleIdentifiers as $roleIdentifier) {
            if ($this->roleRepository->userHasRoleByName($user, $roleIdentifier, $this->guard)) {
                return true;
            }
        }

        return false;
    }
}
