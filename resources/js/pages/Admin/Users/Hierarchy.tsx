import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import StatusBadge from '@/components/shared/StatusBadge';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

interface Node {
    id: number;
    name: string;
    employee_id: string;
    position: string | null;
    department: string | null;
    manager_id: number | null;
    is_active: boolean;
}

interface Props {
    nodes: Node[];
    orphans: string[];
}

function Branch({ node, all, depth }: { node: Node; all: Node[]; depth: number }) {
    const children = all.filter((n) => n.manager_id === node.id);

    return (
        <li>
            <div
                className="flex flex-wrap items-center gap-2 rounded-md px-2 py-1.5 hover:bg-secondary/50"
                style={{ marginLeft: depth * 20 }}
            >
                <Link
                    href={`/admin/users/${node.id}/edit`}
                    className="text-sm font-medium underline-offset-4 hover:underline"
                >
                    {node.name}
                </Link>
                <span className="font-mono text-xs text-muted-foreground">{node.employee_id}</span>
                {node.position && <StatusBadge>{node.position}</StatusBadge>}
                {node.department && (
                    <span className="text-xs text-muted-foreground">{node.department}</span>
                )}
                {!node.is_active && <StatusBadge tone="danger">Non-aktif</StatusBadge>}
                {children.length > 0 && (
                    <span className="text-xs text-muted-foreground">
                        · {children.length} bawahan
                    </span>
                )}
            </div>

            {children.length > 0 && (
                <ul className="border-l border-dashed">
                    {children.map((child) => (
                        <Branch key={child.id} node={child} all={all} depth={depth + 1} />
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function Hierarchy({ nodes, orphans }: Props) {
    const roots = nodes.filter((n) => n.manager_id === null);
    const noManagerCount = orphans.length;

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Hierarki Atasan</h1>}>
            <Head title="Hierarki Atasan" />

            <div className="mb-4 rounded-lg border bg-card p-4">
                <p className="text-sm text-muted-foreground">
                    Approval Level 1 diarahkan ke <strong>atasan langsung</strong> requester.
                    Periksa struktur ini bersama HR sebelum sistem dipakai — kesalahan pemetaan
                    membuat request mandek di tangan orang yang salah.
                </p>
            </div>

            {noManagerCount > 1 && (
                <div className="mb-4 flex gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
                    <AlertTriangle className="size-5 shrink-0 text-amber-600" aria-hidden />
                    <div className="text-sm">
                        <p className="font-medium text-amber-900 dark:text-amber-200">
                            {noManagerCount} user tidak punya atasan
                        </p>
                        <p className="mt-1 text-amber-800 dark:text-amber-300">
                            Request mereka tidak akan menemukan approver Level 1. Umumnya hanya
                            pucuk organisasi yang boleh tanpa atasan.
                        </p>
                        <p className="mt-2 text-xs text-amber-700 dark:text-amber-400">
                            {orphans.join(', ')}
                        </p>
                    </div>
                </div>
            )}

            <div className="rounded-lg border bg-card p-4">
                {roots.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">Belum ada data user.</p>
                ) : (
                    <ul>
                        {roots.map((root) => (
                            <Branch key={root.id} node={root} all={nodes} depth={0} />
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
