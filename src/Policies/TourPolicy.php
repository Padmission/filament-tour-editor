<?php

namespace Padmission\FilamentTourEditor\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\FilamentTourEditor\Models\Tour;

class TourPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Tour $tour): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Tour $tour): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, Tour $tour): bool
    {
        return true;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return true;
    }

    public function import(Authenticatable $user): bool
    {
        return true;
    }

    public function export(Authenticatable $user): bool
    {
        return true;
    }
}
