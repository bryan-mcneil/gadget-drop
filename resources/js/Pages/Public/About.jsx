import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function AboutPage() {
    return (
        <PublicLayout>
            <Head title="About GadgetDrop">
                <meta name="description" content="GadgetDrop is a daily tech picks and gadget review site. Learn who we are, how we find the best gear, and how our content is made." />
            </Head>

            {/* Hero */}
            <div className="bg-white border-b border-gray-100">
                <div className="max-w-3xl mx-auto px-4 py-16 text-center">
                    <p className="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-3">About</p>
                    <h1 className="text-4xl font-extrabold text-gray-900 tracking-tight mb-4">
                        Gadget<span className="text-indigo-600">Drop</span>
                    </h1>
                    <p className="text-lg text-gray-500 leading-relaxed max-w-xl mx-auto">
                        A daily tech picks site built for people who want the honest story on gear — not a wall of specs and marketing fluff.
                    </p>
                </div>
            </div>

            <div className="max-w-3xl mx-auto px-4 py-14 space-y-14">

                {/* Mission */}
                <section className="space-y-4">
                    <h2 className="text-xl font-bold text-gray-900">What we do</h2>
                    <p className="text-gray-600 leading-relaxed">
                        GadgetDrop publishes daily reviews, buying guides, and tech tips focused on consumer electronics and gadgets available on Amazon.
                        Every post is written to answer one question: <em>is this worth your money?</em> We skip the spec sheets and focus on real-world use — who it's for, what it actually does well, and when you should skip it.
                    </p>
                    <p className="text-gray-600 leading-relaxed">
                        We also publish Tech Tips: short, actionable guides to common tech problems drawn from real community discussions. No filler, no padding — just the fix.
                    </p>
                </section>

                {/* How content is made */}
                <section className="space-y-4">
                    <h2 className="text-xl font-bold text-gray-900">How our content is made</h2>
                    <p className="text-gray-600 leading-relaxed">
                        GadgetDrop uses a small set of editorial writing personas — Maya, Ken, Elizabeth, and Sam — each representing a distinct voice and perspective on tech. These are editorial characters created and maintained by the GadgetDrop editorial team, not independent contributors or real individuals.
                    </p>
                    <p className="text-gray-600 leading-relaxed">
                        Our content is produced with the assistance of AI writing tools and reviewed editorially before publication. We use AI as a writing aid, not a replacement for editorial judgment. Every post reflects the editorial position of GadgetDrop.
                    </p>
                    <p className="text-gray-600 leading-relaxed">
                        Product picks are based on publicly available information, Amazon listings, user reviews, and editorial research. We do not conduct independent lab testing.
                    </p>
                </section>

                {/* Editorial voices */}
                <section className="space-y-6">
                    <h2 className="text-xl font-bold text-gray-900">Our editorial voices</h2>
                    <p className="text-sm text-gray-500">
                        Each voice below is a GadgetDrop editorial persona — a distinct writing style and perspective maintained by our team.
                    </p>
                    <div className="grid sm:grid-cols-2 gap-4">
                        {[
                            { name: 'Maya Reeves',     desc: 'Precise and warm. Explains tech in real-world terms with analogy and depth.' },
                            { name: 'Ken Fujimoto',    desc: 'Casual and punchy. Numbers, savings, and dry humor. The Reddit power user voice.' },
                            { name: 'Elizabeth Avery', desc: 'Creative and bubbly. Writes about tech through the lens of freedom and expression.' },
                            { name: 'Sam Johnson',     desc: 'Conversational and opinionated. Focused on how gear fits into everyday life.' },
                        ].map(v => (
                            <div key={v.name} className="bg-gray-50 rounded-xl p-5 border border-gray-200">
                                <p className="font-semibold text-gray-900 mb-1">{v.name}</p>
                                <p className="text-sm text-gray-500">{v.desc}</p>
                                <p className="text-xs text-indigo-400 mt-2 font-medium">GadgetDrop editorial persona</p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Affiliate disclosure */}
                <section className="space-y-4 bg-amber-50 border border-amber-100 rounded-xl p-6">
                    <h2 className="text-lg font-bold text-gray-900">Affiliate disclosure</h2>
                    <p className="text-gray-600 leading-relaxed text-sm">
                        GadgetDrop is a participant in the Amazon Services LLC Associates Program, an affiliate advertising program designed to provide a means for sites to earn advertising fees by advertising and linking to Amazon.com.
                        When you click an Amazon link on GadgetDrop and make a qualifying purchase, we may earn a small commission at <strong>no extra cost to you</strong>.
                        This never influences our editorial recommendations — we only cover products we genuinely believe are worth your attention.
                    </p>
                </section>

                {/* Contact */}
                <section className="space-y-3 text-center">
                    <h2 className="text-xl font-bold text-gray-900">Get in touch</h2>
                    <p className="text-gray-500">Questions, corrections, or partnership enquiries — we'd love to hear from you.</p>
                    <Link href={route('contact')}
                        className="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                        Contact us →
                    </Link>
                </section>

            </div>
        </PublicLayout>
    );
}
