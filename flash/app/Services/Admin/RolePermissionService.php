<?php

namespace App\Services\Admin;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    // ──────────────────────────────────────────────
    //  ROLES — LIST
    // ──────────────────────────────────────────────

    public function listRoles(array $filters = []): Collection
    {
        $query = Role::query();

        // Always load permissions relation for list view
        $query->with('permissions:id,name,slug,group');

        // Counts
        $withCounts = ! empty($filters['with_counts']);
        if ($withCounts) {
            $query->withCount(['permissions', 'users']);
        }

        // Search by name or slug
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('slug', 'LIKE', "%{$term}%");
            });
        }

        // Sorting
        $sortBy  = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'asc';

        // For count-based sorting, always include withCount
        if (in_array($sortBy, ['users_count', 'permissions_count']) && ! $withCounts) {
            $query->withCount(['permissions', 'users']);
        }

        $query->orderBy($sortBy, $sortDir);

        return $query->get();
    }

    // ──────────────────────────────────────────────
    //  ROLES — SHOW DETAIL
    // ──────────────────────────────────────────────

    public function getRoleDetail(int $roleId): Role
    {
        return Role::with([
                'permissions',
                'users' => fn ($q) => $q->select('users.id', 'name', 'email', 'avatar', 'status')
                    ->latest('users.created_at')
                    ->limit(10),
            ])
            ->withCount(['permissions', 'users'])
            ->findOrFail($roleId);
    }

    // ──────────────────────────────────────────────
    //  ROLES — CREATE
    // ──────────────────────────────────────────────

    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            // If this role is set as default, unset existing defaults
            if (! empty($data['is_default'])) {
                Role::where('is_default', true)->update(['is_default' => false]);
            }

            $role = Role::create([
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_default'  => $data['is_default'] ?? false,
            ]);

            // Attach permissions if provided
            if (! empty($data['permissions'])) {
                $role->permissions()->sync($data['permissions']);
            }

            $role->load('permissions');
            $role->loadCount(['permissions', 'users']);

            return $role;
        });
    }

    // ──────────────────────────────────────────────
    //  ROLES — UPDATE
    // ──────────────────────────────────────────────

    public function updateRole(int $roleId, array $data): Role
    {
        return DB::transaction(function () use ($roleId, $data) {
            $role = Role::findOrFail($roleId);

            // Guard: prevent modifying the core 'admin' role slug
            if ($role->slug === 'admin' && isset($data['slug']) && $data['slug'] !== 'admin') {
                abort(422, 'Cannot change the slug of the core admin role.');
            }

            // If this role is becoming default, unset existing defaults
            if (! empty($data['is_default']) && ! $role->is_default) {
                Role::where('is_default', true)->update(['is_default' => false]);
            }

            $fillable = ['name', 'slug', 'description', 'is_default'];
            $updateData = array_intersect_key($data, array_flip($fillable));

            $role->update($updateData);

            // Sync permissions if provided
            if (array_key_exists('permissions', $data)) {
                $role->permissions()->sync($data['permissions'] ?? []);
            }

            $role->load('permissions');
            $role->loadCount(['permissions', 'users']);

            return $role;
        });
    }

    // ──────────────────────────────────────────────
    //  ROLES — DELETE
    // ──────────────────────────────────────────────

    public function deleteRole(int $roleId): void
    {
        $role = Role::withCount('users')->findOrFail($roleId);

        // Guard: prevent deleting the core admin role
        if ($role->slug === 'admin') {
            abort(422, 'Cannot delete the core admin role.');
        }

        // Guard: prevent deleting a role that still has users
        if ($role->users_count > 0) {
            abort(422, "Cannot delete role \"{$role->name}\" because it still has {$role->users_count} user(s) assigned. Reassign them first.");
        }

        DB::transaction(function () use ($role) {
            // Remove all permission associations
            $role->permissions()->detach();

            $role->delete();
        });
    }

    // ──────────────────────────────────────────────
    //  ROLES — ASSIGN PERMISSIONS
    // ──────────────────────────────────────────────

    public function assignPermissions(int $roleId, array $permissionIds): Role
    {
        $role = Role::findOrFail($roleId);

        $role->permissions()->sync($permissionIds);

        $role->load('permissions');
        $role->loadCount(['permissions', 'users']);

        return $role;
    }

    // ──────────────────────────────────────────────
    //  PERMISSIONS — LIST ALL
    // ──────────────────────────────────────────────

    public function listPermissions(): Collection
    {
        return Permission::orderBy('group')
            ->orderBy('name')
            ->get();
    }

    // ──────────────────────────────────────────────
    //  PERMISSIONS — LIST GROUPED
    // ──────────────────────────────────────────────

    public function listPermissionsGrouped(): array
    {
        $permissions = Permission::orderBy('group')
            ->orderBy('name')
            ->get();

        return $permissions
            ->groupBy('group')
            ->map(fn ($perms, $group) => [
                'group'       => $group ?: 'general',
                'permissions' => $perms->map(fn ($p) => [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'slug'        => $p->slug,
                    'description' => $p->description,
                ])->values(),
            ])
            ->values()
            ->toArray();
    }

    // ──────────────────────────────────────────────
    //  PERMISSIONS — CREATE
    // ──────────────────────────────────────────────

    public function createPermission(array $data): Permission
    {
        return Permission::create([
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'group'       => $data['group'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
    }

    // ──────────────────────────────────────────────
    //  PERMISSIONS — UPDATE
    // ──────────────────────────────────────────────

    public function updatePermission(int $permissionId, array $data): Permission
    {
        $permission = Permission::findOrFail($permissionId);

        $fillable = ['name', 'slug', 'group', 'description'];
        $updateData = array_intersect_key($data, array_flip($fillable));

        $permission->update($updateData);

        return $permission;
    }

    // ──────────────────────────────────────────────
    //  PERMISSIONS — DELETE
    // ──────────────────────────────────────────────

    public function deletePermission(int $permissionId): void
    {
        $permission = Permission::withCount('roles')->findOrFail($permissionId);

        // Warn if permission is still assigned to roles
        if ($permission->roles_count > 0) {
            abort(422, "Cannot delete permission \"{$permission->name}\" because it is assigned to {$permission->roles_count} role(s). Remove it from all roles first.");
        }

        $permission->delete();
    }

    // ──────────────────────────────────────────────
    //  OVERVIEW / MATRIX
    // ──────────────────────────────────────────────

    /**
     * Return a permission matrix: for each role, which permissions are enabled.
     * Useful for the admin UI to display a role-permission grid.
     */
    public function getPermissionMatrix(): array
    {
        $roles = Role::with('permissions:id,slug')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $allPermissions = Permission::orderBy('group')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'group']);

        $matrix = [];

        foreach ($roles as $role) {
            $rolePermSlugs = $role->permissions->pluck('slug')->toArray();

            $matrix[] = [
                'role' => [
                    'id'          => $role->id,
                    'name'        => $role->name,
                    'slug'        => $role->slug,
                    'users_count' => $role->users_count,
                ],
                'permissions' => $allPermissions->map(fn ($p) => [
                    'id'      => $p->id,
                    'slug'    => $p->slug,
                    'name'    => $p->name,
                    'group'   => $p->group,
                    'enabled' => in_array($p->slug, $rolePermSlugs),
                ])->toArray(),
            ];
        }

        return [
            'roles'       => $matrix,
            'permissions' => $allPermissions->toArray(),
        ];
    }
}
