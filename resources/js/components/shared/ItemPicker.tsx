import Button from '@/components/shared/Button';
import SelectInput from '@/components/shared/SelectInput';
import TextInput from '@/components/shared/TextInput';
import { Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface PickableItem {
    id: number;
    item_code: string;
    item_name: string;
    category: string | null;
    uom: string | null;
    stock_quantity: number;
    available_quantity: number;
}

interface Props {
    categories: { id: number; name: string }[];
    /** Id item yang sudah dipilih — disembunyikan dari hasil pencarian. */
    excludeIds: number[];
    onPick: (item: PickableItem) => void;
}

/**
 * Pemilih item — wireframe 3.9.3 "Cari Item".
 *
 * Dipakai form pembelian, dan akan dipakai ulang form request pada Fase 5.
 * Item yang sudah masuk keranjang disembunyikan agar tidak terpilih dua kali —
 * satu item hanya boleh satu baris per dokumen.
 */
export default function ItemPicker({ categories, excludeIds, onPick }: Props) {
    const [query, setQuery] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [results, setResults] = useState<PickableItem[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const controller = new AbortController();

        // Jeda singkat supaya setiap ketikan tidak langsung memanggil server.
        const timer = setTimeout(() => {
            setLoading(true);

            const params = new URLSearchParams();
            if (query) params.set('q', query);
            if (categoryId) params.set('category_id', categoryId);

            fetch(`/items/search?${params.toString()}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            })
                .then((response) => (response.ok ? response.json() : { data: [] }))
                .then((payload: { data: PickableItem[] }) => setResults(payload.data))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, categoryId]);

    const visible = results.filter((item) => !excludeIds.includes(item.id));

    return (
        <div className="rounded-lg border bg-card">
            <div className="flex flex-wrap items-center gap-3 border-b p-4">
                <div className="relative min-w-[220px] flex-1">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <TextInput
                        placeholder="Search Items"
                        value={query}
                        className="pl-9"
                        onChange={(e) => setQuery(e.target.value)}
                    />
                </div>

                <SelectInput
                    value={categoryId}
                    className="max-w-[200px]"
                    onChange={(e) => setCategoryId(e.target.value)}
                >
                    <option value="">All Categories</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </SelectInput>
            </div>

            <div className="max-h-72 overflow-y-auto">
                {loading && visible.length === 0 && (
                    <p className="p-6 text-center text-sm text-muted-foreground">Memuat…</p>
                )}

                {!loading && visible.length === 0 && (
                    <p className="p-6 text-center text-sm text-muted-foreground">
                        Tidak ada item yang cocok.
                    </p>
                )}

                {visible.map((item) => (
                    <div
                        key={item.id}
                        className="flex items-center justify-between gap-3 border-b px-4 py-3 last:border-0"
                    >
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">{item.item_name}</p>
                            <p className="font-mono text-xs text-muted-foreground">
                                {item.item_code}
                            </p>
                            <p className="text-xs text-muted-foreground">{item.category ?? '—'}</p>
                        </div>

                        <div className="flex shrink-0 items-center gap-3">
                            <span className="rounded-full border px-2.5 py-0.5 text-xs whitespace-nowrap">
                                Stock: {item.stock_quantity}
                            </span>
                            <Button type="button" onClick={() => onPick(item)}>
                                <Plus className="size-4" aria-hidden />
                                Tambah
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
