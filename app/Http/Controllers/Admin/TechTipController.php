<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RedditService;
use App\Services\TechTipGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TechTipController extends Controller
{
    public function __construct(
        private RedditService $reddit,
        private TechTipGeneratorService $generator,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/TechTips/Index');
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query'     => 'required|string|min:2|max:100',
            'subreddit' => 'required|string|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'time'      => 'nullable|in:hour,day,week,month,year,all',
        ]);

        try {
            $results = $this->reddit->search(
                $request->query,
                $request->subreddit,
                $request->time ?? 'month',
            );

            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch the Reddit thread and return the formatted prompt for the admin to paste into claude.ai.
     */
    public function prepare(Request $request): JsonResponse
    {
        $request->validate(['permalink' => 'required|string']);

        try {
            ['post' => $post, 'comments' => $comments] = $this->reddit->fetchThread($request->permalink);

            return response()->json([
                'prompt'     => $this->generator->buildPrompt($post, $comments),
                'source_url' => $post['url'],
                'title'      => $post['title'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Accept the JSON pasted back from claude.ai and create a draft post.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'source_url'    => 'required|string|max:500',
            'json_response' => 'required|string',
        ]);

        try {
            $data  = $this->generator->parseClaudeResponse($request->json_response);
            $draft = $this->generator->createDraftPost($data, $request->source_url, auth()->id());

            return response()->json([
                'redirect' => route('admin.posts.edit', $draft->id),
                'title'    => $draft->title,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
