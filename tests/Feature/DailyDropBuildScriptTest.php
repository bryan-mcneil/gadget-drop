<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Process-based smoke test for bin/daily-drop-build.php (plain PHP, no Laravel
 * boot). The cloud agent and /morning rely on its exit codes: 0 = built
 * (warnings allowed), 1 = hard error (nothing written). Runs the script
 * against a temp working dir passed as argv[1].
 */
class DailyDropBuildScriptTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'drop-build-test-' . uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function run_build(): Process
    {
        $script  = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'daily-drop-build.php';
        $process = new Process([PHP_BINARY, $script, $this->dir]);
        $process->run();

        return $process;
    }

    private function write(string $name, string $content): void
    {
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . $name, $content);
    }

    private function reviewBlock(): string
    {
        $body = str_repeat('Owners consistently report solid results across the board. ', 100)
            . "\nSee [our other review](/posts/example-review).";

        return <<<MD
===POST===
AUTHOR: Bryan McNeil
TITLE: Anker 737 Power Bank Review: Worth the Premium Price?
EXCERPT: The Anker 737 packs 24,000mAh and 140W output. Here is who should buy it and who should grab the cheaper step-down pick instead.
TYPE: article
CATEGORY: Computers & Accessories
TAGS: power banks | anker
ASIN: B0ABCDEFGH
RATING: 4.4
PROS:
- 140W output charges laptops
CONS:
- Heavy at 1.4 lb
META_TITLE: Anker 737 Power Bank Review
META_DESCRIPTION: Is the Anker 737 worth it? Owner feedback, spec analysis, and who should buy the 24,000mAh 140W power bank this year.
FOCUS_KEYWORD: anker 737 review
TARGET_QUERY: anker 737 power bank review
SLUG: anker-737-power-bank-review
BODY:
{$body}
MD;
    }

    private function newsBlock(bool $withBuyOrWait = true, bool $withSource = true): string
    {
        $buyOrWait = $withBuyOrWait ? "\n## Buy or Wait?\n\nWait for the first discount." : '';
        $source    = $withSource ? 'SOURCE_URL: https://www.theverge.com/example-story' : '';
        $body      = str_repeat('The announcement matters for buyers weighing an upgrade this cycle. ', 70) . $buyOrWait;

        return <<<MD
===POST===
AUTHOR: Bryan McNeil
TITLE: Sony Just Cut the WH-1000XM5 Price Ahead of the XM6 Launch
EXCERPT: Sony dropped the WH-1000XM5 price ahead of the XM6 launch. What the cut means for buyers and whether waiting is the smarter move.
TYPE: tech_news
CATEGORY: Audio & Home Theater
TAGS: sony | headphones
{$source}
META_TITLE: Sony WH-1000XM5 Price Cut: Buy or Wait?
META_DESCRIPTION: Sony cut the WH-1000XM5 price ahead of the XM6 launch. Here is what the price cut means and whether you should buy now or wait.
FOCUS_KEYWORD: sony wh-1000xm5 price cut
SLUG: sony-wh-1000xm5-price-cut
BODY:
{$body}
MD;
    }

    public function test_builds_reviews_and_news_into_output_json(): void
    {
        $this->write('product-1.md', $this->reviewBlock());
        $this->write('news-1.md', $this->newsBlock());

        $process = $this->run_build();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());

        $posts = json_decode((string) file_get_contents($this->dir . DIRECTORY_SEPARATOR . 'output.json'), true);
        $this->assertCount(2, $posts);

        $byType = array_column($posts, null, 'type');
        $review = $byType['article'];
        $news   = $byType['tech_news'];
        $this->assertSame('article', $review['type']);
        $this->assertSame('B0ABCDEFGH', $review['product_asin']);
        $this->assertArrayNotHasKey('source_url', $review);

        $this->assertSame('tech_news', $news['type']);
        $this->assertSame('https://www.theverge.com/example-story', $news['source_url']);
        $this->assertArrayNotHasKey('product_asin', $news);
        $this->assertArrayNotHasKey('rating', $news);
    }

    public function test_target_query_is_carried_into_output_json(): void
    {
        $this->write('product-1.md', $this->reviewBlock());

        $process = $this->run_build();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        $posts = json_decode((string) file_get_contents($this->dir . DIRECTORY_SEPARATOR . 'output.json'), true);
        $this->assertSame('anker 737 power bank review', $posts[0]['seo']['target_query']);
    }

    public function test_news_without_source_url_is_a_hard_error(): void
    {
        $this->write('news-1.md', $this->newsBlock(withSource: false));

        $process = $this->run_build();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('SOURCE_URL', $process->getErrorOutput());
        $this->assertFileDoesNotExist($this->dir . DIRECTORY_SEPARATOR . 'output.json');
    }

    public function test_news_without_buy_or_wait_section_is_a_hard_error(): void
    {
        $this->write('news-1.md', $this->newsBlock(withBuyOrWait: false));

        $process = $this->run_build();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Buy or Wait', $process->getErrorOutput());
    }

    public function test_unknown_type_is_a_hard_error(): void
    {
        $this->write('product-1.md', str_replace('TYPE: article', 'TYPE: listicle', $this->reviewBlock()));

        $process = $this->run_build();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('unknown TYPE', $process->getErrorOutput());
    }

    public function test_no_files_is_a_hard_error(): void
    {
        $process = $this->run_build();

        $this->assertSame(1, $process->getExitCode());
    }
}
