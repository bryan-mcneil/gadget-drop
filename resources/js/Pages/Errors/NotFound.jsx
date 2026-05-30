import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function NotFound() {
    return (
        <PublicLayout>
            <Head title="Page Not Found — GadgetDrop" />

            <div className="min-h-[60vh] flex items-center justify-center px-4">
                <div className="text-center max-w-md">
                    <p className="text-8xl font-extrabold text-indigo-100 select-none mb-2">404</p>
                    <h1 className="text-2xl font-extrabold text-gray-900 mb-3">Page not found</h1>
                    <p className="text-gray-500 mb-8 leading-relaxed">
                        This page doesn't exist or may have been moved. Try the homepage or search for what you're looking for.
                    </p>
                    <div className="flex items-center justify-center gap-3 flex-wrap">
                        <Link href={route('home')}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">
                            Back to home
                        </Link>
                        <Link href={route('search')}
                            className="border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-xl transition-colors text-sm">
                            Search posts
                        </Link>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
