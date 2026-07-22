<?php

namespace App\Http\Controllers;

use App\Models\PriceWatch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Magic-link endpoints for post-purchase price watches. Belt and suspenders:
 * the signature proves the link came from us (403 without it), the UUID token
 * identifies the watch (404 unknown). No accounts anywhere.
 */
class PriceWatchController extends Controller
{
    public function verify(Request $request, string $token): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $watch = PriceWatch::where('token', $token)->with('product')->firstOrFail();

        $alreadyVerified = $watch->verified_at !== null;

        if (! $alreadyVerified) {
            $watch->forceFill(['verified_at' => now()])->save();
        }

        return $this->confirmation($alreadyVerified ? 'already-verified' : 'verified', $watch);
    }

    public function unsubscribe(Request $request, string $token): View
    {
        abort_unless($request->hasValidSignature(), 403);

        // Idempotent by design: a second click on the same mail link lands on
        // the same "watch removed" page instead of a 404.
        PriceWatch::where('token', $token)->delete();

        return $this->confirmation('unsubscribed');
    }

    private function confirmation(string $state, ?PriceWatch $watch = null): View
    {
        view()->share('serverMeta', [
            'title' => 'Price watch | GadgetDrop',
            'description' => 'Manage your GadgetDrop price watch.',
            'og_image' => null,
            'og_type' => 'website',
            'canonical' => null,
            // Utility page reached only from email links — never indexable.
            'noindex' => true,
        ]);

        return view('public.watch-confirm', [
            'state' => $state,
            'watch' => $watch,
        ]);
    }
}
