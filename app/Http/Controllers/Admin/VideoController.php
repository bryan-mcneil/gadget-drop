<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Recap-video upload (plan 10.7). Same shape as ImageController: the admin
 * form uploads here first and stores the returned path on the post. Videos
 * are self-hosted only — storage/app/public/recaps, served through the
 * public symlink and fronted by hcdn; never an external video origin.
 */
class VideoController extends Controller
{
    /** Upload cap in kilobytes (64 MB — a 30 s 1080p recap is ~5-10 MB). */
    public const MAX_KB = 65536;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/webm|max:'.self::MAX_KB,
        ]);

        $path = $request->file('video')->store('recaps', 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
