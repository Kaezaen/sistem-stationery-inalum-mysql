import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import StatusBadge from '@/components/shared/StatusBadge';
import type { Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface ItemSummary {
    id: number;
    item_code: string;
    item_name: string;
    uom: string | null;
    stock_quantity: number;
    reserved_quantity: number;
    available_quantity: number;
    min_stock: number;
    max_stock: number;
    stock_status_label: string;
    stock_status: 'OVER_STOCK' | 'UNDER_STOCK' | 'STOCK_ON_HAND';
}

interface LedgerRow {
    id: number;
    date: string | null;
    type: 'IN' | 'OUT' | 'ADJUSTMENT';
    type_label: string;
    quantity: number;
    net_change: number;
    balance_before: number;
    balance_after: number;
    performed_by: string | null;
    reason: string | null;
}

interface Props {
    item: ItemSummary;
    transactions: Paginated<LedgerRow>;
}

const TONE = {
    OVER_STOCK: 'warning',
    UNDER_STOCK: 'danger',
    STOCK_ON_HAND: 'success',
} as const;

const TYPE_TONE = {
    IN: 'success',
    OUT: 'danger',
    ADJUSTMENT: 'warning',
} as const;

function Stat({ label, value, hint }: { label: string; value: number; hint?: string }) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <p className="text-xs tracking-wide text-muted-foreground uppercase">{label}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
            {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

export default function Show({ item, transactions }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href="/inventory"
                        className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                    </Link>
                    <div className="min-w-0">
                        <h1 className="truncate text-xl font-semibold">Kartu Stok</h1>
                    </div>
                </div>
            }
        >
            <Head title={`Kartu Stok ${item.item_code}`} />

            <div className="mb-6 rounded-lg border bg-card p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="font-mono text-xs text-muted-foreground">{item.item_code}</p>
                        <h2 className="mt-1 text-lg font-semibold">{item.item_name}</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Satuan {item.uom ?? '—'} · batas {item.min_stock}–{item.max_stock}
                        </p>
                    </div>
                    <StatusBadge tone={TONE[item.stock_status]}>
                        {item.stock_status_label}
                    </StatusBadge>
                </div>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <Stat label="Stok Fisik" value={item.stock_quantity} hint="Barang di gudang" />
                <Stat
                    label="Direservasi"
                    value={item.reserved_quantity}
                    hint="Dikunci untuk request yang belum diserahkan"
                />
                <Stat
                    label="Tersedia"
                    value={item.available_quantity}
                    hint="Yang masih dapat dijanjikan ke request baru"
                />
            </div>

            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">Tanggal</th>
                            <th className="px-4 py-3">Jenis</th>
                            <th className="px-4 py-3 text-right">Jumlah</th>
                            <th className="px-4 py-3 text-right">Saldo Awal</th>
                            <th className="px-4 py-3 text-right">Saldo Akhir</th>
                            <th className="px-4 py-3">Pelaku</th>
                            <th className="px-4 py-3">Alasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {transactions.data.map((tx) => (
                            <tr key={tx.id} className="border-b last:border-0">
                                <td className="px-4 py-3 whitespace-nowrap">{tx.date ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge tone={TYPE_TONE[tx.type]}>
                                        {tx.type_label}
                                    </StatusBadge>
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {tx.net_change > 0 ? '+' : ''}
                                    {tx.net_change}
                                </td>
                                <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                    {tx.balance_before}
                                </td>
                                <td className="px-4 py-3 text-right font-medium tabular-nums">
                                    {tx.balance_after}
                                </td>
                                <td className="px-4 py-3">{tx.performed_by ?? '—'}</td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {tx.reason ?? '—'}
                                </td>
                            </tr>
                        ))}

                        {transactions.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Belum ada pergerakan stok untuk item ini.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-sm text-muted-foreground">
                Menampilkan {transactions.from ?? 0}–{transactions.to ?? 0} dari{' '}
                {transactions.total} pergerakan. Ledger bersifat append-only — koreksi dicatat
                sebagai penyesuaian baru, bukan dengan mengubah baris lama.
            </p>
        </AuthenticatedLayout>
    );
}
