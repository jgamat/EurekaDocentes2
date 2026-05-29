<?php

namespace App\Policies;

use App\Models\Alumno;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AlumnoPolicy
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
        return $this->canAny($user, ['view_any_alumno', 'ViewAny:Alumno']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Alumno $alumno): bool
    {
        return $this->canAny($user, ['view_alumno', 'View:Alumno']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canAny($user, ['create_alumno', 'Create:Alumno']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Alumno $alumno): bool
    {
        return $this->canAny($user, ['update_alumno', 'Update:Alumno']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Alumno $alumno): bool
    {
        return $this->canAny($user, ['delete_alumno', 'Delete:Alumno']);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $this->canAny($user, ['delete_any_alumno', 'DeleteAny:Alumno']);
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Alumno $alumno): bool
    {
        return $this->canAny($user, ['force_delete_alumno', 'ForceDelete:Alumno']);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $this->canAny($user, ['force_delete_any_alumno', 'ForceDeleteAny:Alumno']);
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Alumno $alumno): bool
    {
        return $this->canAny($user, ['restore_alumno', 'Restore:Alumno']);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $this->canAny($user, ['restore_any_alumno', 'RestoreAny:Alumno']);
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Alumno $alumno): bool
    {
        return $this->canAny($user, ['replicate_alumno', 'Replicate:Alumno']);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $this->canAny($user, ['reorder_alumno', 'Reorder:Alumno']);
    }
}
