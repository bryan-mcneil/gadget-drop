import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';

export default function TagsIndex({ tags }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    function submitCreate(e) {
        e.preventDefault();
        post(route('admin.tags.store'), { onSuccess: () => reset() });
    }

    function destroy(id) {
        if (confirm('Delete this tag?')) router.delete(route('admin.tags.destroy', id));
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Tags</h2>}>
            <Head title="Tags" />

            <div className="py-8 px-4 max-w-2xl mx-auto space-y-6">
                {flash?.success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{flash.success}</div>
                )}

                <form onSubmit={submitCreate} className="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                    <h3 className="font-semibold text-gray-700">Add Tag</h3>
                    <div className="flex gap-3">
                        <input type="text" placeholder="Tag name" value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="flex-1 border-gray-300 rounded-lg shadow-sm text-sm" />
                        <button type="submit" disabled={processing}
                            className="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                            Add
                        </button>
                    </div>
                    {errors.name && <p className="text-xs text-red-500">{errors.name}</p>}
                </form>

                <div className="flex flex-wrap gap-2">
                    {tags.map((tag) => (
                        <span key={tag.id} className="inline-flex items-center gap-1.5 bg-white border border-gray-200 rounded-full px-3 py-1.5 text-sm text-gray-700 shadow-sm">
                            {tag.name}
                            <span className="text-gray-400 text-xs">({tag.posts_count})</span>
                            <button onClick={() => destroy(tag.id)} className="text-gray-300 hover:text-red-500 ml-1 leading-none">×</button>
                        </span>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
