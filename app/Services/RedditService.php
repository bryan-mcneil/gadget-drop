<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RedditService
{
    private string $userAgent = 'GadgetDrop:TechTipBot/1.0';

    /**
     * Search a subreddit for top posts matching a keyword.
     */
    public function search(string $query, string $subreddit = 'techsupport', string $time = 'month', int $limit = 10): array
    {
        $response = Http::withHeaders(['User-Agent' => $this->userAgent])
            ->timeout(15)
            ->get("https://www.reddit.com/r/{$subreddit}/search.json", [
                'q'           => $query,
                'sort'        => 'top',
                't'           => $time,
                'limit'       => $limit,
                'restrict_sr' => 1,
            ]);

        if (! $response->successful()) {
            throw new \Exception("Reddit API error ({$response->status()})");
        }

        return collect($response->json('data.children') ?? [])
            ->map(fn ($child) => $child['data'])
            ->filter(fn ($p) => ($p['score'] ?? 0) > 5 && ($p['num_comments'] ?? 0) > 3)
            ->filter(fn ($p) => ! in_array($p['selftext'] ?? '', ['', '[deleted]', '[removed]']))
            ->map(fn ($p) => [
                'id'           => $p['id'],
                'title'        => $p['title'],
                'subreddit'    => $p['subreddit'],
                'score'        => $p['score'],
                'num_comments' => $p['num_comments'],
                'permalink'    => $p['permalink'],
                'url'          => 'https://reddit.com' . $p['permalink'],
                'selftext'     => substr($p['selftext'] ?? '', 0, 280),
                'created_utc'  => $p['created_utc'],
            ])
            ->values()
            ->toArray();
    }

    /**
     * Fetch a thread's post body and top-level comments.
     */
    public function fetchThread(string $permalink): array
    {
        $permalink = '/' . ltrim(rtrim($permalink, '/'), '/');

        $response = Http::withHeaders(['User-Agent' => $this->userAgent])
            ->timeout(20)
            ->get("https://www.reddit.com{$permalink}.json", [
                'sort'  => 'top',
                'limit' => 25,
                'depth' => 1,
            ]);

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch Reddit thread ({$response->status()})");
        }

        $data     = $response->json();
        $postData = $data[0]['data']['children'][0]['data'] ?? [];

        if (empty($postData)) {
            throw new \Exception('Reddit thread not found or is empty.');
        }

        $post = [
            'id'        => $postData['id'],
            'title'     => $postData['title'],
            'selftext'  => $postData['selftext'] ?? '',
            'subreddit' => $postData['subreddit'],
            'score'     => $postData['score'],
            'url'       => 'https://reddit.com' . $postData['permalink'],
        ];

        $comments = collect($data[1]['data']['children'] ?? [])
            ->filter(fn ($c) => ($c['kind'] ?? '') === 't1')
            ->map(fn ($c) => $c['data'])
            ->filter(fn ($c) => ($c['score'] ?? 0) > 0
                && ! empty($c['body'])
                && ! in_array($c['body'], ['[deleted]', '[removed]']))
            ->sortByDesc('score')
            ->take(15)
            ->map(fn ($c) => [
                'score' => $c['score'],
                'body'  => substr($c['body'], 0, 800),
            ])
            ->values()
            ->toArray();

        return compact('post', 'comments');
    }
}
