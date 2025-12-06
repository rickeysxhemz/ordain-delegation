<?php

declare(strict_types=1);

use Ordain\Delegation\Domain\Enums\DelegationAction;

describe('DelegationAction', function (): void {
    it('has all expected cases', function (): void {
        expect(DelegationAction::cases())->toHaveCount(7)
            ->and(DelegationAction::ROLE_ASSIGNED->value)->toBe('role_assigned')
            ->and(DelegationAction::ROLE_REVOKED->value)->toBe('role_revoked')
            ->and(DelegationAction::PERMISSION_GRANTED->value)->toBe('permission_granted')
            ->and(DelegationAction::PERMISSION_REVOKED->value)->toBe('permission_revoked')
            ->and(DelegationAction::SCOPE_UPDATED->value)->toBe('scope_updated')
            ->and(DelegationAction::USER_CREATED->value)->toBe('user_created')
            ->and(DelegationAction::UNAUTHORIZED_ATTEMPT->value)->toBe('unauthorized_attempt');
    });

    it('returns human-readable labels', function (): void {
        expect(DelegationAction::ROLE_ASSIGNED->label())->toBe('Role Assigned')
            ->and(DelegationAction::ROLE_REVOKED->label())->toBe('Role Revoked')
            ->and(DelegationAction::PERMISSION_GRANTED->label())->toBe('Permission Granted')
            ->and(DelegationAction::PERMISSION_REVOKED->label())->toBe('Permission Revoked')
            ->and(DelegationAction::SCOPE_UPDATED->label())->toBe('Delegation Scope Updated')
            ->and(DelegationAction::USER_CREATED->label())->toBe('User Created')
            ->and(DelegationAction::UNAUTHORIZED_ATTEMPT->label())->toBe('Unauthorized Attempt');
    });

    it('returns correct severity levels', function (): void {
        expect(DelegationAction::ROLE_ASSIGNED->severity())->toBe('info')
            ->and(DelegationAction::ROLE_REVOKED->severity())->toBe('notice')
            ->and(DelegationAction::PERMISSION_GRANTED->severity())->toBe('info')
            ->and(DelegationAction::PERMISSION_REVOKED->severity())->toBe('notice')
            ->and(DelegationAction::SCOPE_UPDATED->severity())->toBe('info')
            ->and(DelegationAction::USER_CREATED->severity())->toBe('info')
            ->and(DelegationAction::UNAUTHORIZED_ATTEMPT->severity())->toBe('warning');
    });

    it('identifies grant actions correctly', function (): void {
        expect(DelegationAction::ROLE_ASSIGNED->isGrant())->toBeTrue()
            ->and(DelegationAction::PERMISSION_GRANTED->isGrant())->toBeTrue()
            ->and(DelegationAction::ROLE_REVOKED->isGrant())->toBeFalse()
            ->and(DelegationAction::USER_CREATED->isGrant())->toBeFalse();
    });

    it('identifies revoke actions correctly', function (): void {
        expect(DelegationAction::ROLE_REVOKED->isRevoke())->toBeTrue()
            ->and(DelegationAction::PERMISSION_REVOKED->isRevoke())->toBeTrue()
            ->and(DelegationAction::ROLE_ASSIGNED->isRevoke())->toBeFalse()
            ->and(DelegationAction::USER_CREATED->isRevoke())->toBeFalse();
    });
});
