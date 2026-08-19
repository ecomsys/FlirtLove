<?php
namespace App\Observers;

use App\Models\UserProfile;
use Carbon\Carbon;

class UserProfileObserver
{
    public function saving(UserProfile $profile): void
    {
        // Если birth_date изменился или установлен
        if ($profile->isDirty('birth_date') && $profile->birth_date) {
            $profile->age = $profile->birth_date->age;
        } elseif (!$profile->birth_date) {
            $profile->age = null; // Если удалили дату
        }
    }
}