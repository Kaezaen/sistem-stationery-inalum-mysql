import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import StatusBadge from '@/components/shared/StatusBadge';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, X } from 'lucide-react';
import ApprovalTimeline, { type TimelineRow } from './ApprovalTimeline';
import RequestLines, { type RequestLine } from './RequestLines';

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
    items: RequestLine[];
}

interface Props {
    request: RequestDetail;
    history: TimelineRow[];
    canRevise: boolean;
    canCancel: boolean;
}

export default function Show({ request, history, canRevise, canCancel }: Props) {
    // Catatan penolakan terakhir ditonjolkan agar requester langsung tahu
    // apa yang harus diperbaiki tanpa menelusuri seluruh timeline.
    const lastRejection = [...history].reverse().find((row) => row.decision === 'REJECTED');

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href="/requests"
                        className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                    </Link>
                    <h1 className="text-xl font-semibold">Detail Request</h1>
                </div>
            }
        >
            <Head title={request.request_number} />

            <div className="mb-6 rounded-lg border bg-card p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-xs text-muted-foreground">Request ID</p>
                            <p className="font-medium">{request.request_number}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Name</p>
                            <p className="font-medium">{request.requester ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Date Submitted</p>
                            <p className="font-medium">{request.request_date ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Departement/Seksi</p>
                            <p className="font-medium">{request.department ?? '—'}</p>
                        </div>
                    </div>

                    <div className="flex flex-col items-end gap-2">
                        <StatusBadge tone={request.status_tone}>{request.status_label}</StatusBadge>
                        {request.revision_count > 0 && (
                            <span className="text-xs text-muted-foreground">
                                Revisi ke-{request.revision_count}
                            </span>
                        )}
                    </div>
                </div>

                {lastRejection?.notes && (
                    <div className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-300">
                        <strong>Catatan penolakan:</strong> {lastRejection.notes}
                    </div>
                )}
            </div>

            <h2 className="mb-3 font-medium">Requested Items</h2>
            <RequestLines lines={request.items} />

            {history.length > 0 && (
                <div className="mt-6">
                    <ApprovalTimeline history={history} />
                </div>
            )}

            {(canRevise || canCancel) && (
                <div className="mt-6 flex justify-end gap-2">
                    {canCancel && (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => router.post(`/requests/${request.id}/cancel`)}
                        >
                            <X className="size-4" aria-hidden />
                            Batalkan
                        </Button>
                    )}
                    {canRevise && (
                        <Link href={`/requests/${request.id}/revise`}>
                            <Button>
                                <Pencil className="size-4" aria-hidden />
                                Revisi Request
                            </Button>
                        </Link>
                    )}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
