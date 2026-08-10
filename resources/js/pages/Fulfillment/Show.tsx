import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import StatusBadge from '@/components/shared/StatusBadge';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Printer } from 'lucide-react';
import { useState } from 'react';
import ApprovalTimeline, { type TimelineRow } from '../Requests/ApprovalTimeline';

interface Line {
    id: number;
    item_code: string;
    item_name: string;
    uom: string | null;
    physical_stock: number;
    quantity_requested: number;
    quantity_approved: number | null;
    quantity_actual: number | null;
    remark: string | null;
    status_label: string;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger';
}

interface RequestDetail {
    id: number;
    request_number: string;
    requester: string | null;
    department: string | null;
    request_date: string | null;
    status: string;
    status_label: string;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger';
    items: Line[];
}

interface Props {
    request: RequestDetail;
    history: TimelineRow[];
    canHandover: boolean;
}

/** Serah terima barang — wireframe 3.5.2. */
export default function Show({ request, history, canHandover }: Props) {
    const [processing, setProcessing] = useState(false);

    // Nilai awal = jumlah yang disetujui. PIC Gudang tinggal mengurangi bila
    // stok fisik ternyata kurang (keputusan D5).
    const [quantities, setQuantities] = useState<Record<number, number>>(() =>
        Object.fromEntries(request.items.map((line) => [line.id, line.quantity_approved ?? 0])),
    );

    const setQuantity = (lineId: number, value: number, max: number) => {
        setQuantities((prev) => ({ ...prev, [lineId]: Math.min(max, Math.max(0, value)) }));
    };

    const submit = () => {
        setProcessing(true);
        router.post(
            `/handover/${request.id}`,
            { quantities },
            { onFinish: () => setProcessing(false) },
        );
    };

    const isDone = request.status === 'COMPLETED';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href="/handover"
                        className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                    </Link>
                    <h1 className="text-xl font-semibold">Taking Over Item</h1>
                </div>
            }
        >
            <Head title={`Serah Terima ${request.request_number}`} />

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
                            <th className="px-4 py-3">UoM</th>
                            <th className="px-4 py-3 text-right">Quantity Actual</th>
                            {!isDone && <th className="px-4 py-3 text-right">Stok Fisik</th>}
                            <th className="px-4 py-3 text-right">
                                {isDone ? 'Diserahkan' : 'Diberikan'}
                            </th>
                            <th className="px-4 py-3">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        {request.items.map((line, index) => {
                            const approved = line.quantity_approved ?? 0;
                            const chosen = quantities[line.id] ?? 0;

                            return (
                                <tr key={line.id} className="border-b last:border-0">
                                    <td className="px-4 py-3">{index + 1}</td>
                                    <td className="px-4 py-3">
                                        <div>{line.item_name}</div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {line.item_code}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">{line.uom ?? '—'}</td>
                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                        {approved}
                                    </td>

                                    {!isDone && (
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            <span
                                                className={
                                                    line.physical_stock < approved
                                                        ? 'text-red-600'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {line.physical_stock}
                                            </span>
                                        </td>
                                    )}

                                    <td className="px-4 py-3">
                                        {isDone || !canHandover || approved === 0 ? (
                                            <div className="text-right font-medium tabular-nums">
                                                {line.quantity_actual ?? '—'}
                                            </div>
                                        ) : (
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setQuantity(line.id, chosen - 1, approved)
                                                    }
                                                    className="size-7 rounded border text-sm hover:bg-secondary"
                                                    aria-label="Kurangi"
                                                >
                                                    −
                                                </button>
                                                <input
                                                    type="number"
                                                    min={0}
                                                    max={approved}
                                                    value={chosen}
                                                    onChange={(e) =>
                                                        setQuantity(
                                                            line.id,
                                                            Number(e.target.value),
                                                            approved,
                                                        )
                                                    }
                                                    className="h-7 w-16 rounded border bg-background text-center text-sm"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setQuantity(line.id, chosen + 1, approved)
                                                    }
                                                    className="size-7 rounded border text-sm hover:bg-secondary"
                                                    aria-label="Tambah"
                                                >
                                                    +
                                                </button>
                                            </div>
                                        )}
                                    </td>

                                    <td className="px-4 py-3 text-muted-foreground">
                                        {line.remark ?? '—'}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {history.length > 0 && (
                <div className="mt-6">
                    <ApprovalTimeline history={history} />
                </div>
            )}

            <div className="mt-6 flex justify-end gap-2">
                {isDone && (
                    <a href={`/handover/${request.id}/receipt`}>
                        <Button type="button" variant="secondary">
                            <Printer className="size-4" aria-hidden />
                            Bukti Serah Terima
                        </Button>
                    </a>
                )}

                {!isDone && canHandover && (
                    <Button type="button" disabled={processing} onClick={submit}>
                        <Check className="size-4" aria-hidden />
                        {processing ? 'Memproses…' : 'Diberikan'}
                    </Button>
                )}
            </div>

            {!isDone && canHandover && (
                <p className="mt-3 text-right text-xs text-muted-foreground">
                    Menekan Diberikan akan mengurangi stok fisik sesuai jumlah di atas. Sisa yang
                    tidak diserahkan dilepas kembali ke stok tersedia.
                </p>
            )}
        </AuthenticatedLayout>
    );
}
