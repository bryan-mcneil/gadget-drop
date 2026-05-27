/**
 * Extracts the CSRF token from the meta tag and attaches it to all
 * fetch requests so Laravel's VerifyCsrfToken middleware passes.
 */
const token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    const originalFetch = window.fetch;
    window.fetch = (input, init = {}) => {
        init.headers = {
            'X-CSRF-TOKEN': token.content,
            ...init.headers,
        };
        return originalFetch(input, init);
    };
}
