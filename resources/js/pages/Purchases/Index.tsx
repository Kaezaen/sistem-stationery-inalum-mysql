import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import SelectInput from '@/components/shared/SelectInput';
import StatusBadge from '@/components/shared/StatusBadge';
import TextInput from '@/components/shared/TextInput';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

interface PurchaseRow {
    id: number;
    purchase_number: string;
    purchase_date: string | null;
    supplier_name: string;
    creator: string | null;
    verifier: string | null;
    items_count: number;
    status: string;
    status_label: string;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger';
}

interface Props {
    purchases: Paginated<PurchaseRow>;
    filters: { search: string; status: string };
    statuses: { value: string; label: string }[];
    canCreate: boolean;
}

export default function Index({ purchases, filters, statuses, canCreate }: Props) {
    const [search, setSearch] = useState(filters.search);

    const apply = (overrides: Record<string, string>) => {
        router.get(
            '/purchases',
            { search, status: filters.status, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={<h1 className="text-xl font-semibold">Data Purchasing Items</h1>}
        >
            <Head title="Data Purchasing Items" />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <TextInput
                    placeholder="Cari nomor pembelian atau supplier…"
                    value={search}
                    className="max-w-xs"
                    onChange={(e) => {
                        setSearch(e.target.value);
                        apply({ search: e.target.value });
                    }}
                />

                <SelectInput
                    value={filters.status}
                    className="max-w-[220px]"
                    onChange={(e) => apply({ status: e.target.value })}
                >
                    <option value="">Semua status</option>
                    {statuses.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </SelectInput>

                {canCreate && (
                    <Link href="/purchases/create" className="ml-auto">
                        <Button>
                            <Plus className="size-4" aria-hidden />
                            Input Pembelian
                        </Button>
                    </Link>
                )}
            </div>

            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">No Pembelian</th>
                            <th className="px-4 py-3">Supplier</th>
                            <th className="px-4 py-3">Tanggal</th>
                            <th className="px-4 py-3">Diinput</th>
                            <th className="px-4 py-3 text-right">Item</th>
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
                                <td className="px-4 py-3">{purchase.supplier_name}</td>
                                <td className="px-4 py-3 whitespace-nowrap">
                                    {purchase.purchase_date ?? '—'}
                                </td>
                                <td className="px-4 py-3">{purchase.creator ?? '—'}</td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {purchase.items_count}
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge tone={purchase.status_tone}>
                                        {purchase.status_label}
                                    </StatusBadge>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/purchases/${purchase.id}`}
                                        className="text-sm underline-offset-4 hover:underline"
                                    >
                                        Detail
                                    </Link>
                                </td>
                            </tr>
                        ))}

                        {purchases.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Belum ada data pembelian.
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
