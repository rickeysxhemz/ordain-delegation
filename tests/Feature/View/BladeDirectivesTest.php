<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Tests\Fixtures\User;
use Ordain\Delegation\View\BladeDirectives;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The directives are registered as Blade conditions, so `Blade::check()`
 * invokes the same closure a compiled template calls at runtime. Assertions go
 * through that closure — or through a rendered template — rather than through
 * the services behind it, so a directive that is wired up incorrectly fails
 * here instead of passing on the strength of the service being correct.
 */
beforeEach(function (): void {
    app(BladeDirectives::class)->register();
});

function manager(string $email): User
{
    return User::create([
        'name' => 'Manager',
        'email' => $email,
        'can_manage_users' => true,
    ]);
}

describe('BladeDirectives registration', function (): void {
    it('registers canDelegate directive', function (): void {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('canDelegate');
        expect($directives)->toHaveKey('elsecanDelegate');
        expect($directives)->toHaveKey('endcanDelegate');
    });

    it('registers canAssignRole directive', function (): void {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('canAssignRole');
        expect($directives)->toHaveKey('elsecanAssignRole');
        expect($directives)->toHaveKey('endcanAssignRole');
    });

    it('registers canManageUser directive', function (): void {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('canManageUser');
        expect($directives)->toHaveKey('elsecanManageUser');
        expect($directives)->toHaveKey('endcanManageUser');
    });
});

describe('@canDelegate', function (): void {
    it('is false for a guest', function (): void {
        expect(Blade::check('canDelegate'))->toBeFalse();
    });

    it('is true for a user who can create users', function (): void {
        $this->actingAs(manager('manager@example.com'));

        expect(Blade::check('canDelegate'))->toBeTrue();
    });

    it('is false for a user who cannot create users', function (): void {
        $this->actingAs(User::create([
            'name' => 'Regular',
            'email' => 'regular@example.com',
            'can_manage_users' => false,
        ]));

        expect(Blade::check('canDelegate'))->toBeFalse();
    });

    it('renders its body only when the condition passes', function (): void {
        $template = '@canDelegate CREATE @endcanDelegate';

        $this->actingAs(manager('manager2@example.com'));
        expect(trim(Blade::render($template)))->toBe('CREATE');

        $this->actingAs(User::create([
            'name' => 'Regular',
            'email' => 'regular2@example.com',
            'can_manage_users' => false,
        ]));
        expect(trim(Blade::render($template)))->toBe('');
    });

    it('renders the else branch when the condition fails', function (): void {
        // Blade::if() compiles @else<name> to `elseif (check(<name>))`, re-testing
        // the same condition — so the plain @else is what pairs with a directive
        // that takes no expression.
        $template = '@canDelegate YES @else NO @endcanDelegate';

        $this->actingAs(User::create([
            'name' => 'Regular',
            'email' => 'regular3@example.com',
            'can_manage_users' => false,
        ]));
        expect(trim(Blade::render($template)))->toBe('NO');

        $this->actingAs(manager('manager10@example.com'));
        expect(trim(Blade::render($template)))->toBe('YES');
    });
});

describe('@canAssignRole', function (): void {
    it('is false for a guest', function (): void {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        expect(Blade::check('canAssignRole', 'editor'))->toBeFalse();
    });

    it('is true when the role is in the delegator scope', function (): void {
        $user = manager('manager3@example.com');
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $user->assignableRoles()->attach($role->id);

        $this->actingAs($user);

        expect(Blade::check('canAssignRole', 'editor'))->toBeTrue();
    });

    it('is false when the role is outside the delegator scope', function (): void {
        $user = manager('manager4@example.com');
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->actingAs($user);

        expect(Blade::check('canAssignRole', 'admin'))->toBeFalse();
    });

    it('is false for a role that does not exist', function (): void {
        $this->actingAs(manager('manager5@example.com'));

        expect(Blade::check('canAssignRole', 'nonexistent'))->toBeFalse();
    });

    it('renders its body only when the condition passes', function (): void {
        $user = manager('manager6@example.com');
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignableRoles()->attach($role->id);

        $this->actingAs($user);

        expect(trim(Blade::render('@canAssignRole("editor") OK @endcanAssignRole')))->toBe('OK');
        expect(trim(Blade::render('@canAssignRole("admin") OK @endcanAssignRole')))->toBe('');
    });
});

describe('@canManageUser', function (): void {
    it('is false for a guest', function (): void {
        $target = User::create(['name' => 'Target', 'email' => 'target@example.com']);

        expect(Blade::check('canManageUser', $target))->toBeFalse();
    });

    it('is true for a user the delegator created', function (): void {
        $manager = manager('manager7@example.com');
        $target = User::create([
            'name' => 'Target',
            'email' => 'target2@example.com',
            'created_by_user_id' => $manager->id,
        ]);

        $this->actingAs($manager);

        expect(Blade::check('canManageUser', $target))->toBeTrue();
    });

    it('is false for a user the delegator did not create', function (): void {
        $manager = manager('manager8@example.com');
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com']);

        $this->actingAs($manager);

        expect(Blade::check('canManageUser', $other))->toBeFalse();
    });

    it('renders its body only when the condition passes', function (): void {
        $manager = manager('manager9@example.com');
        $owned = User::create([
            'name' => 'Owned',
            'email' => 'owned@example.com',
            'created_by_user_id' => $manager->id,
        ]);
        $other = User::create(['name' => 'Other', 'email' => 'other2@example.com']);

        $this->actingAs($manager);

        expect(trim(Blade::render('@canManageUser($user) EDIT @endcanManageUser', ['user' => $owned])))->toBe('EDIT');
        expect(trim(Blade::render('@canManageUser($user) EDIT @endcanManageUser', ['user' => $other])))->toBe('');
    });
});

describe('BladeDirectives failure handling', function (): void {
    it('is false rather than throwing when the auth guard cannot be resolved', function (): void {
        $target = User::create(['name' => 'Target', 'email' => 'guarded@example.com']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        config()->set('permission-delegation.guard', 'nonexistent-guard');

        expect(Blade::check('canDelegate'))->toBeFalse();
        expect(Blade::check('canAssignRole', 'editor'))->toBeFalse();
        expect(Blade::check('canManageUser', $target))->toBeFalse();
    });

    it('swallows a failure from the delegation service rather than breaking the view', function (): void {
        $manager = manager('manager11@example.com');
        $target = User::create([
            'name' => 'Target',
            'email' => 'target3@example.com',
            'created_by_user_id' => $manager->id,
        ]);
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $manager->assignableRoles()->attach($role->id);

        $this->actingAs($manager);

        $failing = Mockery::mock(DelegationServiceInterface::class);
        $failing->shouldReceive('canCreateUsers')->andThrow(new RuntimeException('boom'));
        $failing->shouldReceive('canAssignRole')->andThrow(new RuntimeException('boom'));
        $failing->shouldReceive('canManageUser')->andThrow(new RuntimeException('boom'));
        $this->app->instance(DelegationServiceInterface::class, $failing);

        expect(Blade::check('canDelegate'))->toBeFalse();
        expect(Blade::check('canAssignRole', 'editor'))->toBeFalse();
        expect(Blade::check('canManageUser', $target))->toBeFalse();
    });
});
