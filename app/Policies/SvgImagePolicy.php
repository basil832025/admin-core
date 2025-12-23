<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\SvgImage;
use Illuminate\Auth\Access\HandlesAuthorization;

class SvgImagePolicy
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

        return $user->can('view_any_svg_image');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, SvgImage $svgImage): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('view_svg_image');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('create_svg_image');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, SvgImage $svgImage): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('update_svg_image');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, SvgImage $svgImage): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('delete_svg_image');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('delete_any_svg_image');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(Authenticatable $user, SvgImage $svgImage): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('force_delete_svg_image');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('force_delete_any_svg_image');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(Authenticatable $user, SvgImage $svgImage): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('restore_svg_image');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('restore_any_svg_image');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(Authenticatable $user, SvgImage $svgImage): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('replicate_svg_image');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(Authenticatable $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $user->can('reorder_svg_image');
    }
}
