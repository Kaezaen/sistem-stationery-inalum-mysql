import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import SelectInput from '@/components/shared/SelectInput';
import TextInput from '@/components/shared/TextInput';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface AuditRow {
    id: number;
    event: string;
    entity: string | null;
    user: string | null;
    ip: string | null;
    created_at: string | null;
    old: Record<string, unknown> | null;
    new: Record<string, unknown> | null;
}

interface Props {
    logs: Paginated<AuditRow>;
    filters: { event: string; from: string | null; until: string | null };
    events: string[];
}

const EVENT_LABELS: Record<string, string> = {
    created: 'Dibuat',
    updated: 'Diubah',
    deleted: 'Dihapus',
    login: 'Login',
    login_failed: 'Login Gagal',
    logout: 'Logout',
    quantity_actual_changed: 'Ubah Qty Diserahkan',
};

function eventLabel(event: string): string {
    return EVENT_LABELS[event] ?? event;
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

export default function Index({ logs, filters, events }: Props) {
    const apply = (overrides: Record<string, string>) => {
        router.get(
            '/admin/audit-logs',
            {
                event: filters.event,
                from: filters.from ?? '',
                until: filters.until ?? '',
                ...overrides,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Audit Log</h1>}>
            <Head title="Audit Log" />

            <div className="mb-4 flex flex-wrap items-end gap-3">
                <label className="flex flex-col gap-1">
                    <span className="text-xs font-medium text-muted-foreground">Kejadian</span>
                    <SelectInput
                        value={filters.event}
                        className="w-52"
                        onChange={(e) => apply({ event: e.target.value })}
                    >
                        <option value="">Semua Kejadian</option>
                        {events.map((ev) => (
                            <option key={ev} value={ev}>
                                {eventLabel(ev)}
                            </option>
                        ))}
                    </SelectInput>
                </label>
                <label className="flex flex-col gap-1">
                    <span className="text-xs font-medium text-muted-foreground">Dari</span>
                    <TextInput
                        type="date"
                        value={filters.from ?? ''}
                        className="w-40"
                        onChange={(e) => apply({ from: e.target.value })}
                    />
                </label>
                <label className="flex flex-col gap-1">
                    <span className="text-xs font-medium text-muted-foreground">Sampai</span>
                    <TextInput
                        type="date"
                        value={filters.until ?? ''}
                        className="w-40"
                        onChange={(e) => apply({ until: e.target.value })}
                    />
                </label>
            </div>

            <div className="overflow-x-auto rounded-xl border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3">Waktu</th>
                            <th className="px-4 py-3">Kejadian</th>
                            <th className="px-4 py-3">Entitas</th>
                            <th className="px-4 py-3">Pengguna</th>
                            <th className="px-4 py-3">IP</th>
                            <th className="px-4 py-3">Perubahan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {logs.data.map((row) => (
                            <tr key={row.id} className="border-b align-top last:border-0">
                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                    {row.created_at}
                                </td>
                                <td className="px-4 py-3 font-medium">{eventLabel(row.event)}</td>
                                <td className="px-4 py-3 font-mono text-xs">{row.entity ?? '—'}</td>
                                <td className="px-4 py-3">{row.user ?? 'Sistem'}</td>
                                <td className="px-4 py-3 font-mono text-xs">{row.ip ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <ChangeList old={row.old} next={row.new} />
                                </td>
                            </tr>
                        ))}

                        {logs.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Tidak ada jejak audit untuk filter ini.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {logs.last_page > 1 && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {logs.links.map((link, index) => (
                        <Link
                            key={index}
                            href={link.url ?? '#'}
                            preserveScroll
                            className={
                                'inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm ' +
                                (link.active ? 'bg-primary text-primary-foreground ' : '') +
                                (!link.url ? 'pointer-events-none opacity-40' : '')
                            }
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}

function ChangeList({
    old,
    next,
}: {
    old: Record<string, unknown> | null;
    next: Record<string, unknown> | null;
}) {
    const keys = Array.from(new Set([...Object.keys(old ?? {}), ...Object.keys(next ?? {})]));

    if (keys.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <ul className="space-y-0.5 text-xs">
            {keys.map((key) => (
                <li key={key}>
                    <span className="text-muted-foreground">{key}:</span>{' '}
                    {old ? (
                        <span className="line-through opacity-60">{formatValue(old[key])}</span>
                    ) : null}
                    {old && next ? ' → ' : null}
                    {next ? <span className="font-medium">{formatValue(next[key])}</span> : null}
                </li>
            ))}
        </ul>
    );
}
