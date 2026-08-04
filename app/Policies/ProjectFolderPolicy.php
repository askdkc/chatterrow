<?php

namespace App\Policies;

use App\Models\ProjectFolder;
use App\Models\User;

class ProjectFolderPolicy
{
    public function update(User $user, ProjectFolder $projectFolder): bool
    {
        return $projectFolder->user_id === $user->id;
    }

    public function delete(User $user, ProjectFolder $projectFolder): bool
    {
        return $projectFolder->user_id === $user->id;
    }
}
