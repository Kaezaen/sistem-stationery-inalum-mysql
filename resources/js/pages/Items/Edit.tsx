import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import ItemForm from './ItemForm';

interface Option {
    id: number;
    code: string;
    name: string;
}

interface EditableItem {
    id: number;
    item_code: string;
    item_name: string;
    description: string | null;
    category_id: number | null;
    uom_id: number | null;
    min_stock: number;
    max_stock: number;
    remark: string | null;
    is_active: boolean;
    stock_quantity: number;
    reserved_quantity: number;
}

interface Props {
    item: EditableItem;
    categories: Option[];
    uoms: Option[];
}

export default function Edit({ item, categories, uoms }: Props) {
    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Ubah Item</h1>}>
            <Head title={`Ubah ${item.item_code}`} />

            <ItemForm
                initial={{
                    item_code: item.item_code,
                    item_name: item.item_name,
                    description: item.description ?? '',
                    category_id: item.category_id ? String(item.category_id) : '',
                    uom_id: item.uom_id ? String(item.uom_id) : '',
                    min_stock: String(item.min_stock),
                    max_stock: String(item.max_stock),
                    remark: item.remark ?? '',
                    is_active: item.is_active,
                }}
                categories={categories}
                uoms={uoms}
                action={`/items/${item.id}`}
                method="put"
                submitLabel="Simpan Perubahan"
                stock={{
                    stock_quantity: item.stock_quantity,
                    reserved_quantity: item.reserved_quantity,
                }}
            />
        </AuthenticatedLayout>
    );
}
