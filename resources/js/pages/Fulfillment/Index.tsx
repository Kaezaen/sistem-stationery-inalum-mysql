import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import StatusBadge from '@/components/shared/StatusBadge';
import TextInput from '@/components/shared/TextInput';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface QueueRow {
    id: number;
    request_number: string;
    requester: string | null;
    department: string | null;
    request_date: string | null;
    items_count: number;
    status_label: string;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger';
}

interface Props {
    requests: Paginated<QueueRow>;
    tab: string;
    filters: { search: string };
}

const TABS = [
    { key: 'pending', label: 'Menunggu Pengambilan' },
    { key: 'completed', label: 'Selesai' },
];

/** Antrian serah terima — filter "Menunggu Pengambilan Item" (Bab 3.5). */
export default function Index({ requests, tab, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    const apply = (overrides: Record<string, string>) => {
        router.get(
            '/handover',
            { tab, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Serah Terima Item</h1>}>
            <Head title="Serah Terima Item" />

            <p className="mb-4 text-sm text-muted-foreground">
                Serahkan barang kepada requester, lalu tekan Diberikan.
            </p>

            <div className="mb-4 flex gap-6 border-b">
                {TABS.map((item) => (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => apply({ tab: item.key })}
                        className={cn(
                            '-mb-px border-b-2 pb-2 text-sm transition-colors',
                            tab === item.key
                                ? 'border-foreground font-medium text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            <div className="mb-4">
                <TextInput
                    placeholder="Cari nomor request…"
                    value={search}
                    className="max-w-md"
                    onChange={(e) => {
                        setSearch(e.target.value);
                        apply({ search: e.target.value });
                    }}
                />
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
                                    <Link href={`/handover/${row.id}`}>
                                        <Button variant="secondary">
                                            {tab === 'completed' ? 'Lihat' : 'Proses'}
                                        </Button>
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
                                    Tidak ada request pada tab ini.
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
