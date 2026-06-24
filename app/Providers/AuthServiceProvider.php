<?php

namespace App\Providers;

use App\Models\RecurringExpense;
use App\Models\Trip;
use App\Policies\RecurringExpensePolicy;
use App\Policies\TripPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Trip::class             => TripPolicy::class,
        RecurringExpense::class => RecurringExpensePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
