import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolCard from '@/Components/Tools/ToolCard';

export default function ToolsIndex({ tools }) {
    return (
        <PublicLayout>
            <Head title="Free Online Tools — GadgetDrop">
                <meta name="description" content="Free browser-based tools for developers and everyday users. Validate JSON, minify JS & CSS, convert images, and more — no sign-up, no server upload." />
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

            {/* Tool grid */}
            <div className="max-w-[100rem] mx-auto px-4 py-12">
                {tools.length > 0 ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                        {tools.map(tool => (
                            <ToolCard key={tool.slug} {...tool} />
                        ))}
                    </div>
                ) : (
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
