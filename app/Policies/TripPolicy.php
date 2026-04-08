<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    /**
     * Determine if the user can update the trip.
     */
    public function update(User $user, Trip $trip): bool
    {
        return $user->id === $trip->created_by;
    }

    /**
     * Determine if the user can update the trip status.
     */
    public function updateStatus(User $user, Trip $trip): bool
    {
        return $user->id === $trip->created_by;
    }

    /**
     * Determine if the user can delete the trip.
     */
    public function delete(User $user, Trip $trip): bool
    {
        return $user->id === $trip->created_by;
    }
}
