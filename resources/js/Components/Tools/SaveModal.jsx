import { useState, useEffect, useRef, useCallback } from 'react';

const FORMATS = [
    {
        id: 'webp',
        label: 'WebP',
        mime: 'image/webp',
        ext: 'webp',
        description: 'Modern format, smallest file size',
        recommended: true,
        hasQuality: true,
    },
    {
        id: 'jpg',
        label: 'JPG',
        mime: 'image/jpeg',
        ext: 'jpg',
        description: 'Small files, great for photos',
        recommended: false,
        hasQuality: true,
    },
    {
        id: 'png',
        label: 'PNG',
        mime: 'image/png',
        ext: 'png',
        description: 'Lossless, ideal for graphics and transparency',
        recommended: false,
        hasQuality: false,
    },
];

function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function canvasToBlob(canvas, mime, quality) {
    return new Promise((resolve) => canvas.toBlob(resolve, mime, quality));
}

export default function SaveModal({ isOpen, onClose, getCanvas, originalName = 'image' }) {
    const [format, setFormat] = useState('webp');
    const [quality, setQuality] = useState(90);
    const [outputSize, setOutputSize] = useState(null);
    const [estimating, setEstimating] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const debounceRef = useRef(null);
    const blobRef = useRef(null);

    const selectedFormat = FORMATS.find(f => f.id === format);

    const estimate = useCallback(async () => {
        const canvas = getCanvas?.();
        if (!canvas) return;
        setEstimating(true);
        try {
            const blob = await canvasToBlob(canvas, selectedFormat.mime, selectedFormat.hasQuality ? quality / 100 : undefined);
            blobRef.current = blob;
            setOutputSize(blob?.size ?? null);
        } catch {
            setOutputSize(null);
        } finally {
            setEstimating(false);
        }
    }, [getCanvas, selectedFormat, quality]);

    useEffect(() => {
        if (!isOpen) return;
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(estimate, 300);
        return () => clearTimeout(debounceRef.current);
    }, [isOpen, estimate]);

    const handleDownload = useCallback(async () => {
        setDownloading(true);
        try {
            const blob = blobRef.current ?? await canvasToBlob(
                getCanvas(),
                selectedFormat.mime,
                selectedFormat.hasQuality ? quality / 100 : undefined
            );
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const base = originalName.replace(/\.[^.]+$/, '');
            a.href = url;
            a.download = `${base}.${selectedFormat.ext}`;
            a.click();
            URL.revokeObjectURL(url);
        } catch {
            // silent — canvas security errors (cross-origin) should not surface
        } finally {
            setDownloading(false);
        }
    }, [getCanvas, selectedFormat, quality, originalName]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Backdrop */}
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

            {/* Panel */}
            <div className="relative bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md text-white overflow-hidden">

                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                    <h2 className="text-base font-semibold">Save Image</h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-white transition-colors">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Format list */}
                <div className="px-6 pt-5 pb-2 space-y-2">
                    {FORMATS.map(f => (
                        <button
                            key={f.id}
                            onClick={() => { setFormat(f.id); blobRef.current = null; }}
                            className={`w-full flex items-center gap-4 px-4 py-3.5 rounded-xl border transition-all text-left
                                ${format === f.id
                                    ? 'border-amber-500 bg-amber-500/10'
                                    : 'border-gray-700 bg-gray-800 hover:border-gray-500'}`}>
                            <div className={`w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0
                                ${format === f.id ? 'bg-amber-500 text-white' : 'bg-gray-700 text-gray-300'}`}>
                                {f.label}
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium">{f.label}</span>
                                    {f.recommended && (
                                        <span className="text-[10px] font-bold uppercase tracking-wider
                                                         bg-amber-500 text-white px-1.5 py-0.5 rounded">
                                            Recommended
                                        </span>
                                    )}
                                </div>
                                <p className="text-xs text-gray-400 mt-0.5">{f.description}</p>
                            </div>
                            {format === f.id && (
                                <svg className="w-4 h-4 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clipRule="evenodd" />
                                </svg>
                            )}
                        </button>
                    ))}
                </div>

                {/* Quality slider — only for lossy formats */}
                {selectedFormat.hasQuality && (
                    <div className="px-6 py-4 border-t border-gray-700/50 mt-2">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-sm font-medium text-gray-200">Quality</span>
                            <span className="text-sm font-bold text-amber-400">{quality}%</span>
                        </div>
                        <input
                            type="range"
                            min={10}
                            max={100}
                            step={1}
                            value={quality}
                            onChange={e => { setQuality(Number(e.target.value)); blobRef.current = null; }}
                            className="w-full h-2 rounded-full appearance-none cursor-pointer
                                       bg-gray-700 accent-amber-500"
                        />
                        <div className="flex justify-between text-[10px] text-gray-500 mt-1.5">
                            <span>Smaller file</span>
                            <span>Best quality</span>
                        </div>

                        {/* Preset buttons */}
                        <div className="flex gap-2 mt-3">
                            {[['Low', 60], ['Med', 80], ['High', 90]].map(([label, val]) => (
                                <button
                                    key={label}
                                    onClick={() => { setQuality(val); blobRef.current = null; }}
                                    className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-colors
                                        ${quality === val
                                            ? 'bg-amber-500 text-white'
                                            : 'bg-gray-700 text-gray-300 hover:bg-gray-600'}`}>
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* File size estimate */}
                <div className="px-6 py-3 border-t border-gray-700/50">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-gray-400">Estimated size</span>
                        <span className={`font-semibold ${estimating ? 'text-gray-500' : 'text-green-400'}`}>
                            {estimating ? 'Calculating…' : formatBytes(outputSize)}
                        </span>
                    </div>
                </div>

                {/* Actions */}
                <div className="px-6 pb-6 pt-3 flex gap-3">
                    <button
                        onClick={onClose}
                        className="flex-1 py-2.5 rounded-xl border border-gray-600 text-gray-300
                                   hover:border-gray-400 hover:text-white transition-colors text-sm font-medium">
                        Cancel
                    </button>
                    <button
                        onClick={handleDownload}
                        disabled={downloading || estimating}
                        className="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl
                                   bg-amber-500 hover:bg-amber-400 disabled:bg-amber-700 disabled:text-amber-300
                                   text-white font-semibold text-sm transition-colors">
                        {downloading ? (
                            <>
                                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                Saving…
                            </>
                        ) : (
                            <>
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Save as {selectedFormat.label}
                            </>
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}
