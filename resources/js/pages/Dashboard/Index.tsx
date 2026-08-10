import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import AnimatedNumber from '@/components/shared/AnimatedNumber';
import StatusBadge from '@/components/shared/StatusBadge';
import { fadeUp, growBar, hoverLift, staggerContainer } from '@/lib/motion';
import { cn } from '@/lib/utils';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, ClipboardList, Clock, Layers, Package } from 'lucide-react';
import { motion } from 'motion/react';

type Tone = 'neutral' | 'success' | 'warning' | 'danger';

interface StatusCount {
    label: string;
    tone: Tone;
    count: number;
}

interface TrendPoint {
    label: string;
    count: number;
}

interface DashboardData {
    myRequests: { total: number; byStatus: StatusCount[] };
    orgRequests: {
        byStatus: StatusCount[];
        trend: TrendPoint[];
        topItems: { item_name: string; qty: number }[];
    } | null;
    stock: {
        underStockCount: number;
        needToBuy: { item_name: string; stock: number; suggested: number }[];
    } | null;
}

interface Props {
    data: DashboardData | null;
}

type StatTone = 'primary' | 'info' | 'warning' | 'tertiary';

const STAT_TONES: Record<StatTone, string> = {
    primary: 'bg-primary/10 text-primary',
    info: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    warning: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    tertiary: 'bg-tertiary/10 text-tertiary',
};

