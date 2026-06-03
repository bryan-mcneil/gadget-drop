<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TechNewsGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    public function __construct(private TechNewsGeneratorService $generator) {}

    public function index(): Response
    {
        return Inertia::render('Admin/News/Index', [
            'authors' => User::where('id', '!=', 1)->orderBy('name')->get(['id', 'name', 'voice']),
        ]);
    }

    /**
     * Build the Claude prompt from the form inputs and return it to the UI for copy/paste.
     */
    public function buildPrompt(Request $request): JsonResponse
    {
        $request->validate([
            'source_urls'       => 'required|string',
            'context'           => 'nullable|string|max:3000',
            'trending_keywords' => 'nullable|string|max:3000',
            'user_id'           => 'required|exists:users,id',
        ]);

        $author = User::findOrFail($request->user_id);
        $urls   = array_values(array_filter(array_map('trim', explode("\n", $request->source_urls))));

        $prompt = $this->generator->buildPrompt(
            urls:        $urls,
            context:     $request->context ?? '',
            trending:    $request->trending_keywords ?? '',
            authorName:  $author->name,
            authorVoice: $author->voice ?? '',
        );

        return response()->json(['prompt' => $prompt]);
    }

    /**
     * Accept the JSON pasted back from claude.ai and create a draft tech_news post.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'source_url'    => 'nullable|url|max:500',
            'json_response' => 'required|string',
            'user_id'       => 'nullable|exists:users,id',
        ]);

        try {
            $data  = $this->generator->parseClaudeResponse($request->json_response);
            $draft = $this->generator->createDraftPost(
                $data,
                $request->source_url,
                $request->user_id ?? auth()->id(),
            );

            return response()->json([
                'redirect' => route('admin.posts.edit', $draft->id),
                'title'    => $draft->title,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
