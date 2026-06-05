import { useState } from 'react';
import { Head } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import ToolSidebar from '@/Components/Tools/ToolSidebar';

function charColor(len, { ideal, warn, max }) {
    if (len === 0)         return 'text-gray-400';
    if (len <= ideal)      return 'text-green-600';
    if (len <= warn)       return 'text-yellow-600';
    return                        'text-red-600';
}

function charBg(len, thresholds) {
    const c = charColor(len, thresholds);
    if (c === 'text-green-600')  return 'bg-green-50 border-green-300';
    if (c === 'text-yellow-600') return 'bg-yellow-50 border-yellow-300';
    if (c === 'text-red-600')    return 'bg-red-50 border-red-300';
    return 'bg-white border-gray-300';
}

function truncate(str, max) {
    if (!str) return '';
    return str.length > max ? str.slice(0, max - 3) + '…' : str;
}

function googleBreadcrumb(url) {
    try {
        const u = new URL(url.startsWith('http') ? url : `https://${url}`);
        const parts = [u.hostname.replace(/^www\./, '')];
        const segments = u.pathname.split('/').filter(Boolean);
        if (segments.length) parts.push(...segments.slice(0, 2));
        const joined = parts.join(' › ');
        return joined.length > 70 ? joined.slice(0, 67) + '…' : joined;
    } catch {
        return url || 'yourdomain.com › page';
    }
}

