<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder creates all necessary roles and permissions for the SIOPAL system.
     * Run this BEFORE UserSeeder.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions
        $permissions = [
            // Resource permissions
            'view_any_laboratorium',
            'view_laboratorium',
            'create_laboratorium',
            'update_laboratorium',
            'delete_laboratorium',
            'delete_any_laboratorium',

            'view_any_course',
            'view_course',
            'create_course',
            'update_course',
            'delete_course',
            'delete_any_course',

            'view_any_schedule',
            'view_schedule',
            'create_schedule',
            'update_schedule',
            'delete_schedule',
            'delete_any_schedule',

            'view_any_prodi',
            'view_prodi',
            'create_prodi',
            'update_prodi',
            'delete_prodi',

            'view_any_dosen',
            'view_dosen',
            'create_dosen',
            'update_dosen',
            'delete_dosen',

            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'delete_user',

            'view_any_role',
            'view_role',
            'create_role',
            'update_role',
            'delete_role',

            // Page permissions
            'page_Dashboard',
            'page_ScheduleWizard',
            'page_ScheduleTimetable',

            // Widget permissions
            'widget_StatsOverview',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles with permissions

        // Super Admin - has all permissions via Gate::before in AuthServiceProvider
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Admin - has most permissions except role management
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(array_filter($permissions, function ($p) {
            return !str_contains($p, 'role') && !str_contains($p, 'user');
        }));

        // Laboran - can manage lab schedules and view data
        $laboran = Role::firstOrCreate(['name' => 'laboran', 'guard_name' => 'web']);
        $laboran->syncPermissions([
            'view_any_laboratorium',
            'view_laboratorium',
            'view_any_course',
            'view_course',
            'view_any_schedule',
            'view_schedule',
            'create_schedule',
            'update_schedule',
            'view_any_prodi',
            'view_prodi',
            'view_any_dosen',
            'view_dosen',
            'page_Dashboard',
            'page_ScheduleWizard',
            'page_ScheduleTimetable',
        ]);

        // Dosen - read-only access to schedules
        $dosen = Role::firstOrCreate(['name' => 'dosen', 'guard_name' => 'web']);
        $dosen->syncPermissions([
            'view_any_schedule',
            'view_schedule',
            'view_any_course',
            'view_course',
            'view_any_laboratorium',
            'view_laboratorium',
            'page_Dashboard',
            'page_ScheduleTimetable',
        ]);

        // Panel User - basic access
        $panelUser = Role::firstOrCreate(['name' => 'panel_user', 'guard_name' => 'web']);
        $panelUser->syncPermissions([
            'page_Dashboard',
        ]);

        $this->command->info('Roles and Permissions seeded successfully!');
        $this->command->table(
            ['Role', 'Permissions Count'],
            [
                ['super_admin', 'ALL (via Gate)'],
                ['admin', $admin->permissions->count()],
                ['laboran', $laboran->permissions->count()],
                ['dosen', $dosen->permissions->count()],
                ['panel_user', $panelUser->permissions->count()],
            ]
        );
    }
}
