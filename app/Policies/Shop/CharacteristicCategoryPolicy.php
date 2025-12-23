<?php

namespace App\Policies\Shop;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\Shop\CharacteristicCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class CharacteristicCategoryPolicy
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

        return $user->can('view_any_characteristic::category');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, CharacteristicCategory $characteristicCategory): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('view_characteristic::category');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('create_characteristic::category');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, CharacteristicCategory $characteristicCategory): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('update_characteristic::category');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, CharacteristicCategory $characteristicCategory): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('delete_characteristic::category');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('delete_any_characteristic::category');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(Authenticatable $user, CharacteristicCategory $characteristicCategory): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('force_delete_characteristic::category');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('force_delete_any_characteristic::category');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(Authenticatable $user, CharacteristicCategory $characteristicCategory): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('restore_characteristic::category');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('restore_any_characteristic::category');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(Authenticatable $user, CharacteristicCategory $characteristicCategory): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('replicate_characteristic::category');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(Authenticatable $user): bool
    {
        if (!$user instanceof User) {

            return false;

        }


        return $user->can('reorder_characteristic::category');
    }
}