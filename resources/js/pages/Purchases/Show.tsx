import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import StatusBadge from '@/components/shared/StatusBadge';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';

interface PurchaseDetail {
    id: number;
    purchase_number: string;
    purchase_date: string | null;
    supplier_name: string;
    creator: string | null;
    verifier: string | null;
    verification_date: string | null;
    notes: string | null;
    rejection_notes: string | null;
    revision_count: number;
    status_label: string;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger';
    items: {
        id: number;
        item_code: string;
        item_name: string;
        uom: string | null;
        quantity: number;
    }[];
}

export default function Show({
    purchase,
    canEdit,
}: {
    purchase: PurchaseDetail;
    canEdit: boolean;
}) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href="/purchases"
                        className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                    </Link>
                    <h1 className="text-xl font-semibold">Detail Pembelian</h1>
                </div>
            }
        >
            <Head title={purchase.purchase_number} />

            <div className="mb-6 rounded-lg border bg-card p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-xs text-muted-foreground">Nomor Pembelian</p>
                            <p className="font-mono font-medium">{purchase.purchase_number}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Nama Supplier</p>
                            <p className="font-medium">{purchase.supplier_name}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Tanggal Pembelian</p>
                            <p className="font-medium">{purchase.purchase_date ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Diinput oleh</p>
                            <p className="font-medium">{purchase.creator ?? '—'}</p>
                        </div>
                    </div>

                    <div className="flex flex-col items-end gap-2">
                        <StatusBadge tone={purchase.status_tone}>
                            {purchase.status_label}
                        </StatusBadge>
                        {purchase.revision_count > 0 && (
                            <span className="text-xs text-muted-foreground">
                                Revisi ke-{purchase.revision_count}
                            </span>
                        )}
                        {canEdit && (
                            <Link href={`/purchases/${purchase.id}/edit`}>
                                <Button variant="secondary">
                                    <Pencil className="size-4" aria-hidden />
                                    Ubah
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {purchase.verifier && (
                    <p className="mt-4 border-t pt-4 text-sm text-muted-foreground">
                        Diperiksa {purchase.verifier} pada {purchase.verification_date ?? '—'}
                    </p>
                )}

                {purchase.rejection_notes && (
                    <div className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-300">
                        <strong>Alasan penolakan:</strong> {purchase.rejection_notes}
                    </div>
                )}
            </div>

            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">No</th>
                            <th className="px-4 py-3">Item</th>
                            <th className="px-4 py-3">UoM</th>
                            <th className="px-4 py-3 text-right">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        {purchase.items.map((line, index) => (
                            <tr key={line.id} className="border-b last:border-0">
                                <td className="px-4 py-3">{index + 1}</td>
                                <td className="px-4 py-3">
                                    <div>{line.item_name}</div>
                                    <div className="font-mono text-xs text-muted-foreground">
                                        {line.item_code}
                                    </div>
                                </td>
                                <td className="px-4 py-3">{line.uom ?? '—'}</td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {line.quantity}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AuthenticatedLayout>
    );
}
