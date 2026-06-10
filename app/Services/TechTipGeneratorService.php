<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Str;

class TechTipGeneratorService
{
    /**
     * Build the prompt to paste into Claude -- no API call made here.
     */
    public function buildPrompt(
        array $urls,
        string $sourceContent,
        string $editorNotes,
        string $authorName,
        string $authorVoice,
    ): string {
        $urlList = collect($urls)
            ->filter()
            ->values()
            ->map(fn ($u, $i) => ($i + 1) . ". {$u}")
            ->implode("\n");

        $voiceBlock = $authorVoice
            ? "**Author Voice & Style ({$authorName}):**\n{$authorVoice}"
            : "**Author:** {$authorName} -- write in a clear, direct, conversational style. Knowledgeable friend, not a manual.";

        $urlBlock = $urlList
            ? "**Source URLs:**\n{$urlList}"
            : '';

        $contentBlock = $sourceContent
            ? "**Source Content (paste from the page):**\n{$sourceContent}"
            : '';

        $notesBlock = $editorNotes
            ? "**Editor Notes:**\n{$editorNotes}"
            : '';

        $extras = implode("\n\n", array_filter([$urlBlock, $contentBlock, $notesBlock]));

        return <<<PROMPT
You are writing a "Tech Tip" post for GadgetDrop, a popular tech gadget and tips website.

{$voiceBlock}

{$extras}

**Task:**
Transform the source material into a clean, actionable Tech Tip article that ranks on Google and genuinely helps readers solve their tech problems. Do not fabricate facts -- stick only to what the sources cover.

**Post Structure:**
- **Intro paragraph** -- describe the problem in concrete terms. No heading. Never "In this article we will..."
- **## The Fix** (or a specific H2 containing the keyword, e.g. "## How to Fix Wi-Fi Dropping on Windows 11") -- numbered steps, **bold** for key actions within each step
- **## Pro Tips** (or equivalent specific heading) -- 2-3 power-user extras, edge cases, or fallbacks
- Optional: **## Why This Works** -- a brief plain-English explanation if the mechanism is non-obvious

**Writing Rules:**
- Write in second person ("you", not "users")
- Keep body 350-600 words -- concise and genuinely helpful
- Lead with the most effective solution
- Mention fallbacks if the primary solution does not work for everyone
- Active voice throughout, no filler words ("very", "really", "truly")
- Use contractions: "you'll", "it's", "doesn't", "that's"
- Vary sentence length: mix short punchy sentences with longer explanatory ones
- One concrete number or real-world comparison per section beats three vague adjectives
- If a sentence could appear unchanged in any tech tip, rewrite it to be specific to this problem

**SEO Rules (apply before writing the JSON -- revise if score is below 75):**
- Title: 50-65 characters, focus keyword near the start, answers the question directly. Do NOT start with "Reddit..."
- Focus keyword must appear in the first 100 words of the body
- H2 headings must contain keyword variants -- never generic labels like "The Fix" alone; make them specific ("How to Fix Bluetooth Audio Lag on Android")
- Excerpt: 120-155 characters, answers the core question, includes the focus keyword
- Meta title: max 70 characters, keyword-first
- Meta description: 120-155 characters, includes keyword, entices the click
- Slug: short, lowercase, hyphenated, keyword-first (e.g. "fix-wifi-dropping-windows-11")

**Banned patterns -- zero exceptions:**
- Em dashes (--) in any form. Use a comma, a period and new sentence, or restructure instead.
- "dive into", "deep dive", "let's dive"
- "game-changer" / "game changer"
- "it's worth noting", "worth noting"
- "seamlessly", "seamless integration"
- "unleash", "unlock your", "elevate your"
- "robust" as a feature adjective
- "cutting-edge"
- "at the end of the day", "in today's world", "in today's fast-paced"
- "look no further"
- Opening a sentence with "Additionally," or "Furthermore,"
- Passive "is designed to" constructions

Respond with ONLY a valid JSON object -- no markdown fences, no explanation, raw JSON only:
{
  "title": "SEO-friendly article title, 50-65 chars, keyword near start",
  "excerpt": "1-2 sentence summary answering the core question, 120-155 chars, includes keyword",
  "body": "Full markdown body -- ## headings, numbered steps, **bold** for key actions",
  "category_name": "one category (e.g. Windows, Networking, Android, iPhone, Productivity, Security, macOS)",
  "tag_names": ["tag1", "tag2", "tag3", "tag4"],
  "seo": {
    "score": 85,
    "meta_title": "meta title max 70 chars, keyword-first",
    "meta_description": "meta description 120-155 chars, includes keyword",
    "focus_keyword": "primary seo keyword phrase",
    "slug": "keyword-first-hyphenated-slug"
  }
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
     * Persist the parsed data as a draft tech_tip Post with category, tags, and SEO meta.
     * Supports both the legacy flat schema (tags, focus_keyword, meta_title, meta_description)
     * and the enriched schema (seo object, category_name, tag_names, seo.slug).
     */
    public function createDraftPost(array $generated, ?string $sourceUrl, int $authorId): Post
    {
        $seo  = $generated['seo'] ?? [];
        $slug = ! empty($seo['slug'])
            ? Str::slug($seo['slug'])
            : $this->slugify($generated['title']);

        $baseSlug = $slug;
        $i        = 1;
        while (Post::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        // Support both tag_names (enriched) and tags (legacy)
        $tagNames = $generated['tag_names'] ?? $generated['tags'] ?? [];
        $tagIds   = collect($tagNames)
            ->map(fn ($name) => Tag::firstOrCreate(
                ['slug' => $this->slugify($name)],
                ['name' => ucwords($name)],
            ))
            ->pluck('id')
            ->toArray();

        // Resolve category
        $categoryId = null;
        if (! empty($generated['category_name'])) {
            $category   = Category::firstOrCreate(
                ['slug' => $this->slugify($generated['category_name'])],
                ['name' => $generated['category_name']],
            );
            $categoryId = $category->id;
        }

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

        if ($categoryId) {
            $post->categories()->sync([$categoryId]);
        }

        $post->tags()->sync($tagIds);

        $post->seoMeta()->create([
            'meta_title'       => $seo['meta_title']       ?? $generated['meta_title']       ?? $generated['title'],
            'meta_description' => $seo['meta_description'] ?? $generated['meta_description'] ?? $generated['excerpt'] ?? '',
            'focus_keyword'    => $seo['focus_keyword']    ?? $generated['focus_keyword']    ?? '',
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
