<?php

namespace App\Policies;

use App\Models\TrainingLesson;
use App\Models\User;

/** Le staff rédige la formation ; seul un super-admin supprime une leçon. */
class TrainingLessonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, TrainingLesson $lesson): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, TrainingLesson $lesson): bool
    {
        return $user->isStaff();
    }

    public function reorder(User $user): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, TrainingLesson $lesson): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
