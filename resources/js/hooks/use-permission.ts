import { usePage } from '@inertiajs/react';

/**
 * Akses kewenangan pengguna yang sedang login.
 *
 * PERINGATAN: hook ini hanya untuk MENYEMBUNYIKAN elemen UI yang tidak relevan.
 * Ini bukan kontrol keamanan — siapa pun dapat memanggil endpoint secara langsung
 * tanpa melewati React. Setiap action yang mengubah state WAJIB diperiksa ulang
 * oleh Policy di sisi server (§5.2 Architecture Blueprint).
 */
export function usePermission() {
    const { auth } = usePage().props;

    const can = (permission: string): boolean => auth.permissions.includes(permission);

    const canAny = (...permissions: string[]): boolean =>
        permissions.some((permission) => auth.permissions.includes(permission));

    const canAll = (...permissions: string[]): boolean =>
        permissions.every((permission) => auth.permissions.includes(permission));

    const hasRole = (role: string): boolean => auth.roles.includes(role);

    return {
        can,
        canAny,
        canAll,
        hasRole,
        isAuthenticated: auth.user !== null,
        user: auth.user,
        roles: auth.roles,
    };
}
