import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import SelectInput from '@/components/shared/SelectInput';
import StatusBadge from '@/components/shared/StatusBadge';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface RequestRow {
    id: number;
    request_number: string;
    requester: string | null;
    department: string | null;
    request_date: string | null;
    items_count: number;
    status: string;
    status_label: string;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger';
}

interface Props {
    requests: Paginated<RequestRow>;
    filters: { status: string };
    statuses: { value: string; label: string }[];
}

export default function Index({ requests, filters, statuses }: Props) {
    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Data Request Items</h1>}>
            <Head title="Data Request Items" />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <SelectInput
                    value={filters.status}
                    className="max-w-[280px]"
                    onChange={(e) =>
                        router.get(
                            '/requests',
                            { status: e.target.value },
                            { preserveState: true, replace: true },
                        )
                    }
                >
                    <option value="">Semua status</option>
                    {statuses.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </SelectInput>

                <Link href="/requests/create" className="ml-auto">
                    <Button>
                        <Plus className="size-4" aria-hidden />
                        Buat Request
                    </Button>
                </Link>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">Request ID</th>
                            <th className="px-4 py-3">Requester</th>
                            <th className="px-4 py-3">Seksi</th>
                            <th className="px-4 py-3">Date</th>
                            <th className="px-4 py-3 text-right">Item</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {requests.data.map((row) => (
                            <tr key={row.id} className="border-b last:border-0">
                                <td className="px-4 py-3 font-medium">{row.request_number}</td>
                                <td className="px-4 py-3">{row.requester ?? '—'}</td>
                                <td className="px-4 py-3">{row.department ?? '—'}</td>
                                <td className="px-4 py-3 whitespace-nowrap">
                                    {row.request_date ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {row.items_count}
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge tone={row.status_tone}>
                                        {row.status_label}
                                    </StatusBadge>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/requests/${row.id}`}
                                        className="text-sm underline-offset-4 hover:underline"
                                    >
                                        Detail
                                    </Link>
                                </td>
                            </tr>
                        ))}

                        {requests.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Belum ada request.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-sm text-muted-foreground">
                Menampilkan {requests.from ?? 0}–{requests.to ?? 0} dari {requests.total} request
            </p>
        </AuthenticatedLayout>
    );
}
