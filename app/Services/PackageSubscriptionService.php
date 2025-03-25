<?php

namespace App\Services;

use App\Models\User;
use App\Models\Package;
use Illuminate\Support\Facades\DB;

class PackageSubscriptionService
{
    protected $domainConnectionService;

    public function __construct(DomainConnectionService $domainConnectionService)
    {
        $this->domainConnectionService = $domainConnectionService;
    }

    /**
     * Assign package role to a user when they subscribe to a package
     *
     * @param User $user
     * @param int $packageId
     * @return bool
     */
    public function assignPackageRoleToUser(User $user, $packageId)
    {
        $db = $this->domainConnectionService->getDomainConnection();

        // Get package
        $package = $db->table('packages')
            ->where('package_id', $packageId)
            ->first();

        if (!$package) {
            return false;
        }

        // Get permissions JSON and extract role_id
        $permissions = json_decode($package->permissions ?? '{}', true) ?: [];
        $roleId = $permissions['role_id'] ?? null;

        if (!$roleId) {
            return false; // No role defined for this package
        }

        // Remove any existing package roles from the user
        $this->removeExistingPackageRoles($db, $user);

        // Assign the new package role to the user
        $db->table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => 'App\\Models\\User',
            'model_id' => $user->id
        ]);

        return true;
    }

    /**
     * Remove existing package roles from a user
     *
     * @param \Illuminate\Database\Connection $db
     * @param User $user
     */
    protected function removeExistingPackageRoles($db, User $user)
    {
        // Get all package role IDs
        $packageRoleIds = $db->table('packages')
            ->whereNotNull('permissions')
            ->get()
            ->pluck('permissions')
            ->filter()
            ->map(function ($permissions) {
                $decoded = json_decode($permissions, true) ?: [];
                return $decoded['role_id'] ?? null;
            })
            ->filter()
            ->toArray();

        if (empty($packageRoleIds)) {
            return;
        }

        // Remove these roles from the user
        $db->table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', 'App\\Models\\User')
            ->whereIn('role_id', $packageRoleIds)
            ->delete();
    }

    /**
     * Remove package role from a user when they unsubscribe
     *
     * @param User $user
     * @param int $packageId
     * @return bool
     */
    public function removePackageRoleFromUser(User $user, $packageId)
    {
        $db = $this->domainConnectionService->getDomainConnection();

        // Get package
        $package = $db->table('packages')
            ->where('package_id', $packageId)
            ->first();

        if (!$package) {
            return false;
        }

        // Get permissions JSON and extract role_id
        $permissions = json_decode($package->permissions ?? '{}', true) ?: [];
        $roleId = $permissions['role_id'] ?? null;

        if (!$roleId) {
            return false; // No role defined for this package
        }

        // Remove the package role from the user
        $db->table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', 'App\\Models\\User')
            ->where('model_id', $user->id)
            ->delete();

        return true;
    }
}
