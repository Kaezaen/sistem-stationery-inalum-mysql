import FlashToast from '@/components/shared/FlashToast';
import InalumLogo from '@/components/shared/InalumLogo';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { Link, router, usePage } from '@inertiajs/react';
import { motion } from 'motion/react';
import {
    BarChart3,
    Bell,
    Building,
    Building2,
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    FilePlus2,
    FileSpreadsheet,
    LayoutDashboard,
    LogOut,
    PackageCheck,
    PackagePlus,
    PackageSearch,
    PackageX,
    ScrollText,
    ShieldCheck,
    ShoppingCart,
    TrendingUp,
    Users,
    Warehouse,
} from 'lucide-react';
import type { PropsWithChildren, ReactNode } from 'react';

interface NavItem {
    label: string;
    href: string | null;
    icon: typeof LayoutDashboard;
    /** Permission yang dibutuhkan agar item tampil. null = selalu tampil. */
    permission?: string;
}

interface NavGroup {
    heading: string | null;
    items: NavItem[];
}

/**
 * Struktur menu diambil langsung dari wireframe blueprint (Gambar 3.1.2),
 * ditambah grup Administrasi yang diwajibkan §3.1 Analisis Requirement.
 *
 * href bernilai null = modul belum diimplementasikan; item tampil non-aktif
 * agar cakupan yang tersisa terlihat jelas selama pengembangan bertahap.
 */
const NAVIGATION: NavGroup[] = [
    {
        heading: null,
        items: [
            { label: 'Dashboard', href: '/', icon: LayoutDashboard },
            { label: 'Notifikasi', href: '/notifications', icon: Bell },
            {
                label: 'Request Items',
                href: '/requests/create',
                icon: FilePlus2,
                permission: 'request.create',
            },
            {
                label: 'Data Request Items',
                href: '/requests',
                icon: ClipboardList,
                permission: 'request.view.own',
            },
            {
                label: 'Verify Request Items',
                href: '/requests/verify',
                icon: ClipboardCheck,
                permission: 'request.approve.l1',
            },
            {
                label: 'Serah Terima Item',
                href: '/handover',
                icon: PackageCheck,
                permission: 'request.handover',
            },
        ],
    },
    {
        heading: 'Master Data',
        items: [
            {
                label: 'Data List Items',
                href: '/items',
                icon: PackageSearch,
                permission: 'item.view',
            },
            {
                label: 'Add New Items',
                href: '/items/create',
                icon: PackagePlus,
                permission: 'item.create',
            },
        ],
    },
    {
        heading: 'Purchasing',
        items: [
            {
                label: 'Purchasing Items',
                href: '/purchases/create',
                icon: ShoppingCart,
                permission: 'purchase.create',
            },
            {
                label: 'Data Purchasing Items',
                href: '/purchases',
                icon: ClipboardList,
                permission: 'purchase.view',
            },
            {
                label: 'Verify Purchasing Items',
                href: '/purchases/verify',
                icon: ClipboardCheck,
                permission: 'purchase.verify',
            },
        ],
    },
    {
        heading: 'Inventory',
        items: [
            {
                label: 'Data Inventory',
                href: '/inventory',
                icon: Warehouse,
                permission: 'inventory.view',
            },
        ],
    },
    {
        heading: 'Reporting',
        items: [
            {
                label: 'Stock by Month',
                href: '/reports/stock-by-month',
                icon: BarChart3,
                permission: 'report.stock.view',
            },
            {
                label: 'Stock by Year',
                href: '/reports/stock-by-year',
                icon: CalendarRange,
                permission: 'report.stock.view',
            },
            {
                label: 'Purchasing',
                href: '/reports/purchasing',
                icon: ShoppingCart,
                permission: 'report.purchasing.view',
            },
            {
                label: 'Need to Buy',
                href: '/reports/need-to-buy',
                icon: PackageX,
                permission: 'report.need_to_buy.view',
            },
            {
                label: 'Request by Month',
                href: '/reports/request-by-month',
                icon: TrendingUp,
                permission: 'report.request.view',
            },
            {
                label: 'Request by Year',
                href: '/reports/request-by-year',
                icon: CalendarRange,
                permission: 'report.request.view',
            },
            {
                label: 'Request by Account',
                href: '/reports/request-by-account',
                icon: Building,
                permission: 'report.request.view',
            },
            {
                label: 'Request by Item',
                href: '/reports/request-by-item',
                icon: FileSpreadsheet,
                permission: 'report.request.view',
            },
        ],
    },
    {
        heading: 'Administrasi',
        items: [
            { label: 'Kelola User', href: '/admin/users', icon: Users, permission: 'user.manage' },
            {
                label: 'Departemen',
                href: '/admin/departments',
                icon: Building2,
                permission: 'user.manage',
            },
            {
                label: 'Role & Permission',
                href: '/admin/roles',
                icon: ShieldCheck,
                permission: 'role.manage',
            },
            {
                label: 'Audit Log',
                href: '/admin/audit-logs',
                icon: ScrollText,
                permission: 'audit.view',
            },
        ],
    },
];

