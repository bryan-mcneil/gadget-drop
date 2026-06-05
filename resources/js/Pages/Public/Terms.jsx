import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

function Section({ title, children }) {
    return (
        <section className="space-y-3">
            <h2 className="text-lg font-bold text-gray-900">{title}</h2>
            <div className="text-gray-600 leading-relaxed space-y-3 text-sm">{children}</div>
        </section>
    );
}

export default function TermsPage() {
    return (
        <PublicLayout>
            <Head title="Terms of Service | GadgetDrop">
                <meta name="description" content="GadgetDrop terms of service. Read the rules and conditions for using this site." />
            </Head>

            <div className="bg-white border-b border-gray-100">
                <div className="max-w-3xl mx-auto px-4 py-12">
                    <p className="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-2">Legal</p>
                    <h1 className="text-3xl font-extrabold text-gray-900">Terms of Service</h1>
                    <p className="text-sm text-gray-400 mt-2">Last updated: May 2026</p>
                </div>
            </div>

            <div className="max-w-3xl mx-auto px-4 py-12 space-y-10">

                <Section title="Agreement to terms">
                    <p>
                        By accessing or using GadgetDrop.tech ("the site," "we," "our"), you agree to be bound by these Terms of Service.
                        If you do not agree, please do not use the site.
                    </p>
                </Section>

                <Section title="What GadgetDrop is">
                    <p>
                        GadgetDrop is an editorial content site that publishes product reviews, buying guides, and tech tips. Content is produced with editorial AI writing assistance and reviewed before publication.
                        We are not a retailer: we do not sell products directly.
                    </p>
                </Section>

                <Section title="Affiliate links and advertising">
                    <p>
                        GadgetDrop participates in the Amazon Services LLC Associates Program. Product links on this site are affiliate links: if you click a link and make a qualifying purchase, we may earn a commission at no extra cost to you.
                        This never influences which products we cover or what we write about them.
                    </p>
                    <p>
                        The site may also display advertisements through Google AdSense. We do not control the content of third-party ads.
                    </p>
                </Section>

                <Section title="Accuracy of information">
                    <p>
                        We aim to keep pricing, availability, and product information accurate, but this data changes frequently. Product prices and availability shown on GadgetDrop are indicative only; always verify current information on Amazon before purchasing.
                        GadgetDrop is not responsible for price discrepancies or stock availability.
                    </p>
                </Section>

                <Section title="Intellectual property">
                    <p>
                        All editorial content on GadgetDrop, including text, graphics, and page designs, is owned by or licensed to GadgetDrop. You may not reproduce, republish, or distribute this content without written permission.
                    </p>
                    <p>
                        Product images are sourced from Amazon via the Amazon Product Advertising API and remain the property of Amazon or the respective rights holders.
                    </p>
                </Section>

                <Section title="Newsletter">
                    <p>
                        By subscribing to the GadgetDrop newsletter, you consent to receive weekly email digests. You can unsubscribe at any time using the link in any email or by visiting{' '}
                        <Link href={route('unsubscribe')} className="text-indigo-600 underline">GadgetDrop.tech/unsubscribe</Link>.
                        We will not share your email address with any third party.
                    </p>
                </Section>

                <Section title="Disclaimer of warranties">
                    <p>
                        GadgetDrop is provided "as is" without warranties of any kind, express or implied. We do not warrant that the site will be uninterrupted, error-free, or free of harmful components.
                        We are not liable for any damages arising from your use of the site or any products purchased through affiliate links.
                    </p>
                </Section>

                <Section title="Limitation of liability">
                    <p>
                        To the fullest extent permitted by law, GadgetDrop and its operators shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of this site or reliance on any content published here.
                        Our total liability for any claim shall not exceed $10.
                    </p>
                </Section>

                <Section title="Third-party sites">
                    <p>
                        GadgetDrop links to Amazon and other third-party websites. We are not responsible for the content, policies, or practices of any third-party sites. Visiting those sites is at your own risk.
                    </p>
                </Section>

                <Section title="Changes to these terms">
                    <p>
                        We may revise these terms at any time. Continued use of the site after changes are posted constitutes acceptance of the updated terms. The date at the top of this page reflects the most recent revision.
                    </p>
                </Section>

                <Section title="Governing law">
                    <p>
                        These terms are governed by the laws of the United States, without regard to conflict-of-law principles.
                    </p>
                </Section>

                <Section title="Contact">
                    <p>
                        Questions about these terms? Email us at{' '}
                        <a href="mailto:hello@gadgetdrop.tech" className="text-indigo-600 underline">hello@gadgetdrop.tech</a>{' '}
                        or visit our <Link href={route('contact')} className="text-indigo-600 underline">contact page</Link>.
                    </p>
                </Section>

            </div>
        </PublicLayout>
    );
}
