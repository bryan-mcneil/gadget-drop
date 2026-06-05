import { useState, useRef, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import Cropper from 'react-cropper';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolSidebar from '@/Components/Tools/ToolSidebar';
import DropZone from '@/Components/Tools/DropZone';
import SaveModal from '@/Components/Tools/SaveModal';

const ASPECT_PRESETS = [
    { label: 'Free',  value: NaN },
    { label: '1:1',   value: 1 },
    { label: '16:9',  value: 16 / 9 },
    { label: '4:3',   value: 4 / 3 },
];

export default function ImageCropper({ sidebarProducts = [], metaTitle, metaDescription }) {
    const [image, setImage] = useState(null);
    const [aspectKey, setAspectKey] = useState('Free');
    const [circleMode, setCircleMode] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const cropperRef = useRef(null);

    const handleFile = useCallback((file) => {
        setImage(file);
        setModalOpen(false);
        setCircleMode(false);
        setAspectKey('Free');
    }, []);

    const setAspect = useCallback((preset) => {
        setAspectKey(preset.label);
        if (preset.label === '1:1') setCircleMode(false);
        const cropper = cropperRef.current?.cropper;
        if (!cropper) return;
        cropper.setAspectRatio(isNaN(preset.value) ? NaN : preset.value);
    }, []);

    const toggleCircle = useCallback(() => {
        setCircleMode(prev => {
            const next = !prev;
            if (next) {
                // Force 1:1 for circle
                setAspectKey('1:1');
                cropperRef.current?.cropper?.setAspectRatio(1);
            }
            return next;
        });
    }, []);

    const rotate = useCallback((deg) => {
        cropperRef.current?.cropper?.rotate(deg);
    }, []);

    const flip = useCallback((axis) => {
        const cropper = cropperRef.current?.cropper;
        if (!cropper) return;
        const data = cropper.getImageData();
        if (axis === 'h') cropper.scaleX(data.scaleX === -1 ? 1 : -1);
        else cropper.scaleY(data.scaleY === -1 ? 1 : -1);
    }, []);

    const reset = useCallback(() => {
        cropperRef.current?.cropper?.reset();
        setCircleMode(false);
        setAspectKey('Free');
    }, []);

    const newImage = useCallback(() => {
        setImage(null);
        setModalOpen(false);
        setCircleMode(false);
        setAspectKey('Free');
    }, []);

    const getCanvas = useCallback(() => {
        const cropper = cropperRef.current?.cropper;
        if (!cropper) return null;

        const raw = cropper.getCroppedCanvas({ imageSmoothingQuality: 'high' });
        if (!raw) return null;

        if (!circleMode) return raw;

        // Circle mask — draw into a square canvas with a circular clip
        const size = Math.min(raw.width, raw.height);
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext('2d');
        ctx.beginPath();
        ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
        ctx.closePath();
        ctx.clip();
        ctx.drawImage(raw, 0, 0, size, size);
        return canvas;
    }, [circleMode]);

    const currentAspect = ASPECT_PRESETS.find(p => p.label === aspectKey) ?? ASPECT_PRESETS[0];

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
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Image Cropper</h1>
                    <p className="mt-2 text-gray-500 max-w-2xl">
                        Upload an image, crop it to the perfect size, rotate, flip, or go circle for profile pictures.
                        Everything runs in your browser, nothing is sent to a server.
                    </p>
                </div>
            </div>

            {/* Main content */}
            <div className="max-w-[100rem] mx-auto px-4 py-8">
                <div className="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">

                    {/* Tool panel */}
                    <div className="space-y-5">

                        {!image ? (
                            <DropZone onFile={handleFile} />
                        ) : (
                            <>
                                {/* Controls toolbar */}
                                <div className="flex flex-wrap items-center gap-2">
                                    {/* Aspect ratio presets */}
                                    <div className="flex rounded-lg border border-gray-200 overflow-hidden">
                                        {ASPECT_PRESETS.map(preset => (
                                            <button
                                                key={preset.label}
                                                onClick={() => setAspect(preset)}
                                                className={`px-3 py-2 text-xs font-semibold transition-colors border-r border-gray-200 last:border-r-0
                                                    ${aspectKey === preset.label && !circleMode
                                                        ? 'bg-amber-500 text-white'
                                                        : 'bg-white text-gray-600 hover:bg-gray-50'}`}>
                                                {preset.label}
                                            </button>
                                        ))}
                                    </div>

                                    {/* Circle toggle */}
                                    <button
                                        onClick={toggleCircle}
                                        title="Circle crop"
                                        className={`flex items-center gap-1.5 px-3 py-2 rounded-lg border text-xs font-semibold transition-colors
                                            ${circleMode
                                                ? 'bg-amber-500 border-amber-500 text-white'
                                                : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'}`}>
                                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <circle cx="12" cy="12" r="9" />
                                        </svg>
                                        Circle
                                    </button>

                                    <div className="w-px h-6 bg-gray-200 mx-1 hidden sm:block" />

                                    {/* Rotate */}
                                    <button
                                        onClick={() => rotate(-90)}
                                        title="Rotate left"
                                        className="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200
                                                   bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                                        </svg>
                                    </button>
                                    <button
                                        onClick={() => rotate(90)}
                                        title="Rotate right"
                                        className="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200
                                                   bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 15l6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" />
                                        </svg>
                                    </button>

                                    <div className="w-px h-6 bg-gray-200 mx-1 hidden sm:block" />

                                    {/* Flip */}
                                    <button
                                        onClick={() => flip('h')}
                                        title="Flip horizontal"
                                        className="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200
                                                   bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                        </svg>
                                    </button>
                                    <button
                                        onClick={() => flip('v')}
                                        title="Flip vertical"
                                        className="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200
                                                   bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ transform: 'rotate(90deg)' }}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                        </svg>
                                    </button>

                                    <div className="w-px h-6 bg-gray-200 mx-1 hidden sm:block" />

                                    {/* Reset */}
                                    <button
                                        onClick={reset}
                                        className="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200
                                                   bg-white text-gray-500 hover:bg-gray-50 text-xs font-medium transition-colors">
                                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                        Reset
                                    </button>
                                </div>

                                {/* Cropper */}
                                <div className={`rounded-2xl overflow-hidden border border-gray-200 bg-gray-100
                                                 ${circleMode ? 'cropper-circle-mode' : ''}`}>
                                    <Cropper
                                        ref={cropperRef}
                                        src={image.src}
                                        style={{ maxHeight: '560px', width: '100%' }}
                                        aspectRatio={isNaN(currentAspect.value) ? NaN : currentAspect.value}
                                        guides={true}
                                        viewMode={1}
                                        dragMode="move"
                                        autoCropArea={0.8}
                                        responsive={true}
                                        restore={false}
                                        checkCrossOrigin={false}
                                        background={true}
                                    />
                                </div>

                                {circleMode && (
                                    <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200
                                                  rounded-xl px-4 py-2.5">
                                        Circle mode: the crop area is square. The downloaded image will be a circle with a transparent background (save as PNG for best results).
                                    </p>
                                )}

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
                                        onClick={newImage}
                                        className="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400
                                                   text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                        New Image
                                    </button>
                                </div>

                                <p className="text-xs text-gray-400">
                                    Tip: need to convert the format too?{' '}
                                    <a href={route('tools.image-converter')}
                                       className="text-amber-600 hover:text-amber-700 font-medium">
                                        Try the Image Converter
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
