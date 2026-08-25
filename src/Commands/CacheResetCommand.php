<?php

declare(strict_types=1);

namespace Ordain\Delegation\Commands;

use Illuminate\Console\Command;
use Ordain\Delegation\Contracts\CacheInvalidatorInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\UserRepositoryInterface;

final class CacheResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delegation:cache-reset
                            {user? : Optional user ID to clear cache for specific user}
                            {--all : Clear delegation cache for every user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the delegation permission cache';

    /**
     * Execute the console command.
     */
    public function handle(
        DelegationServiceInterface $delegationService,
        UserRepositoryInterface $userRepository,
    ): int {
        /** @var string|null $userId */
        $userId = $this->argument('user');
        $clearAll = (bool) $this->option('all');

        if ($userId !== null) {
            return $this->clearUserCache($delegationService, $userRepository, $userId);
        }

        if ($clearAll) {
            return $this->clearAllCache($delegationService, $userRepository);
        }

        $this->info('Delegation Cache Reset');
        $this->newLine();
        $this->line('Usage:');
        $this->line('  <fg=green>php artisan delegation:cache-reset {user_id}</>');
        $this->line('    Clear cache for a specific user');
        $this->newLine();
        $this->line('  <fg=green>php artisan delegation:cache-reset --all</>');
        $this->line('    Clear cache for every user');

        return self::SUCCESS;
    }

    /**
     * Clear cache for a specific user.
     */
    private function clearUserCache(
        DelegationServiceInterface $delegationService,
        UserRepositoryInterface $userRepository,
        string $userId,
    ): int {
        $user = $userRepository->findById($userId);

        if ($user === null) {
            $this->error("User with ID {$userId} not found.");

            return self::FAILURE;
        }

        if (! $delegationService instanceof CacheInvalidatorInterface) {
            $this->warnCachingDisabled();

            return self::SUCCESS;
        }

        $delegationService->forgetUserCache($user);

        $this->info("Cache cleared for user #{$userId}");
        $this->newLine();
        $this->warnAboutRoleScopedKeys();

        return self::SUCCESS;
    }

    /**
     * Clear delegation cache for every user.
     */
    private function clearAllCache(
        DelegationServiceInterface $delegationService,
        UserRepositoryInterface $userRepository,
    ): int {
        $this->warn('Clearing all delegation cache...');

        if (! $delegationService instanceof CacheInvalidatorInterface) {
            $this->warnCachingDisabled();

            return self::SUCCESS;
        }

        $userIds = $userRepository->getAllIds();
        $usersProcessed = 0;

        $bar = $this->output->createProgressBar($userIds->count());
        $bar->start();

        foreach ($userIds as $userId) {
            $user = $userRepository->findById($userId);

            if ($user !== null) {
                $delegationService->forgetUserCache($user);
                $usersProcessed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Cache clearing complete.');
        $this->line("  Users processed: {$usersProcessed}");

        $this->newLine();
        $this->warnAboutRoleScopedKeys();

        return self::SUCCESS;
    }

    /**
     * Warn that there is no delegation cache to clear.
     */
    private function warnCachingDisabled(): void
    {
        $this->warn('Delegation caching is disabled - there is nothing to clear.');
        $this->line('Enable <fg=green>permission-delegation.cache.enabled</> to use this command.');
    }

    /**
     * Warn about the cache entries this command intentionally leaves alone.
     */
    private function warnAboutRoleScopedKeys(): void
    {
        $this->warn('Note: role- and permission-scoped cache keys (can_assign_role_*, can_assign_perm_*) are not cleared.');
        $this->line('These expire based on TTL, or can be cleared by flushing the entire cache store.');
    }
}
