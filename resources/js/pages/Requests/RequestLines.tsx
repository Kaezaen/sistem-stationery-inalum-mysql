import StatusBadge from '@/components/shared/StatusBadge';

export interface RequestLine {
    id: number;
    item_code: string;
    item_name: string;
    category?: string | null;
    uom?: string | null;
    quantity_requested: number;
    quantity_approved?: number | null;
    quantity_actual?: number | null;
    remark?: string | null;
    status_label?: string;
    status_tone?: 'neutral' | 'success' | 'warning' | 'danger';
}

/**
 * Tabel baris request — dipakai layar detail, revisi, dan verifikasi read-only.
 *
 * Kolom "Disetujui" hanya muncul setelah PIC Stationery menetapkan kuantitas,
 * agar requester tidak melihat kolom kosong yang membingungkan.
 */
export default function RequestLines({ lines }: { lines: RequestLine[] }) {
    const showApproved = lines.some((line) => line.quantity_approved !== null);
    const showActual = lines.some((line) => line.quantity_actual !== null);

    return (
        <div className="overflow-x-auto rounded-lg border bg-card">
            <table className="w-full text-sm">
                <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-3">No</th>
                        <th className="px-4 py-3">Item</th>
                        <th className="px-4 py-3 text-right">Quantity</th>
                        {showApproved && <th className="px-4 py-3 text-right">Disetujui</th>}
                        {showActual && <th className="px-4 py-3 text-right">Diserahkan</th>}
                        <th className="px-4 py-3">Remark</th>
                        {lines.some((l) => l.status_label) && <th className="px-4 py-3">Status</th>}
                    </tr>
                </thead>
                <tbody>
                    {lines.map((line, index) => (
                        <tr key={line.id} className="border-b last:border-0">
                            <td className="px-4 py-3">{index + 1}</td>
                            <td className="px-4 py-3">
                                <div>{line.item_name}</div>
                                <div className="font-mono text-xs text-muted-foreground">
                                    {line.item_code}
                                </div>
                                {line.category && (
                                    <div className="text-xs text-muted-foreground">
                                        {line.category}
                                    </div>
                                )}
                            </td>
                            <td className="px-4 py-3 text-right tabular-nums">
                                {line.quantity_requested}
                            </td>
                            {showApproved && (
                                <td className="px-4 py-3 text-right font-medium tabular-nums">
                                    {line.quantity_approved ?? '—'}
                                </td>
                            )}
                            {showActual && (
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {line.quantity_actual ?? '—'}
                                </td>
                            )}
                            <td className="px-4 py-3 text-muted-foreground">
                                {line.remark ?? '—'}
                            </td>
                            {line.status_label && (
                                <td className="px-4 py-3">
                                    <StatusBadge tone={line.status_tone ?? 'neutral'}>
                                        {line.status_label}
                                    </StatusBadge>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
