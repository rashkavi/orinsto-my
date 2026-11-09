<?php

class UserPermissions
{
    private static array $userPermissions = [];

    public static function setUserPermissions(array $userPermissions): void
    {
        self::$userPermissions = $userPermissions;
    }

    public static function hasPermission(
        string $type,
        int $permissionId,
        ?int $permission_type_related_id = null
    ): bool {
        foreach (self::$userPermissions as $perm) {
            if (
                isset($perm->permission_type, $perm->permission_id) &&
                $perm->permission_type === $type &&
                (int)$perm->permission_id === $permissionId &&
                (
                    $permission_type_related_id === null ||
                    (int)($perm->permission_type_related_id ?? 0) === $permission_type_related_id
                )
            ) {
                return true;
            }
        }

        return false;
    }

    public static function validPermisisonType(
        string $type,
        ?int $permission_type_related_id = null
    ): bool {
        foreach (self::$userPermissions as $perm) {
            if (
                isset($perm->permission_type) &&
                $perm->permission_type === $type &&
                (
                    $permission_type_related_id === null ||
                    (int)($perm->permission_type_related_id ?? 0) === $permission_type_related_id
                )
            ) {
                return true;
            }
        }

        return false;
    }
}