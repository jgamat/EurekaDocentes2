<?php

namespace App\Policies;

use App\Models\Proceso;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcesoPolicy
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
        return $this->canAny($user, ['view_any_proceso', 'ViewAny:Proceso']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Proceso $proceso): bool
    {
        return $this->canAny($user, ['view_proceso', 'View:Proceso']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canAny($user, ['create_proceso', 'Create:Proceso']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Proceso $proceso): bool
    {
        return $this->canAny($user, ['update_proceso', 'Update:Proceso']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Proceso $proceso): bool
    {
        return $this->canAny($user, ['delete_proceso', 'Delete:Proceso']);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $this->canAny($user, ['delete_any_proceso', 'DeleteAny:Proceso']);
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Proceso $proceso): bool
    {
        return $this->canAny($user, ['force_delete_proceso', 'ForceDelete:Proceso']);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $this->canAny($user, ['force_delete_any_proceso', 'ForceDeleteAny:Proceso']);
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Proceso $proceso): bool
    {
        return $this->canAny($user, ['restore_proceso', 'Restore:Proceso']);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $this->canAny($user, ['restore_any_proceso', 'RestoreAny:Proceso']);
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Proceso $proceso): bool
    {
        return $this->canAny($user, ['replicate_proceso', 'Replicate:Proceso']);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $this->canAny($user, ['reorder_proceso', 'Reorder:Proceso']);
    }
}
