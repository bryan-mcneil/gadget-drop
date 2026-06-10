<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;

class DailyDropImporterService
{
    /**
     * Parse pasted text into an array of post data arrays.
     * Accepts a single JSON object or a JSON array of objects.
     */
    public function parseJson(string $text): array
    {
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```\s*$/m', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }

        // Wrap single object in an array
        if (isset($decoded['title'])) {
            $decoded = [$decoded];
        }

        if (! is_array($decoded) || empty($decoded)) {
            throw new \Exception('JSON must be a post object or an array of post objects.');
        }

        foreach ($decoded as $i => $item) {
            if (! isset($item['title'], $item['body'])) {
                throw new \Exception("Item #" . ($i + 1) . " is missing required fields: title and body.");
            }
        }

        return $decoded;
    }

    /**
     * Import an array of parsed post data as draft posts.
     * Returns an array of ['id' => ..., 'title' => ...] for each created post.
     */
    public function importAll(array $posts, int $userId): array
    {
        $created = [];

        foreach ($posts as $data) {
            $post      = $this->importOne($data, $userId);
            $created[] = ['id' => $post->id, 'title' => $post->title];
        }

        return $created;
    }

    public function importOne(array $data, int $userId): Post
    {
        $slug     = $this->slugify($data['title']);
        $baseSlug = $slug;
        $i        = 1;

        while (Post::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        // Resolve author by name, fall back to current user
        $authorId = $userId;
        if (! empty($data['author_name'])) {
            $author = User::whereRaw('LOWER(name) = ?', [strtolower($data['author_name'])])->first();
            if ($author) {
                $authorId = $author->id;
            }
        }

        // Resolve category (find or create)
        $categoryId = null;
        if (! empty($data['category_name'])) {
            $category   = Category::firstOrCreate(
                ['slug' => $this->slugify($data['category_name'])],
                ['name' => $data['category_name']],
            );
            $categoryId = $category->id;
        }

        // Resolve tags (find or create)
        $tagIds = collect($data['tag_names'] ?? [])
            ->map(fn ($name) => Tag::firstOrCreate(
                ['slug' => $this->slugify($name)],
                ['name' => ucwords($name)],
            ))
            ->pluck('id')
            ->toArray();

        $post = Post::create([
            'type'     => $data['type'] ?? 'article',
            'title'    => $data['title'],
            'slug'     => $slug,
            'excerpt'  => $data['excerpt'] ?? null,
            'body'     => $data['body'],
            'status'   => 'draft',
            'user_id'  => $authorId,
            'rating'   => $data['rating'] ?? null,
            'pros'     => ! empty($data['pros']) ? $data['pros'] : null,
            'cons'     => ! empty($data['cons']) ? $data['cons'] : null,
        ]);

        // Sync category
        if ($categoryId) {
            $post->categories()->sync([$categoryId]);
        }

        // Sync tags
        if ($tagIds) {
            $post->tags()->sync($tagIds);
        }

        // Attach product by ASIN
        if (! empty($data['product_asin'])) {
            $product = Product::where('asin', $data['product_asin'])->first();
            if ($product) {
                $post->products()->sync([$product->id => ['display_order' => 0]]);
            }
        }

        // Create SEO meta
        $seo = $data['seo'] ?? [];
        $post->seoMeta()->create([
            'meta_title'       => $seo['meta_title']       ?? $data['title'],
            'meta_description' => $seo['meta_description'] ?? $data['excerpt'] ?? '',
            'focus_keyword'    => $seo['focus_keyword']    ?? '',
        ]);

        return $post;
    }

    /**
     * Parse an Amazon product URL and extract the 10-character ASIN.
     * Returns null if no ASIN can be found.
     */
    public function parseAsin(string $url): ?string
    {
        if (preg_match('/\/dp\/([A-Z0-9]{10})/i', $url, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('/\/gp\/product\/([A-Z0-9]{10})/i', $url, $m)) {
            return strtoupper($m[1]);
        }
        // Bare ASIN (user pasted just the ID)
        if (preg_match('/^([A-Z0-9]{10})$/i', trim($url), $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /**
     * Build the Claude prompt for a single targeted product -- no API call made here.
     */
    public function buildPrompt(
        string $asin,
        string $productUrl,
        string $sourceContent,
        string $editorNotes,
        string $authorName,
        string $authorVoice,
    ): string {
        $voiceBlock = $authorVoice
            ? "**Author Voice & Style ({$authorName}):**\n{$authorVoice}"
            : "**Author:** {$authorName} -- write in a clear, direct, conversational tech reviewer style.";

        $contentBlock = $sourceContent
            ? "**Source Content (product details, reviews, specs):**\n{$sourceContent}"
            : '';

        $notesBlock = $editorNotes
            ? "**Editor Notes:**\n{$editorNotes}"
            : '';

        $extras = implode("\n\n", array_filter([$contentBlock, $notesBlock]));

        return <<<PROMPT
You are writing a product review post for GadgetDrop, a popular tech gadget and tips website.

{$voiceBlock}

**Product URL:** {$productUrl}
**ASIN:** {$asin} -- use this exact value in the product_asin field of your JSON output.

{$extras}

**Task:**
Write a compelling, publish-ready product review post that ranks on Google and drives purchase decisions. Do not fabricate specs or prices -- use only the source content provided.

**Post Structure (separate each section with a markdown --- horizontal rule):**

- **Hook paragraph** -- relatable problem or surprising fact. No heading. Never "In this article we will..."

---

- **What it is** -- Required SEO heading. Never the generic label "What It Is". Write an H2 containing the product name or focus keyword: e.g. ## What Is the Anker 737 Power Bank? Plain-language, no jargon.

---

- **Who it's for** -- Required SEO heading. Never generic. Frame the audience: e.g. ## Who Should Buy the Sony WH-1000XM5?

---

- **Key features** -- 3-5 bullets, benefits-first. Make the heading product-specific for SEO (## Key Features is a last resort).

---

- **Honest take** -- one genuine downside or "not for you if..." (builds trust). Product-specific heading.

---

- **FAQ** -- 3 questions a real buyer would Google before purchasing. Format:
  **Q: {question}**
  {2-3 sentence answer in the author's voice}
  Target "People Also Ask" style: comparisons, compatibility, "is it worth it", battery life, etc.

---

- **Verdict** -- punchy 2-sentence wrap-up.

---

- **CTA** -- a markdown link: [Check the current price on Amazon ->](https://www.amazon.com/dp/{$asin})

**Writing Rules:**
- Active voice, no filler words ("very", "really", "truly")
- Never start with "Overall" or "In conclusion"
- Do NOT include Amazon Associates disclosure (added automatically by the site)
- Use contractions: "you'll", "it's", "doesn't", "that's"
- Vary sentence length: mix short punchy sentences with longer explanatory ones
- One concrete number or comparison per section beats three vague adjectives
- If a sentence could appear unchanged in any product review, rewrite it to be specific to this product
- Body: 600-1000 words

**SEO Rules (apply before writing the JSON -- revise if score is below 75):**
- Title: 50-65 characters, focus keyword near the start, compelling
- Focus keyword in the first 100 words of the body
- H2 headings must contain keyword variants -- never generic labels alone
- Excerpt: 120-155 characters, includes keyword, entices the click
- Meta title: max 70 characters, keyword-first
- Meta description: 120-155 characters, includes keyword
- Slug: short, lowercase, hyphenated, keyword-first (e.g. "anker-737-power-bank-review")

**Banned patterns -- zero exceptions:**
- Em dashes (--) in any form. Use a comma, a period and new sentence, or restructure instead.
- "dive into", "deep dive", "let's dive"
- "game-changer" / "game changer"
- "it's worth noting", "worth noting"
- "seamlessly", "seamless integration"
- "unleash", "unlock your", "elevate your"
- "robust" as a feature adjective
- "cutting-edge" unless quoting the manufacturer verbatim
- "at the end of the day", "in today's world", "in today's fast-paced"
- "look no further"
- Opening a sentence with "Additionally," or "Furthermore,"
- Passive "is designed to" constructions

Respond with ONLY a valid JSON object -- no markdown fences, no explanation, raw JSON only:
{
  "title": "50-65 chars, focus keyword near start",
  "excerpt": "120-155 chars, entices the click, includes keyword",
  "body": "Full markdown body -- ## headings, --- dividers, **bold** preserved as a single JSON string",
  "type": "article",
  "author_name": "{$authorName}",
  "category_name": "one of: Audio, Smart Home, Wearables, Gaming, Accessories, Productivity, Photography, Computers, Monitors, or best-fit category",
  "tag_names": ["tag1", "tag2", "tag3", "tag4", "tag5"],
  "product_asin": "{$asin}",
  "rating": 4.2,
  "pros": ["benefit 1 in 5-10 words", "benefit 2", "benefit 3"],
  "cons": ["drawback 1 in 5-10 words"],
  "seo": {
    "score": 85,
    "meta_title": "max 70 chars, keyword-first",
    "meta_description": "120-155 chars, includes keyword",
    "focus_keyword": "primary seo keyword phrase",
    "slug": "keyword-first-hyphenated-slug"
  }
}
PROMPT;
    }

    private function slugify(string $str): string
    {
        return Str::slug($str);
    }
}