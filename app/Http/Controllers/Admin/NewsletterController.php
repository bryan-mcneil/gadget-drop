<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\WeeklyDigest;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Newsletter', [
            'subscriberCount' => Subscriber::count(),
        ]);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        Mail::to($request->email)->send(new WeeklyDigest(
            unsubscribeUrl: url('/unsubscribe')
        ));

        return response()->json(['message' => 'Test email sent to '.$request->email]);
    }

    public function sendAll(): JsonResponse
    {
        $subscribers = Subscriber::all();

        foreach ($subscribers as $subscriber) {
            Mail::to($subscriber->email)->send(new WeeklyDigest(
                unsubscribeUrl: url('/unsubscribe/'.$subscriber->token)
            ));
        }

        return response()->json(['message' => "Weekly digest sent to {$subscribers->count()} subscribers."]);
    }
}
