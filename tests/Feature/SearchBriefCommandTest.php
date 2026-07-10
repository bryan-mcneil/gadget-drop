<?php

namespace Tests\Feature;

use App\Models\SearchOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SearchBriefCommandTest extends TestCase
{
    use RefreshDatabase;

    private function opportunity(string $kind, string $query, float $score, array $evidence = []): SearchOpportunity
    {
        return SearchOpportunity::create([
            'kind' => $kind,
            'query' => $query,
            'query_hash' => sha1($query),
            'score' => $score,
            'status' => 'open',
            'evidence' => $evidence + ['impressions' => 100, 'phrasings' => [['query' => $query]]],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    public function test_brief_renders_opportunities(): void
    {
        $this->opportunity('content_gap', 'best budget anc headphones', 40);
        $this->opportunity('rising', 'usb-c power bank fire sale', 25, ['rising' => ['recent' => 80, 'prior' => 10]]);

        $code = Artisan::call('search:brief');
        $out = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('SEO Brief', $out);
        $this->assertStringContainsString('best budget anc headphones', $out);
        $this->assertStringContainsString('Cadence is fixed', $out);
    }

    public function test_brief_falls_back_when_no_data(): void
    {
        $this->artisan('search:brief')
            ->expectsOutputToContain('No opportunities yet')
            ->assertSuccessful();
    }

    public function test_brief_write_flag_creates_the_file(): void
    {
        $this->opportunity('content_gap', 'best budget anc headphones', 40);
        $path = base_path('daily-drop/seo-brief.md');
        @unlink($path);

        $this->artisan('search:brief --write')
            ->expectsOutputToContain('seo-brief.md')
            ->assertSuccessful();

        $this->assertFileExists($path);
        $this->assertStringContainsString('best budget anc headphones', (string) file_get_contents($path));
        @unlink($path);
    }

    public function test_status_compact_summarises(): void
    {
        $this->opportunity('striking_distance', 'anker 737 review', 12);

        $this->artisan('search:status --compact')
            ->expectsOutputToContain('1 open opportunity')
            ->assertSuccessful();
    }
}
