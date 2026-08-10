import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    employee_id: string;
    department: string | null;
}

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface Option {
    value: string;
    label: string;
    description?: string;
}

export interface DepartmentOption {
    id: number;
    code: string;
    name: string;
}

export interface ManagerOption {
    id: number;
    name: string;
    employee_id: string;
}

export interface SharedProps {
    auth: {
        user: AuthUser | null;
        /** Slug role, mis. 'pic_stationery'. Diisi modul Identity pada Fase 1. */
        roles: string[];
        /** Slug permission, mis. 'request.approve.l2'. Untuk menyembunyikan UI saja —
         *  penegakan sesungguhnya ada di Policy sisi server. */
        permissions: string[];
    };
    notifications: {
        unread: number;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    ziggy: {
        location: string;
        [key: string]: unknown;
    };
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, SharedProps {}
}
