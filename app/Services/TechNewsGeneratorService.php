<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tag;

class TechNewsGeneratorService
{
    /**
     * Build the prompt to paste into Claude — no API call made here.
     * Trending keywords are woven into the prompt instructions only, never stored.
     */
    public function buildPrompt(array $urls, string $context, string $trending, string $authorName, string $authorVoice): string
    {
        $urlList = collect($urls)
            ->filter()
            ->values()
            ->map(fn ($u, $i) => ($i + 1) . ". {$u}")
            ->implode("\n");

        $voiceBlock = $authorVoice
            ? "**Author Voice & Style ({$authorName}):**\n{$authorVoice}"
            : "**Author:** {$authorName} — write in a clear, direct, conversational tech journalist style.";

        $trendingBlock = $trending
            ? "**Google Trending Topics:**\nNaturally weave relevant terms from this list into the copy where they fit. Do NOT list them explicitly or reference Google Trends. Let them inform word choice and context only.\n{$trending}"
            : '';

        $contextBlock = $context
            ? "**Editor Notes / Additional Context:**\n{$context}"
            : '';

        return <<<PROMPT
You are writing a "Tech News" article for GadgetDrop, a gadget review site whose readers are BUYERS. GadgetDrop news covers consumer-gadget launches, price cuts, restocks, and spec refreshes — the news a person deciding what to buy actually needs. It does NOT cover general gaming/entertainment news with no buying angle.

{$voiceBlock}

**Source URLs to cover:**
{$urlList}

{$contextBlock}

{$trendingBlock}

**Task:**
Write a tech news article based on the source material above that adds real value beyond the source: what this means for someone about to spend money. Do not fabricate facts — stick only to what the sources cover. If the story has NO plausible purchase decision attached (e.g. pure entertainment news), say so instead of writing the article.

**Writing Guidelines:**
- Title: News-style headline, clear and factual. Under 80 characters.
- Lead paragraph: Answer who/what/when/why upfront (inverted pyramid)
- Body structure: Key facts → Context/Background → What it means for buyers → **required closing section `## Buy or Wait?`** with a practical, opinionated recommendation (buy now / wait for the refresh / skip and get X instead)
- Use **bold** for key terms, product names, and company names on first mention
- Keep body 400–700 words — punchy and scannable
- Tone: informed tech journalist, not hype. Let the facts speak; the Buy or Wait section is where the opinion lives.
- No affiliate product pitches in the body — the analysis IS the value
- Never claim first-hand testing ("we tested", "our measurements") — attribute claims to the source, the spec sheet, or owner feedback

Respond with ONLY a valid JSON object — no markdown fences, no explanation, raw JSON only:
{
  "title": "News headline under 80 chars",
  "excerpt": "1–2 sentence lede answering what happened and why it matters, max 160 chars",
  "body": "Full markdown body with ## headings, **bold** for key terms on first mention",
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
     * Persist the parsed data as a draft tech_news Post with tags and SEO meta.
     */
    public function createDraftPost(array $generated, ?string $sourceUrl, int $authorId): Post
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
            'type'       => 'tech_news',
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
