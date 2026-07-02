<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use App\Support\NavigationData;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Re-attribute every post to a single real author and retire the fictional
 * personas. Run once locally and once on production. Idempotent: running it
 * again after consolidation is a no-op.
 *
 *   php artisan authors:consolidate --bio="..." --avatar="/storage/..." --retire-personas
 *
 * Name / slug / email default to config('site.author.*'). The real author is a
 * dedicated PUBLIC user, deliberately NOT the id-1 login account (the public
 * author page 404s for id 1 by design).
 */
class ConsolidateAuthor extends Command
{
    protected $signature = 'authors:consolidate
        {--name= : Public display name (defaults to config site.author.name)}
        {--slug= : URL slug (defaults to config site.author.slug)}
        {--email= : Match/create the author user by this email (defaults to config site.author.email)}
        {--bio= : Author bio (optional)}
        {--avatar= : avatar_url for the author (optional)}
        {--retire-personas : Delete the now post-less legacy persona users}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Re-attribute every post to one real author and optionally retire the fictional personas.';

    public function handle(): int
    {
        $name  = $this->option('name')  ?: config('site.author.name');
        $slug  = $this->option('slug')  ?: config('site.author.slug');
        $email = $this->option('email') ?: config('site.author.email');
        $dry   = (bool) $this->option('dry-run');
        $tag   = $dry ? '[dry-run] ' : '';

        if (! $name || ! $slug || ! $email) {
            $this->error('Need a name, slug and email. Set them in config/site.php or pass --name --slug --email.');
            return self::FAILURE;
        }

        // Resolve (or build) the real author — by email first, then slug.
        $author = User::where('email', $email)->first() ?? User::where('slug', $slug)->first();

        if ($author && $author->id === 1) {
            $this->error("Resolved to user id 1 (the login/system account). The public author page 404s for id 1 by design — use a different --email so a dedicated public author is created.");
            return self::FAILURE;
        }

        if (! $author) {
            $this->info("{$tag}Creating public author: {$name} <{$email}> (/author/{$slug})");
            $author = new User();
            $author->email    = $email;
            $author->password = Str::random(40); // hashed by cast; this account is not for login
        } else {
            $this->info("{$tag}Updating existing author: {$author->name} <{$author->email}> (#{$author->id})");
        }

        $author->name = $name;
        $author->slug = $slug;
        if ($this->option('bio'))    { $author->bio = $this->option('bio'); }
        if ($this->option('avatar')) { $author->avatar_url = $this->option('avatar'); }

        if (! $dry) {
            $author->save();
        }

        $authorId = $author->id; // null in dry-run if the user is new

        $total   = Post::count();
        $toMove  = $authorId ? Post::where('user_id', '!=', $authorId)->count() : $total;
        $this->info("{$tag}Re-attributing {$toMove} of {$total} post(s) to {$name}" . ($authorId ? " (#{$authorId})" : ''));

        if (! $dry) {
            Post::query()->update(['user_id' => $author->id]);
        }

        if ($this->option('retire-personas')) {
            // Only ever touch the known legacy persona slugs — never the author or
            // the id-1 login account — and only once they hold no posts.
            $personaSlugs = config('site.legacy_author_slugs', []);
            $personas = User::whereIn('slug', $personaSlugs)
                ->where('id', '!=', 1)
                ->when($authorId, fn ($q) => $q->where('id', '!=', $authorId))
                ->when(! $dry, fn ($q) => $q->whereDoesntHave('posts'))
                ->get();

            $this->info("{$tag}Retiring {$personas->count()} persona user(s): " . ($personas->pluck('slug')->implode(', ') ?: '—'));
            if (! $dry) {
                $personas->each->delete();
            }
        }

        if (! $dry) {
            NavigationData::flush();
        }

        $this->info("{$tag}Done.");
        return self::SUCCESS;
    }
}
