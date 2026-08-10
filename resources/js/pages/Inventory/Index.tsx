import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import SelectInput from '@/components/shared/SelectInput';
import StatusBadge from '@/components/shared/StatusBadge';
import TextInput from '@/components/shared/TextInput';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';

interface InventoryRow {
    id: number;
    item_code: string;
    item_name: string;
    uom: string | null;
    category: string | null;
    stock_quantity: number;
    reserved_quantity: number;
    available_quantity: number;
    min_stock: number;
    max_stock: number;
    stock_status: 'OVER_STOCK' | 'UNDER_STOCK' | 'STOCK_ON_HAND';
    stock_status_label: string;
}

interface Props {
    items: Paginated<InventoryRow>;
    categories: { id: number; name: string }[];
    filters: { search: string; category_id: number | null; status: string };
}

const TONE = {
    OVER_STOCK: 'warning',
    UNDER_STOCK: 'danger',
    STOCK_ON_HAND: 'success',
} as const;

export default function Index({ items, categories, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    const apply = (overrides: Record<string, string | number | null>) => {
        router.get(
            '/inventory',
            {
                search,
                category_id: filters.category_id ?? '',
                status: filters.status,
                ...overrides,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Data Inventory</h1>}>
            <Head title="Data Inventory" />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    onClick={() => router.reload()}
                    className="inline-flex h-9 items-center gap-2 rounded-md border px-4 text-sm font-medium hover:bg-secondary"
                >
                    <RefreshCw className="size-4" aria-hidden />
                    Refresh
                </button>

                <TextInput
                    placeholder="Search Items"
                    value={search}
                    className="max-w-xs"
                    onChange={(e) => {
                        setSearch(e.target.value);
                        apply({ search: e.target.value });
                    }}
                />

                <SelectInput
                    value={filters.category_id ?? ''}
                    className="max-w-[200px]"
                    onChange={(e) => apply({ category_id: e.target.value })}
                >
                    <option value="">All Categories</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </SelectInput>

                <SelectInput
                    value={filters.status}
                    className="max-w-[180px]"
                    onChange={(e) => apply({ status: e.target.value })}
                >
                    <option value="">Status</option>
                    <option value="over">Over Stock</option>
                    <option value="under">Under Stock</option>
                    <option value="on_hand">Stock On Hand</option>
                </SelectInput>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">Item Code</th>
                            <th className="px-4 py-3">Item Name</th>
                            <th className="px-4 py-3">UoM</th>
                            <th className="px-4 py-3 text-right">Stock</th>
                            <th className="px-4 py-3 text-right">Reserved</th>
                            <th className="px-4 py-3 text-right">Min Stock</th>
                            <th className="px-4 py-3 text-right">Max Stock</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {items.data.map((item) => (
                            <tr key={item.id} className="border-b last:border-0">
                                <td className="px-4 py-3 font-mono text-xs font-medium">
                                    {item.item_code}
                                </td>
                                <td className="px-4 py-3">{item.item_name}</td>
                                <td className="px-4 py-3">{item.uom ?? '—'}</td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {item.stock_quantity}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {item.reserved_quantity > 0 ? (
                                        <span title="Dikunci untuk request yang belum diserahkan">
                                            {item.reserved_quantity}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">—</span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {item.min_stock}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {item.max_stock}
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge tone={TONE[item.stock_status]}>
                                        {item.stock_status_label}
                                    </StatusBadge>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/inventory/${item.id}`}
                                        className="text-sm underline-offset-4 hover:underline"
                                    >
                                        Kartu Stok
                                    </Link>
                                </td>
                            </tr>
                        ))}

                        {items.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={9}
                                    className="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Tidak ada item yang cocok.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-sm text-muted-foreground">
                Menampilkan {items.from ?? 0}–{items.to ?? 0} dari {items.total} data
            </p>
        </AuthenticatedLayout>
    );
}
