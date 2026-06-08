<?php

namespace Tests\Unit;

use App\Support\ArticleBody;
use PHPUnit\Framework\TestCase;

class ArticleBodyTest extends TestCase
{
    public function test_splits_body_into_three_sections_with_images_in_order(): void
    {
        $body = "Para one\n\n---\n\n> A quote\n\nPara four\n\nPara five\n\nPara six";

        $sections = ArticleBody::sections($body, ['img1.jpg', 'img2.jpg', 'img3.jpg'], ['cover', 'contain', 'cover']);

        $this->assertCount(3, $sections);
        $this->assertSame('img1.jpg', $sections[0]['image']);
        $this->assertSame('img2.jpg', $sections[1]['image']);
        $this->assertSame('img3.jpg', $sections[2]['image']);
        $this->assertSame('contain', $sections[1]['fit']);
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
        $sections = ArticleBody::sections("> A pulled quote", [], []);
        $html = implode('', array_column($sections, 'html'));

        $this->assertStringContainsString('not-prose', $html);
        $this->assertStringContainsString('border-indigo-400', $html);
    }

    public function test_markdown_is_converted_to_html(): void
    {
        $sections = ArticleBody::sections('**bold** text', [], []);

        $this->assertStringContainsString('<strong>bold</strong>', $sections[0]['html']);
    }

    public function test_empty_body_yields_empty_sections(): void
    {
        $sections = ArticleBody::sections('', [], []);

        $this->assertCount(3, $sections);
        $this->assertSame('', $sections[0]['html']);
    }
}
