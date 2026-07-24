<?php

namespace Tests\Unit;

use App\Support\ArticleBody;
use PHPUnit\Framework\TestCase;

class ArticleBodyTest extends TestCase
{
    /** A realistic review-shaped body: intro, divided sections, list, quote. */
    private function longBody(): string
    {
        return implode("\n\n", [
            'Intro paragraph that sets up the review with enough words to carry weight.',
            'Second intro paragraph continuing the setup with a similar amount of text.',
            '---',
            '## What Is It',
            'Section one paragraph A, describing the product in reasonable detail here.',
            'Section one paragraph B, adding more descriptive detail for the reader.',
            'Section one paragraph C, closing out the first section of the review.',
            '---',
            '## Who Should Buy It',
            'Section two paragraph A, talking about the ideal buyer at some length.',
            'Section two paragraph B, adding nuance about who should skip this one.',
            '- Bullet one about a feature',
            '- Bullet two about another feature',
            '---',
            '## Verdict',
            'Verdict paragraph A, weighing everything up with a good amount of text.',
            'Verdict paragraph B, the closing recommendation for interested buyers.',
        ]);
    }

    public function test_images_render_only_between_paragraphs_of_running_text(): void
    {
        $sections = ArticleBody::sections($this->longBody(), ['img1.jpg', 'img2.jpg', 'img3.jpg'], ['cover', 'contain', 'cover']);

        $withImages = array_values(array_filter($sections, fn ($s) => $s['image'] !== null));
        $this->assertCount(3, $withImages);

        foreach ($sections as $i => $section) {
            if ($section['image'] === null) {
                continue;
            }
            // The chunk before an image always ends in running text…
            $this->assertStringEndsWith('</p>', trim($section['html']), "section $i should end with a paragraph before its image");
            // …and is never a heading hugging the image from above.
            $this->assertStringEndsNotWith('</h2>', trim($section['html']));
        }
    }

    public function test_image_never_lands_after_a_heading(): void
    {
        // Old splitter cut by block count, which could park an image directly
        // after the H2 (between the heading and its first paragraph).
        $body = "P1 text\n\nP2 text\n\n## Heading\n\nP3 text\n\nP4 text\n\nP5 text\n\n## Heading Two\n\nP6 text\n\nP7 text";

        $sections = ArticleBody::sections($body, ['img1.jpg'], ['cover']);

        foreach ($sections as $section) {
            if ($section['image'] !== null) {
                $this->assertStringEndsWith('</p>', trim($section['html']));
            }
        }
    }

    public function test_no_image_at_document_end_when_in_flow_slots_exist(): void
    {
        $sections = ArticleBody::sections($this->longBody(), ['img1.jpg', 'img2.jpg', 'img3.jpg'], []);

        $last = end($sections);
        $this->assertNull($last['image'], 'the final section must be image-free running text');
        $this->assertNotSame('', trim($last['html']));
    }

    public function test_images_keep_upload_order_and_fits(): void
    {
        $sections = ArticleBody::sections($this->longBody(), ['img1.jpg', 'img2.jpg', 'img3.jpg'], ['cover', 'contain', 'cover']);

        $images = array_values(array_map(
            fn ($s) => ['image' => $s['image'], 'fit' => $s['fit']],
            array_filter($sections, fn ($s) => $s['image'] !== null)
        ));

        $this->assertSame(['img1.jpg', 'img2.jpg', 'img3.jpg'], array_column($images, 'image'));
        $this->assertSame('contain', $images[1]['fit']);
    }

    public function test_captions_ride_with_their_image(): void
    {
        $sections = ArticleBody::sections(
            $this->longBody(),
            ['img1.jpg', 'img2.jpg', 'img3.jpg'],
            [],
            ['First caption', null, '  ']
        );

        $withImages = array_values(array_filter($sections, fn ($s) => $s['image'] !== null));

        $this->assertSame('First caption', $withImages[0]['caption']);
        $this->assertNull($withImages[1]['caption']);
        $this->assertNull($withImages[2]['caption'], 'whitespace-only captions normalise to null');
    }

    public function test_single_paragraph_sections_use_the_section_closing_slot(): void
    {
        // No paragraph→paragraph pair exists anywhere, so tier 1 is empty and
        // placement must fall back to tier 2: after the paragraph that closes
        // a section, before the next heading — never after the heading itself.
        $body = implode("\n\n", [
            'Intro paragraph standing alone before the first section heading.',
            '## Section One',
            'The only paragraph in section one, followed directly by a heading.',
            '## Section Two',
            'The only paragraph in section two, also followed by a heading.',
            '## Section Three',
            'The final paragraph of the document, closing out the article.',
        ]);

        $sections = ArticleBody::sections($body, ['img1.jpg'], []);

        $withImage = array_values(array_filter($sections, fn ($s) => $s['image'] !== null));
        $this->assertCount(1, $withImage);
        $this->assertStringEndsWith('</p>', trim($withImage[0]['html']));

        // The image is followed by more content (the next section's heading),
        // not parked at the document end.
        $last = end($sections);
        $this->assertNull($last['image']);
        $this->assertStringContainsString('<h2>', $last['html']);
    }

    public function test_short_body_still_renders_every_image(): void
    {
        $sections = ArticleBody::sections("Only paragraph here", ['img1.jpg', 'img2.jpg', 'img3.jpg'], []);

        $images = array_column(array_filter($sections, fn ($s) => $s['image'] !== null), 'image');
        $this->assertSame(['img1.jpg', 'img2.jpg', 'img3.jpg'], array_values($images));
    }

    public function test_skipped_image_slots_compact(): void
    {
        // image_2 empty: image_3 still renders, in order, nothing crashes.
        $sections = ArticleBody::sections($this->longBody(), ['img1.jpg', null, 'img3.jpg'], ['cover', 'cover', 'contain']);

        $images = array_values(array_filter($sections, fn ($s) => $s['image'] !== null));
        $this->assertSame(['img1.jpg', 'img3.jpg'], array_column($images, 'image'));
        $this->assertSame('contain', $images[1]['fit']);
    }

    public function test_hr_becomes_gradient_divider(): void
    {
        $sections = ArticleBody::sections("Intro paragraph\n\n---\n\nAfter the rule", [], []);
        $html = implode('', array_column($sections, 'html'));

        $this->assertStringContainsString('bg-gradient-to-r from-indigo-500', $html);
        $this->assertStringNotContainsString('<hr', $html);
    }

    public function test_blockquote_gets_indigo_callout_classes(): void
    {
        $sections = ArticleBody::sections('> A pulled quote', [], []);
        $html = implode('', array_column($sections, 'html'));

        $this->assertStringContainsString('not-prose', $html);
        $this->assertStringContainsString('border-indigo-400', $html);
    }

    public function test_markdown_is_converted_to_html(): void
    {
        $sections = ArticleBody::sections('**bold** text', [], []);

        $this->assertStringContainsString('<strong>bold</strong>', $sections[0]['html']);
    }

    public function test_empty_body_yields_no_sections(): void
    {
        $this->assertSame([], ArticleBody::sections('', [], []));
        $this->assertSame([], ArticleBody::sections(null, [], []));
    }

    public function test_empty_body_with_images_still_renders_them(): void
    {
        $sections = ArticleBody::sections('', ['img1.jpg'], ['contain'], ['A caption']);

        $this->assertCount(1, $sections);
        $this->assertSame('', $sections[0]['html']);
        $this->assertSame('img1.jpg', $sections[0]['image']);
        $this->assertSame('A caption', $sections[0]['caption']);
    }
}