/** Inisial dari nama (maksimal dua huruf) untuk avatar. */
function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function NavLink({ item, active }: { item: NavItem; active: boolean }) {
    const className = cn(
        'relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
        active && 'bg-sidebar-primary/15 font-medium text-white',
        active &&
            "before:absolute before:top-1/2 before:-left-4 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-sidebar-primary before:content-['']",
        !active && item.href && 'text-sidebar-foreground/65 hover:bg-white/5 hover:text-white',
        !item.href && 'cursor-not-allowed text-sidebar-foreground/30',
    );

    const content = (
        <>
            <item.icon className="size-4 shrink-0" aria-hidden />
            <span className="truncate">{item.label}</span>
        </>
    );

    if (!item.href) {
        return (
            <span className={className} title="Belum diimplementasikan">
                {content}
            </span>
        );
    }

    return (
        <Link href={item.href} className={className}>
            {content}
        </Link>
    );
}

export default function AuthenticatedLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, flash, ziggy, notifications } = usePage().props;
    const { can } = usePermission();
    const currentPath = new URL(ziggy.location).pathname;
    const unread = notifications?.unread ?? 0;

    // Item tanpa permission selalu tampil; sisanya mengikuti kewenangan user.
    // Ini hanya menyembunyikan menu — otorisasi sesungguhnya ada di Policy server.
    const groups = NAVIGATION.map((group) => ({
        ...group,
        items: group.items.filter((item) => !item.permission || can(item.permission)),
    })).filter((group) => group.items.length > 0);

    // Href terpanjang yang cocok yang menang, sehingga '/items/create' menyalakan
    // "Add New Items" saja — bukan sekaligus "Data List Items" yang prefiksnya juga cocok.
    const activeHref =
        NAVIGATION.flatMap((group) => group.items)
            .map((item) => item.href)
            .filter((href): href is string => href !== null)
            .filter((href) =>
                href === '/'
                    ? currentPath === '/'
                    : currentPath === href || currentPath.startsWith(`${href}/`),
            )
            .sort((a, b) => b.length - a.length)[0] ?? null;

    return (
        <div className="flex min-h-full">
            <aside className="sidebar-surface hidden w-64 shrink-0 border-r border-sidebar-border bg-sidebar text-sidebar-foreground lg:flex lg:flex-col">
                <div className="flex h-16 items-center gap-2.5 border-b border-sidebar-border px-5">
                    <Link
                        href="/"
                        className="flex items-center rounded-md bg-white px-2 py-1"
                        aria-label="Beranda"
                    >
                        <InalumLogo className="h-6 w-auto" />
                    </Link>
                    <span className="rounded-md bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-sidebar-foreground/80 uppercase">
                        Stationery
                    </span>
                </div>

                <nav className="flex-1 space-y-6 overflow-y-auto p-4">
                    {groups.map((group, index) => (
                        <div key={group.heading ?? `group-${index}`} className="space-y-1">
                            {group.heading && (
                                <p className="px-3 pb-1 text-xs font-medium tracking-wide text-sidebar-foreground/45 uppercase">
                                    {group.heading}
                                </p>
                            )}
                            {group.items.map((item) => (
                                <NavLink
                                    key={item.label}
                                    item={item}
                                    active={item.href !== null && item.href === activeHref}
                                />
                            ))}
                        </div>
                    ))}
                </nav>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-16 items-center justify-between gap-4 border-b bg-card px-6">
                    <div className="min-w-0">{header}</div>

                    <div className="flex items-center gap-3">
                        <Link
                            href="/notifications"
                            className="relative rounded-md p-2 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            title="Notifikasi"
                            aria-label={`Notifikasi${unread > 0 ? ` (${unread} belum dibaca)` : ''}`}
                        >
                            <Bell className="size-5" aria-hidden />
                            {unread > 0 && (
                                <motion.span
                                    initial={{ scale: 0 }}
                                    animate={{ scale: 1 }}
                                    transition={{ type: 'spring', stiffness: 500, damping: 18 }}
                                    className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold text-destructive-foreground"
                                >
                                    {unread > 99 ? '99+' : unread}
                                </motion.span>
                            )}
                        </Link>

                        <div className="hidden text-right text-sm leading-tight sm:block">
                            <p className="font-medium">{auth.user?.name ?? 'Belum login'}</p>
                            <p className="text-xs text-muted-foreground">
                                {auth.user?.department ?? '—'}
                                {auth.roles.length > 0 && ` · ${auth.roles.join(', ')}`}
                            </p>
                        </div>

                        {auth.user && (
                            <div
                                className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                title={auth.user.name}
                                aria-hidden
                            >
                                {initials(auth.user.name)}
                            </div>
                        )}

                        {auth.user && (
                            <button
                                type="button"
                                onClick={() => router.post('/logout')}
                                title="Keluar"
                                className="rounded-md p-2 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            >
                                <LogOut className="size-4" aria-hidden />
                            </button>
                        )}
                    </div>
                </header>

                <FlashToast flash={flash} />

                <main className="flex-1 p-6">
                    {/* Entrance transform-only (opasitas tetap 1) agar konten tak
                        pernah tak terlihat bila animasi tertunda; halaman berisi
                        tabel tetap aman. Efek fade yang lebih kaya ada per-halaman. */}
                    <motion.div
                        initial={{ y: 8 }}
                        animate={{ y: 0 }}
                        transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
                    >
                        {children}
                    </motion.div>
                </main>
            </div>
        </div>
    );
}
