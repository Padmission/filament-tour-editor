<?php

namespace Padmission\FilamentTourEditor\Policies;

use App\Models\User;
use Padmission\FilamentTourEditor\Models\Tour;

class TourPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function view(User $user, Tour $tour): bool
    {
        return (bool) $user;
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function update(User $user, Tour $tour): bool
    {
        return $user->isGlobalAdmin();
    }

    public function delete(User $user, Tour $tour): bool
    {
        return $user->isGlobalAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }
}
