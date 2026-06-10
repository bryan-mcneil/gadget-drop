<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DailyDropImporterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyDropController extends Controller
{
    public function __construct(private DailyDropImporterService $importer) {}

    public function index(): Response
    {
        return Inertia::render('Admin/DailyDrop/Index', [
            'authors' => User::where('id', '!=', 1)->orderBy('name')->get(['id', 'name', 'voice']),
        ]);
    }

    /**
     * Parse the Amazon URL, build the Claude prompt, and return it for copy/paste.
     */
    public function buildPrompt(Request $request): JsonResponse
    {
        $request->validate([
            'product_url'    => 'required|string|max:1000',
            'source_content' => 'nullable|string|max:10000',
            'editor_notes'   => 'nullable|string|max:3000',
            'user_id'        => 'required|exists:users,id',
        ]);

        $asin = $this->importer->parseAsin($request->product_url);

        if (! $asin) {
            return response()->json(['error' => 'Could not find an ASIN in that URL. Use a link like amazon.com/dp/B0XXXXXXXXX or paste the ASIN directly.'], 422);
        }

        $author = User::findOrFail($request->user_id);

        $prompt = $this->importer->buildPrompt(
            asin:          $asin,
            productUrl:    $request->product_url,
            sourceContent: $request->source_content ?? '',
            editorNotes:   $request->editor_notes ?? '',
            authorName:    $author->name,
            authorVoice:   $author->voice ?? '',
        );

        return response()->json(['prompt' => $prompt, 'asin' => $asin]);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'json_response' => 'required|string',
            'user_id'       => 'nullable|exists:users,id',
        ]);

        try {
            $posts   = $this->importer->parseJson($request->json_response);
            $created = $this->importer->importAll($posts, $request->user_id ?? auth()->id());

            return response()->json(['posts' => $created]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}