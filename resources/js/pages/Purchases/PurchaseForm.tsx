import Button from '@/components/shared/Button';
import FormField from '@/components/shared/FormField';
import ItemPicker, { type PickableItem } from '@/components/shared/ItemPicker';
import TextInput from '@/components/shared/TextInput';
import { Link, useForm } from '@inertiajs/react';
import { RotateCcw, Save } from 'lucide-react';
import type { FormEvent } from 'react';

export interface PurchaseLine {
    item_id: number;
    item_code: string;
    item_name: string;
    category: string | null;
    quantity: number;
}

interface FormShape {
    purchase_number: string;
    purchase_date: string;
    supplier_name: string;
    notes: string;
    items: PurchaseLine[];
    [key: string]: string | PurchaseLine[];
}

interface Props {
    initial: FormShape;
    categories: { id: number; name: string }[];
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
}

/**
 * Form pembelian — wireframe 3.9.2, dengan pemilih item 3.9.3.
 */
export default function PurchaseForm({ initial, categories, action, method, submitLabel }: Props) {
    const { data, setData, post, put, processing, errors, reset } = useForm<FormShape>(initial);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (method === 'post') {
            post(action);
        } else {
            put(action);
        }
    };

    const addItem = (item: PickableItem) => {
        setData('items', [
            ...data.items,
            {
                item_id: item.id,
                item_code: item.item_code,
                item_name: item.item_name,
                category: item.category,
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

    // Pesan kesalahan pada baris item dikirim server dengan kunci 'items'.
    const itemsError = (errors as Record<string, string | undefined>).items;

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="rounded-lg border bg-card p-6">
                <p className="mb-4 text-sm text-muted-foreground">
                    Fill in the details to add stocks to the inventory.
                </p>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Nomor Pembelian"
                        htmlFor="purchase_number"
                        error={errors.purchase_number}
                        required
                        hint="Ikuti nomor faktur pemasok. Nomor yang sama tidak dapat diinput dua kali."
                    >
                        <TextInput
                            id="purchase_number"
                            value={data.purchase_number}
                            placeholder="Masukkan nomor pembelian"
                            invalid={Boolean(errors.purchase_number)}
                            onChange={(e) => setData('purchase_number', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label="Nama Supplier"
                        htmlFor="supplier_name"
                        error={errors.supplier_name}
                        required
                    >
                        <TextInput
                            id="supplier_name"
                            value={data.supplier_name}
                            placeholder="Masukkan nama supplier"
                            invalid={Boolean(errors.supplier_name)}
                            onChange={(e) => setData('supplier_name', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label="Tanggal Pembelian"
                        htmlFor="purchase_date"
                        error={errors.purchase_date}
                        required
                    >
                        <TextInput
                            id="purchase_date"
                            type="date"
                            value={data.purchase_date}
                            invalid={Boolean(errors.purchase_date)}
                            onChange={(e) => setData('purchase_date', e.target.value)}
                        />
                    </FormField>
                </div>
            </div>

            <div>
                <h2 className="mb-3 font-medium">Tambah Item</h2>
                <ItemPicker
                    categories={categories}
                    excludeIds={data.items.map((line) => line.item_id)}
                    onPick={addItem}
                />
            </div>

            <div className="rounded-lg border bg-card">
                <div className="flex items-center justify-between border-b p-4">
                    <h2 className="font-medium">Selected Items</h2>
                    <span className="text-sm text-muted-foreground">{data.items.length} item</span>
                </div>

                {itemsError && (
                    <p className="border-b bg-red-50 px-4 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-300">
                        {itemsError}
                    </p>
                )}

                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">No</th>
                            <th className="px-4 py-3">Item</th>
                            <th className="px-4 py-3 text-right">Stock</th>
                            <th className="px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.items.map((line, index) => (
                            <tr key={line.item_id} className="border-b last:border-0">
                                <td className="px-4 py-3">{index + 1}</td>
                                <td className="px-4 py-3">
                                    <div>{line.item_name}</div>
                                    <div className="font-mono text-xs text-muted-foreground">
                                        {line.item_code}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {line.category ?? '—'}
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
                                                setQuantity(line.item_id, Number(e.target.value))
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
                                    Belum ada item dipilih. Cari dan tambahkan item di atas.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="flex justify-end gap-2">
                <Link href="/purchases">
                    <Button type="button" variant="secondary">
                        Batal
                    </Button>
                </Link>
                <Button type="button" variant="secondary" onClick={() => reset()}>
                    <RotateCcw className="size-4" aria-hidden />
                    Reset
                </Button>
                <Button type="submit" disabled={processing || data.items.length === 0}>
                    <Save className="size-4" aria-hidden />
                    {processing ? 'Menyimpan…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
