<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        $user->deleteProfilePhoto();
        
        // Delete tokens if they exist
        if ($user->tokens && $user->tokens->count() > 0) {
            $user->tokens->each->delete();
        }
        
        $user->delete();
    }
}
