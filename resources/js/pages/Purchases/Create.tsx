import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import PurchaseForm from './PurchaseForm';

interface Props {
    categories: { id: number; name: string }[];
    today: string;
}

export default function Create({ categories, today }: Props) {
    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Purchasing Items</h1>}>
            <Head title="Purchasing Items" />

            <PurchaseForm
                initial={{
                    purchase_number: '',
                    purchase_date: today,
                    supplier_name: '',
                    notes: '',
                    items: [],
                }}
                categories={categories}
                action="/purchases"
                method="post"
                submitLabel="Simpan"
            />
        </AuthenticatedLayout>
    );
}
