<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Unit;

use Ordain\Delegation\Domain\Enums\DelegationAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DelegationActionEnumTest extends TestCase
{
    #[Test]
    public function all_actions_have_correct_values(): void
    {
        $this->assertSame('role_assigned', DelegationAction::ROLE_ASSIGNED->value);
        $this->assertSame('role_revoked', DelegationAction::ROLE_REVOKED->value);
        $this->assertSame('permission_granted', DelegationAction::PERMISSION_GRANTED->value);
        $this->assertSame('permission_revoked', DelegationAction::PERMISSION_REVOKED->value);
        $this->assertSame('scope_updated', DelegationAction::SCOPE_UPDATED->value);
        $this->assertSame('user_created', DelegationAction::USER_CREATED->value);
        $this->assertSame('unauthorized_attempt', DelegationAction::UNAUTHORIZED_ATTEMPT->value);
    }

    #[Test]
    public function all_actions_have_labels(): void
    {
        $this->assertSame('Role Assigned', DelegationAction::ROLE_ASSIGNED->label());
        $this->assertSame('Role Revoked', DelegationAction::ROLE_REVOKED->label());
        $this->assertSame('Permission Granted', DelegationAction::PERMISSION_GRANTED->label());
        $this->assertSame('Permission Revoked', DelegationAction::PERMISSION_REVOKED->label());
        $this->assertSame('Delegation Scope Updated', DelegationAction::SCOPE_UPDATED->label());
        $this->assertSame('User Created', DelegationAction::USER_CREATED->label());
        $this->assertSame('Unauthorized Attempt', DelegationAction::UNAUTHORIZED_ATTEMPT->label());
    }

    #[Test]
    public function severity_returns_correct_levels(): void
    {
        $this->assertSame('info', DelegationAction::ROLE_ASSIGNED->severity());
        $this->assertSame('notice', DelegationAction::ROLE_REVOKED->severity());
        $this->assertSame('info', DelegationAction::PERMISSION_GRANTED->severity());
        $this->assertSame('notice', DelegationAction::PERMISSION_REVOKED->severity());
        $this->assertSame('info', DelegationAction::SCOPE_UPDATED->severity());
        $this->assertSame('info', DelegationAction::USER_CREATED->severity());
        $this->assertSame('warning', DelegationAction::UNAUTHORIZED_ATTEMPT->severity());
    }

    #[Test]
    public function is_grant_returns_true_for_grant_actions(): void
    {
        $this->assertTrue(DelegationAction::ROLE_ASSIGNED->isGrant());
        $this->assertTrue(DelegationAction::PERMISSION_GRANTED->isGrant());

        $this->assertFalse(DelegationAction::ROLE_REVOKED->isGrant());
        $this->assertFalse(DelegationAction::PERMISSION_REVOKED->isGrant());
        $this->assertFalse(DelegationAction::SCOPE_UPDATED->isGrant());
        $this->assertFalse(DelegationAction::USER_CREATED->isGrant());
        $this->assertFalse(DelegationAction::UNAUTHORIZED_ATTEMPT->isGrant());
    }

    #[Test]
    public function is_revoke_returns_true_for_revoke_actions(): void
    {
        $this->assertTrue(DelegationAction::ROLE_REVOKED->isRevoke());
        $this->assertTrue(DelegationAction::PERMISSION_REVOKED->isRevoke());

        $this->assertFalse(DelegationAction::ROLE_ASSIGNED->isRevoke());
        $this->assertFalse(DelegationAction::PERMISSION_GRANTED->isRevoke());
        $this->assertFalse(DelegationAction::SCOPE_UPDATED->isRevoke());
        $this->assertFalse(DelegationAction::USER_CREATED->isRevoke());
        $this->assertFalse(DelegationAction::UNAUTHORIZED_ATTEMPT->isRevoke());
    }

    #[Test]
    public function can_create_from_value(): void
    {
        $action = DelegationAction::from('role_assigned');

        $this->assertSame(DelegationAction::ROLE_ASSIGNED, $action);
    }

    #[Test]
    public function try_from_returns_null_for_invalid_value(): void
    {
        $action = DelegationAction::tryFrom('invalid_action');

        $this->assertNull($action);
    }

    #[Test]
    public function cases_returns_all_actions(): void
    {
        $cases = DelegationAction::cases();

        $this->assertCount(7, $cases);
        $this->assertContains(DelegationAction::ROLE_ASSIGNED, $cases);
        $this->assertContains(DelegationAction::UNAUTHORIZED_ATTEMPT, $cases);
    }
}
