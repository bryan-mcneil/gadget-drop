import { useState, useRef } from 'react';

/**
 * Drop-in replacement for image URL inputs.
 * Supports both pasting a URL and uploading a file directly.
 * Calls onChange(url) when either source resolves.
 */
export default function ImageUploader({
    value       = '',
    onChange,
    placeholder = 'Paste a URL or upload →',
    previewClass = 'h-32',
    previewFit  = 'cover',
}) {
    const [uploading, setUploading] = useState(false);
    const [error,     setError]     = useState(null);
    const fileRef = useRef(null);

    async function handleFile(e) {
        const file = e.target.files[0];
        if (!file) return;

        setUploading(true);
        setError(null);

        const body = new FormData();
        body.append('image', file);

        try {
            const res = await fetch(route('admin.images.store'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'Accept':       'application/json',
                },
                body,
            });

            if (!res.ok) {
                const json = await res.json().catch(() => ({}));
                throw new Error(json?.message ?? 'Upload failed');
            }

            const { url } = await res.json();
            onChange(url);
        } catch (err) {
            setError(err.message ?? 'Upload failed. Please try again.');
        } finally {
            setUploading(false);
            e.target.value = ''; // allow re-selecting the same file
        }
    }

    return (
        <div className="space-y-2">
            <div className="flex gap-2 items-center">
                {/* URL text input */}
                <input
                    type="text"
                    value={value}
                    onChange={(e) => { setError(null); onChange(e.target.value); }}
                    placeholder={placeholder}
                    className="flex-1 border-gray-300 rounded-lg shadow-sm text-sm"
                />

                {/* Upload button */}
                <button
                    type="button"
                    onClick={() => fileRef.current?.click()}
                    disabled={uploading}
                    className="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-sm
                               bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg border
                               border-gray-300 transition-colors disabled:opacity-50 whitespace-nowrap"
                >
                    {uploading ? (
                        <>
                            <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            Uploading…
                        </>
                    ) : (
                        <>
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            Upload
                        </>
                    )}
                </button>

                <input
                    ref={fileRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    className="hidden"
                    onChange={handleFile}
                />
            </div>

            {error && <p className="text-xs text-red-500">{error}</p>}

            {value && (
                <img
                    src={value}
                    alt="Preview"
                    className={`w-full ${previewClass} ${previewFit === 'contain' ? 'object-contain bg-gray-50 p-2' : 'object-cover'} rounded-lg border border-gray-200`}
                    onError={(e) => { e.target.style.display = 'none'; }}
                    onLoad={(e)  => { e.target.style.display = ''; }}
                />
            )}
        </div>
    );
}
