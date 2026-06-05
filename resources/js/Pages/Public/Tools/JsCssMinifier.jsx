import { useState, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolSidebar from '@/Components/Tools/ToolSidebar';

function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    return `${(bytes / 1024).toFixed(1)} KB`;
}

function minifyCSS(css) {
    return css
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/\s+/g, ' ')
        .replace(/\s*([{}:;,>~+])\s*/g, '$1')
        .replace(/;}/g, '}')
        .replace(/\s*!\s*important/gi, '!important')
        .trim();
}

export default function JsCssMinifier({ sidebarProducts = [], metaTitle, metaDescription }) {
    const [tab, setTab] = useState('js'); // 'js' | 'css'
    const [input, setInput] = useState('');
    const [output, setOutput] = useState('');
    const [status, setStatus] = useState(null); // null | { type: 'success' | 'error', message: string, savings?: string }
    const [loading, setLoading] = useState(false);
    const [copied, setCopied] = useState(false);

    const inputBytes = new TextEncoder().encode(input).length;
    const outputBytes = new TextEncoder().encode(output).length;
    const savingsPct = inputBytes > 0 && outputBytes > 0
        ? Math.round((1 - outputBytes / inputBytes) * 100)
        : null;

    const handleTabChange = useCallback((newTab) => {
        setTab(newTab);
        setInput('');
        setOutput('');
        setStatus(null);
        setCopied(false);
    }, []);

    const minify = useCallback(async () => {
        const raw = input.trim();
        if (!raw) {
            setStatus({ type: 'error', message: 'Nothing to minify, paste some code first.' });
            return;
        }

        setLoading(true);
        setStatus(null);

        try {
            let result = '';
            if (tab === 'js') {
                const { minify: terserMinify } = await import('terser');
                const res = await terserMinify(raw, {
                    compress: true,
                    mangle: true,
                    format: { comments: false },
                });
                result = res.code ?? '';
            } else {
                result = minifyCSS(raw);
            }

            setOutput(result);
            const inB = new TextEncoder().encode(raw).length;
            const outB = new TextEncoder().encode(result).length;
            const pct = Math.round((1 - outB / inB) * 100);
            setStatus({
                type: 'success',
                message: `Minified successfully. Saved ${pct}% (${formatBytes(inB)} to ${formatBytes(outB)})`,
            });
        } catch (err) {
            setOutput('');
            setStatus({ type: 'error', message: err.message });
        } finally {
            setLoading(false);
        }
    }, [input, tab]);

    const clear = useCallback(() => {
        setInput('');
        setOutput('');
        setStatus(null);
        setCopied(false);
    }, []);

    const copy = useCallback(async () => {
        if (!output) return;
        try {
            await navigator.clipboard.writeText(output);
        } catch {
            const el = document.createElement('textarea');
            el.value = output;
            el.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }, [output]);

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
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">JS & CSS Minifier</h1>
                    <p className="mt-2 text-gray-500 max-w-2xl">
                        Paste your JavaScript or CSS and click <strong>Minify</strong> to shrink file size instantly.
                        See exactly how much space you saved. Runs entirely in your browser.
                    </p>
                </div>
            </div>

            {/* Main content */}
            <div className="max-w-[100rem] mx-auto px-4 py-8">
                <div className="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">

                    {/* Tool panel */}
                    <div className="space-y-4">

                        {/* JS / CSS tab switcher */}
                        <div className="flex border-b border-gray-200">
                            {['js', 'css'].map(t => (
                                <button
                                    key={t}
                                    onClick={() => handleTabChange(t)}
                                    className={`px-5 py-2.5 text-sm font-semibold transition-colors border-b-2 -mb-px
                                        ${tab === t
                                            ? 'border-amber-500 text-amber-700'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}`}>
                                    {t.toUpperCase()}
                                </button>
                            ))}
                        </div>

                        {/* Status banner */}
                        {status && (
                            <div className={`flex items-start gap-3 px-4 py-3 rounded-xl text-sm font-medium
                                ${status.type === 'success'
                                    ? 'bg-green-50 text-green-800 border border-green-200'
                                    : 'bg-red-50 text-red-800 border border-red-200'}`}>
                                {status.type === 'success' ? (
                                    <svg className="w-5 h-5 flex-shrink-0 mt-0.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                ) : (
                                    <svg className="w-5 h-5 flex-shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                    </svg>
                                )}
                                <span>{status.message}</span>
                            </div>
                        )}

                        {/* Input / Output panels — stacked on mobile, side-by-side on desktop */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Input */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <label className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                        Input
                                    </label>
                                    {inputBytes > 0 && (
                                        <span className="text-xs text-gray-400">{formatBytes(inputBytes)}</span>
                                    )}
                                </div>
                                <textarea
                                    value={input}
                                    onChange={e => { setInput(e.target.value); setStatus(null); setOutput(''); }}
                                    placeholder={tab === 'js'
                                        ? 'Paste your JavaScript here...'
                                        : 'Paste your CSS here...'}
                                    spellCheck={false}
                                    className="w-full h-64 md:min-h-[520px] font-mono text-sm bg-gray-950 text-green-400 caret-green-400
                                               rounded-xl p-4 resize-y border border-gray-800
                                               focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                                               placeholder:text-gray-600"
                                />
                            </div>

                            {/* Output */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <label className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                        Output
                                    </label>
                                    <div className="flex items-center gap-2">
                                        {outputBytes > 0 && (
                                            <span className="text-xs text-gray-400">{formatBytes(outputBytes)}</span>
                                        )}
                                        {savingsPct !== null && savingsPct > 0 && (
                                            <span className="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                                −{savingsPct}%
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <textarea
                                    readOnly
                                    value={output}
                                    placeholder="Minified output will appear here..."
                                    spellCheck={false}
                                    className="w-full h-64 md:min-h-[520px] font-mono text-sm bg-gray-900 text-gray-300
                                               rounded-xl p-4 resize-y border border-gray-800
                                               focus:outline-none cursor-default placeholder:text-gray-600"
                                />
                            </div>
                        </div>

                        {/* Action buttons */}
                        <div className="flex flex-wrap gap-3">
                            <button
                                onClick={minify}
                                disabled={loading}
                                className="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300
                                           text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                                {loading ? (
                                    <>
                                        <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Minifying…
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                                        </svg>
                                        Minify
                                    </>
                                )}
                            </button>
                            <button
                                onClick={copy}
                                disabled={!output}
                                className="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400
                                           text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm
                                           disabled:opacity-40 disabled:cursor-not-allowed">
                                {copied ? (
                                    <>
                                        <svg className="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        Copied!
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                        </svg>
                                        Copy Output
                                    </>
                                )}
                            </button>
                            <button
                                onClick={clear}
                                className="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400
                                           text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm">
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                Clear
                            </button>
                        </div>

                        <p className="text-xs text-gray-400 pt-1">
                            <span className="font-medium text-gray-500">JS:</span> powered by Terser, the same engine used by Vite and Webpack in production.
                            &nbsp;<span className="font-medium text-gray-500">CSS:</span> whitespace, comment, and redundant-semicolon removal.
                        </p>
                    </div>

                    {/* Sidebar */}
                    <div className="mt-10 lg:mt-0">
                        <ToolSidebar products={sidebarProducts} />
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
