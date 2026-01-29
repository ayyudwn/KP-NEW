<?php

namespace App\Traits;

use App\Models\Laboratorium;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

trait HasLabPermissions
{
    /**
     * Check if user has permission to access specific lab
     *
     * @param string|int $lab ID or ruang of the lab
     * @param string $action Action (view, manage, edit, delete)
     * @return bool
     */
    public function hasLabPermission($lab, string $action = 'view'): bool
    {
        // Super admin can access everything
        if ($this->hasRole('super_admin')) {
            return true;
        }

        if (is_numeric($lab)) {
            // If lab ID is provided, get the lab ruang
            $laboratory = Laboratorium::find($lab);
            if (!$laboratory) {
                return false;
            }
            $labName = $laboratory->ruang;
        } else {
            // Lab name was provided directly
            $labName = $lab;
        }

        $labSlug = strtolower(str_replace([' ', '.'], ['_', '_'], $labName));
        // Format: lab_{slug}_{action}
        $permissionName = "lab_{$labSlug}_{$action}";

        return $this->can($permissionName);
    }

    /**
     * Get all labs the user has permission to access
     *
     * @param string $action Action (view, manage, edit, delete)
     * @return array
     */
    public function getAuthorizedLabIds(string $action = 'view'): array
    {
        // Force refresh the cache for testing
        $cacheKey = "user_{$this->id}_lab_permissions_{$action}";
        Cache::forget($cacheKey);

        // Cache the results for a shorter time during testing (5 minutes)
        return Cache::remember($cacheKey, 300, function () use ($action) {
            // Super admin can access all labs
            if ($this->hasRole('super_admin')) {
                return Laboratorium::pluck('id')->toArray();
            }

            $authorizedLabs = [];

            // Get all permissions for this user
            $userPermissions = $this->getAllPermissions()->pluck('name')->toArray();

            // Get all labs
            $laboratories = Laboratorium::all();

            // For each lab, check if the user has the specific permission
            foreach ($laboratories as $lab) {
                $labSlug = strtolower(str_replace([' ', '.'], ['_', '_'], $lab->ruang));
                // Format: lab_{slug}_{action}
                $permissionName = "lab_{$labSlug}_{$action}";

                // If the user has this permission (through any role), add the lab
                if (in_array($permissionName, $userPermissions)) {
                    $authorizedLabs[] = $lab->id;
                }
            }

            return $authorizedLabs;
        });
    }

    /**
     * Apply lab permission filter to a query
     *
     * @param Builder $query
     * @param string $labIdField
     * @param string $action
     * @return Builder
     */
    public function scopeFilterByLabPermission(Builder $query, string $labIdField = 'laboratorium_id', string $action = 'view'): Builder
    {
        // Super admin has access to everything
        if ($this->hasRole('super_admin')) {
            return $query;
        }

        $authorizedLabIds = $this->getAuthorizedLabIds($action);

        return $query->whereIn($labIdField, $authorizedLabIds);
    }
}
