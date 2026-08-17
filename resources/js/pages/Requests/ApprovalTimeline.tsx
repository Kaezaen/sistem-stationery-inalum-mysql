import StatusBadge from '@/components/shared/StatusBadge';

export interface TimelineRow {
    id: number;
    level: number;
    decision: string;
    decision_label: string;
    approver: string | null;
    role: string;
    date: string | null;
    notes: string | null;
    superseded: boolean;
}

const LEVEL_LABEL: Record<number, string> = {
    1: 'Pimpinan SIT',
    2: 'PIC Stationery',
    3: 'Pimpinan SGA',
};

/**
 * Riwayat keputusan approval.
 *
 * Keputusan yang dianulir revisi TETAP ditampilkan — auditor perlu melihat
 * bahwa dokumen ini pernah ditolak, oleh siapa, dan dengan alasan apa.
 */
export default function ApprovalTimeline({ history }: { history: TimelineRow[] }) {
    if (history.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border bg-card p-6">
            <h2 className="mb-3 font-medium">Riwayat Approval</h2>
            <ol className="space-y-3">
                {history.map((row) => (
                    <li key={row.id} className="flex flex-wrap items-start gap-3 text-sm">
                        <StatusBadge tone={row.decision === 'APPROVED' ? 'success' : 'danger'}>
                            {row.decision_label}
                        </StatusBadge>
                        <div className="min-w-0">
                            <p>
                                <span className="font-medium">
                                    Level {row.level} · {LEVEL_LABEL[row.level] ?? row.role}
                                </span>{' '}
                                <span className="text-muted-foreground">
                                    — {row.approver ?? '—'} · {row.date}
                                </span>
                                {row.superseded && (
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        (dianulir oleh revisi)
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
    );
}
