import { useState, useRef, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolSidebar from '@/Components/Tools/ToolSidebar';
import DropZone from '@/Components/Tools/DropZone';
import SaveModal from '@/Components/Tools/SaveModal';

function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function ImageConverter({ sidebarProducts = [], metaTitle, metaDescription }) {
    const [image, setImage] = useState(null); // { src, name, size, type }
    const [width, setWidth] = useState('');
    const [height, setHeight] = useState('');
    const [aspectLocked, setAspectLocked] = useState(true);
    const [modalOpen, setModalOpen] = useState(false);
    const imgRef = useRef(null);
    const naturalSize = useRef({ w: 0, h: 0 });

    const handleFile = useCallback((file) => {
        setImage(file);
        setModalOpen(false);
        // Load the image to get natural dimensions
        const img = new Image();
        img.onload = () => {
            naturalSize.current = { w: img.naturalWidth, h: img.naturalHeight };
            setWidth(String(img.naturalWidth));
            setHeight(String(img.naturalHeight));
        };
        img.src = file.src;
    }, []);

    const handleWidthChange = useCallback((val) => {
        const n = parseInt(val, 10);
        setWidth(val);
        if (aspectLocked && naturalSize.current.w && !isNaN(n) && n > 0) {
            const ratio = naturalSize.current.h / naturalSize.current.w;
            setHeight(String(Math.round(n * ratio)));
        }
    }, [aspectLocked]);

    const handleHeightChange = useCallback((val) => {
        const n = parseInt(val, 10);
        setHeight(val);
        if (aspectLocked && naturalSize.current.h && !isNaN(n) && n > 0) {
            const ratio = naturalSize.current.w / naturalSize.current.h;
            setWidth(String(Math.round(n * ratio)));
        }
    }, [aspectLocked]);

    const getCanvas = useCallback(() => {
        if (!image) return null;
        const img = imgRef.current;
        if (!img) return null;
        const w = Math.max(1, parseInt(width, 10) || img.naturalWidth);
        const h = Math.max(1, parseInt(height, 10) || img.naturalHeight);
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, w, h);
        return canvas;
    }, [image, width, height]);

    const reset = useCallback(() => {
        setImage(null);
        setWidth('');
        setHeight('');
        setModalOpen(false);
    }, []);

    const originalW = naturalSize.current.w;
    const originalH = naturalSize.current.h;

    return (
        <PublicLayout>
            <Head title={metaTitle}>
                <meta name="description" content={metaDescription} />
            </Head>

            {/* Page header */}
            <div className="bg-white border-b border-gray-100">
                <div className="max-w-[100rem] mx-auto px-4 py-10">
                    <div className="flex items-center gap-2 mb-2">
                        <span className="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700
                                         text-xs font-semibold uppercase tracking-widest px-2.5 py-1 rounded-full">
                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.598-2.167A9.027 9.027 0 0 1 9.496 3.28c-1.586.068-3.07.817-4.188 2.015L4.5 6.122" />
                            </svg>
                            Tool
                        </span>
                    </div>
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Image Converter</h1>
                    <p className="mt-2 text-gray-500 max-w-2xl">
                        Upload an image, resize it, then save as JPG, PNG, or WebP.
                        Everything runs in your browser, nothing is sent to a server.
                    </p>
                </div>
            </div>

            {/* Main content */}
            <div className="max-w-[100rem] mx-auto px-4 py-8">
                <div className="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">

                    {/* Tool panel */}
                    <div className="space-y-6">

                        {!image ? (
                            <DropZone onFile={handleFile} />
                        ) : (
                            <>
                                {/* Image preview */}
                                <div className="bg-gray-950 rounded-2xl overflow-hidden border border-gray-800">
                                    <div className="flex items-center justify-between px-4 py-2.5 border-b border-gray-800">
                                        <span className="text-xs text-gray-400 font-mono truncate max-w-[60%]">
                                            {image.name}
                                        </span>
                                        <span className="text-xs text-gray-500">{formatBytes(image.size)}</span>
                                    </div>
                                    <div className="p-4 flex items-center justify-center min-h-[280px] max-h-[520px] overflow-hidden">
                                        <img
                                            ref={imgRef}
                                            src={image.src}
                                            alt="Preview"
                                            className="max-w-full max-h-[480px] rounded-xl object-contain"
                                        />
                                    </div>
                                </div>

                                {/* Resize controls */}
                                <div className="bg-white border border-gray-200 rounded-2xl p-5">
                                    <h2 className="text-sm font-semibold text-gray-700 mb-4">Resize (optional)</h2>

                                    <div className="flex items-center gap-3">
                                        {/* Width */}
                                        <div className="flex-1 space-y-1">
                                            <label className="text-xs text-gray-500 font-medium">Width (px)</label>
                                            <input
                                                type="number"
                                                min={1}
                                                value={width}
                                                onChange={e => handleWidthChange(e.target.value)}
                                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                                           focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
                                            />
                                        </div>

                                        {/* Aspect lock */}
                                        <button
                                            onClick={() => setAspectLocked(l => !l)}
                                            title={aspectLocked ? 'Unlock aspect ratio' : 'Lock aspect ratio'}
                                            className={`mt-5 w-9 h-9 rounded-lg border flex items-center justify-center flex-shrink-0 transition-colors
                                                ${aspectLocked
                                                    ? 'border-amber-400 bg-amber-50 text-amber-600'
                                                    : 'border-gray-300 bg-white text-gray-400 hover:border-gray-400'}`}>
                                            {aspectLocked ? (
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                </svg>
                                            ) : (
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 0 1 4.5-4.5 4.5 4.5 0 0 1 4.5 4.5v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                </svg>
                                            )}
                                        </button>

                                        {/* Height */}
                                        <div className="flex-1 space-y-1">
                                            <label className="text-xs text-gray-500 font-medium">Height (px)</label>
                                            <input
                                                type="number"
                                                min={1}
                                                value={height}
                                                onChange={e => handleHeightChange(e.target.value)}
                                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                                           focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
                                            />
                                        </div>
                                    </div>

                                    {originalW > 0 && (
                                        <p className="text-xs text-gray-400 mt-3">
                                            Original: {originalW} x {originalH} px
                                            {aspectLocked && (
                                                <span className="ml-2 text-amber-600 font-medium">Aspect ratio locked</span>
                                            )}
                                        </p>
                                    )}
                                </div>

                                {/* Action buttons */}
                                <div className="flex flex-wrap gap-3">
                                    <button
                                        onClick={() => setModalOpen(true)}
                                        className="flex items-center gap-2 bg-amber-500 hover:bg-amber-600
                                                   text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        Save As...
                                    </button>
                                    <button
                                        onClick={reset}
                                        className="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400
                                                   text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                        New Image
                                    </button>
                                </div>

                                <p className="text-xs text-gray-400">
                                    Tip: need to crop first?{' '}
                                    <a href={route('tools.image-cropper')}
                                       className="text-amber-600 hover:text-amber-700 font-medium">
                                        Try the Image Cropper
                                    </a>
                                </p>
                            </>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="mt-10 lg:mt-0">
                        <ToolSidebar products={sidebarProducts} />
                    </div>
                </div>
            </div>

            <SaveModal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                getCanvas={getCanvas}
                originalName={image?.name ?? 'image'}
            />
        </PublicLayout>
    );
}
