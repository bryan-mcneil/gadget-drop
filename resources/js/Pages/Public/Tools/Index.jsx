import { Head } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolCard from '@/Components/Tools/ToolCard';

const CATEGORIES = [
    {
        key: 'developer',
        label: 'Developer Tools',
        description: 'Validate, minify, and debug code right in your browser.',
        icon: (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
            </svg>
        ),
    },
    {
        key: 'image',
        label: 'Image Tools',
        description: 'Convert, resize, and crop images without uploading anything.',
        icon: (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
        ),
    },
];

export default function ToolsIndex({ tools }) {
    const grouped = CATEGORIES.map(cat => ({
        ...cat,
        tools: tools.filter(t => t.category === cat.key),
    })).filter(cat => cat.tools.length > 0);

    const uncategorized = tools.filter(t => !CATEGORIES.some(c => c.key === t.category));

    return (
        <PublicLayout>
            <Head title="Free Online Tools — GadgetDrop">
                <meta name="description" content="Free browser-based tools for developers and everyday users. Validate JSON, minify JS & CSS, convert and crop images — no sign-up, no server upload." />
            </Head>

            {/* Hero */}
            <div className="bg-white border-b border-gray-100">
                <div className="max-w-[100rem] mx-auto px-4 py-14 text-center">
                    <div className="inline-flex items-center gap-2 bg-amber-50 text-amber-700 text-xs font-semibold
                                    uppercase tracking-widest px-3 py-1.5 rounded-full mb-4">
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.598-2.167A9.027 9.027 0 0 1 9.496 3.28c-1.586.068-3.07.817-4.188 2.015L4.5 6.122" />
                        </svg>
                        Free Tools
                    </div>
                    <h1 className="text-4xl font-extrabold text-gray-900 tracking-tight mb-4">
                        Online Tools That Actually <span className="text-amber-500">Work</span>
                    </h1>
                    <p className="text-lg text-gray-500 max-w-xl mx-auto leading-relaxed">
                        Fast, free, and private. Everything runs in your browser, nothing is uploaded to a server.
                    </p>
                </div>
            </div>

            {/* Categorized tool grid */}
            <div className="max-w-[100rem] mx-auto px-4 py-12 space-y-12">
                {grouped.map(cat => (
                    <section key={cat.key}>
                        <div className="flex items-center gap-3 mb-6">
                            <div className="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
                                {cat.icon}
                            </div>
                            <div>
                                <h2 className="text-lg font-bold text-gray-900">{cat.label}</h2>
                                <p className="text-sm text-gray-500">{cat.description}</p>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                            {cat.tools.map(tool => (
                                <ToolCard key={tool.slug} {...tool} />
                            ))}
                        </div>
                    </section>
                ))}

                {uncategorized.length > 0 && (
                    <section>
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                            {uncategorized.map(tool => (
                                <ToolCard key={tool.slug} {...tool} />
                            ))}
                        </div>
                    </section>
                )}

                {tools.length === 0 && (
                    <p className="text-center text-gray-400 py-16">Tools coming soon.</p>
                )}
            </div>

            {/* Privacy callout */}
            <div className="max-w-[100rem] mx-auto px-4 pb-16">
                <div className="bg-amber-50 border border-amber-100 rounded-2xl p-8 text-center">
                    <h2 className="text-lg font-bold text-gray-900 mb-2">100% Private, 100% Free</h2>
                    <p className="text-sm text-gray-500 max-w-lg mx-auto">
                        Every tool on this page runs entirely in your browser. Your code, JSON, and images never leave your device.
                        No accounts, no limits, no cost.
                    </p>
                </div>
            </div>
        </PublicLayout>
    );
}
