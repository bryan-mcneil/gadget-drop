@extends('layouts.public')

@php
    $cookieRow = function ($category, $name, $provider, $purpose, $duration, $essential = false) {
        return compact('category', 'name', 'provider', 'purpose', 'duration', 'essential');
    };
    $rows = [
        $cookieRow('Essential', 'XSRF-TOKEN, gadgetdrop_session', 'GadgetDrop', 'Security and session management. Required for the site to function.', 'Session / 2 hours', true),
        $cookieRow('Advertising', '__gads, __gpi, ANID, IDE, and others', 'Google AdSense', 'Used to serve and personalise advertisements based on your browsing activity across websites.', 'Up to 13 months', false),
        $cookieRow('Affiliate tracking', 'Server-side log only (no cookie)', 'GadgetDrop', 'When you click an Amazon affiliate link we log a hashed IP, referrer, and timestamp server-side. No cookie is set on your device.', 'N/A', true),
    ];
@endphp

@section('content')
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-3xl mx-auto px-4 py-12">
            <p class="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-2">Legal</p>
            <h1 class="text-3xl font-extrabold text-gray-900">Cookie Policy</h1>
            <p class="text-sm text-gray-400 mt-2">Last updated: May 2026</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 py-12 space-y-10">
        {{-- Preference manager --}}
        <div x-data="cookiePrefs" class="rounded-xl border p-5 space-y-3"
            :class="status === 'accepted' ? 'bg-emerald-50 border-emerald-200' : (status === 'rejected' ? 'bg-gray-50 border-gray-200' : 'bg-indigo-50 border-indigo-200')">
            <p class="text-sm font-semibold text-gray-900">Your current preference</p>
            <p class="text-sm text-gray-600">
                <span x-show="status === 'accepted'">You have accepted all cookies. Personalised ads and full analytics are enabled.</span>
                <span x-show="status === 'rejected'">You have rejected non-essential cookies. Only essential cookies are active. Ads shown are non-personalised.</span>
                <span x-show="!status">No preference set. Your choice will be requested when you next visit a page.</span>
            </p>
            <div class="flex flex-wrap gap-2">
                <button x-show="status !== 'accepted'" @click="accept"
                    class="px-4 py-1.5 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                    Accept all cookies
                </button>
                <button x-show="status !== 'rejected'" @click="reject"
                    class="px-4 py-1.5 text-xs font-medium border border-gray-300 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                    Reject non-essential
                </button>
                <button x-show="status" x-cloak @click="reset"
                    class="px-4 py-1.5 text-xs font-medium text-gray-400 hover:text-gray-600 transition-colors underline">
                    Clear preference
                </button>
            </div>
        </div>

        <section class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900">What are cookies?</h2>
            <p class="text-sm text-gray-600 leading-relaxed">
                Cookies are small text files stored on your device by your browser when you visit a website. They are widely used to make websites work, improve user experience, and provide information to site owners. Some cookies are essential for a site to function; others are used for analytics and advertising and require your consent under GDPR and similar laws.
            </p>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-bold text-gray-900">Cookies we use</h2>
            <div class="space-y-3">
                @foreach($rows as $row)
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div class="flex items-center gap-3 px-4 py-2.5 bg-gray-50 border-b border-gray-200">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $row['essential'] ? 'bg-gray-200 text-gray-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $row['essential'] ? 'Essential' : 'Non-essential' }}
                            </span>
                            <span class="text-xs font-semibold text-gray-700">{{ $row['category'] }}</span>
                        </div>
                        <div class="px-4 py-3 grid sm:grid-cols-2 gap-2 text-xs text-gray-600">
                            <div><span class="font-medium text-gray-800">Cookie name:</span> <span class="font-mono">{{ $row['name'] }}</span></div>
                            <div><span class="font-medium text-gray-800">Provider:</span> {{ $row['provider'] }}</div>
                            <div class="sm:col-span-2"><span class="font-medium text-gray-800">Purpose:</span> {{ $row['purpose'] }}</div>
                            <div><span class="font-medium text-gray-800">Duration:</span> {{ $row['duration'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900">Managing cookies in your browser</h2>
            <p class="text-sm text-gray-600 leading-relaxed">
                You can also control cookies directly through your browser settings. Most browsers allow you to view, block, and delete cookies. Note that blocking essential cookies may prevent parts of the site from working correctly.
            </p>
            <ul class="text-sm text-gray-600 space-y-1 list-disc list-inside">
                <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer" class="text-indigo-600 underline">Google Chrome</a></li>
                <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" rel="noopener noreferrer" class="text-indigo-600 underline">Mozilla Firefox</a></li>
                <li><a href="https://support.apple.com/en-gb/guide/safari/sfri11471/mac" target="_blank" rel="noopener noreferrer" class="text-indigo-600 underline">Apple Safari</a></li>
                <li><a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener noreferrer" class="text-indigo-600 underline">Microsoft Edge</a></li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900">Google advertising opt-out</h2>
            <p class="text-sm text-gray-600 leading-relaxed">
                You can opt out of Google personalised advertising across all sites at
                <a href="https://www.google.com/settings/ads" target="_blank" rel="noopener noreferrer" class="text-indigo-600 underline">google.com/settings/ads</a>
                or by installing the
                <a href="https://support.google.com/ads/answer/7395996" target="_blank" rel="noopener noreferrer" class="text-indigo-600 underline">Google Analytics Opt-out Browser Add-on</a>.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900">Contact</h2>
            <p class="text-sm text-gray-600">
                Questions about our use of cookies? Email
                <a href="mailto:hello@gadgetdrop.tech" class="text-indigo-600 underline">hello@gadgetdrop.tech</a>.
            </p>
        </section>
    </div>
@endsection
