/**
 * Extracts the CSRF token from the meta tag and attaches it to same-origin
 * fetch requests so Laravel's VerifyCsrfToken middleware passes. Cross-origin
 * requests (the HLS downloader reads video CDNs directly) are left untouched:
 * the token is ours alone, and the extra header would force a CORS preflight
 * that most CDNs refuse.
 */
const token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    const originalFetch = window.fetch;
    window.fetch = (input, init = {}) => {
        let origin = null;
        try {
            origin = new URL(input instanceof Request ? input.url : String(input), window.location.href).origin;
        } catch {
            // Not a URL; let fetch() itself reject it.
        }

        if (origin !== window.location.origin) {
            return originalFetch(input, init);
        }

        init.headers = {
            'X-CSRF-TOKEN': token.content,
            ...init.headers,
        };
        return originalFetch(input, init);
    };
}
