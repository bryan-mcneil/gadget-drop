import { Head } from '@inertiajs/react';
import { useState } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';

const KEY = 'gadgetdrop_consent';

function grantConsent() {
    if (window.gtag) {
        window.gtag('consent', 'update', {
            ad_storage:         'granted',
            ad_user_data:       'granted',
            ad_personalization: 'granted',
            analytics_storage:  'granted',
        });
    }
}

export default function CookiesPage() {
    const current = typeof window !== 'undefined' ? localStorage.getItem(KEY) : null;
    const [status, setStatus] = useState(current);

    function accept() {
        localStorage.setItem(KEY, 'accepted');
        grantConsent();
        setStatus('accepted');
    }

    function reject() {
        localStorage.setItem(KEY, 'rejected');
        setStatus('rejected');
    }

    function reset() {
        localStorage.removeItem(KEY);
        setStatus(null);
    }

    return (
        <PublicLayout>
            <Head title="Cookie Policy | GadgetDrop">
                <meta name="description" content="GadgetDrop cookie policy. Learn what cookies we use, why, and how to manage your preferences." />
            </Head>

            <div className="bg-white border-b border-gray-100">
                <div className="max-w-3xl mx-auto px-4 py-12">
                    <p className="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-2">Legal</p>
                    <h1 className="text-3xl font-extrabold text-gray-900">Cookie Policy</h1>
                    <p className="text-sm text-gray-400 mt-2">Last updated: May 2026</p>
                </div>
            </div>

            <div className="max-w-3xl mx-auto px-4 py-12 space-y-10">

                {/* Preference manager */}
                <div className={`rounded-xl border p-5 space-y-3 ${
                    status === 'accepted' ? 'bg-emerald-50 border-emerald-200' :
                    status === 'rejected' ? 'bg-gray-50 border-gray-200' :
                    'bg-indigo-50 border-indigo-200'
                }`}>
                    <p className="text-sm font-semibold text-gray-900">Your current preference</p>
                    <p className="text-sm text-gray-600">
                        {status === 'accepted' && 'You have accepted all cookies. Personalised ads and full analytics are enabled.'}
                        {status === 'rejected' && 'You have rejected non-essential cookies. Only essential cookies are active. Ads shown are non-personalised.'}
                        {!status && 'No preference set. Your choice will be requested when you next visit a page.'}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {status !== 'accepted' && (
                            <button onClick={accept}
                                className="px-4 py-1.5 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                                Accept all cookies
                            </button>
                        )}
                        {status !== 'rejected' && (
                            <button onClick={reject}
                                className="px-4 py-1.5 text-xs font-medium border border-gray-300 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                                Reject non-essential
                            </button>
                        )}
                        {status && (
                            <button onClick={reset}
                                className="px-4 py-1.5 text-xs font-medium text-gray-400 hover:text-gray-600 transition-colors underline">
                                Clear preference
                            </button>
                        )}
                    </div>
                </div>

                {/* What are cookies */}
                <section className="space-y-3">
                    <h2 className="text-lg font-bold text-gray-900">What are cookies?</h2>
                    <p className="text-sm text-gray-600 leading-relaxed">
                        Cookies are small text files stored on your device by your browser when you visit a website. They are widely used to make websites work, improve user experience, and provide information to site owners. Some cookies are essential for a site to function; others are used for analytics and advertising and require your consent under GDPR and similar laws.
                    </p>
                </section>

                {/* Cookie table */}
                <section className="space-y-4">
                    <h2 className="text-lg font-bold text-gray-900">Cookies we use</h2>

                    <div className="space-y-3">
                        <CookieRow
                            category="Essential"
                            name="XSRF-TOKEN, gadgetdrop_session"
                            provider="GadgetDrop"
                            purpose="Security and session management. Required for the site to function."
                            duration="Session / 2 hours"
                            essential
                        />
                        <CookieRow
                            category="Advertising"
                            name="__gads, __gpi, ANID, IDE, and others"
                            provider="Google AdSense"
                            purpose="Used to serve and personalise advertisements based on your browsing activity across websites."
                            duration="Up to 13 months"
                        />
                        <CookieRow
                            category="Affiliate tracking"
                            name="Server-side log only (no cookie)"
                            provider="GadgetDrop"
                            purpose="When you click an Amazon affiliate link we log a hashed IP, referrer, and timestamp server-side. No cookie is set on your device."
                            duration="N/A"
                            essential
                        />
                    </div>
                </section>

                {/* Managing cookies */}
                <section className="space-y-3">
                    <h2 className="text-lg font-bold text-gray-900">Managing cookies in your browser</h2>
                    <p className="text-sm text-gray-600 leading-relaxed">
                        You can also control cookies directly through your browser settings. Most browsers allow you to view, block, and delete cookies. Note that blocking essential cookies may prevent parts of the site from working correctly.
                    </p>
                    <ul className="text-sm text-gray-600 space-y-1 list-disc list-inside">
                        <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer" className="text-indigo-600 underline">Google Chrome</a></li>
                        <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" rel="noopener noreferrer" className="text-indigo-600 underline">Mozilla Firefox</a></li>
                        <li><a href="https://support.apple.com/en-gb/guide/safari/sfri11471/mac" target="_blank" rel="noopener noreferrer" className="text-indigo-600 underline">Apple Safari</a></li>
                        <li><a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener noreferrer" className="text-indigo-600 underline">Microsoft Edge</a></li>
                    </ul>
                </section>

                {/* Google opt-out */}
                <section className="space-y-3">
                    <h2 className="text-lg font-bold text-gray-900">Google advertising opt-out</h2>
                    <p className="text-sm text-gray-600 leading-relaxed">
                        You can opt out of Google personalised advertising across all sites at{' '}
                        <a href="https://www.google.com/settings/ads" target="_blank" rel="noopener noreferrer" className="text-indigo-600 underline">google.com/settings/ads</a>{' '}
                        or by installing the{' '}
                        <a href="https://support.google.com/ads/answer/7395996" target="_blank" rel="noopener noreferrer" className="text-indigo-600 underline">Google Analytics Opt-out Browser Add-on</a>.
                    </p>
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-bold text-gray-900">Contact</h2>
                    <p className="text-sm text-gray-600">
                        Questions about our use of cookies? Email{' '}
                        <a href="mailto:hello@gadgetdrop.tech" className="text-indigo-600 underline">hello@gadgetdrop.tech</a>.
                    </p>
                </section>
            </div>
        </PublicLayout>
    );
}

function CookieRow({ category, name, provider, purpose, duration, essential = false }) {
    return (
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div className="flex items-center gap-3 px-4 py-2.5 bg-gray-50 border-b border-gray-200">
                <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${
                    essential ? 'bg-gray-200 text-gray-700' : 'bg-amber-100 text-amber-700'
                }`}>
                    {essential ? 'Essential' : 'Non-essential'}
                </span>
                <span className="text-xs font-semibold text-gray-700">{category}</span>
            </div>
            <div className="px-4 py-3 grid sm:grid-cols-2 gap-2 text-xs text-gray-600">
                <div><span className="font-medium text-gray-800">Cookie name:</span> <span className="font-mono">{name}</span></div>
                <div><span className="font-medium text-gray-800">Provider:</span> {provider}</div>
                <div className="sm:col-span-2"><span className="font-medium text-gray-800">Purpose:</span> {purpose}</div>
                <div><span className="font-medium text-gray-800">Duration:</span> {duration}</div>
            </div>
        </div>
    );
}
