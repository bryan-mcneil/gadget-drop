import { useState, useRef, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolSidebar from '@/Components/Tools/ToolSidebar';
import DropZone from '@/Components/Tools/DropZone';

/* ── Utilities ──────────────────────────────────────────────── */

function rgbToHex(r, g, b) {
    return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
}

function hexToRgb(hex) {
    const h = hex.replace('#', '');
    return [
        parseInt(h.slice(0, 2), 16),
        parseInt(h.slice(2, 4), 16),
        parseInt(h.slice(4, 6), 16),
    ];
}

function rgbToHsl(r, g, b) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    const l = (max + min) / 2;
    if (max === min) return [0, 0, Math.round(l * 100)];
    const d = max - min;
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    let h;
    if (max === r)      h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
    else if (max === g) h = ((b - r) / d + 2) / 6;
    else                h = ((r - g) / d + 4) / 6;
    return [Math.round(h * 360), Math.round(s * 100), Math.round(l * 100)];
}

function colorDistance(r1, g1, b1, r2, g2, b2) {
    return Math.sqrt((r1 - r2) ** 2 + (g1 - g2) ** 2 + (b1 - b2) ** 2);
}

function luminance(r, g, b) {
    return (0.299 * r + 0.587 * g + 0.114 * b) / 255;
}

/* Generates a 10-step Tailwind-style scale from a base hex color */
const SCALE_STEPS = [
    { label: '50',  white: 0.92 },
    { label: '100', white: 0.80 },
    { label: '200', white: 0.64 },
    { label: '300', white: 0.44 },
    { label: '400', white: 0.22 },
    { label: '500', white: 0    },
    { label: '600', black: 0.12 },
    { label: '700', black: 0.26 },
    { label: '800', black: 0.44 },
    { label: '900', black: 0.60 },
];

function generateScale(hex) {
    const [r, g, b] = hexToRgb(hex);
    return SCALE_STEPS.map(({ label, white = 0, black = 0 }) => {
        const nr = Math.round(white ? r + (255 - r) * white : r * (1 - black));
        const ng = Math.round(white ? g + (255 - g) * white : g * (1 - black));
        const nb = Math.round(white ? b + (255 - b) * white : b * (1 - black));
        return { label, hex: rgbToHex(nr, ng, nb), r: nr, g: ng, b: nb };
    });
}

/* ── Image extraction ───────────────────────────────────────── */

function extractColors(src, count) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            const SIZE   = 150;
            const scale  = Math.min(SIZE / img.width, SIZE / img.height, 1);
            const canvas = document.createElement('canvas');
            canvas.width  = Math.round(img.width  * scale);
            canvas.height = Math.round(img.height * scale);
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            const { data } = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const buckets  = new Map();
            const step = 32;

            for (let i = 0; i < data.length; i += 4) {
                if (data[i + 3] < 128) continue;
                const r = Math.round(data[i]     / step) * step;
                const g = Math.round(data[i + 1] / step) * step;
                const b = Math.round(data[i + 2] / step) * step;
                const key = (r << 16) | (g << 8) | b;
                buckets.set(key, (buckets.get(key) ?? 0) + 1);
            }

            const sorted = [...buckets.entries()]
                .sort((a, b) => b[1] - a[1])
                .map(([key]) => [(key >> 16) & 0xff, (key >> 8) & 0xff, key & 0xff]);

            const palette = [];
            for (const [r, g, b] of sorted) {
                if (!palette.some(([pr, pg, pb]) => colorDistance(r, g, b, pr, pg, pb) < 64))
                    palette.push([r, g, b]);
                if (palette.length >= count) break;
            }

            resolve(palette.map(([r, g, b]) => ({
                hex: rgbToHex(r, g, b),
                rgb: `rgb(${r}, ${g}, ${b})`,
                hsl: (() => { const [h, s, l] = rgbToHsl(r, g, b); return `hsl(${h}, ${s}%, ${l}%)`; })(),
                r, g, b,
            })));
        };
        img.src = src;
    });
}

/* ── Shared copy helper ─────────────────────────────────────── */

