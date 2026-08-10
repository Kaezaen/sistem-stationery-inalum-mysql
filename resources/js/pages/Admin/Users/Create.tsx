import AuthenticatedLayout from '@/components/layouts/AuthenticatedLayout';
import type { DepartmentOption, ManagerOption, Option } from '@/types';
import { Head } from '@inertiajs/react';
import UserForm from './UserForm';

interface Props {
    departments: DepartmentOption[];
    managers: ManagerOption[];
    positions: Option[];
    availableRoles: Option[];
}

export default function Create({ departments, managers, positions, availableRoles }: Props) {
    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-semibold">Tambah User</h1>}>
            <Head title="Tambah User" />

            <UserForm
                initial={{
                    employee_id: '',
                    username: '',
                    name: '',
                    email: '',
                    password: '',
                    password_confirmation: '',
                    department_id: '',
                    position: '',
                    manager_id: '',
                    is_active: true,
                    roles: [],
                }}
                departments={departments}
                managers={managers}
                positions={positions}
                availableRoles={availableRoles}
                action="/admin/users"
                method="post"
                submitLabel="Simpan User"
            />
        </AuthenticatedLayout>
    );
}
