<?php

namespace App\Policies;

use App\Models\RecurringExpense;
use App\Models\Trip;
use App\Models\User;

class RecurringExpensePolicy
{
    public function activate(User $user, Trip $trip): bool
    {
        return $trip->created_by === $user->id
            && $user->canActivateNewGroup();
    }

    public function deactivate(User $user, Trip $trip): bool
    {
        return $trip->created_by === $user->id;
    }

    public function create(User $user, Trip $trip): bool
    {
        return $trip->created_by === $user->id
            && $trip->recurring_expenses_enabled
            && $user->hasActivePremium()
            && $user->isWithinGroupQuota();
    }

    public function update(User $user, RecurringExpense $recurringExpense): bool
    {
        return $recurringExpense->trip->created_by === $user->id;
    }

    public function delete(User $user, RecurringExpense $recurringExpense): bool
    {
        return $recurringExpense->trip->created_by === $user->id;
    }

    public function confirmOccurrence(User $user, RecurringExpense $recurringExpense): bool
    {
        return $recurringExpense->trip->participants()
            ->where('user_id', $user->id)
            ->exists();
    }
}
