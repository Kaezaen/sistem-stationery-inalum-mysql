import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import ItemForm from './ItemForm';

interface Option {
    id: number;
    code: string;
    name: string;
}

export default function Create({ categories, uoms }: { categories: Option[]; uoms: Option[] }) {
    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Add New Items</h1>}>
            <Head title="Add New Items" />

            <p className="mb-4 text-sm text-muted-foreground">
                Fill in the details to add a new item to the inventory.
            </p>

            <ItemForm
                initial={{
                    item_code: '',
                    item_name: '',
                    description: '',
                    category_id: '',
                    uom_id: '',
                    min_stock: '',
                    max_stock: '',
                    remark: '',
                    is_active: true,
                }}
                categories={categories}
                uoms={uoms}
                action="/items"
                method="post"
                submitLabel="Add Item"
            />
        </AuthenticatedLayout>
    );
}
