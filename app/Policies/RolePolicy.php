<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('view_any_role');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, Role $role): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('view_role');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('create_role');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, Role $role): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('update_role');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, Role $role): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('delete_role');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('delete_any_role');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(Authenticatable $user, Role $role): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('force_delete_role');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('force_delete_any_role');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(Authenticatable $user, Role $role): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('restore_role');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('restore_any_role');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(Authenticatable $user, Role $role): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('replicate_role');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('reorder_role');
    }
}
