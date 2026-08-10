import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import StatusBadge from '@/components/shared/StatusBadge';
import TextInput from '@/components/shared/TextInput';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Network, Plus } from 'lucide-react';
import { useState } from 'react';

interface UserRow {
    id: number;
    employee_id: string;
    name: string;
    email: string;
    department: string | null;
    manager: string | null;
    position: string | null;
    is_active: boolean;
    roles: string[];
}

interface Props {
    users: Paginated<UserRow>;
    filters: { search: string };
}

export default function Index({ users, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    const submitSearch = (value: string) => {
        setSearch(value);
        router.get('/admin/users', { search: value }, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Kelola User</h1>}>
            <Head title="Kelola User" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <TextInput
                    placeholder="Cari NIP, nama, username, atau email…"
                    value={search}
                    className="max-w-sm"
                    onChange={(e) => submitSearch(e.target.value)}
                />

                <div className="flex gap-2">
                    <Link href="/admin/users/hierarchy">
                        <Button variant="secondary">
                            <Network className="size-4" aria-hidden />
                            Hierarki Atasan
                        </Button>
                    </Link>
                    <Link href="/admin/users/create">
                        <Button>
                            <Plus className="size-4" aria-hidden />
                            Tambah User
                        </Button>
                    </Link>
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">NIP</th>
                            <th className="px-4 py-3">Nama</th>
                            <th className="px-4 py-3">Departemen</th>
                            <th className="px-4 py-3">Atasan</th>
                            <th className="px-4 py-3">Role</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.map((user) => (
                            <tr key={user.id} className="border-b last:border-0">
                                <td className="px-4 py-3 font-medium">{user.employee_id}</td>
                                <td className="px-4 py-3">
                                    <div>{user.name}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {user.email}
                                    </div>
                                </td>
                                <td className="px-4 py-3">{user.department ?? '—'}</td>
                                <td className="px-4 py-3">
                                    {user.manager ?? (
                                        <span className="text-muted-foreground">Tidak ada</span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-wrap gap-1">
                                        {user.roles.map((role) => (
                                            <StatusBadge key={role}>{role}</StatusBadge>
                                        ))}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge tone={user.is_active ? 'success' : 'danger'}>
                                        {user.is_active ? 'Aktif' : 'Non-aktif'}
                                    </StatusBadge>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/admin/users/${user.id}/edit`}
                                        className="text-sm underline-offset-4 hover:underline"
                                    >
                                        Ubah
                                    </Link>
                                </td>
                            </tr>
                        ))}

                        {users.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Tidak ada user yang cocok.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-sm text-muted-foreground">
                Menampilkan {users.from ?? 0}–{users.to ?? 0} dari {users.total} user
            </p>
        </AuthenticatedLayout>
    );
}
