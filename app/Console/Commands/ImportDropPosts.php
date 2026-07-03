<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DailyDropImporterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CLI half of the content pipeline: turns daily-drop/output.json (written by
 * bin/daily-drop-build.php) into draft posts. Same importer the
 * /admin/daily-drop Import page uses, so both paths behave identically.
 */
class ImportDropPosts extends Command
{
    protected $signature = 'posts:import
                            {file=daily-drop/output.json : Path to the pipeline JSON (relative to the project root or absolute)}
                            {--dry-run : Parse and validate without writing anything}';

    protected $description = 'Import the daily-drop pipeline JSON as draft posts (reviews, tech tips, tech news)';

    public function handle(DailyDropImporterService $importer): int
    {
        $file = $this->argument('file');
        $path = is_file($file) ? $file : base_path($file);

        if (! is_file($path)) {
            $this->error("File not found: {$file}. Run `php bin/daily-drop-build.php` first.");

            return self::FAILURE;
        }

        try {
            $posts = $importer->parseJson((string) file_get_contents($path));
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $author = User::siteAuthor();
        if (! $author) {
            $this->error('No site author found (config site.author.slug) and no authenticated user. Aborting.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            // Import inside a transaction that always rolls back so type/category
            // resolution and validation run for real without persisting anything.
            DB::beginTransaction();
            try {
                $created = $importer->importAll($posts, $author->id);
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            DB::rollBack();

            $this->info('[dry-run] ' . count($created) . ' post(s) would import cleanly — nothing was written:');
            foreach ($posts as $data) {
                $this->line(sprintf('  %-9s %s', $data['type'] ?? 'article', $data['title']));
            }

            return self::SUCCESS;
        }

        try {
            $created = $importer->importAll($posts, $author->id);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(count($created) . ' draft(s) created:');
        foreach ($created as $i => $row) {
            $type = $posts[$i]['type'] ?? 'article';
            $this->line(sprintf('  #%-5d %-9s %s', $row['id'], $type, $row['title']));
        }
        $this->comment('All posts are DRAFTS — review, add images, and publish in /admin/posts.');

        return self::SUCCESS;
    }
}
