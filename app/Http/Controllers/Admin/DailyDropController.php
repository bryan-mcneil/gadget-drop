<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DailyDropImporterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Universal post importer: paste the pipeline's JSON (daily-drop/output.json)
 * and every post lands as a draft. Browser fallback for `php artisan
 * posts:import`; accepts all three post types (article, tech_tip, tech_news).
 */
class DailyDropController extends Controller
{
    public function __construct(private DailyDropImporterService $importer) {}

    public function index(): Response
    {
        return Inertia::render('Admin/DailyDrop/Index');
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'json_response' => 'required|string',
        ]);

        try {
            $posts = $this->importer->parseJson($request->json_response);
            $created = $this->importer->importAll($posts, User::siteAuthor()->id);

            return response()->json(['posts' => $created]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
