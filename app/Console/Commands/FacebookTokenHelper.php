<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * The one-time Facebook token ritual, scripted. Input: the App ID + App Secret
 * (Meta app dashboard → Settings → Basic) and a short-lived user token from
 * the Graph API Explorer (generated with pages_manage_posts +
 * pages_read_engagement approved for the GadgetDrop Page). The command then:
 *
 *   1. exchanges it for a long-lived user token (~60 days), and
 *   2. calls /me/accounts with it — the Page tokens THAT returns never expire.
 *
 * Output is the exact .env block to paste. Re-run any time a token is revoked
 * (password change, app removal); nothing is stored anywhere by this command.
 */
class FacebookTokenHelper extends Command
{
    protected $signature = 'social:fb-token
        {--app-id= : Meta App ID (Settings → Basic)}
        {--app-secret= : Meta App Secret (same screen)}
        {--token= : Short-lived user token from the Graph API Explorer}';

    protected $description = 'Exchange a short-lived Graph API Explorer token for a never-expiring Page token.';

    public function handle(): int
    {
        $version = config('services.social.platforms.facebook.graph_version', 'v25.0');

        $appId = $this->option('app-id') ?: $this->ask('Meta App ID (app dashboard → Settings → Basic)');
        $appSecret = $this->option('app-secret') ?: $this->secret('App Secret (same screen — click Show)');
        $shortToken = $this->option('token') ?: $this->secret('Short-lived user token (Graph API Explorer → Generate Access Token, approving pages_manage_posts + pages_read_engagement)');

        if (! $appId || ! $appSecret || ! $shortToken) {
            $this->error('App ID, App Secret, and a short-lived token are all required.');

            return self::FAILURE;
        }

        $exchange = Http::acceptJson()->timeout(15)
            ->get("https://graph.facebook.com/{$version}/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $shortToken,
            ]);

        $longLived = $exchange->json('access_token');

        if ($exchange->failed() || ! $longLived) {
            $this->error('Token exchange failed: '.($exchange->json('error.message') ?? "HTTP {$exchange->status()}"));
            $this->line('Short-lived tokens expire in about an hour — generate a fresh one in the Graph API Explorer and retry.');

            return self::FAILURE;
        }

        $this->info('Long-lived user token acquired (~60 days). Fetching your Pages…');

        $accounts = Http::acceptJson()->timeout(15)
            ->get("https://graph.facebook.com/{$version}/me/accounts", [
                'access_token' => $longLived,
            ]);

        $pages = $accounts->json('data', []);

        if ($accounts->failed() || $pages === []) {
            $this->error($accounts->failed()
                ? '/me/accounts failed: '.($accounts->json('error.message') ?? "HTTP {$accounts->status()}")
                : 'No Pages returned — make sure the token was generated with the Page selected and pages_manage_posts approved.');

            return self::FAILURE;
        }

        foreach ($pages as $page) {
            $this->newLine();
            $this->line("<options=bold>{$page['name']}</> (id {$page['id']})");
            $this->line('Paste into .env (this Page token does not expire):');
            $this->newLine();
            $this->line("  FACEBOOK_PAGE_ID={$page['id']}");
            $this->line("  FACEBOOK_PAGE_TOKEN={$page['access_token']}");
        }

        $this->newLine();
        $this->info('Then: SOCIAL_FACEBOOK_ENABLED=true, SOCIAL_FACEBOOK_MODE=api — and on prod re-run `php artisan optimize` so the cached config picks it up.');

        return self::SUCCESS;
    }
}
