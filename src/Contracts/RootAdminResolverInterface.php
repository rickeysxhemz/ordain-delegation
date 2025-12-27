<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

interface RootAdminResolverInterface
{
    public function isRootAdmin(DelegatableUserInterface $user): bool;
}
