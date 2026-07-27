import { usePage } from '@inertiajs/react';
import { SharedData } from '@/types';

export function usePermission() {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth?.permissions || [];
    const roles = auth?.roles || [];

    const can = (permission: string): boolean => {
        return permissions.includes(permission);
    };

    const hasRole = (role: string): boolean => {
        return roles.includes(role);
    };

    const hasAnyPermission = (permissionList: string[]): boolean => {
        return permissionList.some(permission => permissions.includes(permission));
    };

    const hasAllPermissions = (permissionList: string[]): boolean => {
        return permissionList.every(permission => permissions.includes(permission));
    };

    return {
        can,
        hasRole,
        hasAnyPermission,
        hasAllPermissions,
        permissions,
        roles,
    };
}