async function copyText(text) {
    try { await navigator.clipboard.writeText(text); }
    catch {
        const el = document.createElement('textarea');
        el.value = text;
        el.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }
}

/* ── CopyChip (image tab detail cards) ─────────────────────── */

function CopyChip({ value }) {
    const [copied, setCopied] = useState(false);
    async function copy() { await copyText(value); setCopied(true); setTimeout(() => setCopied(false), 1500); }
    return (
        <button onClick={copy} className="font-mono text-xs text-gray-600 hover:text-amber-700 transition-colors text-left leading-tight">
            {copied ? <span className="text-green-600">Copied!</span> : value}
        </button>
    );
}

/* ── Scale swatch (builder tab) ─────────────────────────────── */

function ScaleSwatch({ hex, label, isBase }) {
    const [copied, setCopied] = useState(false);
    const [r, g, b] = hexToRgb(hex);
    const lum = luminance(r, g, b);
    const textColor = lum > 0.45 ? 'rgba(0,0,0,0.65)' : 'rgba(255,255,255,0.85)';

    async function copy() { await copyText(hex); setCopied(true); setTimeout(() => setCopied(false), 1500); }

    return (
        <button
            onClick={copy}
            title={`${label}: ${hex}`}
            className={`group flex flex-col items-center justify-end pb-2 pt-3 flex-1 min-w-0 transition-all duration-150 hover:scale-y-105 hover:z-10 relative ${isBase ? 'ring-2 ring-inset ring-white/40' : ''}`}
            style={{ background: hex }}
        >
            <span className="text-[10px] font-bold leading-none mb-1" style={{ color: textColor }}>{label}</span>
            <span className="font-mono text-[9px] leading-none opacity-0 group-hover:opacity-100 transition-opacity" style={{ color: textColor }}>
                {copied ? 'Copied!' : hex}
            </span>
        </button>
    );
}

/* ── Main component ─────────────────────────────────────────── */

const DEFAULT_COLORS = ['#3b82f6'];

