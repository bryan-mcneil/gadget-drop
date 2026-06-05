import { useState, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolSidebar from '@/Components/Tools/ToolSidebar';

export default function JsonValidator({ sidebarProducts = [], metaTitle, metaDescription }) {
    const [input, setInput] = useState('');
    const [status, setStatus] = useState(null); // null | { type: 'success' | 'error', message: string }
    const [copied, setCopied] = useState(false);

    const validate = useCallback(() => {
        const raw = input.trim();
        if (!raw) {
            setStatus({ type: 'error', message: 'Nothing to validate, paste some JSON first.' });
            return;
        }
        try {
            const parsed = JSON.parse(raw);
            const formatted = JSON.stringify(parsed, null, 2);
            setInput(formatted);
            setStatus({ type: 'success', message: 'Valid JSON, formatted successfully.' });
        } catch (err) {
            setStatus({ type: 'error', message: err.message });
        }
    }, [input]);

    const clear = useCallback(() => {
        setInput('');
        setStatus(null);
        setCopied(false);
    }, []);

    const copy = useCallback(async () => {
        if (!input) return;
        try {
            await navigator.clipboard.writeText(input);
        } catch {
            const el = document.createElement('textarea');
            el.value = input;
            el.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }, [input]);

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
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">JSON Validator</h1>
                    <p className="mt-2 text-gray-500 max-w-2xl">
                        Paste your JSON below and click <strong>Validate & Format</strong>. Errors are shown with a plain-English explanation.
                        Everything runs in your browser, nothing is sent to a server.
                    </p>
                </div>
            </div>

            {/* Main content */}
            <div className="max-w-[100rem] mx-auto px-4 py-8">
                <div className="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">

                    {/* Tool panel */}
                    <div className="space-y-4">

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

                        {/* Editor */}
                        <div className="relative">
                            <textarea
                                value={input}
                                onChange={e => { setInput(e.target.value); setStatus(null); }}
                                placeholder={'Paste your JSON here...\n\nExample:\n{\n  "name": "GadgetDrop",\n  "version": 1\n}'}
                                spellCheck={false}
                                className="w-full min-h-[30rem] font-mono text-sm bg-gray-950 text-green-400 caret-green-400
                                           rounded-xl p-4 resize-y border border-gray-800
                                           focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400
                                           placeholder:text-gray-600"
                            />
                        </div>

                        {/* Action buttons */}
                        <div className="flex flex-wrap gap-3">
                            <button
                                onClick={validate}
                                className="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white
                                           font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                Validate & Format
                            </button>
                            <button
                                onClick={copy}
                                disabled={!input}
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
                                        Copy
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

                        {/* How-to hint */}
                        <div className="text-xs text-gray-400 space-y-1 pt-2">
                            <p><span className="font-medium text-gray-500">Tip:</span> Validate & Format will also pretty-print minified JSON, making it easy to read.</p>
                        </div>
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