export default function Index({ data }: Props) {
    const { auth } = usePage().props;

    if (!data) {
        return (
            <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Dashboard</h1>}>
                <Head title="Dashboard" />
            </AuthenticatedLayout>
        );
    }

    const orgTotal = data.orgRequests
        ? data.orgRequests.byStatus.reduce((sum, s) => sum + s.count, 0)
        : 0;
    const inProgress = data.orgRequests
        ? data.orgRequests.byStatus
              .filter((s) => s.tone === 'warning')
              .reduce((sum, s) => sum + s.count, 0)
        : 0;

    const stats: {
        label: string;
        value: number;
        icon: typeof ClipboardList;
        tone: StatTone;
        hint: string;
    }[] = [
        {
            label: 'Request Saya',
            value: data.myRequests.total,
            icon: ClipboardList,
            tone: 'primary',
            hint: 'total yang saya ajukan',
        },
    ];

    if (data.orgRequests) {
        stats.push(
            {
                label: 'Total Request Organisasi',
                value: orgTotal,
                icon: Layers,
                tone: 'info',
                hint: 'seluruh periode',
            },
            {
                label: 'Dalam Proses',
                value: inProgress,
                icon: Clock,
                tone: 'warning',
                hint: 'menunggu approval / diambil',
            },
        );
    }

    if (data.stock) {
        stats.push({
            label: 'Item di Bawah Minimum',
            value: data.stock.underStockCount,
            icon: AlertTriangle,
            tone: 'tertiary',
            hint: 'perlu dibeli',
        });
    }

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Dashboard</h1>}>
            <Head title="Dashboard" />

            <motion.div className="mb-6" variants={fadeUp} initial="hidden" animate="visible">
                <h2 className="text-2xl font-semibold tracking-tight">
                    Selamat datang{auth.user ? `, ${auth.user.name}` : ''}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Ringkasan permintaan dan posisi stok terkini.
                </p>
            </motion.div>

            {/* Baris stat */}
            <motion.div
                className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                variants={staggerContainer}
                initial="hidden"
                animate="visible"
            >
                {stats.map((stat) => (
                    <motion.div
                        key={stat.label}
                        variants={fadeUp}
                        whileHover={hoverLift.whileHover}
                        whileTap={hoverLift.whileTap}
                        className="rounded-xl border bg-card p-5"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <span
                                className={cn(
                                    'flex size-10 items-center justify-center rounded-lg',
                                    STAT_TONES[stat.tone],
                                )}
                            >
                                <stat.icon className="size-5" aria-hidden />
                            </span>
                            <AnimatedNumber
                                value={stat.value}
                                className="text-3xl font-semibold tabular-nums"
                            />
                        </div>
                        <p className="mt-3 text-sm font-medium">{stat.label}</p>
                        <p className="text-xs text-muted-foreground">{stat.hint}</p>
                    </motion.div>
                ))}
            </motion.div>

            {/* Detail */}
            <motion.div
                className="grid gap-6 lg:grid-cols-2"
                variants={staggerContainer}
                initial="hidden"
                animate="visible"
            >
                {data.orgRequests && (
                    <Card title="Tren Request (6 Bulan)" icon={ClipboardList}>
                        <TrendChart points={data.orgRequests.trend} />
                    </Card>
                )}

                {data.orgRequests && (
                    <Card title="Status Request Organisasi" icon={Layers}>
                        {data.orgRequests.byStatus.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {data.orgRequests.byStatus.map((s) => (
                                    <StatusBadge key={s.label} tone={s.tone}>
                                        {`${s.label} · ${s.count}`}
                                    </StatusBadge>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">Belum ada request.</p>
                        )}

                        {data.orgRequests.topItems.length > 0 && (
                            <div className="mt-5">
                                <p className="mb-2 flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <Package className="size-3.5" aria-hidden />
                                    Item terbanyak diminta (90 hari)
                                </p>
                                <ul className="space-y-1.5 text-sm">
                                    {data.orgRequests.topItems.map((item) => (
                                        <li
                                            key={item.item_name}
                                            className="flex items-center justify-between gap-3"
                                        >
                                            <span className="truncate">{item.item_name}</span>
                                            <span className="shrink-0 font-medium tabular-nums">
                                                {item.qty}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </Card>
                )}

                {data.stock && (
                    <Card
                        title="Perlu Dibeli"
                        icon={AlertTriangle}
                        action={{ href: '/reports/need-to-buy', label: 'Laporan lengkap' }}
                    >
                        {data.stock.needToBuy.length > 0 ? (
                            <ul className="space-y-1.5 text-sm">
                                {data.stock.needToBuy.map((item) => (
                                    <li
                                        key={item.item_name}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <span className="truncate">{item.item_name}</span>
                                        <span className="shrink-0 text-muted-foreground tabular-nums">
                                            stok {item.stock} · beli{' '}
                                            <span className="font-medium text-tertiary">
                                                {item.suggested}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Semua item di atas minimum.
                            </p>
                        )}
                    </Card>
                )}

                <Card
                    title="Request Saya"
                    icon={ClipboardList}
                    action={{ href: '/requests', label: 'Lihat semua' }}
                >
                    {data.myRequests.byStatus.length > 0 ? (
                        <div className="flex flex-wrap gap-2">
                            {data.myRequests.byStatus.map((s) => (
                                <StatusBadge key={s.label} tone={s.tone}>
                                    {`${s.label} · ${s.count}`}
                                </StatusBadge>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Anda belum mengajukan request.
                        </p>
                    )}
                </Card>
            </motion.div>
        </AuthenticatedLayout>
    );
}

function Card({
    title,
    icon: Icon,
    action,
    children,
}: {
    title: string;
    icon: typeof ClipboardList;
    action?: { href: string; label: string };
    children: React.ReactNode;
}) {
    return (
        <motion.div
            variants={fadeUp}
            whileHover={hoverLift.whileHover}
            className="rounded-xl border bg-card p-6"
        >
            <div className="mb-4 flex items-center justify-between gap-3">
                <h3 className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Icon className="size-4" aria-hidden />
                    {title}
                </h3>
                {action && (
                    <Link href={action.href} className="text-sm underline-offset-4 hover:underline">
                        {action.label}
                    </Link>
                )}
            </div>
            {children}
        </motion.div>
    );
}

function TrendChart({ points }: { points: TrendPoint[] }) {
    const max = Math.max(1, ...points.map((p) => p.count));

    return (
        <motion.div
            className="flex h-40 items-end gap-2"
            variants={staggerContainer}
            initial="hidden"
            animate="visible"
        >
            {points.map((point) => (
                <div key={point.label} className="flex flex-1 flex-col items-center gap-1">
                    <span className="text-xs font-medium tabular-nums">{point.count}</span>
                    <div className="flex w-full flex-1 items-end">
                        <motion.div
                            variants={growBar}
                            className="w-full origin-bottom rounded-t bg-gradient-to-t from-primary/70 to-primary"
                            style={{
                                height: `${Math.max(2, Math.round((point.count / max) * 100))}%`,
                            }}
                            title={`${point.label}: ${point.count}`}
                        />
                    </div>
                    <span className="text-xs text-muted-foreground">{point.label}</span>
                </div>
            ))}
        </motion.div>
    );
}
