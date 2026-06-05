import { useState, useCallback, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolSidebar from '@/Components/Tools/ToolSidebar';

const CHARS = {
    upper:   'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    lower:   'abcdefghijklmnopqrstuvwxyz',
    numbers: '0123456789',
    symbols: '!@#$%^&*()_+-=[]{}|;:,.<>?',
};

function calcStrength(length, typeCount) {
    if (typeCount === 0) return null;
    if (typeCount === 1 || length < 10) return { label: 'Weak',        color: 'bg-red-500',    text: 'text-red-600',    pct: 25 };
    if (typeCount === 2 || length < 14) return { label: 'Fair',        color: 'bg-yellow-500', text: 'text-yellow-600', pct: 50 };
    if (typeCount === 3 || length < 20) return { label: 'Strong',      color: 'bg-lime-500',   text: 'text-lime-600',   pct: 75 };
    return                              { label: 'Very Strong',  color: 'bg-green-500',  text: 'text-green-600',  pct: 100 };
}

function generatePassword(length, opts) {
    let pool = '';
    if (opts.upper)   pool += CHARS.upper;
    if (opts.lower)   pool += CHARS.lower;
    if (opts.numbers) pool += CHARS.numbers;
    if (opts.symbols) pool += CHARS.symbols;
    if (!pool) return '';
    const arr = new Uint32Array(length);
    crypto.getRandomValues(arr);
    return Array.from(arr, n => pool[n % pool.length]).join('');
}

export default function PasswordGenerator({ sidebarProducts = [], metaTitle, metaDescription }) {
    const [length, setLength]     = useState(16);
    const [opts, setOpts]         = useState({ upper: true, lower: true, numbers: true, symbols: true });
    const [password, setPassword] = useState('');
    const [copied, setCopied]     = useState(false);

    const regen = useCallback(() => {
        setPassword(generatePassword(length, opts));
        setCopied(false);
    }, [length, opts]);

    useEffect(() => { regen(); }, [length, opts]);

    function toggleOpt(key) {
        setOpts(prev => {
            const next = { ...prev, [key]: !prev[key] };
            if (Object.values(next).every(v => !v)) return prev;
            return next;
        });
    }

    async function copy() {
        if (!password) return;
        try {
            await navigator.clipboard.writeText(password);
        } catch {
            const el = document.createElement('textarea');
            el.value = password;
            el.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    const typeCount = Object.values(opts).filter(Boolean).length;
    const strength  = calcStrength(length, typeCount);

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
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Password Generator</h1>
                    <p className="mt-2 text-gray-500 max-w-2xl">
                        Generate cryptographically random passwords using your browser's built-in <code className="text-amber-700 bg-amber-50 px-1 rounded text-xs">crypto.getRandomValues()</code>.
                        Adjust length and character types. Nothing leaves your browser.
                    </p>
                </div>
            </div>

            <div className="max-w-[100rem] mx-auto px-4 py-8">
                <div className="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">
                    <div className="space-y-6">

                        {/* Password display */}
                        <div className="bg-gray-950 border border-gray-800 rounded-2xl p-5">
                            <div className="flex items-start gap-3">
                                <p className="flex-1 font-mono text-lg text-green-400 tracking-widest break-all leading-relaxed select-all min-h-[2.5rem]">
                                    {password || <span className="text-gray-600 text-base">Enable at least one character type</span>}
                                </p>
                                <div className="flex-shrink-0 flex flex-col gap-2">
                                    <button
                                        onClick={copy}
                                        disabled={!password}
                                        className="flex items-center justify-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded-lg transition-colors text-sm disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap"
                                    >
                                        {copied ? (
                                            <>
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
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
                                        onClick={regen}
                                        className="flex items-center justify-center gap-1.5 bg-gray-800 hover:bg-gray-700 text-gray-300 font-medium px-4 py-2 rounded-lg transition-colors text-sm whitespace-nowrap"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                        New
                                    </button>
                                </div>
                            </div>

                            {strength && (
                                <div className="mt-4 pt-4 border-t border-gray-800">
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="text-xs text-gray-500">Password strength</span>
                                        <span className={`text-xs font-bold ${strength.text}`}>{strength.label}</span>
                                    </div>
                                    <div className="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                                        <div
                                            className={`h-full rounded-full transition-all duration-300 ${strength.color}`}
                                            style={{ width: `${strength.pct}%` }}
                                        />
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Length */}
                        <div className="bg-white border border-gray-200 rounded-2xl p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="font-bold text-gray-900">Length</h2>
                                <span className="text-2xl font-black text-amber-600">{length}</span>
                            </div>
                            <input
                                type="range"
                                min={8}
                                max={64}
                                value={length}
                                onChange={e => setLength(Number(e.target.value))}
                                className="w-full accent-amber-500"
                            />
                            <div className="flex items-center justify-between mt-3">
                                <span className="text-xs text-gray-400">8</span>
                                <div className="flex gap-1">
                                    {[12, 16, 20, 32, 64].map(n => (
                                        <button
                                            key={n}
                                            onClick={() => setLength(n)}
                                            className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors ${
                                                length === n
                                                    ? 'bg-amber-100 text-amber-700 border border-amber-200'
                                                    : 'text-gray-400 hover:text-gray-700 hover:bg-gray-100'
                                            }`}
                                        >
                                            {n}
                                        </button>
                                    ))}
                                </div>
                                <span className="text-xs text-gray-400">64</span>
                            </div>
                        </div>

                        {/* Character types */}
                        <div className="bg-white border border-gray-200 rounded-2xl p-6">
                            <h2 className="font-bold text-gray-900 mb-4">Character Types</h2>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                {[
                                    { key: 'upper',   label: 'Uppercase', example: 'A–Z' },
                                    { key: 'lower',   label: 'Lowercase', example: 'a–z' },
                                    { key: 'numbers', label: 'Numbers',   example: '0–9' },
                                    { key: 'symbols', label: 'Symbols',   example: '!@#$' },
                                ].map(({ key, label, example }) => (
                                    <button
                                        key={key}
                                        onClick={() => toggleOpt(key)}
                                        className={`flex flex-col items-center gap-1.5 p-4 rounded-xl border-2 font-semibold transition-all select-none ${
                                            opts[key]
                                                ? 'border-amber-400 bg-amber-50 text-amber-700'
                                                : 'border-gray-200 bg-white text-gray-400 hover:border-gray-300'
                                        }`}
                                    >
                                        <span className="text-base font-mono font-bold">{example}</span>
                                        <span className="text-xs font-semibold">{label}</span>
                                        {opts[key] ? (
                                            <svg className="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        ) : (
                                            <div className="w-4 h-4" />
                                        )}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <p className="text-xs text-gray-400">
                            Uses <code className="bg-gray-100 px-1 rounded">crypto.getRandomValues()</code>, a cryptographically secure random number generator built into your browser. Nothing is sent to any server.
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