export default function ColorPalette({ sidebarProducts = [], metaTitle, metaDescription }) {
    const [tab, setTab] = useState('image');

    /* Image tab state */
    const [imageSrc, setImageSrc] = useState(null);
    const [palette,  setPalette]  = useState([]);
    const [count,    setCount]    = useState(6);
    const [loading,  setLoading]  = useState(false);
    const currentSrc = useRef(null);

    /* Builder tab state */
    const [baseColors, setBaseColors] = useState(DEFAULT_COLORS);

    /* ── Image tab handlers ── */

    const handleFile = useCallback(async ({ src }) => {
        setImageSrc(src);
        currentSrc.current = src;
        setLoading(true);
        const colors = await extractColors(src, count);
        if (currentSrc.current === src) setPalette(colors);
        setLoading(false);
    }, [count]);

    async function reextract(newCount) {
        setCount(newCount);
        if (!imageSrc) return;
        setLoading(true);
        const colors = await extractColors(imageSrc, newCount);
        setPalette(colors);
        setLoading(false);
    }

    async function copyAllHex() {
        await copyText(palette.map(c => c.hex).join('\n'));
    }

    /* ── Builder tab handlers ── */

    function addColor() {
        if (baseColors.length >= 3) return;
        const presets = ['#3b82f6', '#ef4444', '#10b981'];
        const next = presets.find(p => !baseColors.includes(p)) ?? '#8b5cf6';
        setBaseColors([...baseColors, next]);
    }

    function removeColor(i) {
        setBaseColors(baseColors.filter((_, idx) => idx !== i));
    }

    function updateColor(i, hex) {
        const next = [...baseColors];
        next[i] = hex;
        setBaseColors(next);
    }

    async function copyRowHex(hex) {
        const scale = generateScale(hex);
        await copyText(scale.map(s => s.hex).join('\n'));
    }

    return (
        <PublicLayout>
            <Head title={metaTitle}>
                <meta name="description" content={metaDescription} />
            </Head>

            <div className="bg-white border-b border-gray-100">
                <div className="max-w-[100rem] mx-auto px-4 py-10">
                    <div className="flex items-center gap-2 mb-2">
                        <span className="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 text-xs font-semibold uppercase tracking-widest px-2.5 py-1 rounded-full">
                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.598-2.167A9.027 9.027 0 0 1 9.496 3.28c-1.586.068-3.07.817-4.188 2.015L4.5 6.122" />
                            </svg>
                            Tool
                        </span>
                    </div>
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Color Palette Extractor</h1>
                    <p className="mt-2 text-gray-500 max-w-2xl">
                        Extract dominant colors from any image, or build a full tint-and-shade scale from up to three base colors. Click any swatch to copy its HEX code.
                    </p>
                </div>
            </div>

            <div className="max-w-[100rem] mx-auto px-4 py-8">
                <div className="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">
                    <div className="space-y-6">

                        {/* Tabs */}
                        <div className="flex border-b border-gray-200">
                            <button
                                onClick={() => setTab('image')}
                                className={`flex items-center gap-2 px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px ${tab === 'image' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z" />
                                </svg>
                                Extract from Image
                            </button>
                            <button
                                onClick={() => setTab('builder')}
                                className={`flex items-center gap-2 px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px ${tab === 'builder' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.375 3.375 0 0 1 3.375 17.625v-2.25A3.375 3.375 0 0 1 6.75 12H21a3.375 3.375 0 0 1 3.375 3.375v2.25A3.375 3.375 0 0 1 21 21H6.75Z" />
                                </svg>
                                Build from Colors
                            </button>
                        </div>

                        {/* ── Extract from Image tab ── */}
                        {tab === 'image' && (
                            <>
                                {!imageSrc ? (
                                    <DropZone onFile={handleFile} />
                                ) : (
                                    <div className="relative">
                                        <img
                                            src={imageSrc}
                                            alt="Uploaded"
                                            className="w-full max-h-64 object-contain rounded-2xl bg-gray-100 border border-gray-200"
                                        />
                                        <button
                                            onClick={() => { setImageSrc(null); setPalette([]); }}
                                            className="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/90 border border-gray-300 hover:bg-white flex items-center justify-center text-gray-600 hover:text-gray-900 transition-colors shadow-sm"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                )}

                                {imageSrc && (
                                    <div className="flex items-center gap-3">
                                        <span className="text-sm font-semibold text-gray-700">Colors to extract:</span>
                                        <div className="flex gap-1.5">
                                            {[4, 6, 8, 12].map(n => (
                                                <button
                                                    key={n}
                                                    onClick={() => reextract(n)}
                                                    disabled={loading}
                                                    className={`px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-50 ${count === n ? 'bg-amber-500 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:border-amber-400'}`}
                                                >
                                                    {n}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {loading && (
                                    <div className="flex items-center gap-3 text-sm text-gray-500">
                                        <svg className="w-5 h-5 animate-spin text-amber-500" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Extracting colors…
                                    </div>
                                )}

                                {palette.length > 0 && !loading && (
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between">
                                            <h2 className="font-bold text-gray-900">{palette.length} dominant colors</h2>
                                            <button onClick={copyAllHex} className="flex items-center gap-1.5 text-sm font-medium text-amber-600 hover:text-amber-700 transition-colors">
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                                </svg>
                                                Copy all HEX
                                            </button>
                                        </div>

                                        <div className="flex rounded-2xl overflow-hidden h-16 border border-gray-200 shadow-sm">
                                            {palette.map(color => (
                                                <div key={color.hex} className="flex-1" style={{ background: color.hex }} title={color.hex} />
                                            ))}
                                        </div>

                                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                            {palette.map(color => (
                                                <div key={color.hex} className="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                                                    <div className="h-20" style={{ background: color.hex }} />
                                                    <div className="p-3 space-y-1.5">
                                                        <div className="flex items-center justify-between">
                                                            <span className="font-mono font-bold text-sm text-gray-900">{color.hex}</span>
                                                            <CopyChip value={color.hex} />
                                                        </div>
                                                        <div className="flex items-center justify-between">
                                                            <span className="font-mono text-xs text-gray-500">{color.rgb}</span>
                                                            <CopyChip value={color.rgb} />
                                                        </div>
                                                        <div className="flex items-center justify-between">
                                                            <span className="font-mono text-xs text-gray-500">{color.hsl}</span>
                                                            <CopyChip value={color.hsl} />
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <p className="text-xs text-gray-400">
                                    Colors are extracted by sampling pixels and grouping similar shades. The image is processed entirely in your browser. Nothing is uploaded.
                                </p>
                            </>
                        )}

                        {/* ── Build from Colors tab ── */}
                        {tab === 'builder' && (
                            <div className="space-y-6">
                                <p className="text-sm text-gray-500">
                                    Pick up to three base colors. Each generates a 10-step tint-and-shade scale. Click any swatch to copy its HEX.
                                </p>

                                {/* Color pickers */}
                                <div className="flex flex-wrap items-center gap-3">
                                    {baseColors.map((hex, i) => (
                                        <div key={i} className="flex items-center gap-2">
                                            <label className="relative cursor-pointer group">
                                                <span
                                                    className="block w-10 h-10 rounded-xl border-2 border-white shadow-md ring-1 ring-gray-200 group-hover:ring-amber-400 transition-all"
                                                    style={{ background: hex }}
                                                />
                                                <input
                                                    type="color"
                                                    value={hex}
                                                    onChange={e => updateColor(i, e.target.value)}
                                                    className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                                />
                                            </label>
                                            <span className="font-mono text-sm text-gray-600 w-16">{hex}</span>
                                            {baseColors.length > 1 && (
                                                <button
                                                    onClick={() => removeColor(i)}
                                                    className="w-6 h-6 rounded-full bg-gray-100 hover:bg-red-100 text-gray-400 hover:text-red-500 flex items-center justify-center transition-colors"
                                                >
                                                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            )}
                                        </div>
                                    ))}

                                    {baseColors.length < 3 && (
                                        <button
                                            onClick={addColor}
                                            className="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-dashed border-gray-300 text-gray-400 hover:border-amber-400 hover:text-amber-600 text-sm font-medium transition-colors"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                            Add color
                                        </button>
                                    )}
                                </div>

                                {/* Scale column headers */}
                                <div className="space-y-3">
                                    <div className="flex ml-[72px] gap-0">
                                        {SCALE_STEPS.map(({ label }) => (
                                            <div key={label} className="flex-1 text-center text-[10px] text-gray-400 font-semibold pb-1">{label}</div>
                                        ))}
                                    </div>

                                    {baseColors.map((hex, i) => {
                                        const scale = generateScale(hex);
                                        return (
                                            <div key={i} className="flex items-stretch gap-0">
                                                {/* Color swatch + copy row button */}
                                                <div className="flex-shrink-0 w-[72px] flex flex-col items-center justify-center gap-1.5 pr-2">
                                                    <div className="w-8 h-8 rounded-lg shadow-sm border border-white ring-1 ring-gray-200" style={{ background: hex }} />
                                                    <button
                                                        onClick={() => copyRowHex(hex)}
                                                        className="text-[10px] text-gray-400 hover:text-amber-600 font-medium transition-colors leading-tight text-center"
                                                    >
                                                        Copy row
                                                    </button>
                                                </div>

                                                {/* Scale swatches */}
                                                <div className="flex flex-1 rounded-xl overflow-hidden shadow-sm border border-gray-200" style={{ height: '72px' }}>
                                                    {scale.map(swatch => (
                                                        <ScaleSwatch
                                                            key={swatch.label}
                                                            hex={swatch.hex}
                                                            label={swatch.label}
                                                            isBase={swatch.label === '500'}
                                                        />
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>

                                <p className="text-xs text-gray-400">
                                    Scales are generated by mixing the base color with white (tints) and black (shades). Click a swatch to copy its HEX. "Copy row" copies all 10 shades as a newline-separated list.
                                </p>
                            </div>
                        )}
                    </div>

                    <div className="mt-10 lg:mt-0">
                        <ToolSidebar products={sidebarProducts} />
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
