import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import type { DepartmentOption, ManagerOption, Option } from '@/types';
import { Head } from '@inertiajs/react';
import UserForm from './UserForm';

interface EditableUser {
    id: number;
    employee_id: string;
    username: string;
    name: string;
    email: string;
    department_id: number | null;
    position: string | null;
    manager_id: number | null;
    is_active: boolean;
    roles: string[];
}

interface Props {
    user: EditableUser;
    departments: DepartmentOption[];
    managers: ManagerOption[];
    positions: Option[];
    availableRoles: Option[];
}

export default function Edit({ user, departments, managers, positions, availableRoles }: Props) {
    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Ubah User</h1>}>
            <Head title={`Ubah ${user.name}`} />

            <UserForm
                initial={{
                    employee_id: user.employee_id,
                    username: user.username,
                    name: user.name,
                    email: user.email,
                    password: '',
                    password_confirmation: '',
                    department_id: user.department_id ? String(user.department_id) : '',
                    position: user.position ?? '',
                    manager_id: user.manager_id ? String(user.manager_id) : '',
                    is_active: user.is_active,
                    roles: user.roles.filter((r) => r !== 'requester'),
                }}
                departments={departments}
                managers={managers}
                positions={positions}
                availableRoles={availableRoles}
                action={`/admin/users/${user.id}`}
                method="put"
                submitLabel="Simpan Perubahan"
            />
        </AuthenticatedLayout>
    );
}
