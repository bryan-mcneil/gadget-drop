import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';

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

export default function CookieConsent() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (!localStorage.getItem(KEY)) {
            // Short delay so the page renders first
            const t = setTimeout(() => setVisible(true), 800);
            return () => clearTimeout(t);
        }
    }, []);

    function accept() {
        localStorage.setItem(KEY, 'accepted');
        grantConsent();
        setVisible(false);
    }

    function reject() {
        localStorage.setItem(KEY, 'rejected');
        setVisible(false);
    }

    if (!visible) return null;

    return (
        <div
            role="dialog"
            aria-label="Cookie consent"
            className="fixed bottom-0 left-0 right-0 z-[9998] p-4 sm:p-6"
        >
            <div className="max-w-4xl mx-auto bg-white border border-gray-200 rounded-2xl shadow-2xl
                            flex flex-col sm:flex-row items-start sm:items-center gap-4 p-5">
                {/* Icon */}
                <div className="flex-shrink-0 w-10 h-10 rounded-xl bg-indigo-100
                                flex items-center justify-center text-lg select-none">
                    🍪
                </div>

                {/* Text */}
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-900 mb-0.5">We use cookies</p>
                    <p className="text-xs text-gray-500 leading-relaxed">
                        GadgetDrop uses cookies for advertising (Google AdSense) and affiliate link tracking.
                        Non-essential cookies are only set with your consent.{' '}
                        <Link href={route('cookies')} className="text-indigo-500 underline hover:text-indigo-700">
                            Cookie Policy
                        </Link>
                    </p>
                </div>

                {/* Buttons */}
                <div className="flex items-center gap-2 flex-shrink-0 w-full sm:w-auto">
                    <button
                        onClick={reject}
                        className="flex-1 sm:flex-none px-4 py-2 text-xs font-medium text-gray-600
                                   border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Reject non-essential
                    </button>
                    <button
                        onClick={accept}
                        className="flex-1 sm:flex-none px-4 py-2 text-xs font-semibold text-white
                                   bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                        Accept all
                    </button>
                </div>
            </div>
        </div>
    );
}
