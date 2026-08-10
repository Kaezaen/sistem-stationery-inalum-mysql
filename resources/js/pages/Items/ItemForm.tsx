import Button from '@/components/shared/Button';
import FormField from '@/components/shared/FormField';
import SelectInput from '@/components/shared/SelectInput';
import TextInput from '@/components/shared/TextInput';
import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export interface ItemFormData {
    item_code: string;
    item_name: string;
    description: string;
    category_id: string;
    uom_id: string;
    min_stock: string;
    max_stock: string;
    remark: string;
    is_active: boolean;
    [key: string]: string | boolean;
}

interface CategoryOption {
    id: number;
    code: string;
    name: string;
}

interface UomOption {
    id: number;
    code: string;
    name: string;
}

interface Props {
    initial: ItemFormData;
    categories: CategoryOption[];
    uoms: UomOption[];
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
    /** Hanya diisi saat ubah — stok tidak dapat disunting dari sini. */
    stock?: { stock_quantity: number; reserved_quantity: number };
}

export default function ItemForm({
    initial,
    categories,
    uoms,
    action,
    method,
    submitLabel,
    stock,
}: Props) {
    const { data, setData, post, put, processing, errors } = useForm<ItemFormData>(initial);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (method === 'post') {
            post(action);
        } else {
            put(action);
        }
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="rounded-lg border bg-card p-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Item Code"
                        htmlFor="item_code"
                        error={errors.item_code}
                        required
                    >
                        <TextInput
                            id="item_code"
                            value={data.item_code}
                            placeholder="Masukkan item code"
                            invalid={Boolean(errors.item_code)}
                            onChange={(e) => setData('item_code', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label="Item Name"
                        htmlFor="item_name"
                        error={errors.item_name}
                        required
                    >
                        <TextInput
                            id="item_name"
                            value={data.item_name}
                            placeholder="Masukkan item name"
                            invalid={Boolean(errors.item_name)}
                            onChange={(e) => setData('item_name', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label="UoM (Unit of Measure)"
                        htmlFor="uom_id"
                        error={errors.uom_id}
                        required
                    >
                        <SelectInput
                            id="uom_id"
                            value={data.uom_id}
                            invalid={Boolean(errors.uom_id)}
                            onChange={(e) => setData('uom_id', e.target.value)}
                        >
                            <option value="">Pilih UoM</option>
                            {uoms.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.code} — {u.name}
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>

                    <FormField
                        label="Category"
                        htmlFor="category_id"
                        error={errors.category_id}
                        required
                    >
                        <SelectInput
                            id="category_id"
                            value={data.category_id}
                            invalid={Boolean(errors.category_id)}
                            onChange={(e) => setData('category_id', e.target.value)}
                        >
                            <option value="">Pilih Category</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>

                    <FormField
                        label="Min Stock"
                        htmlFor="min_stock"
                        error={errors.min_stock}
                        required
                        hint="Di bawah angka ini item masuk laporan Need to Buy."
                    >
                        <TextInput
                            id="min_stock"
                            type="number"
                            min={0}
                            value={data.min_stock}
                            placeholder="e.g., 10"
                            invalid={Boolean(errors.min_stock)}
                            onChange={(e) => setData('min_stock', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label="Max Stock"
                        htmlFor="max_stock"
                        error={errors.max_stock}
                        required
                    >
                        <TextInput
                            id="max_stock"
                            type="number"
                            min={0}
                            value={data.max_stock}
                            placeholder="e.g., 100"
                            invalid={Boolean(errors.max_stock)}
                            onChange={(e) => setData('max_stock', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label="Remarks"
                        htmlFor="remark"
                        error={errors.remark}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            id="remark"
                            value={data.remark}
                            placeholder="Masukkan Remark"
                            onChange={(e) => setData('remark', e.target.value)}
                        />
                    </FormField>
                </div>

                <label className="mt-4 flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                        className="size-4 rounded border"
                    />
                    Item aktif — item non-aktif tidak muncul saat membuat request
                </label>
            </div>

            {stock && (
                <div className="rounded-lg border bg-muted/30 p-4 text-sm">
                    <p className="font-medium">Posisi stok saat ini</p>
                    <p className="mt-1 text-muted-foreground">
                        Stok fisik <strong>{stock.stock_quantity}</strong> · direservasi{' '}
                        <strong>{stock.reserved_quantity}</strong>
                    </p>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Stok tidak dapat disunting dari sini. Perubahan stok hanya terjadi lewat
                        verifikasi pembelian dan serah terima barang, agar setiap pergerakan
                        meninggalkan jejak yang dapat diaudit.
                    </p>
                </div>
            )}

            <div className="flex justify-end gap-2">
                <Link href="/items">
                    <Button type="button" variant="secondary">
                        Batal
                    </Button>
                </Link>
                <Button type="submit" disabled={processing}>
                    {processing ? 'Menyimpan…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
