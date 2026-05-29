import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';

export default function Newsletter({ subscriberCount }) {
    const [testEmail, setTestEmail]     = useState('');
    const [testStatus, setTestStatus]   = useState(null);
    const [testLoading, setTestLoading] = useState(false);

    const [sendStatus, setSendStatus]   = useState(null);
    const [sendLoading, setSendLoading] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);

    async function handleTest(e) {
        e.preventDefault();
        setTestLoading(true);
        setTestStatus(null);
        try {
            const { data } = await axios.post(route('admin.newsletter.test'), { email: testEmail });
            setTestStatus({ ok: true, msg: data.message });
        } catch (err) {
            setTestStatus({ ok: false, msg: err.response?.data?.message ?? 'Something went wrong.' });
        } finally {
            setTestLoading(false);
        }
    }

    async function handleSendAll() {
        setSendLoading(true);
        setSendStatus(null);
        setConfirmOpen(false);
        try {
            const { data } = await axios.post(route('admin.newsletter.send-all'));
            setSendStatus({ ok: true, msg: data.message });
        } catch (err) {
            setSendStatus({ ok: false, msg: err.response?.data?.message ?? 'Something went wrong.' });
        } finally {
            setSendLoading(false);
        }
    }

    return (
        <AuthenticatedLayout header={
            <h2 className="text-xl font-semibold text-gray-800">Newsletter</h2>
        }>
            <Head title="Newsletter" />

            <div className="py-8 px-4 max-w-2xl mx-auto space-y-6">

                {/* Subscriber count */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 flex items-center gap-5">
                    <div className="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                        <svg className="w-7 h-7 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4a4 4 0 11-8 0 4 4 0 018 0zm6 4a2 2 0 100-4 2 2 0 000 4zM3 16a2 2 0 100-4 2 2 0 000 4z" />
                        </svg>
                    </div>
                    <div>
                        <p className="text-3xl font-extrabold text-gray-900">{subscriberCount.toLocaleString()}</p>
                        <p className="text-sm text-gray-500">active subscribers</p>
                    </div>
                </div>

                {/* Test send */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
                    <div>
                        <h3 className="text-base font-semibold text-gray-800">Send Test Email</h3>
                        <p className="text-sm text-gray-500 mt-0.5">Preview this week's digest before sending to subscribers.</p>
                    </div>

                    <form onSubmit={handleTest} className="flex gap-3">
                        <input
                            type="email"
                            value={testEmail}
                            onChange={e => setTestEmail(e.target.value)}
                            placeholder="you@example.com"
                            required
                            className="flex-1 border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <button
                            type="submit"
                            disabled={testLoading}
                            className="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition">
                            {testLoading ? 'Sending…' : 'Send Test'}
                        </button>
                    </form>

                    {testStatus && (
                        <p className={`text-sm font-medium ${testStatus.ok ? 'text-emerald-600' : 'text-red-500'}`}>
                            {testStatus.ok ? '✓ ' : '✗ '}{testStatus.msg}
                        </p>
                    )}
                </div>

                {/* Send to all */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
                    <div>
                        <h3 className="text-base font-semibold text-gray-800">Send to All Subscribers</h3>
                        <p className="text-sm text-gray-500 mt-0.5">
                            Sends this week's digest to all <span className="font-semibold text-gray-700">{subscriberCount.toLocaleString()}</span> subscribers.
                            Each email includes a personal unsubscribe link.
                        </p>
                    </div>

                    {!confirmOpen ? (
                        <button
                            onClick={() => setConfirmOpen(true)}
                            disabled={sendLoading || subscriberCount === 0}
                            className="bg-gray-800 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-40 transition">
                            {sendLoading ? 'Sending…' : 'Send Weekly Digest'}
                        </button>
                    ) : (
                        <div className="flex items-center gap-3 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <p className="text-sm text-amber-800 flex-1">
                                This will send to <strong>{subscriberCount.toLocaleString()} people</strong>. Are you sure?
                            </p>
                            <button onClick={handleSendAll}
                                className="bg-red-600 text-white px-4 py-1.5 rounded-lg text-sm font-medium hover:bg-red-700 transition">
                                Yes, Send
                            </button>
                            <button onClick={() => setConfirmOpen(false)}
                                className="bg-white border border-gray-300 text-gray-600 px-4 py-1.5 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                                Cancel
                            </button>
                        </div>
                    )}

                    {sendStatus && (
                        <p className={`text-sm font-medium ${sendStatus.ok ? 'text-emerald-600' : 'text-red-500'}`}>
                            {sendStatus.ok ? '✓ ' : '✗ '}{sendStatus.msg}
                        </p>
                    )}
                </div>

                {/* Note about mail config */}
                <p className="text-xs text-gray-400 text-center">
                    Make sure <code className="bg-gray-100 px-1 py-0.5 rounded">MAIL_MAILER</code> is configured in your production <code className="bg-gray-100 px-1 py-0.5 rounded">.env</code> before sending to subscribers.
                </p>

            </div>
        </AuthenticatedLayout>
    );
}
