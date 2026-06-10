<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The database cache driver only deletes an expired row when that exact key is
 * read again. Content-hashed keys (ArticleBody) and per-ASIN Amazon lookups are
 * never re-read once the content changes, so without a sweep the cache table
 * grows forever. Scheduled daily in routes/console.php.
 */
class PruneExpiredCache extends Command
{
    protected $signature = 'cache:prune-expired';

    protected $description = 'Delete expired rows from the database cache table.';

    public function handle(): int
    {
        $deleted = DB::table(config('cache.stores.database.table', 'cache'))
            ->where('expiration', '<', now()->getTimestamp())
            ->delete();

        $this->info("Pruned {$deleted} expired cache row(s).");

        return self::SUCCESS;
    }
}