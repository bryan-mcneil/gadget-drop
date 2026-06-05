<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixEmDashes extends Command
{
    protected $signature = 'content:fix-em-dashes
                            {--dry-run : Preview all changes without writing anything}
                            {--id= : Process only a specific post ID}';

    protected $description = 'Replace em dashes with commas in post body content';

    private array $changeLog = [];
    private int $postsChanged = 0;

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $id       = $this->option('id') ? (int) $this->option('id') : null;

        if ($isDryRun) {
            $this->info('[DRY RUN] No data will be written. Use without --dry-run to apply.');
            $this->newLine();
        } else {
            $this->warn('This will permanently modify post body content in the database.');
            $this->warn('Run with --dry-run first to preview every change.');
            $this->newLine();
            if (!$this->confirm('Proceed and write changes to the database?', false)) {
                $this->info('Aborted — no changes written.');
                return Command::SUCCESS;
            }
        }

        DB::beginTransaction();

        try {
            $query = Post::query()->select('id', 'title', 'body');
            if ($id !== null) {
                $query->where('id', $id);
            }

            foreach ($query->cursor() as $post) {
                $original = $post->body;

                if (!is_string($original) || !str_contains($original, '—')) {
                    continue;
                }

                $replaced = $this->replaceEmDashes($original);

                if ($replaced === $original) {
                    continue;
                }

                $this->postsChanged++;
                $this->changeLog[] = [
                    'post_id' => $post->id,
                    'title'   => $post->title,
                    'before'  => $original,
                    'after'   => $replaced,
                ];

                $this->line("<fg=cyan>Post #{$post->id}</> — {$post->title}");
                if ($this->getOutput()->isVerbose()) {
                    $this->showDiff($original, $replaced);
                }

                if (!$isDryRun) {
                    $post->body = $replaced;
                    $post->saveQuietly();
                }
            }

            if ($isDryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Unexpected error — all changes rolled back.');
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        $this->newLine();
        $this->writeLog($isDryRun);

        $verb = $isDryRun ? 'Would modify' : 'Modified';
        $this->info("{$verb} {$this->postsChanged} post(s).");

        return Command::SUCCESS;
    }

    private function replaceEmDashes(string $text): string
    {
        // Protect em dashes that should not become commas:
        //   - Opening a line (list-style markers)
        //   - Right after an HTML tag
        //   - Right after closing bold markdown (**text** — description)
        $placeholder      = "\x00NODASH\x00";
        $placeholderSpace = "\x00NODASH_S\x00";

        // Protect em dashes that open a line or follow an HTML tag — restored without a leading space
        $text = preg_replace('/^[ \t]*—[ \t]*/mu', $placeholder, $text);
        $text = preg_replace('/(>)[ \t]*—[ \t]*/u', '$1' . $placeholder, $text);

        // Protect em dashes that follow closing bold markdown (**text** — description)
        // restored with a leading space to preserve the original rhythm
        $text = preg_replace('/(\*\*)[ \t]*—[ \t]*/u', '$1' . $placeholderSpace, $text);

        // Replace all remaining em dashes, absorbing surrounding whitespace so
        // "word—word", "word — word", and "word— word" all become "word, word".
        $text = preg_replace('/[ \t]*—[ \t]*/u', ', ', $text);

        // Restore protected dashes
        $text = str_replace($placeholder, '— ', $text);
        $text = str_replace($placeholderSpace, ' — ', $text);

        // Safety: fix double commas (em dash was adjacent to an existing comma)
        $text = preg_replace('/,[ \t]*,/u', ',', $text);

        // Safety: fix comma before sentence-ending punctuation
        $text = preg_replace('/,[ \t]*([.!?])/u', '$1', $text);

        // Safety: fix comma immediately after an HTML tag
        $text = preg_replace('/(>),[ \t]*/u', '$1', $text);

        // Safety: fix comma at the very start of the string
        $text = preg_replace('/^,[ \t]*/u', '', $text);

        return $text;
    }

    private function showDiff(string $before, string $after): void
    {
        $beforeLines = explode("\n", $before);
        $afterLines  = explode("\n", $after);
        $count       = max(count($beforeLines), count($afterLines));

        for ($i = 0; $i < $count; $i++) {
            $b = $beforeLines[$i] ?? '';
            $a = $afterLines[$i]  ?? '';
            if ($b !== $a) {
                $this->line('  <fg=red>- ' . mb_substr($b, 0, 120) . '</>');
                $this->line('  <fg=green>+ ' . mb_substr($a, 0, 120) . '</>');
            }
        }
    }

    private function writeLog(bool $isDryRun): void
    {
        if (empty($this->changeLog)) {
            $this->info('No em dashes found in post bodies — nothing to log.');
            return;
        }

        $prefix   = $isDryRun ? 'dry-run-' : '';
        $filename = storage_path('logs/' . $prefix . 'em-dash-fix-' . now()->format('Y-m-d-His') . '.json');

        file_put_contents(
            $filename,
            json_encode($this->changeLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info("Log written → {$filename}");
    }
}
