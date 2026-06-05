import { useState, useRef, useCallback, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolSidebar from '@/Components/Tools/ToolSidebar';
import DropZone from '@/Components/Tools/DropZone';
import SaveModal from '@/Components/Tools/SaveModal';

const DEFAULT_TOLERANCE = 32;

// Checkerboard background to visualize transparency
const CHECKER_STYLE = {
    backgroundImage: [
        'linear-gradient(45deg, #d1d5db 25%, transparent 25%)',
        'linear-gradient(-45deg, #d1d5db 25%, transparent 25%)',
        'linear-gradient(45deg, transparent 75%, #d1d5db 75%)',
        'linear-gradient(-45deg, transparent 75%, #d1d5db 75%)',
    ].join(', '),
    backgroundSize: '20px 20px',
    backgroundPosition: '0 0, 0 10px, 10px -10px, -10px 0px',
    backgroundColor: '#f9fafb',
};

function toHex({ r, g, b }) {
    return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
}

// Flood-fill background removal (BFS from image borders).
// Only removes pixels reachable FROM the border through a connected chain of
// within-tolerance pixels — so white text inside a purple shirt is safe because
// the flood fill stops at the purple pixels and never reaches the interior white.
function applyRemoval(ctx, w, h, target, tolerance) {
    const imageData = ctx.getImageData(0, 0, w, h);
    const { data } = imageData;
    const feather = Math.max(tolerance * 0.6, 8);
    const fullTol = tolerance + feather;
    const tr = target.r, tg = target.g, tb = target.b;

    // Inline distance — avoids function call overhead in the hot loop
    function dist(i) {
        const dr = data[i] - tr, dg = data[i + 1] - tg, db = data[i + 2] - tb;
        return Math.sqrt(dr * dr + dg * dg + db * db);
    }

    const size = w * h;
    const visited = new Uint8Array(size);   // 1 byte per pixel
    const queue   = new Uint32Array(size);  // pixel index per slot
    let qHead = 0, qTail = 0;

    function enqueue(px) {
        if (visited[px]) return;
        if (dist(px * 4) <= fullTol) {
            visited[px] = 1;
            queue[qTail++] = px;
        }
    }

    // Seed BFS from every border pixel
    for (let x = 0; x < w; x++) {
        enqueue(x);               // top row
        enqueue((h - 1) * w + x); // bottom row
    }
    for (let y = 1; y < h - 1; y++) {
        enqueue(y * w);           // left column
        enqueue(y * w + w - 1);   // right column
    }

    // BFS — spread inward, only crossing pixels within tolerance
    while (qHead < qTail) {
        const px = queue[qHead++];
        const i  = px * 4;
        const d  = dist(i);

        if (d <= tolerance) {
            data[i + 3] = 0; // fully transparent
        } else {
            // Feather: shadow zone gets partial alpha so the edge looks soft
            data[i + 3] = Math.round(((d - tolerance) / feather) * data[i + 3]);
        }

        const x = px % w;
        const y = (px - x) / w;
        if (x > 0)     enqueue(px - 1);
        if (x < w - 1) enqueue(px + 1);
        if (y > 0)     enqueue(px - w);
        if (y < h - 1) enqueue(px + w);
    }

    ctx.putImageData(imageData, 0, 0);
}

export default function BackgroundRemover({ sidebarProducts = [], metaTitle, metaDescription }) {
    const [image, setImage] = useState(null);
    const [target, setTarget] = useState({ r: 255, g: 255, b: 255 });
    const [tolerance, setTolerance] = useState(DEFAULT_TOLERANCE);
    const [resultUrl, setResultUrl] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [sampling, setSampling] = useState(false);

    const resultCanvasRef = useRef(null);
    const previewImgRef = useRef(null);

    const runProcess = useCallback((img, tgt, tol) => {
        setProcessing(true);
        // Defer so the spinner renders before the blocking pixel loop
        setTimeout(() => {
            try {
                const canvas = resultCanvasRef.current;
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0);
                applyRemoval(ctx, canvas.width, canvas.height, tgt, tol);
                setResultUrl(canvas.toDataURL('image/png'));
            } finally {
                setProcessing(false);
            }
        }, 20);
    }, []);

    // Load image element, then auto-process
    const handleFile = useCallback((file) => {
        setImage(file);
        setResultUrl(null);
        setSampling(false);
        setTarget({ r: 255, g: 255, b: 255 });

        const img = new window.Image();
        img.onload = () => runProcess(img, { r: 255, g: 255, b: 255 }, DEFAULT_TOLERANCE);
        img.src = file.src;
        previewImgRef.current = img;
    }, [runProcess]);

    const apply = useCallback(() => {
        const img = previewImgRef.current;
        if (!img) return;
        runProcess(img, target, tolerance);
    }, [runProcess, target, tolerance]);

    // Sample a pixel from the original image at click coordinates
    const handleImageClick = useCallback((e) => {
        if (!sampling) return;
        const el = e.currentTarget;
        const rect = el.getBoundingClientRect();
        const scaleX = el.naturalWidth / rect.width;
        const scaleY = el.naturalHeight / rect.height;
        const px = Math.round((e.clientX - rect.left) * scaleX);
        const py = Math.round((e.clientY - rect.top) * scaleY);

        const tmp = document.createElement('canvas');
        const img = previewImgRef.current;
        if (!img) return;
        tmp.width = img.naturalWidth;
        tmp.height = img.naturalHeight;
        const ctx = tmp.getContext('2d');
        ctx.drawImage(img, 0, 0);
        const [r, g, b] = ctx.getImageData(px, py, 1, 1).data;
        setTarget({ r, g, b });
        setSampling(false);
    }, [sampling]);

    const getCanvas = useCallback(() => resultCanvasRef.current, []);

    const newImage = useCallback(() => {
        setImage(null);
        setResultUrl(null);
        setSampling(false);
        setTarget({ r: 255, g: 255, b: 255 });
        setTolerance(DEFAULT_TOLERANCE);
        if (previewImgRef.current) previewImgRef.current = null;
    }, []);

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
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Background Remover</h1>
                    <p className="mt-2 text-gray-500 max-w-2xl">
                        Remove white or solid backgrounds from product images instantly. Shadows are
                        feathered automatically so products still look natural. Runs entirely in your browser,
                        nothing is sent to a server.
                    </p>
                </div>
            </div>

            {/* Main content */}
            <div className="max-w-[100rem] mx-auto px-4 py-8">
                <div className="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">

                    <div className="space-y-5">

                        {!image ? (
                            <DropZone onFile={handleFile} />
                        ) : (
                            <>
                                {/* Before / After panels */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">

                                    {/* Original */}
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <label className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                                Original
                                            </label>
                                            {sampling && (
                                                <span className="text-xs text-amber-700 bg-amber-50 border border-amber-200
                                                                 px-2 py-0.5 rounded-full animate-pulse">
                                                    Click anywhere to sample that color
                                                </span>
                                            )}
                                        </div>
                                        <div className={`bg-gray-100 rounded-2xl overflow-hidden border-2 transition-all
                                                          min-h-[220px] flex items-center justify-center
                                                          ${sampling ? 'border-amber-400 cursor-crosshair' : 'border-transparent'}`}>
                                            <img
                                                src={image.src}
                                                alt="Original"
                                                onClick={handleImageClick}
                                                className="max-w-full max-h-[400px] object-contain select-none"
                                                draggable={false}
                                            />
                                        </div>
                                    </div>

                                    {/* Result */}
                                    <div className="space-y-2">
                                        <label className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                            Result
                                        </label>
                                        <div
                                            className="rounded-2xl overflow-hidden border-2 border-transparent
                                                       min-h-[220px] flex items-center justify-center"
                                            style={CHECKER_STYLE}>
                                            {processing ? (
                                                <div className="flex flex-col items-center gap-3 text-gray-400">
                                                    <svg className="w-7 h-7 animate-spin" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                    </svg>
                                                    <span className="text-xs font-medium">Removing background...</span>
                                                </div>
                                            ) : resultUrl ? (
                                                <img
                                                    src={resultUrl}
                                                    alt="Result"
                                                    className="max-w-full max-h-[400px] object-contain select-none"
                                                    draggable={false}
                                                />
                                            ) : null}
                                        </div>
                                    </div>
                                </div>

                                {/* Controls */}
                                <div className="bg-white border border-gray-200 rounded-2xl p-5 space-y-5">

                                    {/* Background color row */}
                                    <div className="flex flex-wrap items-center gap-4">
                                        <div className="flex items-center gap-2.5">
                                            <span className="text-sm font-medium text-gray-700">Background color</span>
                                            <div
                                                className="w-8 h-8 rounded-lg border-2 border-gray-300 shadow-sm flex-shrink-0"
                                                style={{ backgroundColor: toHex(target) }}
                                                title={toHex(target)}
                                            />
                                            <span className="text-xs font-mono text-gray-400">{toHex(target)}</span>
                                        </div>
                                        <button
                                            onClick={() => setSampling(s => !s)}
                                            className={`flex items-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg border transition-colors
                                                ${sampling
                                                    ? 'bg-amber-500 border-amber-500 text-white'
                                                    : 'bg-white border-gray-300 text-gray-600 hover:border-amber-400 hover:text-amber-700'}`}>
                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" />
                                            </svg>
                                            {sampling ? 'Sampling... click image' : 'Pick from image'}
                                        </button>
                                        <button
                                            onClick={() => setTarget({ r: 255, g: 255, b: 255 })}
                                            className="text-xs text-gray-400 hover:text-gray-600 underline underline-offset-2 transition-colors">
                                            Reset to white
                                        </button>
                                    </div>

                                    {/* Tolerance slider */}
                                    <div>
                                        <div className="flex items-center justify-between mb-2">
                                            <div>
                                                <span className="text-sm font-medium text-gray-700">Tolerance</span>
                                                <span className="ml-2 text-xs text-gray-400">
                                                    higher catches more shadow and near-white pixels
                                                </span>
                                            </div>
                                            <span className="text-sm font-bold text-amber-600 tabular-nums">{tolerance}</span>
                                        </div>
                                        <input
                                            type="range"
                                            min={0}
                                            max={120}
                                            value={tolerance}
                                            onChange={e => setTolerance(Number(e.target.value))}
                                            className="w-full h-2 rounded-full appearance-none cursor-pointer
                                                       bg-gray-200 accent-amber-500"
                                        />
                                        <div className="flex justify-between text-[10px] text-gray-400 mt-1.5">
                                            <span>Precise (exact match only)</span>
                                            <span>Loose (removes shadows too)</span>
                                        </div>

                                        {/* Quick presets */}
                                        <div className="flex gap-2 mt-3">
                                            {[['Clean white', 20], ['White + light shadow', 40], ['White + heavy shadow', 65]].map(([label, val]) => (
                                                <button
                                                    key={label}
                                                    onClick={() => setTolerance(val)}
                                                    className={`flex-1 py-1.5 text-xs font-medium rounded-lg border transition-colors
                                                        ${tolerance === val
                                                            ? 'bg-amber-500 border-amber-500 text-white'
                                                            : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-amber-300 hover:text-amber-700'}`}>
                                                    {label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                {/* Action buttons */}
                                <div className="flex flex-wrap gap-3">
                                    <button
                                        onClick={apply}
                                        disabled={processing}
                                        className="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300
                                                   text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                                        {processing ? (
                                            <>
                                                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                </svg>
                                                Processing...
                                            </>
                                        ) : (
                                            <>
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                                                </svg>
                                                Apply
                                            </>
                                        )}
                                    </button>
                                    <button
                                        onClick={() => setModalOpen(true)}
                                        disabled={!resultUrl || processing}
                                        className="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400
                                                   text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm
                                                   disabled:opacity-40 disabled:cursor-not-allowed">
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

                                <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5">
                                    Save as <strong>PNG</strong> or <strong>WebP</strong> to keep the transparent background.
                                    JPG does not support transparency and will show a white fill instead.
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

            {/* Hidden result canvas — getCanvas() returns this */}
            <canvas ref={resultCanvasRef} className="hidden" />

            <SaveModal
                isOpen={modalOpen}
                onClose={() => setModalOpen(false)}
                getCanvas={getCanvas}
                originalName={image?.name ?? 'image'}
            />
        </PublicLayout>
    );
}
