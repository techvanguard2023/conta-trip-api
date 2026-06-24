<?php

namespace App\Jobs;

use App\Models\RecurringExpense;
use App\Services\RecurringExpenseProcessor;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRecurringExpenses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(RecurringExpenseProcessor $processor): void
    {
        $today = Carbon::today();

        RecurringExpense::where('status', 'active')
            ->where('next_occurrence_at', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->with('trip')
            ->each(fn (RecurringExpense $template) => $processor->processOne($template));
    }
}
