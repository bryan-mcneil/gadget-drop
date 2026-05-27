<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tag;

class TechTipGeneratorService
{
    /**
     * Build the prompt to paste into Claude — no API call made here.
     */
    public function buildPrompt(array $post, array $comments): string
    {
        $commentsText = collect($comments)
            ->map(fn ($c, $i) => ($i + 1) . ". [Score: {$c['score']}]\n{$c['body']}")
            ->implode("\n\n---\n\n");

        return <<<PROMPT
You are writing a "Tech Tip" post for GadgetDrop, a popular tech gadget and tips website.

Transform this Reddit discussion into a clean, actionable Tech Tip article that ranks on Google and genuinely helps readers solve their tech problems.

**Reddit Thread:**
Subreddit: r/{$post['subreddit']}
Title: {$post['title']}
Post Body: {$post['selftext']}

**Top Community Answers (sorted by upvotes):**
{$commentsText}

**Writing Guidelines:**
- Title: SEO-friendly, answer the question directly. Do NOT start with "Reddit..."
- Write in second person ("you", not "users")
- Body structure: ## The Problem | ## The Fix (numbered steps) | ## Pro Tips | optionally ## Why This Works
- Use **bold** for key actions within steps
- Keep body 350–600 words — concise and genuinely helpful
- Lead with the most upvoted solution as the primary fix
- Mention fallbacks if the top solution doesn't work for everyone
- Tone: knowledgeable friend, not a manual

Respond with ONLY a valid JSON object — no markdown fences, no explanation, raw JSON only:
{
  "title": "SEO-friendly article title",
  "excerpt": "1-2 sentence summary answering the core question, max 160 chars",
  "body": "Full markdown body with ## headings, numbered steps, **bold** for key actions",
  "tags": ["tag1", "tag2", "tag3", "tag4"],
  "focus_keyword": "primary seo keyword phrase",
  "meta_title": "Meta title max 70 chars",
  "meta_description": "Meta description max 160 chars"
}
PROMPT;
    }

    /**
     * Parse raw text pasted back from Claude into a validated array.
     */
    public function parseClaudeResponse(string $text): array
    {
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```\s*$/m', '', $text);
        $text = trim($text);

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $text = $matches[0];
        }

        $data = json_decode($text, true);

        if (! $data || ! isset($data['title'], $data['body'])) {
            throw new \Exception('Could not parse as valid JSON. Make sure you copied Claude\'s full response.');
        }

        return $data;
    }

    /**
     * Persist the parsed data as a draft Post with tags and SEO meta.
     */
    public function createDraftPost(array $generated, string $sourceUrl, int $authorId): Post
    {
        $slug     = $this->slugify($generated['title']);
        $baseSlug = $slug;
        $i        = 1;

        while (Post::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        $tagIds = collect($generated['tags'] ?? [])
            ->map(fn ($name) => Tag::firstOrCreate(
                ['slug' => $this->slugify($name)],
                ['name' => ucwords($name)]
            ))
            ->pluck('id')
            ->toArray();

        $post = Post::create([
            'type'       => 'tech_tip',
            'title'      => $generated['title'],
            'slug'       => $slug,
            'excerpt'    => $generated['excerpt'] ?? '',
            'body'       => $generated['body'],
            'source_url' => $sourceUrl,
            'status'     => 'draft',
            'user_id'    => $authorId,
        ]);

        $post->tags()->sync($tagIds);

        $post->seoMeta()->create([
            'meta_title'       => $generated['meta_title']       ?? $generated['title'],
            'meta_description' => $generated['meta_description'] ?? $generated['excerpt'] ?? '',
            'focus_keyword'    => $generated['focus_keyword']    ?? '',
        ]);

        return $post;
    }

    private function slugify(string $str): string
    {
        $str = mb_strtolower(trim($str));
        $str = preg_replace('/[^\w\s-]/u', '', $str);
        $str = preg_replace('/[\s_]+/', '-', $str);

        return trim($str, '-');
    }
}
