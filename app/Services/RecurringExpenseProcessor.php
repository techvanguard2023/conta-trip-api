<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseOccurrence;
use App\Traits\SendsNotifications;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringExpenseProcessor
{
    use SendsNotifications;

    public function processOne(RecurringExpense $template): void
    {
        $occurrenceDate = Carbon::parse($template->next_occurrence_at)->toDateString();

        DB::beginTransaction();
        try {
            $occurrence = RecurringExpenseOccurrence::firstOrCreate(
                [
                    'recurring_expense_id' => $template->id,
                    'occurrence_date'      => $occurrenceDate,
                ],
                ['expense_id' => null]
            );

            // Já existia e estava pendente — não reprocessar
            if (!$occurrence->wasRecentlyCreated && $occurrence->expense_id === null) {
                DB::rollBack();
                return;
            }

            $trip = $template->trip;

            if (!$template->requires_confirmation && !$template->is_variable_amount) {
                $expense = Expense::create([
                    'trip_id'     => $template->trip_id,
                    'description' => $template->description,
                    'amount'      => $template->amount,
                    'payer_id'    => $template->split_config[0]['participant_id'],
                    'category'    => $template->category,
                    'date'        => now(),
                    'split_type'  => $template->split_type,
                ]);

                foreach ($template->split_config as $split) {
                    ExpenseSplit::create([
                        'expense_id'     => $expense->id,
                        'participant_id' => $split['participant_id'],
                        'amount'         => $split['amount'],
                    ]);
                }

                $occurrence->update(['expense_id' => $expense->id]);
                $this->notifyNewExpense($trip, $expense);
            } else {
                $this->notifyRecurringExpensePending($trip, $template);
                $this->notifyWhatsAppRecurringDue($trip, $template);
            }

            $template->update([
                'next_occurrence_at' => $template->calculateNextOccurrence(
                    Carbon::parse($occurrenceDate)
                )->toDateString(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao processar despesa recorrente', [
                'recurring_expense_id' => $template->id,
                'error'                => $e->getMessage(),
            ]);
        }
    }
}
