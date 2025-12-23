<?php

namespace App\Policies\Shop;

use App\Models\User;
use App\Models\Shop\ProductCategory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class ProductCategoryPolicy
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

        return $user->can('view_any_product::category');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, ProductCategory $productCategory): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('view_product::category');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('create_product::category');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, ProductCategory $productCategory): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('update_product::category');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, ProductCategory $productCategory): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('delete_product::category');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('delete_any_product::category');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(Authenticatable $user, ProductCategory $productCategory): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('force_delete_product::category');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('force_delete_any_product::category');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(Authenticatable $user, ProductCategory $productCategory): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('restore_product::category');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('restore_any_product::category');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(Authenticatable $user, ProductCategory $productCategory): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('replicate_product::category');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('reorder_product::category');
    }
}
