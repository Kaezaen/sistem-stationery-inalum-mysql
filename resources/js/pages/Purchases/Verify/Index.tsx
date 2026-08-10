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
    purchase_number: string;
    purchase_date: string | null;
    supplier_name: string;
    creator: string | null;
    items_count: number;
    status_label: string;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger';
}

interface Props {
    purchases: Paginated<QueueRow>;
    tab: string;
    filters: { search: string };
}

const TABS = [
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
];

export default function Index({ purchases, tab, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    const apply = (overrides: Record<string, string>) => {
        router.get(
            '/purchases/verify',
            { tab, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={<h1 className="text-xl font-semibold">Verify Purchasing Items</h1>}
        >
            <Head title="Verify Purchasing Items" />

            <p className="mb-4 text-sm text-muted-foreground">
                Review and approve or reject purchasing items.
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
                    placeholder="Search by ID or name…"
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
                            <th className="px-4 py-3">No Pembelian</th>
                            <th className="px-4 py-3">Requester</th>
                            <th className="px-4 py-3">Supplier</th>
                            <th className="px-4 py-3">Date</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {purchases.data.map((purchase) => (
                            <tr key={purchase.id} className="border-b last:border-0">
                                <td className="px-4 py-3 font-mono text-xs font-medium">
                                    {purchase.purchase_number}
                                </td>
                                <td className="px-4 py-3">{purchase.creator ?? '—'}</td>
                                <td className="px-4 py-3">{purchase.supplier_name}</td>
                                <td className="px-4 py-3 whitespace-nowrap">
                                    {purchase.purchase_date ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge tone={purchase.status_tone}>
                                        {purchase.status_label}
                                    </StatusBadge>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link href={`/purchases/verify/${purchase.id}`}>
                                        <Button variant="secondary">Verifikasi</Button>
                                    </Link>
                                </td>
                            </tr>
                        ))}

                        {purchases.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Tidak ada dokumen pada tab ini.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-sm text-muted-foreground">
                Menampilkan {purchases.from ?? 0}–{purchases.to ?? 0} dari {purchases.total} data
            </p>
        </AuthenticatedLayout>
    );
}
