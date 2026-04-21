<?php

namespace App\Policies;

use App\Models\Docente;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocentePolicy
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
        return $this->canAny($user, ['view_any_docente', 'ViewAny:Docente']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Docente $docente): bool
    {
        return $this->canAny($user, ['view_docente', 'View:Docente']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canAny($user, ['create_docente', 'Create:Docente']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Docente $docente): bool
    {
        return $this->canAny($user, ['update_docente', 'Update:Docente']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Docente $docente): bool
    {
        return $this->canAny($user, ['delete_docente', 'Delete:Docente']);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $this->canAny($user, ['delete_any_docente', 'DeleteAny:Docente']);
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Docente $docente): bool
    {
        return $this->canAny($user, ['force_delete_docente', 'ForceDelete:Docente']);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $this->canAny($user, ['force_delete_any_docente', 'ForceDeleteAny:Docente']);
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Docente $docente): bool
    {
        return $this->canAny($user, ['restore_docente', 'Restore:Docente']);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $this->canAny($user, ['restore_any_docente', 'RestoreAny:Docente']);
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Docente $docente): bool
    {
        return $this->canAny($user, ['replicate_docente', 'Replicate:Docente']);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $this->canAny($user, ['reorder_docente', 'Reorder:Docente']);
    }
}
