<?php

namespace App\Policies;

use App\Models\Administrativo;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdministrativoPolicy
{
    use HandlesAuthorization;

    /**
     * Accept both legacy permission keys and Shield v4 keys.
     */
    private function canAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canAny($user, ['view_any_administrativo', 'ViewAny:Administrativo']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Administrativo $administrativo): bool
    {
        return $this->canAny($user, ['view_administrativo', 'View:Administrativo']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canAny($user, ['create_administrativo', 'Create:Administrativo']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Administrativo $administrativo): bool
    {
        return $this->canAny($user, ['update_administrativo', 'Update:Administrativo']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Administrativo $administrativo): bool
    {
        return $this->canAny($user, ['delete_administrativo', 'Delete:Administrativo']);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $this->canAny($user, ['delete_any_administrativo', 'DeleteAny:Administrativo']);
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Administrativo $administrativo): bool
    {
        return $this->canAny($user, ['force_delete_administrativo', 'ForceDelete:Administrativo']);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $this->canAny($user, ['force_delete_any_administrativo', 'ForceDeleteAny:Administrativo']);
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Administrativo $administrativo): bool
    {
        return $this->canAny($user, ['restore_administrativo', 'Restore:Administrativo']);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $this->canAny($user, ['restore_any_administrativo', 'RestoreAny:Administrativo']);
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Administrativo $administrativo): bool
    {
        return $this->canAny($user, ['replicate_administrativo', 'Replicate:Administrativo']);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $this->canAny($user, ['reorder_administrativo', 'Reorder:Administrativo']);
    }
}
