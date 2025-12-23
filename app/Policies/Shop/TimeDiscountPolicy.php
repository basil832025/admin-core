<?php

namespace App\Policies\Shop;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\Shop\TimeDiscount;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimeDiscountPolicy
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

        return $user->can('view_any_shop::time::discount');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, TimeDiscount $timeDiscount): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('view_shop::time::discount');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('create_shop::time::discount');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, TimeDiscount $timeDiscount): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('update_shop::time::discount');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, TimeDiscount $timeDiscount): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('delete_shop::time::discount');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('delete_any_shop::time::discount');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(Authenticatable $user, TimeDiscount $timeDiscount): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('force_delete_shop::time::discount');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('force_delete_any_shop::time::discount');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(Authenticatable $user, TimeDiscount $timeDiscount): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('restore_shop::time::discount');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('restore_any_shop::time::discount');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(Authenticatable $user, TimeDiscount $timeDiscount): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('replicate_shop::time::discount');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('reorder_shop::time::discount');
    }
}