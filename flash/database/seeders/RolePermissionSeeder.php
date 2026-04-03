<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ──────────────────────────────────────────────
        //  Create Permissions (grouped by module)
        // ──────────────────────────────────────────────

        $permissionsData = [
            // Users
            ['name' => 'View Users',          'slug' => 'view_users',          'group' => 'users',          'description' => 'View all users and user details'],
            ['name' => 'Create Users',         'slug' => 'create_users',        'group' => 'users',          'description' => 'Create new user accounts'],
            ['name' => 'Edit Users',           'slug' => 'edit_users',          'group' => 'users',          'description' => 'Edit existing user profiles and settings'],
            ['name' => 'Delete Users',         'slug' => 'delete_users',        'group' => 'users',          'description' => 'Archive or permanently delete users'],
            ['name' => 'Reset User Password',  'slug' => 'reset_user_password', 'group' => 'users',          'description' => 'Reset passwords for any user'],

            // Roles & Permissions
            ['name' => 'View Roles',           'slug' => 'view_roles',          'group' => 'roles',          'description' => 'View roles and assigned permissions'],
            ['name' => 'Manage Roles',         'slug' => 'manage_roles',        'group' => 'roles',          'description' => 'Create, edit, and delete roles'],
            ['name' => 'Assign Permissions',   'slug' => 'assign_permissions',  'group' => 'roles',          'description' => 'Assign or revoke permissions on roles'],

            // Plans & Subscriptions
            ['name' => 'View Plans',           'slug' => 'view_plans',          'group' => 'plans',          'description' => 'View subscription plans and features'],
            ['name' => 'Manage Plans',         'slug' => 'manage_plans',        'group' => 'plans',          'description' => 'Create, edit, and archive plans'],
            ['name' => 'View Subscriptions',   'slug' => 'view_subscriptions',  'group' => 'subscriptions',  'description' => 'View user subscriptions'],
            ['name' => 'Manage Subscriptions', 'slug' => 'manage_subscriptions','group' => 'subscriptions',  'description' => 'Cancel, pause, or modify subscriptions'],

            // Payments
            ['name' => 'View Payments',        'slug' => 'view_payments',       'group' => 'payments',       'description' => 'View payment transactions and invoices'],
            ['name' => 'Manage Payments',      'slug' => 'manage_payments',     'group' => 'payments',       'description' => 'Process refunds and manage disputes'],

            // AI Requests
            ['name' => 'View AI Requests',     'slug' => 'view_ai_requests',    'group' => 'ai_requests',    'description' => 'View AI generation requests and logs'],
            ['name' => 'Manage AI Requests',   'slug' => 'manage_ai_requests',  'group' => 'ai_requests',    'description' => 'Cancel or retry AI requests'],

            // Generated Images
            ['name' => 'View Images',          'slug' => 'view_images',         'group' => 'images',         'description' => 'View generated images gallery'],
            ['name' => 'Manage Images',        'slug' => 'manage_images',       'group' => 'images',         'description' => 'Delete or flag images (NSFW, public)'],

            // Support Tickets
            ['name' => 'View Tickets',         'slug' => 'view_tickets',        'group' => 'support',        'description' => 'View support tickets'],
            ['name' => 'Manage Tickets',       'slug' => 'manage_tickets',      'group' => 'support',        'description' => 'Reply to and close support tickets'],

            // Settings
            ['name' => 'View Settings',        'slug' => 'view_settings',       'group' => 'settings',       'description' => 'View platform settings'],
            ['name' => 'Manage Settings',      'slug' => 'manage_settings',     'group' => 'settings',       'description' => 'Modify platform settings and configuration'],

            // Notifications
            ['name' => 'View Notifications',   'slug' => 'view_notifications',  'group' => 'notifications',  'description' => 'View system notifications'],
            ['name' => 'Send Notifications',   'slug' => 'send_notifications',  'group' => 'notifications',  'description' => 'Send broadcasts and admin notifications'],

            // Dashboard
            ['name' => 'View Dashboard',       'slug' => 'view_dashboard',      'group' => 'dashboard',      'description' => 'Access the admin dashboard overview'],

            // Coupons
            ['name' => 'View Coupons',         'slug' => 'view_coupons',        'group' => 'coupons',        'description' => 'View discount coupons'],
            ['name' => 'Manage Coupons',       'slug' => 'manage_coupons',      'group' => 'coupons',        'description' => 'Create, edit, and delete coupons'],
        ];

        $permissions = [];
        foreach ($permissionsData as $pData) {
            $permissions[$pData['slug']] = Permission::firstOrCreate(
                ['slug' => $pData['slug']],
                $pData
            );
        }

        // ──────────────────────────────────────────────
        //  Ensure Roles Exist
        // ──────────────────────────────────────────────

        $admin = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Full platform access — all permissions', 'is_default' => false]
        );

        $support = Role::firstOrCreate(
            ['slug' => 'support'],
            ['name' => 'Support', 'description' => 'Support team — tickets, AI requests, and limited user views', 'is_default' => false]
        );

        $user = Role::firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'description' => 'Standard platform user — no admin panel access', 'is_default' => true]
        );

        // ──────────────────────────────────────────────
        //  Assign Permissions to Roles
        // ──────────────────────────────────────────────

        // Admin → ALL permissions
        $admin->permissions()->sync(
            collect($permissions)->pluck('id')->toArray()
        );

        // Support → limited: view users, view/manage tickets, view/manage AI requests, view images, view dashboard
        $support->permissions()->sync([
            $permissions['view_users']->id,
            $permissions['view_ai_requests']->id,
            $permissions['manage_ai_requests']->id,
            $permissions['view_images']->id,
            $permissions['view_tickets']->id,
            $permissions['manage_tickets']->id,
            $permissions['view_dashboard']->id,
            $permissions['view_notifications']->id,
        ]);

        // User → no admin permissions (empty)
        $user->permissions()->sync([]);
    }
}
