import { useEffect, useRef } from 'react';

export default function AdUnit({ slot, format = 'auto', className = '' }) {
    const ref = useRef(null);

    useEffect(() => {
        // Guard: adsbygoogle may not be loaded yet on very first render
        if (typeof window === 'undefined') return;

        try {
            (window.adsbygoogle = window.adsbygoogle || []).push({});
        } catch {
            // Silently ignore if the slot was already initialized
        }
    }, []);

    return (
        <div ref={ref} className={`overflow-hidden ${className}`}>
            <ins
                className="adsbygoogle"
                style={{ display: 'block' }}
                data-ad-client="ca-pub-3856395634564582"
                data-ad-slot={slot}
                data-ad-format={format}
                data-full-width-responsive="true"
            />
        </div>
    );
}
