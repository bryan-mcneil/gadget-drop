import { useState, useRef } from 'react';
import { Head } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolSidebar from '@/Components/Tools/ToolSidebar';

function encodeText(str) {
    try { return btoa(unescape(encodeURIComponent(str))); }
    catch { return null; }
}

function decodeText(str) {
    try { return decodeURIComponent(escape(atob(str.replace(/\s/g, '')))); }
    catch { return null; }
}

function CopyButton({ text }) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        if (!text) return;
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
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    return (
        <button onClick={copy} className="flex items-center gap-1.5 text-xs font-medium text-amber-600 hover:text-amber-700 transition-colors">
            {copied ? (
                <><svg className="w-3.5 h-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>Copied!</>
            ) : (
                <><svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>Copy</>
            )}
        </button>
    );
}

export default function Base64Encoder({ sidebarProducts = [], metaTitle, metaDescription }) {
    const [mode, setMode]       = useState('text');
    const [input, setInput]     = useState('');
    const [output, setOutput]   = useState('');
    const [error, setError]     = useState(null);
    const [fileName, setFileName] = useState(null);
    const fileRef = useRef(null);

    function encode() {
        setError(null);
        if (!input.trim()) { setError('Enter some text to encode.'); return; }
        const result = encodeText(input);
        if (result === null) { setError('Could not encode. Check for unsupported characters.'); return; }
        setOutput(result);
    }

    function decode() {
        setError(null);
        if (!input.trim()) { setError('Enter a Base64 string to decode.'); return; }
        const result = decodeText(input);
        if (result === null) { setError('Invalid Base64 string. Make sure the input is valid Base64.'); return; }
        setOutput(result);
    }

    function clear() {
        setInput('');
        setOutput('');
        setError(null);
        setFileName(null);
    }

    function switchMode(next) {
        setMode(next);
        clear();
    }

    function handleFile(e) {
        const file = e.target.files[0];
        if (!file) return;
        setError(null);
        setFileName(file.name);
        const reader = new FileReader();
        reader.onload = (ev) => {
            const base64 = ev.target.result.split(',')[1];
            setOutput(base64);
        };
        reader.readAsDataURL(file);
        e.target.value = '';
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
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Base64 Encoder / Decoder</h1>
                    <p className="mt-2 text-gray-500 max-w-2xl">
                        Encode text to Base64 or decode a Base64 string back to plain text. Switch to <strong>File</strong> mode to convert any file to a Base64 data URL, useful for embedding images in CSS or HTML. Everything runs in your browser.
                    </p>
                </div>
            </div>

            <div className="max-w-[100rem] mx-auto px-4 py-8">
                <div className="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">
                    <div className="space-y-4">

                        {/* Mode tabs */}
                        <div className="flex border-b border-gray-200">
                            {[
                                { id: 'text', label: 'Text' },
                                { id: 'file', label: 'File → Base64' },
                            ].map(({ id, label }) => (
                                <button
                                    key={id}
                                    onClick={() => switchMode(id)}
                                    className={`px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px ${
                                        mode === id
                                            ? 'border-amber-500 text-amber-700'
                                            : 'border-transparent text-gray-500 hover:text-gray-700'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>

                        {/* Error */}
                        {error && (
                            <div className="flex items-start gap-3 px-4 py-3 rounded-xl text-sm font-medium bg-red-50 text-red-800 border border-red-200">
                                <svg className="w-5 h-5 flex-shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                </svg>
                                {error}
                            </div>
                        )}

                        {mode === 'text' ? (
                            <>
                                <div>
                                    <label className="block text-sm font-semibold text-gray-700 mb-2">Input</label>
                                    <textarea
                                        value={input}
                                        onChange={e => { setInput(e.target.value); setError(null); setOutput(''); }}
                                        placeholder="Paste text to encode, or a Base64 string to decode..."
                                        spellCheck={false}
                                        className="w-full min-h-[10rem] font-mono text-sm bg-gray-950 text-gray-200 caret-amber-400
                                                   rounded-xl p-4 resize-y border border-gray-800
                                                   focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                                                   placeholder:text-gray-600"
                                    />
                                </div>

                                <div className="flex flex-wrap gap-3">
                                    <button
                                        onClick={encode}
                                        className="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                        Encode to Base64
                                    </button>
                                    <button
                                        onClick={decode}
                                        className="flex items-center gap-2 bg-white border border-gray-300 hover:border-amber-400 text-gray-700 font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                                        </svg>
                                        Decode from Base64
                                    </button>
                                    <button
                                        onClick={clear}
                                        className="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                        Clear
                                    </button>
                                </div>

                                {output && (
                                    <div>
                                        <div className="flex items-center justify-between mb-2">
                                            <label className="text-sm font-semibold text-gray-700">Output</label>
                                            <CopyButton text={output} />
                                        </div>
                                        <textarea
                                            readOnly
                                            value={output}
                                            className="w-full min-h-[10rem] font-mono text-sm bg-gray-950 text-amber-400 rounded-xl p-4 resize-y border border-gray-800 focus:outline-none"
                                        />
                                    </div>
                                )}
                            </>
                        ) : (
                            <>
                                <div
                                    onClick={() => fileRef.current?.click()}
                                    className="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition-colors py-14 px-6 text-center select-none"
                                >
                                    <div className="w-14 h-14 rounded-2xl bg-white border border-gray-200 flex items-center justify-center text-gray-400">
                                        <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold text-gray-700">
                                            {fileName ? fileName : 'Click to choose any file'}
                                        </p>
                                        <p className="text-xs text-gray-400 mt-1">Image, PDF, font, or any binary file</p>
                                    </div>
                                    <input ref={fileRef} type="file" className="hidden" onChange={handleFile} />
                                </div>

                                {output && (
                                    <div>
                                        <div className="flex items-center justify-between mb-2">
                                            <label className="text-sm font-semibold text-gray-700">Base64 output</label>
                                            <div className="flex items-center gap-3">
                                                <span className="text-xs text-gray-400">{output.length.toLocaleString()} chars</span>
                                                <CopyButton text={output} />
                                            </div>
                                        </div>
                                        <textarea
                                            readOnly
                                            value={output}
                                            rows={6}
                                            className="w-full font-mono text-xs bg-gray-950 text-amber-400 rounded-xl p-4 resize-y border border-gray-800 focus:outline-none"
                                        />
                                        <p className="mt-2 text-xs text-gray-400">
                                            To use in HTML/CSS, prefix with the data URL header:
                                            {' '}<code className="bg-gray-100 px-1 rounded">data:image/png;base64,{'{output}'}</code>
                                        </p>
                                    </div>
                                )}
                            </>
                        )}

                        <p className="text-xs text-gray-400 pt-2">
                            All encoding and decoding happens entirely in your browser. No text or files are sent to any server.
                        </p>
                    </div>

                    <div className="mt-10 lg:mt-0">
                        <ToolSidebar products={sidebarProducts} />
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
