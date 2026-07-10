<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Report-only scan for phrases that imply first-hand testing the site didn't
 * do ("we tested", "our measurements", …). GadgetDrop reviews are research-
 * based (see /how-we-review), so published posts must not claim otherwise.
 *
 *   php artisan content:flag-claims          — human-readable table
 *   php artisan content:flag-claims --json   — machine-readable
 *   php artisan content:flag-claims --id=42  — scan a single post
 *
 * This command NEVER writes; fix flagged posts by hand in /admin/posts/{id}/edit
 * and re-run until clean. Phrase list lives in config/content.php.
 */
class FlagTestingClaims extends Command
{
    protected $signature = 'content:flag-claims
        {--json : Output findings as JSON}
        {--id= : Scan a single post id instead of all published posts}
        {--all : Include drafts and archived posts, not just published}';

    protected $description = 'Flag published posts containing unverifiable first-hand-testing claims (report-only).';

    public function handle(): int
    {
        $phrases = config('content.testing_claim_phrases', []);

        if (empty($phrases)) {
            $this->error('No phrases configured in content.testing_claim_phrases.');

            return self::FAILURE;
        }

        $query = Post::query()->select(['id', 'title', 'slug', 'excerpt', 'body', 'status']);

        if ($this->option('id')) {
            $query->where('id', (int) $this->option('id'));
        } elseif (! $this->option('all')) {
            $query->published();
        }

        $findings = [];

        $query->chunkById(100, function ($posts) use ($phrases, &$findings) {
            foreach ($posts as $post) {
                foreach (['title', 'excerpt', 'body'] as $field) {
                    $haystack = mb_strtolower((string) $post->{$field});

                    foreach ($phrases as $phrase) {
                        $offset = 0;
                        while (($pos = mb_strpos($haystack, mb_strtolower($phrase), $offset)) !== false) {
                            $start = max(0, $pos - 60);
                            $findings[] = [
                                'post_id' => $post->id,
                                'status' => $post->status,
                                'title' => Str::limit($post->title, 50),
                                'field' => $field,
                                'phrase' => $phrase,
                                'snippet' => '…'.trim(mb_substr((string) $post->{$field}, $start, mb_strlen($phrase) + 120)).'…',
                                'edit' => url("/admin/posts/{$post->id}/edit"),
                            ];
                            $offset = $pos + mb_strlen($phrase);
                        }
                    }
                }
            }
        });

        if ($this->option('json')) {
            $this->line(json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return empty($findings) ? self::SUCCESS : self::FAILURE;
        }

        if (empty($findings)) {
            $this->info('Clean — no testing-claim phrases found.');

            return self::SUCCESS;
        }

        $this->warn(count($findings).' testing-claim occurrence(s) found. These posts claim first-hand testing; rewrite to research-based framing (e.g. "verified-purchase owners consistently report…").');

        $this->table(
            ['Post', 'Status', 'Title', 'Field', 'Phrase', 'Snippet', 'Edit URL'],
            array_map(fn ($f) => [
                $f['post_id'],
                $f['status'],
                $f['title'],
                $f['field'],
                $f['phrase'],
                Str::limit($f['snippet'], 80),
                $f['edit'],
            ], $findings),
        );

        return self::FAILURE;
    }
}
