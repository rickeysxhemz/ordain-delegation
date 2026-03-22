<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ordain\Delegation\Services\Infrastructure\TransactionManager;
use Ordain\Delegation\Tests\Fixtures\User;

uses(RefreshDatabase::class);

describe('TransactionManager', function (): void {
    it('executes callback within transaction', function (): void {
        $manager = new TransactionManager(
            connection: app(ConnectionInterface::class),
            userTable: (new User)->getTable(),
        );

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
        $manager = new TransactionManager(
            connection: app(ConnectionInterface::class),
            userTable: (new User)->getTable(),
        );

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

        $manager = new TransactionManager(
            connection: app(ConnectionInterface::class),
            userTable: (new User)->getTable(),
        );

        // This should not throw
        $manager->lockUserForUpdate($user);

        expect(true)->toBeTrue();
    });

    it('uses default table name', function (): void {
        $manager = new TransactionManager(
            connection: app(ConnectionInterface::class),
        );

        // Should use 'users' table as default
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $manager->lockUserForUpdate($user);

        expect(true)->toBeTrue();
    });

    it('executes transaction with default table', function (): void {
        $manager = new TransactionManager(
            connection: app(ConnectionInterface::class),
        );

        $result = $manager->transaction(fn () => 'success');

        expect($result)->toBe('success');
    });
});
