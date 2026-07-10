<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_adsense_is_absent_while_disabled(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('adsbygoogle', false)
            ->assertDontSee('pagead2.googlesyndication.com', false);
    }

    public function test_adsense_renders_when_flag_enabled(): void
    {
        config(['services.adsense.enabled' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('pagead2.googlesyndication.com/pagead/js/adsbygoogle.js', false);
    }

    public function test_public_links_use_wire_navigate(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('wire:navigate', false);
    }

    public function test_pages_declare_max_image_preview_for_discover(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('name="robots" content="max-image-preview:large"', false);
    }
}
