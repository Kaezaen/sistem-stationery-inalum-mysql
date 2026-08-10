import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import FormField from '@/components/shared/FormField';
import StatusBadge from '@/components/shared/StatusBadge';
import TextInput from '@/components/shared/TextInput';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, X } from 'lucide-react';
import { useState } from 'react';
import ApprovalTimeline, { type TimelineRow } from '../ApprovalTimeline';

interface Line {
    id: number;
    item_code: string;
    item_name: string;
    category: string | null;
    uom: string | null;
    available_stock: number;
    quantity_requested: number;
    quantity_approved: number | null;
    remark: string | null;
}

interface RequestDetail {
    id: number;
    request_number: string;
    requester: string | null;
    department: string | null;
    request_date: string | null;
    notes: string | null;
    revision_count: number;
    status_label: string;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger';
    items: Line[];
}

interface Props {
    request: RequestDetail;
    history: TimelineRow[];
    /** l1 & l3 = keputusan biner; l2 = penetapan kuantitas per baris. */
    mode: 'l1' | 'l2' | 'l3' | 'readonly';
    canDecide: boolean;
}

type Decisions = Record<number, { quantity: number; remark: string }>;

export default function Show({ request, history, mode, canDecide }: Props) {
    const [rejecting, setRejecting] = useState(false);

    // Nilai awal mengikuti jumlah yang diminta — PIC Stationery tinggal
    // mengurangi bila stok tidak mencukupi (wireframe 3.3.2).
    const [decisions, setDecisions] = useState<Decisions>(() =>
        Object.fromEntries(
            request.items.map((line) => [
                line.id,
                {
                    quantity: line.quantity_approved ?? line.quantity_requested,
                    remark: line.remark ?? '',
                },
            ]),
        ),
    );

    const rejectForm = useForm({ rejection_notes: '' });

    const setQuantity = (lineId: number, value: number, max: number) => {
        setDecisions((prev) => ({
            ...prev,
            [lineId]: {
                ...prev[lineId]!,
                quantity: Math.min(max, Math.max(0, value)),
            },
        }));
    };

    const setRemark = (lineId: number, value: string) => {
        setDecisions((prev) => ({ ...prev, [lineId]: { ...prev[lineId]!, remark: value } }));
    };

    const approve = () => {
        if (mode === 'l2') {
            router.post(`/requests/verify/${request.id}/approve`, { decisions });

            return;
        }

        router.post(`/requests/verify/${request.id}/approve`);
    };

    const isQuantitative = mode === 'l2';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href="/requests/verify"
                        className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                    </Link>
                    <h1 className="text-xl font-semibold">Verify Requests</h1>
                </div>
            }
        >
            <Head title={`Verifikasi ${request.request_number}`} />

            <div className="mb-6 grid gap-6 rounded-lg border bg-card p-6 sm:grid-cols-2">
                <div>
                    <h2 className="mb-3 font-medium">Request Information</h2>
                    <dl className="space-y-2 text-sm">
                        <div>
                            <dt className="text-xs text-muted-foreground">Request ID</dt>
                            <dd className="font-medium">{request.request_number}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">Date Submitted</dt>
                            <dd className="font-medium">{request.request_date ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">Status</dt>
                            <dd className="mt-1">
                                <StatusBadge tone={request.status_tone}>
                                    {request.status_label}
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
                            <dd className="font-medium">{request.requester ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">Departement/Seksi</dt>
                            <dd className="font-medium">{request.department ?? '—'}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <h2 className="mb-3 font-medium">Requested Items</h2>
            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">No</th>
                            <th className="px-4 py-3">Item</th>
                            <th className="px-4 py-3 text-right">Quantity</th>
                            <th className="px-4 py-3 text-right">
                                {isQuantitative ? 'Quantity Actual' : 'Disetujui'}
                            </th>
                            {isQuantitative && <th className="px-4 py-3 text-right">Tersedia</th>}
                            <th className="px-4 py-3">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        {request.items.map((line, index) => (
                            <tr key={line.id} className="border-b last:border-0">
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
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {line.quantity_requested}
                                </td>

                                <td className="px-4 py-3">
                                    {isQuantitative && canDecide ? (
                                        <div className="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setQuantity(
                                                        line.id,
                                                        (decisions[line.id]?.quantity ?? 0) - 1,
                                                        line.quantity_requested,
                                                    )
                                                }
                                                className="size-7 rounded border text-sm hover:bg-secondary"
                                                aria-label="Kurangi"
                                            >
                                                −
                                            </button>
                                            <input
                                                type="number"
                                                min={0}
                                                max={line.quantity_requested}
                                                value={decisions[line.id]?.quantity ?? 0}
                                                onChange={(e) =>
                                                    setQuantity(
                                                        line.id,
                                                        Number(e.target.value),
                                                        line.quantity_requested,
                                                    )
                                                }
                                                className="h-7 w-16 rounded border bg-background text-center text-sm"
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setQuantity(
                                                        line.id,
                                                        (decisions[line.id]?.quantity ?? 0) + 1,
                                                        line.quantity_requested,
                                                    )
                                                }
                                                className="size-7 rounded border text-sm hover:bg-secondary"
                                                aria-label="Tambah"
                                            >
                                                +
                                            </button>
                                        </div>
                                    ) : (
                                        <div className="text-right font-medium tabular-nums">
                                            {line.quantity_approved ?? '—'}
                                        </div>
                                    )}
                                </td>

                                {isQuantitative && (
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        <span
                                            className={
                                                (decisions[line.id]?.quantity ?? 0) >
                                                line.available_stock
                                                    ? 'text-red-600'
                                                    : 'text-muted-foreground'
                                            }
                                        >
                                            {line.available_stock}
                                        </span>
                                    </td>
                                )}

                                <td className="px-4 py-3">
                                    {isQuantitative && canDecide ? (
                                        <TextInput
                                            placeholder="masukkan catatan…"
                                            value={decisions[line.id]?.remark ?? ''}
                                            onChange={(e) => setRemark(line.id, e.target.value)}
                                        />
                                    ) : (
                                        <span className="text-muted-foreground">
                                            {line.remark ?? '—'}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {history.length > 0 && (
                <div className="mt-6">
                    <ApprovalTimeline history={history} />
                </div>
            )}

            {canDecide && (
                <div className="mt-6">
                    {rejecting ? (
                        <div className="rounded-lg border bg-card p-6">
                            <FormField
                                label="Alasan penolakan"
                                htmlFor="rejection_notes"
                                error={rejectForm.errors.rejection_notes}
                                required
                                hint="Jelaskan agar yang merevisi tahu apa yang harus diperbaiki."
                            >
                                <textarea
                                    id="rejection_notes"
                                    rows={3}
                                    value={rejectForm.data.rejection_notes}
                                    onChange={(e) =>
                                        rejectForm.setData('rejection_notes', e.target.value)
                                    }
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
                                    disabled={rejectForm.processing}
                                    onClick={() =>
                                        rejectForm.post(`/requests/verify/${request.id}/reject`)
                                    }
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
                                {isQuantitative ? 'Ditolak Seluruhnya' : 'Ditolak'}
                            </Button>
                            <Button type="button" onClick={approve}>
                                <Check className="size-4" aria-hidden />
                                {isQuantitative ? 'Submit' : 'Disetujui'}
                            </Button>
                        </div>
                    )}

                    {isQuantitative && (
                        <p className="mt-3 text-right text-xs text-muted-foreground">
                            Menyimpan akan mengunci stok sesuai jumlah di atas hingga barang
                            diserahkan.
                        </p>
                    )}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
