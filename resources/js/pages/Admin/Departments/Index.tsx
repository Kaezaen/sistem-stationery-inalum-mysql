import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import StatusBadge from '@/components/shared/StatusBadge';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface DepartmentRow {
    id: number;
    code: string;
    name: string;
    account_code: string | null;
    parent: string | null;
    head: string | null;
    users_count: number;
    is_active: boolean;
}

export default function Index({ departments }: { departments: DepartmentRow[] }) {
    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Departemen / Seksi</h1>}>
            <Head title="Departemen" />

            <div className="mb-4 flex justify-end">
                <Link href="/admin/departments/create">
                    <Button>
                        <Plus className="size-4" aria-hidden />
                        Tambah Departemen
                    </Button>
                </Link>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">Kode</th>
                            <th className="px-4 py-3">Nama</th>
                            <th className="px-4 py-3">Kode Akun</th>
                            <th className="px-4 py-3">Induk</th>
                            <th className="px-4 py-3">Kepala</th>
                            <th className="px-4 py-3">User</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {departments.map((d) => (
                            <tr key={d.id} className="border-b last:border-0">
                                <td className="px-4 py-3 font-medium">{d.code}</td>
                                <td className="px-4 py-3">{d.name}</td>
                                <td className="px-4 py-3">{d.account_code ?? '—'}</td>
                                <td className="px-4 py-3">{d.parent ?? '—'}</td>
                                <td className="px-4 py-3">{d.head ?? '—'}</td>
                                <td className="px-4 py-3">{d.users_count}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge tone={d.is_active ? 'success' : 'danger'}>
                                        {d.is_active ? 'Aktif' : 'Non-aktif'}
                                    </StatusBadge>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/admin/departments/${d.id}/edit`}
                                        className="text-sm underline-offset-4 hover:underline"
                                    >
                                        Ubah
                                    </Link>
                                </td>
                            </tr>
                        ))}

                        {departments.length === 0 && (
                            <tr>
                                <td
                                    colSpan={8}
                                    className="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Belum ada departemen.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AuthenticatedLayout>
    );
}
