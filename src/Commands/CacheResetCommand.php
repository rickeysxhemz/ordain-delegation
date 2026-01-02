<?php

declare(strict_types=1);

namespace Ordain\Delegation\Commands;

use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggableStore;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\UserRepositoryInterface;
use Ordain\Delegation\Services\CachedDelegationService;

final class CacheResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delegation:cache-reset
                            {user? : Optional user ID to clear cache for specific user}
                            {--all : Clear all delegation cache (requires cache tags or prefix scan)}';

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
        CacheRepository $cache,
        DelegationServiceInterface $delegationService,
        UserRepositoryInterface $userRepository,
    ): int {
        /** @var string|null $userId */
        $userId = $this->argument('user');
        $clearAll = (bool) $this->option('all');
        /** @var string $prefix */
        $prefix = config('permission-delegation.cache.prefix', 'delegation_');

        if ($userId !== null) {
            return $this->clearUserCache($cache, $delegationService, $userRepository, $userId, $prefix);
        }

        if ($clearAll) {
            return $this->clearAllCache($cache, $userRepository, $prefix);
        }

        $this->info('Delegation Cache Reset');
        $this->newLine();
        $this->line('Usage:');
        $this->line('  <fg=green>php artisan delegation:cache-reset {user_id}</>');
        $this->line('    Clear cache for a specific user');
        $this->newLine();
        $this->line('  <fg=green>php artisan delegation:cache-reset --all</>');
        $this->line('    Attempt to clear all delegation cache');
        $this->newLine();
        $this->warn('Note: For complete cache clearing, consider using a cache driver that supports tags (Redis, Memcached).');

        return self::SUCCESS;
    }

    /**
     * Clear cache for a specific user.
     */
    private function clearUserCache(
        CacheRepository $cache,
        DelegationServiceInterface $delegationService,
        UserRepositoryInterface $userRepository,
        string $userId,
        string $prefix,
    ): int {
        $user = $userRepository->findById($userId);

        if ($user === null) {
            $this->error("User with ID {$userId} not found.");

            return self::FAILURE;
        }

        // Clear known cache keys for this user
        $keysCleared = 0;
        $cacheTypes = [
            'scope',
            'assignable_roles',
            'assignable_perms',
            'can_create_users',
        ];

        foreach ($cacheTypes as $type) {
            $key = "{$prefix}{$type}_{$userId}";
            if ($cache->forget($key)) {
                $keysCleared++;
            }
        }

        $this->info("Cache cleared for user #{$userId}");
        $this->line('  Keys processed: '.count($cacheTypes));
        $this->line("  Keys cleared: {$keysCleared}");

        // Check if using CachedDelegationService
        if ($delegationService instanceof CachedDelegationService) {
            $delegationService->forgetUserCache($user);
            $this->line('  <fg=green>CachedDelegationService cache invalidated.</>');
        }

        return self::SUCCESS;
    }

    /**
     * Attempt to clear all delegation cache.
     */
    private function clearAllCache(
        CacheRepository $cache,
        UserRepositoryInterface $userRepository,
        string $prefix,
    ): int {
        $this->warn('Clearing all delegation cache...');

        // Check if cache supports tags (Redis, Memcached)
        if ($cache instanceof Repository && $cache->getStore() instanceof TaggableStore) {
            $cache->tags(['delegation'])->flush();
            $this->info('All delegation cache cleared via tags.');

            return self::SUCCESS;
        }

        // Fallback: Clear known patterns
        $this->line('Cache tags not supported by current driver. Clearing known patterns...');

        $userIds = $userRepository->getAllIds();
        $keysCleared = 0;

        $cacheTypes = [
            'scope',
            'assignable_roles',
            'assignable_perms',
            'can_create_users',
        ];

        $bar = $this->output->createProgressBar($userIds->count());
        $bar->start();

        foreach ($userIds as $userId) {
            foreach ($cacheTypes as $type) {
                $key = "{$prefix}{$type}_{$userId}";
                if ($cache->forget($key)) {
                    $keysCleared++;
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Cache clearing complete.');
        $this->line("  Users processed: {$userIds->count()}");
        $this->line("  Keys cleared: {$keysCleared}");

        $this->newLine();
        $this->warn('Note: Role/permission-specific cache keys (can_assign_role_*, can_assign_perm_*) may still exist.');
        $this->line('These will expire based on TTL or can be cleared by flushing the entire cache store.');

        return self::SUCCESS;
    }
}
