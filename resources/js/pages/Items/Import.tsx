import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import FormField from '@/components/shared/FormField';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import type { FormEvent } from 'react';

interface Props {
    requiredHeaders: string[];
    categories: string[];
    uoms: string[];
}

export default function Import({ requiredHeaders, categories, uoms }: Props) {
    const page = usePage().props as unknown as { importErrors?: string[] };
    const importErrors = page.importErrors ?? [];

    const { setData, post, processing, errors, progress } = useForm<{
        file: File | null;
        update_existing: boolean;
    }>({
        file: null,
        update_existing: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/items/import', { forceFormData: true });
    };

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Import Item</h1>}>
            <Head title="Import Item" />

            <div className="grid max-w-5xl gap-6 lg:grid-cols-[1fr_320px]">
                <form onSubmit={submit} className="space-y-6">
                    <div className="rounded-lg border bg-card p-6">
                        <p className="mb-4 text-sm text-muted-foreground">
                            Unggah katalog item dalam format CSV. Simpan spreadsheet Anda sebagai
                            CSV terlebih dahulu — format ini dapat diekspor oleh semua versi Excel
                            dan tidak membawa formula.
                        </p>

                        <FormField label="Berkas CSV" htmlFor="file" error={errors.file} required>
                            <input
                                id="file"
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                                className="block w-full rounded-md border bg-background px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-secondary file:px-3 file:py-1 file:text-sm"
                            />
                        </FormField>

                        <label className="mt-4 flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                onChange={(e) => setData('update_existing', e.target.checked)}
                                className="mt-0.5 size-4 rounded border"
                            />
                            <span>
                                Perbarui item yang kodenya sudah ada
                                <span className="block text-xs text-muted-foreground">
                                    Bila tidak dicentang, item dengan kode yang sudah terdaftar akan
                                    dilewati. Stok tidak pernah diubah oleh import.
                                </span>
                            </span>
                        </label>

                        {progress && (
                            <div className="mt-4 h-1.5 overflow-hidden rounded bg-secondary">
                                <div
                                    className="h-full bg-primary transition-all"
                                    style={{ width: `${progress.percentage ?? 0}%` }}
                                />
                            </div>
                        )}
                    </div>

                    {importErrors.length > 0 && (
                        <div className="rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
                            <p className="text-sm font-medium text-red-900 dark:text-red-200">
                                {importErrors.length} baris tidak dapat diproses
                            </p>
                            <ul className="mt-2 space-y-0.5 text-xs text-red-800 dark:text-red-300">
                                {importErrors.slice(0, 20).map((message) => (
                                    <li key={message}>{message}</li>
                                ))}
                            </ul>
                            {importErrors.length > 20 && (
                                <p className="mt-2 text-xs text-red-700 dark:text-red-400">
                                    …dan {importErrors.length - 20} baris lainnya.
                                </p>
                            )}
                        </div>
                    )}

                    <div className="flex justify-end gap-2">
                        <Link href="/items">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing}>
                            <Upload className="size-4" aria-hidden />
                            {processing ? 'Mengunggah…' : 'Import'}
                        </Button>
                    </div>
                </form>

                <aside className="space-y-4">
                    <div className="rounded-lg border bg-card p-5">
                        <h2 className="text-sm font-medium">Kolom wajib</h2>
                        <ul className="mt-2 space-y-1">
                            {requiredHeaders.map((header) => (
                                <li key={header}>
                                    <code className="rounded bg-secondary px-1.5 py-0.5 font-mono text-xs">
                                        {header}
                                    </code>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Kolom opsional: <code className="font-mono">description</code>,{' '}
                            <code className="font-mono">remark</code>
                        </p>

                        <a href="/items/import/template" className="mt-4 inline-block">
                            <Button type="button" variant="secondary">
                                <Download className="size-4" aria-hidden />
                                Unduh template
                            </Button>
                        </a>
                    </div>

                    <div className="rounded-lg border bg-card p-5">
                        <h2 className="text-sm font-medium">Nilai kategori yang dikenal</h2>
                        <p className="mt-2 text-xs text-muted-foreground">
                            {categories.join(' · ')}
                        </p>

                        <h2 className="mt-4 text-sm font-medium">Nilai UoM yang dikenal</h2>
                        <p className="mt-2 text-xs text-muted-foreground">{uoms.join(' · ')}</p>

                        <p className="mt-3 text-xs text-muted-foreground">
                            Baris dengan kategori atau UoM di luar daftar ini akan dilewati dan
                            dilaporkan, bukan dibuat otomatis — agar salah ketik tidak diam-diam
                            menambah master data baru.
                        </p>
                    </div>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
