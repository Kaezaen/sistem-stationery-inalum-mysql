import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import PurchaseForm from './PurchaseForm';

interface PurchaseDetail {
    id: number;
    purchase_number: string;
    purchase_date_raw: string | null;
    supplier_name: string;
    notes: string | null;
    rejection_notes: string | null;
    status: string;
    items: {
        item_id: number;
        item_code: string;
        item_name: string;
        category: string | null;
        quantity: number;
    }[];
}

interface Props {
    purchase: PurchaseDetail;
    categories: { id: number; name: string }[];
}

export default function Edit({ purchase, categories }: Props) {
    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Ubah Pembelian</h1>}>
            <Head title={`Ubah ${purchase.purchase_number}`} />

            {purchase.rejection_notes && (
                <div className="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
                    <p className="text-sm font-medium text-red-900 dark:text-red-200">
                        Ditolak PIC Stationery
                    </p>
                    <p className="mt-1 text-sm text-red-800 dark:text-red-300">
                        {purchase.rejection_notes}
                    </p>
                    <p className="mt-2 text-xs text-red-700 dark:text-red-400">
                        Perbaiki sesuai catatan di atas. Menyimpan perubahan akan langsung
                        mengajukan ulang dokumen ini untuk diverifikasi.
                    </p>
                </div>
            )}

            <PurchaseForm
                initial={{
                    purchase_number: purchase.purchase_number,
                    purchase_date: purchase.purchase_date_raw ?? '',
                    supplier_name: purchase.supplier_name,
                    notes: purchase.notes ?? '',
                    items: purchase.items.map((line) => ({
                        item_id: line.item_id,
                        item_code: line.item_code,
                        item_name: line.item_name,
                        category: line.category,
                        quantity: line.quantity,
                    })),
                }}
                categories={categories}
                action={`/purchases/${purchase.id}`}
                method="put"
                submitLabel="Simpan Perubahan"
            />
        </AuthenticatedLayout>
    );
}
