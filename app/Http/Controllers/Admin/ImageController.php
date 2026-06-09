<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ImageVariants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:8192',
        ]);

        $path = $request->file('image')->store('uploads', 'public');

        // Generate responsive WebP variants alongside the original (no-op for
        // unsupported sources or when GD WebP is unavailable).
        ImageVariants::generate($path);

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
