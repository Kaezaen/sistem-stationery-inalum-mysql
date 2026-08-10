import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import ItemPicker, { type PickableItem } from '@/components/shared/ItemPicker';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Line {
    item_id: number;
    item_code: string;
    item_name: string;
    category: string | null;
    available: number;
    quantity: number;
}

interface FormShape {
    notes: string;
    items: Line[];
    [key: string]: string | Line[];
}

interface Props {
    categories: { id: number; name: string }[];
}

/** Halaman "Request Items" — wireframe 3.1.2. */
export default function Create({ categories }: Props) {
    const { data, setData, post, processing, errors } = useForm<FormShape>({
        notes: '',
        items: [],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/requests');
    };

    const addItem = (item: PickableItem) => {
        setData('items', [
            ...data.items,
            {
                item_id: item.id,
                item_code: item.item_code,
                item_name: item.item_name,
                category: item.category,
                available: item.available_quantity,
                quantity: 1,
            },
        ]);
    };

    const setQuantity = (itemId: number, quantity: number) => {
        setData(
            'items',
            data.items.map((line) =>
                line.item_id === itemId ? { ...line, quantity: Math.max(1, quantity) } : line,
            ),
        );
    };

    const removeItem = (itemId: number) => {
        setData(
            'items',
            data.items.filter((line) => line.item_id !== itemId),
        );
    };

    const itemsError = (errors as Record<string, string | undefined>).items;

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Request Items</h1>}>
            <Head title="Request Items" />

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <h2 className="font-medium">Create New Request</h2>
                    <p className="mt-1 mb-3 text-sm text-muted-foreground">
                        Select items you want to request from the inventory.
                    </p>

                    <ItemPicker
                        categories={categories}
                        excludeIds={data.items.map((line) => line.item_id)}
                        onPick={addItem}
                    />
                </div>

                <div className="rounded-lg border bg-card">
                    <div className="flex items-center justify-between border-b p-4">
                        <h2 className="font-medium">Selected Items</h2>
                        <span className="text-sm text-muted-foreground">
                            {data.items.length} item
                        </span>
                    </div>

                    {itemsError && (
                        <p className="border-b bg-red-50 px-4 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-300">
                            {itemsError}
                        </p>
                    )}

                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3 text-right">Quantity</th>
                                <th className="px-4 py-3 text-right">Available Stock</th>
                                <th className="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.items.map((line) => (
                                <tr key={line.item_id} className="border-b last:border-0">
                                    <td className="px-4 py-3">
                                        <div>{line.item_name}</div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {line.item_code}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setQuantity(line.item_id, line.quantity - 1)
                                                }
                                                className="size-7 rounded border text-sm hover:bg-secondary"
                                                aria-label="Kurangi"
                                            >
                                                −
                                            </button>
                                            <input
                                                type="number"
                                                min={1}
                                                value={line.quantity}
                                                onChange={(e) =>
                                                    setQuantity(
                                                        line.item_id,
                                                        Number(e.target.value),
                                                    )
                                                }
                                                className="h-7 w-16 rounded border bg-background text-center text-sm"
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setQuantity(line.item_id, line.quantity + 1)
                                                }
                                                className="size-7 rounded border text-sm hover:bg-secondary"
                                                aria-label="Tambah"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {line.available}
                                        {line.quantity > line.available && (
                                            <span
                                                className="ml-2 text-xs text-amber-600"
                                                title="Melebihi stok tersedia — PIC Stationery dapat mengurangi jumlahnya"
                                            >
                                                melebihi stok
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <button
                                            type="button"
                                            onClick={() => removeItem(line.item_id)}
                                            className="rounded-md border px-3 py-1 text-xs hover:bg-secondary"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))}

                            {data.items.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Belum ada item dipilih.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-end gap-2">
                    <Link href="/requests">
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </Link>
                    <Button type="submit" disabled={processing || data.items.length === 0}>
                        {processing ? 'Mengirim…' : 'Submit Request'}
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
