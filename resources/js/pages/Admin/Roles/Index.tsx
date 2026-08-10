import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import StatusBadge from '@/components/shared/StatusBadge';
import { Head } from '@inertiajs/react';
import { Lock } from 'lucide-react';

interface RoleRow {
    value: string;
    label: string;
    description: string;
    permissions: string[];
    users_count: number;
}

interface Props {
    roles: RoleRow[];
    allPermissions: string[];
}

export default function Index({ roles, allPermissions }: Props) {
    return (
        <AuthenticatedLayout
            header={<h1 className="text-xl font-semibold">Role &amp; Permission</h1>}
        >
            <Head title="Role & Permission" />

            <div className="mb-4 flex gap-3 rounded-lg border bg-card p-4">
                <Lock className="size-5 shrink-0 text-muted-foreground" aria-hidden />
                <div className="text-sm text-muted-foreground">
                    <p>
                        Matriks ini bersifat <strong>read-only</strong>. Role dan permission adalah
                        turunan langsung matriks §5.1 dokumen arsitektur, dikunci di kode dan
                        diterapkan oleh seeder.
                    </p>
                    <p className="mt-1">
                        Perubahan kewenangan dilakukan lewat perubahan kode agar tercatat di version
                        control — bukan lewat UI yang tidak meninggalkan jejak audit.
                    </p>
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                {roles.map((role) => (
                    <div key={role.value} className="rounded-lg border bg-card p-5">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h2 className="font-medium">{role.label}</h2>
                                <code className="font-mono text-xs text-muted-foreground">
                                    {role.value}
                                </code>
                            </div>
                            <StatusBadge>{`${role.users_count} user`}</StatusBadge>
                        </div>

                        <p className="mt-2 text-sm text-muted-foreground">{role.description}</p>

                        <div className="mt-4">
                            <p className="mb-2 text-xs tracking-wide text-muted-foreground uppercase">
                                {role.permissions.length} permission
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {role.permissions.map((permission) => (
                                    <code
                                        key={permission}
                                        className="rounded bg-secondary px-1.5 py-0.5 font-mono text-xs"
                                    >
                                        {permission}
                                    </code>
                                ))}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <p className="mt-4 text-sm text-muted-foreground">
                Total {allPermissions.length} permission terdaftar di sistem.
            </p>
        </AuthenticatedLayout>
    );
}
