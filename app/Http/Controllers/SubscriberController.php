<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriberController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|max:255']);

        if (Subscriber::where('email', $request->email)->exists()) {
            return response()->json(['message' => 'already_subscribed'], 409);
        }

        Subscriber::create([
            'email' => $request->email,
            'token' => (string) Str::uuid(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'subscribed']);
    }

    public function showUnsubscribe(): View
    {
        view()->share('serverMeta', [
            'title' => 'Unsubscribe | GadgetDrop',
            'description' => 'Unsubscribe from the GadgetDrop newsletter.',
            'og_image' => null,
            'og_type' => 'website',
            'canonical' => route('unsubscribe'),
        ]);

        return view('public.unsubscribe', [
            'status' => session('unsubscribe_status'),
        ]);
    }

    /** One-click unsubscribe via token link (used in email footers). */
    public function destroyByToken(string $token): RedirectResponse
    {
        Subscriber::where('token', $token)->delete();

        return redirect()->route('unsubscribe')
            ->with('unsubscribe_status', 'success');
    }

    /** Form-based unsubscribe — accepts email, returns JSON for fetch. */
    public function destroyByEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|max:255']);

        $deleted = Subscriber::where('email', $request->email)->delete();

        return $deleted
            ? response()->json(['message' => 'unsubscribed'])
            : response()->json(['message' => 'not_found'], 404);
    }
}
