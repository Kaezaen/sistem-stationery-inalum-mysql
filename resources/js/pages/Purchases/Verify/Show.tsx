import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import FormField from '@/components/shared/FormField';
import StatusBadge from '@/components/shared/StatusBadge';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, X } from 'lucide-react';
import { useState } from 'react';

interface PurchaseDetail {
    id: number;
    purchase_number: string;
    purchase_date: string | null;
    supplier_name: string;
    creator: string | null;
    notes: string | null;
    rejection_notes: string | null;
    status_label: string;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger';
    items: {
        id: number;
        item_code: string;
        item_name: string;
        uom: string | null;
        quantity: number;
        current_stock: number;
    }[];
}

interface HistoryRow {
    id: number;
    decision: string;
    decision_label: string;
    approver: string | null;
    role: string;
    date: string | null;
    notes: string | null;
    superseded: boolean;
}

interface Props {
    purchase: PurchaseDetail;
    history: HistoryRow[];
    canVerify: boolean;
}

export default function Show({ purchase, history, canVerify }: Props) {
    const [rejecting, setRejecting] = useState(false);

    const { data, setData, post, processing, errors } = useForm({ rejection_notes: '' });

    const submitReject = () => post(`/purchases/reject/${purchase.id}`);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href="/purchases/verify"
                        className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                    </Link>
                    <h1 className="text-xl font-semibold">Verify Purchasing Items</h1>
                </div>
            }
        >
            <Head title={`Verifikasi ${purchase.purchase_number}`} />

            <div className="mb-6 grid gap-6 rounded-lg border bg-card p-6 sm:grid-cols-2">
                <div>
                    <h2 className="mb-3 font-medium">Purchasing Information</h2>
                    <dl className="space-y-2 text-sm">
                        <div>
                            <dt className="text-xs text-muted-foreground">Nomor Pembelian</dt>
                            <dd className="font-mono font-medium">{purchase.purchase_number}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">Date Submitted</dt>
                            <dd className="font-medium">{purchase.purchase_date ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">Status</dt>
                            <dd className="mt-1">
                                <StatusBadge tone={purchase.status_tone}>
                                    {purchase.status_label}
                                </StatusBadge>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h2 className="mb-3 font-medium">Requester Information</h2>
                    <dl className="space-y-2 text-sm">
                        <div>
                            <dt className="text-xs text-muted-foreground">Name</dt>
                            <dd className="font-medium">{purchase.creator ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">Supplier</dt>
                            <dd className="font-medium">{purchase.supplier_name}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <h2 className="mb-3 font-medium">Purchased Items</h2>
            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">No</th>
                            <th className="px-4 py-3">Item</th>
                            <th className="px-4 py-3">UoM</th>
                            <th className="px-4 py-3 text-right">Stok Saat Ini</th>
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
                                <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                    {line.current_stock}
                                </td>
                                <td className="px-4 py-3 text-right font-medium tabular-nums">
                                    +{line.quantity}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {history.length > 0 && (
                <div className="mt-6 rounded-lg border bg-card p-6">
                    <h2 className="mb-3 font-medium">Riwayat Keputusan</h2>
                    <ol className="space-y-3">
                        {history.map((row) => (
                            <li key={row.id} className="flex gap-3 text-sm">
                                <StatusBadge
                                    tone={row.decision === 'APPROVED' ? 'success' : 'danger'}
                                >
                                    {row.decision_label}
                                </StatusBadge>
                                <div className="min-w-0">
                                    <p>
                                        {row.approver ?? '—'}{' '}
                                        <span className="text-muted-foreground">({row.role})</span>{' '}
                                        <span className="text-muted-foreground">· {row.date}</span>
                                        {row.superseded && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                — dianulir oleh revisi
                                            </span>
                                        )}
                                    </p>
                                    {row.notes && (
                                        <p className="mt-0.5 text-muted-foreground">{row.notes}</p>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ol>
                </div>
            )}

            {canVerify && (
                <div className="mt-6">
                    {rejecting ? (
                        <div className="rounded-lg border bg-card p-6">
                            <FormField
                                label="Alasan penolakan"
                                htmlFor="rejection_notes"
                                error={errors.rejection_notes}
                                required
                                hint="Jelaskan agar PIC Gudang tahu apa yang harus diperbaiki."
                            >
                                <textarea
                                    id="rejection_notes"
                                    rows={3}
                                    value={data.rejection_notes}
                                    onChange={(e) => setData('rejection_notes', e.target.value)}
                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                />
                            </FormField>

                            <div className="mt-4 flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setRejecting(false)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={processing}
                                    onClick={submitReject}
                                >
                                    Kirim Penolakan
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setRejecting(true)}
                            >
                                <X className="size-4" aria-hidden />
                                Ditolak
                            </Button>
                            <Button
                                type="button"
                                disabled={processing}
                                onClick={() => router.post(`/purchases/verify/${purchase.id}`)}
                            >
                                <Check className="size-4" aria-hidden />
                                Disetujui
                            </Button>
                        </div>
                    )}

                    <p className="mt-3 text-right text-xs text-muted-foreground">
                        Menyetujui akan langsung menambah stok sesuai jumlah di atas.
                    </p>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
