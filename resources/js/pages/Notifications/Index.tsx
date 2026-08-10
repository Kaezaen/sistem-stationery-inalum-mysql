import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import Button from '@/components/shared/Button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { BellOff, CheckCheck } from 'lucide-react';

interface NotificationRow {
    id: string;
    code: string | null;
    title: string;
    message: string;
    reference: string | null;
    url: string | null;
    read: boolean;
    created_at: string | null;
}

interface Props {
    notifications: Paginated<NotificationRow>;
    unread: number;
}

export default function Index({ notifications, unread }: Props) {
    const open = (row: NotificationRow) => {
        router.post(`/notifications/${row.id}/read`, {}, { preserveScroll: true });
    };

    const markAll = () => {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Notifikasi</h1>}>
            <Head title="Notifikasi" />

            <div className="mb-4 flex items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    {unread > 0 ? `${unread} notifikasi belum dibaca` : 'Semua notifikasi terbaca'}
                </p>
                {unread > 0 && (
                    <Button type="button" variant="secondary" onClick={markAll}>
                        <CheckCheck className="size-4" aria-hidden />
                        Tandai semua terbaca
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-xl border bg-card">
                <ul className="divide-y">
                    {notifications.data.map((row) => (
                        <li key={row.id}>
                            <button
                                type="button"
                                onClick={() => open(row)}
                                className={cn(
                                    'flex w-full items-start gap-3 px-5 py-4 text-left transition-colors hover:bg-muted/50',
                                    !row.read && 'bg-accent/40',
                                )}
                            >
                                <span
                                    className={cn(
                                        'mt-1.5 size-2 shrink-0 rounded-full',
                                        row.read ? 'bg-transparent' : 'bg-primary',
                                    )}
                                    aria-hidden
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="flex items-center justify-between gap-3">
                                        <span
                                            className={cn(
                                                'truncate',
                                                row.read ? 'font-medium' : 'font-semibold',
                                            )}
                                        >
                                            {row.title}
                                        </span>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {row.created_at}
                                        </span>
                                    </span>
                                    <span className="mt-0.5 block text-sm text-muted-foreground">
                                        {row.message}
                                    </span>
                                    {row.reference && (
                                        <span className="mt-1 block font-mono text-xs text-muted-foreground">
                                            {row.reference}
                                        </span>
                                    )}
                                </span>
                            </button>
                        </li>
                    ))}

                    {notifications.data.length === 0 && (
                        <li className="flex flex-col items-center gap-2 px-5 py-16 text-center text-muted-foreground">
                            <BellOff className="size-8" aria-hidden />
                            <p className="text-sm">Belum ada notifikasi.</p>
                        </li>
                    )}
                </ul>
            </div>

            {notifications.last_page > 1 && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {notifications.links.map((link, index) => (
                        <Link
                            key={index}
                            href={link.url ?? '#'}
                            preserveScroll
                            className={cn(
                                'inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm',
                                link.active && 'bg-primary text-primary-foreground',
                                !link.url && 'pointer-events-none opacity-40',
                            )}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
