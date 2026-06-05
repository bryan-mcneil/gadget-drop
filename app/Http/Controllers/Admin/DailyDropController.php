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
            'authors' => User::where('id', '!=', 1)->orderBy('name')->get(['id', 'name']),
        ]);
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