export default function MetaTagPreviewer({ sidebarProducts = [], metaTitle, metaDescription }) {
    const [title,      setTitle]      = useState('');
    const [desc,       setDesc]       = useState('');
    const [url,        setUrl]        = useState('');
    const [ogTitle,    setOgTitle]    = useState('');
    const [ogDesc,     setOgDesc]     = useState('');
    const [ogImage,    setOgImage]    = useState('');
    const [preview,    setPreview]    = useState('google');

    const displayTitle  = title      || 'Page Title';
    const displayDesc   = desc       || 'Meta description goes here. This is the text that appears under your page title in search results.';
    const displayUrl    = url        || 'https://yourdomain.com/page-slug';
    const socialTitle   = ogTitle    || title  || 'Page Title';
    const socialDesc    = ogDesc     || desc   || 'Description for social media sharing.';

    const TITLE_T = { ideal: 60, warn: 70, max: 100 };
    const DESC_T  = { ideal: 160, warn: 200, max: 300 };

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
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Meta Tag Previewer</h1>
                    <p className="mt-2 text-gray-500 max-w-2xl">
                        Preview how your page title and description appear in Google search results, Twitter cards, and Facebook shares. Character counters flag when you're in the safe zone.
                    </p>
                </div>
            </div>

            <div className="max-w-[100rem] mx-auto px-4 py-8">
                <div className="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">
                    <div className="space-y-8">

                        {/* Form */}
                        <div className="bg-white border border-gray-200 rounded-2xl p-6 space-y-5">
                            <h2 className="font-bold text-gray-900 text-base">Page meta tags</h2>

                            {/* Title */}
                            <div>
                                <div className="flex items-center justify-between mb-1.5">
                                    <label className="text-sm font-semibold text-gray-700">
                                        Page Title <span className="text-gray-400 font-normal">({"<title>"})</span>
                                    </label>
                                    <span className={`text-xs font-bold tabular-nums ${charColor(title.length, TITLE_T)}`}>
                                        {title.length} / 60
                                    </span>
                                </div>
                                <input
                                    type="text"
                                    value={title}
                                    onChange={e => setTitle(e.target.value)}
                                    placeholder="Best Wireless Earbuds 2025 | GadgetDrop"
                                    className={`w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 transition-colors ${charBg(title.length, TITLE_T)}`}
                                />
                                <p className="mt-1 text-xs text-gray-400">Keep under 60 characters to avoid truncation in Google.</p>
                            </div>

                            {/* Description */}
                            <div>
                                <div className="flex items-center justify-between mb-1.5">
                                    <label className="text-sm font-semibold text-gray-700">
                                        Meta Description <span className="text-gray-400 font-normal">({"<meta name=\"description\">"})</span>
                                    </label>
                                    <span className={`text-xs font-bold tabular-nums ${charColor(desc.length, DESC_T)}`}>
                                        {desc.length} / 160
                                    </span>
                                </div>
                                <textarea
                                    value={desc}
                                    onChange={e => setDesc(e.target.value)}
                                    placeholder="Our top picks for wireless earbuds this year, tested for sound quality, battery life, and comfort..."
                                    rows={3}
                                    className={`w-full px-4 py-2.5 rounded-xl border text-sm resize-none focus:outline-none focus:ring-2 focus:ring-amber-400 transition-colors ${charBg(desc.length, DESC_T)}`}
                                />
                                <p className="mt-1 text-xs text-gray-400">Keep under 160 characters. Google may rewrite descriptions that are too long or unhelpful.</p>
                            </div>

                            {/* URL */}
                            <div>
                                <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                                    Page URL <span className="text-gray-400 font-normal">(for Google breadcrumb display)</span>
                                </label>
                                <input
                                    type="url"
                                    value={url}
                                    onChange={e => setUrl(e.target.value)}
                                    placeholder="https://gadgetdrop.tech/posts/best-wireless-earbuds-2025"
                                    className="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"
                                />
                            </div>

                            {/* OG section */}
                            <div className="pt-4 border-t border-gray-100 space-y-4">
                                <h3 className="font-bold text-gray-800 text-sm">
                                    Open Graph / Social
                                    <span className="ml-2 text-xs font-normal text-gray-400">Optional, defaults to title & description above</span>
                                </h3>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">OG Title <span className="text-gray-400 font-normal">(og:title)</span></label>
                                    <input
                                        type="text"
                                        value={ogTitle}
                                        onChange={e => setOgTitle(e.target.value)}
                                        placeholder={title || 'Same as page title if left empty'}
                                        className="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">OG Description <span className="text-gray-400 font-normal">(og:description)</span></label>
                                    <textarea
                                        value={ogDesc}
                                        onChange={e => setOgDesc(e.target.value)}
                                        placeholder={desc || 'Same as meta description if left empty'}
                                        rows={2}
                                        className="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">OG Image URL <span className="text-gray-400 font-normal">(og:image)</span></label>
                                    <input
                                        type="url"
                                        value={ogImage}
                                        onChange={e => setOgImage(e.target.value)}
                                        placeholder="https://gadgetdrop.tech/images/og-earbuds.jpg"
                                        className="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"
                                    />
                                    <p className="mt-1 text-xs text-gray-400">Recommended: 1200×630 px for Twitter/Facebook. Smaller images may not display.</p>
                                </div>
                            </div>
                        </div>

                        {/* Preview area */}
                        <div className="bg-white border border-gray-200 rounded-2xl p-6 space-y-4">
                            <div className="flex items-center justify-between">
                                <h2 className="font-bold text-gray-900 text-base">Live preview</h2>
                                <div className="flex border border-gray-200 rounded-lg overflow-hidden">
                                    {[
                                        { id: 'google',   label: 'Google' },
                                        { id: 'twitter',  label: 'Twitter' },
                                        { id: 'facebook', label: 'Facebook' },
                                    ].map(({ id, label }) => (
                                        <button
                                            key={id}
                                            onClick={() => setPreview(id)}
                                            className={`px-4 py-1.5 text-xs font-semibold transition-colors ${
                                                preview === id
                                                    ? 'bg-amber-500 text-white'
                                                    : 'bg-white text-gray-500 hover:text-gray-700'
                                            }`}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {preview === 'google' && (
                                <div className="bg-white rounded-xl border border-gray-200 p-5 max-w-[600px]">
                                    <p className="text-xs text-gray-500 mb-1 truncate">{googleBreadcrumb(displayUrl)}</p>
                                    <p className="text-[#1a0dab] text-xl font-normal hover:underline cursor-pointer leading-tight mb-1">
                                        {truncate(displayTitle, 60)}
                                    </p>
                                    <p className="text-sm text-gray-600 leading-snug">
                                        {truncate(displayDesc, 160)}
                                    </p>
                                </div>
                            )}

                            {preview === 'twitter' && (
                                <div className="max-w-[500px] rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                                    {ogImage ? (
                                        <img src={ogImage} alt="OG preview" className="w-full h-52 object-cover" onError={e => { e.target.style.display = 'none'; }} />
                                    ) : (
                                        <div className="w-full h-52 bg-gray-100 flex items-center justify-center">
                                            <span className="text-xs text-gray-400">No og:image set</span>
                                        </div>
                                    )}
                                    <div className="p-4 bg-white">
                                        <p className="text-xs text-gray-400 uppercase tracking-wide mb-1">
                                            {(() => { try { return new URL(displayUrl.startsWith('http') ? displayUrl : `https://${displayUrl}`).hostname.replace(/^www\./, ''); } catch { return 'yourdomain.com'; } })()}
                                        </p>
                                        <p className="font-bold text-gray-900 text-sm leading-snug">{truncate(socialTitle, 70)}</p>
                                        <p className="text-sm text-gray-500 mt-0.5 leading-snug">{truncate(socialDesc, 125)}</p>
                                    </div>
                                </div>
                            )}

                            {preview === 'facebook' && (
                                <div className="max-w-[500px] rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                                    {ogImage ? (
                                        <img src={ogImage} alt="OG preview" className="w-full h-56 object-cover" onError={e => { e.target.style.display = 'none'; }} />
                                    ) : (
                                        <div className="w-full h-56 bg-gray-100 flex items-center justify-center">
                                            <span className="text-xs text-gray-400">No og:image set</span>
                                        </div>
                                    )}
                                    <div className="p-3 bg-[#f0f2f5]">
                                        <p className="text-xs text-gray-500 uppercase tracking-wide mb-0.5">
                                            {(() => { try { return new URL(displayUrl.startsWith('http') ? displayUrl : `https://${displayUrl}`).hostname.replace(/^www\./, ''); } catch { return 'yourdomain.com'; } })()}
                                        </p>
                                        <p className="font-bold text-gray-900 text-sm leading-snug">{truncate(socialTitle, 88)}</p>
                                        <p className="text-sm text-gray-500 leading-snug">{truncate(socialDesc, 110)}</p>
                                    </div>
                                </div>
                            )}

                            {/* Character summary */}
                            <div className="flex flex-wrap gap-3 pt-2 border-t border-gray-100">
                                <div className="flex items-center gap-1.5">
                                    <span className={`w-2 h-2 rounded-full ${title.length > 0 && title.length <= 60 ? 'bg-green-500' : title.length > 60 ? 'bg-red-500' : 'bg-gray-300'}`} />
                                    <span className="text-xs text-gray-500">Title: <strong className="text-gray-700">{title.length}</strong> chars</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className={`w-2 h-2 rounded-full ${desc.length > 0 && desc.length <= 160 ? 'bg-green-500' : desc.length > 160 ? 'bg-red-500' : 'bg-gray-300'}`} />
                                    <span className="text-xs text-gray-500">Description: <strong className="text-gray-700">{desc.length}</strong> chars</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className={`w-2 h-2 rounded-full ${ogImage ? 'bg-green-500' : 'bg-gray-300'}`} />
                                    <span className="text-xs text-gray-500">OG image: <strong className="text-gray-700">{ogImage ? 'set' : 'not set'}</strong></span>
                                </div>
                            </div>
                        </div>

                        <p className="text-xs text-gray-400">
                            This preview is approximate. Google may rewrite titles and descriptions based on the page content and search query. Social networks cache OG tags. Use the platform's sharing debugger to force a refresh after updating.
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
