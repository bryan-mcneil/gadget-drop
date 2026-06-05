import { useRef, useState, useCallback } from 'react';

const MAX_HARD = 20 * 1024 * 1024; // 20 MB
const MAX_WARN = 10 * 1024 * 1024; // 10 MB
const ACCEPT = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/tiff'];

export default function DropZone({ onFile }) {
    const inputRef = useRef(null);
    const [dragging, setDragging] = useState(false);
    const [error, setError] = useState(null);
    const [warning, setWarning] = useState(null);

    const process = useCallback((file) => {
        if (!file) return;
        setError(null);
        setWarning(null);

        if (!file.type.startsWith('image/')) {
            setError('Please upload an image file (JPG, PNG, WebP, etc.)');
            return;
        }
        if (file.size > MAX_HARD) {
            setError(`File is too large (${(file.size / 1024 / 1024).toFixed(1)} MB). Maximum is 20 MB.`);
            return;
        }
        if (file.size > MAX_WARN) {
            setWarning(`Large file (${(file.size / 1024 / 1024).toFixed(1)} MB). Processing may be slow on mobile.`);
        }

        const reader = new FileReader();
        reader.onload = (e) => onFile({ src: e.target.result, name: file.name, size: file.size, type: file.type });
        reader.readAsDataURL(file);
    }, [onFile]);

    const onDrop = useCallback((e) => {
        e.preventDefault();
        setDragging(false);
        process(e.dataTransfer.files[0]);
    }, [process]);

    const onDragOver = useCallback((e) => { e.preventDefault(); setDragging(true); }, []);
    const onDragLeave = useCallback(() => setDragging(false), []);
    const onInputChange = useCallback((e) => process(e.target.files[0]), [process]);

    return (
        <div className="space-y-3">
            <div
                onDrop={onDrop}
                onDragOver={onDragOver}
                onDragLeave={onDragLeave}
                onClick={() => inputRef.current?.click()}
                className={`relative flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed
                            cursor-pointer transition-colors py-14 px-6 text-center select-none
                            ${dragging
                                ? 'border-amber-400 bg-amber-50'
                                : 'border-gray-300 bg-gray-50 hover:border-amber-400 hover:bg-amber-50'}`}>
                <div className={`w-14 h-14 rounded-2xl flex items-center justify-center transition-colors
                                ${dragging ? 'bg-amber-100 text-amber-600' : 'bg-white text-gray-400 border border-gray-200'}`}>
                    <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round"
                            d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                </div>
                <div>
                    <p className="text-sm font-semibold text-gray-700">
                        {dragging ? 'Drop your image here' : 'Drag & drop an image here'}
                    </p>
                    <p className="text-xs text-gray-400 mt-1">or click to browse: JPG, PNG, WebP, GIF up to 20 MB</p>
                </div>
                <input
                    ref={inputRef}
                    type="file"
                    accept={ACCEPT.join(',')}
                    className="hidden"
                    onChange={onInputChange}
                />
            </div>

            {warning && (
                <div className="flex items-start gap-2 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800">
                    <svg className="w-4 h-4 flex-shrink-0 mt-0.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <span>{warning}</span>
                </div>
            )}

            {error && (
                <div className="flex items-start gap-2 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-800">
                    <svg className="w-4 h-4 flex-shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <span>{error}</span>
                </div>
            )}
        </div>
    );
}
