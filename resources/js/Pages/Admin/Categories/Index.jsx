import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ImageUploader from '@/Components/ImageUploader';

export default function CategoriesIndex({ categories }) {
    const { flash } = usePage().props;
    const [editing, setEditing] = useState(null);

    const createForm = useForm({ name: '', description: '', featured_image: '' });
    const editForm   = useForm({ name: '', description: '', featured_image: '' });

    function startEdit(cat) {
        setEditing(cat.id);
        editForm.setData({
            name:           cat.name,
            description:    cat.description ?? '',
            featured_image: cat.featured_image ?? '',
        });
    }

    function submitCreate(e) {
        e.preventDefault();
        createForm.post(route('admin.categories.store'), { onSuccess: () => createForm.reset() });
    }

    function submitEdit(e, id) {
        e.preventDefault();
        editForm.put(route('admin.categories.update', id), { onSuccess: () => setEditing(null) });
    }

    function destroy(id) {
        if (confirm('Delete this category?')) router.delete(route('admin.categories.destroy', id));
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Categories</h2>}>
            <Head title="Categories" />

            <div className="py-8 px-4 max-w-3xl mx-auto space-y-6">
                {flash?.success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                        {flash.success}
                    </div>
                )}

                {/* Create form */}
                <form onSubmit={submitCreate} className="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                    <h3 className="font-semibold text-gray-700">Add Category</h3>
                    <div className="grid grid-cols-2 gap-3">
                        <input
                            type="text"
                            placeholder="Category name *"
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value)}
                            className="border-gray-300 rounded-lg shadow-sm text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Description (optional)"
                            value={createForm.data.description}
                            onChange={(e) => createForm.setData('description', e.target.value)}
                            className="border-gray-300 rounded-lg shadow-sm text-sm"
                        />
                    </div>
                    <ImageUploader
                        value={createForm.data.featured_image}
                        onChange={(url) => createForm.setData('featured_image', url)}
                        placeholder="Featured image URL (optional)"
                        previewClass="h-24"
                    />
                    {createForm.errors.name && (
                        <p className="text-xs text-red-500">{createForm.errors.name}</p>
                    )}
                    <button
                        type="submit"
                        disabled={createForm.processing}
                        className="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Add
                    </button>
                </form>

                {/* List */}
                <div className="bg-white rounded-xl shadow overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th className="px-4 py-3 text-left w-12" />
                                <th className="px-4 py-3 text-left">Name</th>
                                <th className="px-4 py-3 text-left">Posts</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {categories.map((cat) => (
                                editing === cat.id ? (
                                    /* ── Expanded edit row ── */
                                    <tr key={cat.id} className="bg-indigo-50/40">
                                        <td colSpan={4} className="px-4 py-4">
                                            <form onSubmit={(e) => submitEdit(e, cat.id)} className="space-y-3">
                                                <div className="grid grid-cols-2 gap-3">
                                                    <input
                                                        type="text"
                                                        value={editForm.data.name}
                                                        onChange={(e) => editForm.setData('name', e.target.value)}
                                                        className="border-gray-300 rounded-lg shadow-sm text-sm"
                                                        placeholder="Category name"
                                                        autoFocus
                                                    />
                                                    <input
                                                        type="text"
                                                        value={editForm.data.description}
                                                        onChange={(e) => editForm.setData('description', e.target.value)}
                                                        className="border-gray-300 rounded-lg shadow-sm text-sm"
                                                        placeholder="Description (optional)"
                                                    />
                                                </div>
                                                <ImageUploader
                                                    value={editForm.data.featured_image}
                                                    onChange={(url) => editForm.setData('featured_image', url)}
                                                    placeholder="Featured image URL (optional)"
                                                    previewClass="h-24"
                                                />
                                                {editForm.errors.name && (
                                                    <p className="text-xs text-red-500">{editForm.errors.name}</p>
                                                )}
                                                <div className="flex gap-2">
                                                    <button
                                                        type="submit"
                                                        disabled={editForm.processing}
                                                        className="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                                                    >
                                                        Save
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => setEditing(null)}
                                                        className="text-gray-500 px-3 py-1.5 rounded-lg text-sm hover:bg-gray-100"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                ) : (
                                    /* ── Normal row ── */
                                    <tr key={cat.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            {cat.featured_image ? (
                                                <img
                                                    src={cat.featured_image}
                                                    alt={cat.name}
                                                    className="w-10 h-10 rounded-lg object-cover"
                                                />
                                            ) : (
                                                <div className="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                                                    <span className="text-indigo-500 font-bold text-sm">
                                                        {cat.name.charAt(0)}
                                                    </span>
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-gray-900">{cat.name}</p>
                                            {cat.description && (
                                                <p className="text-xs text-gray-400 truncate max-w-xs">{cat.description}</p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-gray-500">{cat.posts_count}</td>
                                        <td className="px-4 py-3 text-right space-x-3">
                                            <button
                                                onClick={() => startEdit(cat)}
                                                className="text-indigo-600 hover:underline text-sm"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() => destroy(cat.id)}
                                                className="text-red-500 hover:underline text-sm"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                )
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
