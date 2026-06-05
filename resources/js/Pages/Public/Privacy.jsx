import { Head } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

function Section({ title, children }) {
    return (
        <section className="space-y-3">
            <h2 className="text-lg font-bold text-gray-900">{title}</h2>
            <div className="text-gray-600 leading-relaxed space-y-3 text-sm">{children}</div>
        </section>
    );
}

export default function PrivacyPage() {
    return (
        <PublicLayout>
            <Head title="Privacy Policy | GadgetDrop">
                <meta name="description" content="GadgetDrop privacy policy. Learn how we collect, use, and protect your data." />
            </Head>

            <div className="bg-white border-b border-gray-100">
                <div className="max-w-3xl mx-auto px-4 py-12">
                    <p className="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-2">Legal</p>
                    <h1 className="text-3xl font-extrabold text-gray-900">Privacy Policy</h1>
                    <p className="text-sm text-gray-400 mt-2">Last updated: May 2026</p>
                </div>
            </div>

            <div className="max-w-3xl mx-auto px-4 py-12 space-y-10">

                <Section title="Overview">
                    <p>
                        GadgetDrop ("we," "our," or "us") operates GadgetDrop.tech. This policy explains what information we collect, how we use it, and your rights regarding your data.
                        By using this site you agree to the collection and use of information as described here.
                    </p>
                </Section>

                <Section title="Information we collect">
                    <p><strong>Email address:</strong> if you subscribe to our newsletter, we collect your email address to send you weekly content digests. You can unsubscribe at any time using the link in any email we send.</p>
                    <p><strong>Affiliate click data:</strong> when you click an outbound Amazon link, we log a hashed version of your IP address (one-way hash, not reversible), the referring page, your browser user agent string, and a timestamp. This is used solely for measuring affiliate link performance. We do not store your raw IP address.</p>
                    <p><strong>Usage data:</strong> like most websites, our hosting provider automatically collects standard server log data including anonymised IP addresses, pages visited, browser type, and referring URLs. We use this to understand site traffic.</p>
                    <p><strong>Cookies:</strong> we and our third-party advertising partners (Google AdSense) use cookies to serve and personalise ads. See the Advertising section below for details and opt-out options.</p>
                </Section>

                <Section title="How we use your information">
                    <ul className="list-disc list-inside space-y-1.5">
                        <li>To send the weekly GadgetDrop newsletter (subscribers only)</li>
                        <li>To measure the performance of affiliate links and improve our content</li>
                        <li>To understand how visitors use the site and improve its design</li>
                        <li>To serve relevant advertising through Google AdSense</li>
                    </ul>
                    <p>We do not sell, rent, or share your personal information with third parties for their own marketing purposes.</p>
                </Section>

                <Section title="Advertising: Google AdSense">
                    <p>
                        GadgetDrop uses Google AdSense to display advertisements. Google and its partners use cookies to serve ads based on your prior visits to this and other websites.
                        You may opt out of personalised advertising by visiting <a href="https://www.google.com/settings/ads" className="text-indigo-600 underline" target="_blank" rel="noopener noreferrer">Google Ad Settings</a> or <a href="https://www.aboutads.info/" className="text-indigo-600 underline" target="_blank" rel="noopener noreferrer">aboutads.info</a>.
                    </p>
                    <p>
                        Google's use of advertising cookies enables it and its partners to serve ads based on your visit to GadgetDrop and other sites on the Internet.
                        For more information, see Google's <a href="https://policies.google.com/privacy" className="text-indigo-600 underline" target="_blank" rel="noopener noreferrer">Privacy & Terms</a>.
                    </p>
                </Section>

                <Section title="Amazon Associates">
                    <p>
                        GadgetDrop participates in the Amazon Services LLC Associates Program. Amazon may use cookies to track purchases resulting from links on this site.
                        See <a href="https://www.amazon.com/gp/help/customer/display.html?nodeId=468496" className="text-indigo-600 underline" target="_blank" rel="noopener noreferrer">Amazon's Privacy Notice</a> for details.
                    </p>
                </Section>

                <Section title="Email newsletter">
                    <p>
                        If you subscribe to our newsletter, we store your email address for the sole purpose of sending you the weekly GadgetDrop digest.
                        We use a one-click unsubscribe link in every email. You can also unsubscribe at any time by visiting <strong>GadgetDrop.tech/unsubscribe</strong>.
                        We do not share subscriber email addresses with any third party.
                    </p>
                </Section>

                <Section title="Data retention">
                    <p>Subscriber email addresses are retained until you unsubscribe, at which point they are permanently deleted. Affiliate click logs are retained for up to 24 months for performance analysis and then deleted. Server access logs are retained by our hosting provider per their standard policy.</p>
                </Section>

                <Section title="Your rights">
                    <p>Depending on your location you may have rights to access, correct, or delete personal data we hold about you. To exercise these rights or ask any privacy-related question, contact us at <a href="mailto:hello@gadgetdrop.tech" className="text-indigo-600 underline">hello@gadgetdrop.tech</a>.</p>
                </Section>

                <Section title="Children's privacy">
                    <p>GadgetDrop is not directed at children under 13. We do not knowingly collect personal information from children under 13. If you believe we have inadvertently collected such information, please contact us immediately.</p>
                </Section>

                <Section title="Changes to this policy">
                    <p>We may update this policy from time to time. The date at the top of this page reflects the most recent revision. Continued use of the site after changes constitutes acceptance of the updated policy.</p>
                </Section>

                <Section title="Contact">
                    <p>
                        Questions about this policy? Email us at{' '}
                        <a href="mailto:hello@gadgetdrop.tech" className="text-indigo-600 underline">hello@gadgetdrop.tech</a>.
                    </p>
                </Section>

            </div>
        </PublicLayout>
    );
}
