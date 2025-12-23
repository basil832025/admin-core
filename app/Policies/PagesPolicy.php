<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\Pages;
use Illuminate\Auth\Access\HandlesAuthorization;

class PagesPolicy
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

        return $user->can('view_any_pages');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, Pages $pages): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('view_pages');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('create_pages');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, Pages $pages): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('update_pages');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, Pages $pages): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('delete_pages');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('delete_any_pages');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(Authenticatable $user, Pages $pages): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('force_delete_pages');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('force_delete_any_pages');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(Authenticatable $user, Pages $pages): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('restore_pages');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('restore_any_pages');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(Authenticatable $user, Pages $pages): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('replicate_pages');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('reorder_pages');
    }
}