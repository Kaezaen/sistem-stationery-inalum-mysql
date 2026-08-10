import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import SelectInput from '@/components/shared/SelectInput';
import StatusBadge from '@/components/shared/StatusBadge';
import TextInput from '@/components/shared/TextInput';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Upload } from 'lucide-react';
import { useState } from 'react';

interface ItemRow {
    id: number;
    item_code: string;
    item_name: string;
    category: string | null;
    uom: string | null;
    stock_quantity: number;
    available_quantity: number;
    min_stock: number;
    max_stock: number;
    stock_status: 'OVER_STOCK' | 'UNDER_STOCK' | 'STOCK_ON_HAND';
    stock_status_label: string;
    is_active: boolean;
}

interface CategoryOption {
    id: number;
    code: string;
    name: string;
}

interface Props {
    items: Paginated<ItemRow>;
    categories: CategoryOption[];
    filters: { search: string; category_id: number | null; status: string };
    canManage: boolean;
}

const STATUS_TONE = {
    OVER_STOCK: 'warning',
    UNDER_STOCK: 'danger',
    STOCK_ON_HAND: 'success',
} as const;

export default function Index({ items, categories, filters, canManage }: Props) {
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (overrides: Record<string, string | number | null>) => {
        router.get(
            '/items',
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
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Data List Items</h1>}>
            <Head title="Data List Items" />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <TextInput
                    placeholder="Cari item code atau nama…"
                    value={search}
                    className="max-w-xs"
                    onChange={(e) => {
                        setSearch(e.target.value);
                        applyFilters({ search: e.target.value });
                    }}
                />

                <SelectInput
                    value={filters.category_id ?? ''}
                    className="max-w-[200px]"
                    onChange={(e) => applyFilters({ category_id: e.target.value })}
                >
                    <option value="">Semua kategori</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </SelectInput>

                <SelectInput
                    value={filters.status}
                    className="max-w-[160px]"
                    onChange={(e) => applyFilters({ status: e.target.value })}
                >
                    <option value="">Semua status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Non-aktif</option>
                </SelectInput>

                {canManage && (
                    <div className="ml-auto flex gap-2">
                        <Link href="/items/import">
                            <Button variant="secondary">
                                <Upload className="size-4" aria-hidden />
                                Import CSV
                            </Button>
                        </Link>
                        <Link href="/items/create">
                            <Button>
                                <Plus className="size-4" aria-hidden />
                                Add New Item
                            </Button>
                        </Link>
                    </div>
                )}
            </div>

            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">Item Code</th>
                            <th className="px-4 py-3">Item Name</th>
                            <th className="px-4 py-3">Category</th>
                            <th className="px-4 py-3">UoM</th>
                            <th className="px-4 py-3 text-right">Stock</th>
                            <th className="px-4 py-3 text-right">Min</th>
                            <th className="px-4 py-3 text-right">Max</th>
                            <th className="px-4 py-3">Status</th>
                            {canManage && <th className="px-4 py-3" />}
                        </tr>
                    </thead>
                    <tbody>
                        {items.data.map((item) => (
                            <tr key={item.id} className="border-b last:border-0">
                                <td className="px-4 py-3 font-mono text-xs font-medium">
                                    {item.item_code}
                                </td>
                                <td className="px-4 py-3">
                                    <div>{item.item_name}</div>
                                    {!item.is_active && (
                                        <span className="text-xs text-muted-foreground">
                                            Non-aktif
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3">{item.category ?? '—'}</td>
                                <td className="px-4 py-3">{item.uom ?? '—'}</td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {item.stock_quantity}
                                    {item.available_quantity !== item.stock_quantity && (
                                        <span
                                            className="ml-1 text-xs text-muted-foreground"
                                            title="Tersedia setelah dikurangi reservasi"
                                        >
                                            ({item.available_quantity})
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {item.min_stock}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {item.max_stock}
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge tone={STATUS_TONE[item.stock_status]}>
                                        {item.stock_status_label}
                                    </StatusBadge>
                                </td>
                                {canManage && (
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={`/items/${item.id}/edit`}
                                            className="text-sm underline-offset-4 hover:underline"
                                        >
                                            Ubah
                                        </Link>
                                    </td>
                                )}
                            </tr>
                        ))}

                        {items.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={canManage ? 9 : 8}
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
                Menampilkan {items.from ?? 0}–{items.to ?? 0} dari {items.total} item
            </p>
        </AuthenticatedLayout>
    );
}
