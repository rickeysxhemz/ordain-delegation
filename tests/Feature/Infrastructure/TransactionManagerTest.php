<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ordain\Delegation\Services\Infrastructure\TransactionManager;
use Ordain\Delegation\Tests\Fixtures\User;

uses(RefreshDatabase::class);

describe('TransactionManager', function (): void {
    it('executes callback within transaction', function (): void {
        $manager = new TransactionManager(User::class);

        $result = $manager->transaction(function () {
            return User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        });

        expect($result)->toBeInstanceOf(User::class);
        expect(User::count())->toBe(1);
    });

    it('rolls back transaction on exception', function (): void {
        $manager = new TransactionManager(User::class);

        try {
            $manager->transaction(function () {
                User::create([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]);

                throw new Exception('Simulated failure');
            });
        } catch (Exception) {
            // Expected
        }

        expect(User::count())->toBe(0);
    });

    it('locks user for update', function (): void {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $manager = new TransactionManager(User::class);

        // This should not throw
        $manager->lockUserForUpdate($user);

        expect(true)->toBeTrue();
    });

    it('uses default table when class does not exist', function (): void {
        $manager = new TransactionManager('NonExistentClass');

        // Should use 'users' table as default
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $manager->lockUserForUpdate($user);

        expect(true)->toBeTrue();
    });

    it('uses default class when null provided', function (): void {
        $manager = new TransactionManager;

        $result = $manager->transaction(fn () => 'success');

        expect($result)->toBe('success');
    });
});
