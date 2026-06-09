<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function publicRoutes(): array
    {
        return [
            'home'      => ['/', 'GadgetDrop'],
            'about'     => ['/about', 'What we do'],
            'privacy'   => ['/privacy', 'Privacy Policy'],
            'terms'     => ['/terms', 'Terms of Service'],
            'contact'   => ['/contact', 'Contact GadgetDrop'],
            'cookies'   => ['/cookies', 'Cookie Policy'],
            'news'      => ['/news', 'Tech News'],
            'search'    => ['/search', 'Find your next drop'],
            'tools'     => ['/tools', 'Online Tools'],
            'unsubscribe' => ['/unsubscribe', 'Unsubscribe'],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function test_public_page_renders_server_side(string $url, string $needle): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee($needle, false);
    }

    public static function toolRoutes(): array
    {
        return [
            ['/tools/json-validator', 'JSON Validator'],
            ['/tools/js-css-minifier', 'Minifier'],
            ['/tools/image-editor', 'Image Editor'],
            ['/tools/image-converter', 'Image Converter'],
            ['/tools/image-cropper', 'Image Cropper'],
            ['/tools/background-remover', 'Background Remover'],
            ['/tools/password-generator', 'Password Generator'],
            ['/tools/base64-encoder', 'Base64 Encoder'],
            ['/tools/color-palette', 'Color Palette'],
            ['/tools/meta-tag-previewer', 'Meta Tag Previewer'],
        ];
    }

    #[DataProvider('toolRoutes')]
    public function test_tool_page_renders(string $url, string $needle): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee($needle, false);
    }

    public function test_unknown_url_returns_blade_404(): void
    {
        $this->get('/no-such-page-xyz')
            ->assertNotFound()
            ->assertSee('Page not found', false);
    }
}
