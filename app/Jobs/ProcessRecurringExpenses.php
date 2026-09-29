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
use Illuminate\Support\Facades\Log;

class ProcessRecurringExpenses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    // Nunca deve levar mais que alguns segundos por template. Um timeout
    // baixo evita que um travamento monopolize o único worker da fila por
    // até 1h (o tempo do --max-time do queue:work), o que atrasava todas
    // as outras notificações enfileiradas atrás dele.
    public $timeout = 120;

    public function handle(RecurringExpenseProcessor $processor): void
    {
        $today = Carbon::today();

        RecurringExpense::where('status', 'active')
            ->where('next_occurrence_at', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->with('trip')
            ->each(function (RecurringExpense $template) use ($processor) {
                // Isola falhas por template — um travamento/erro num template
                // não pode abortar o processamento dos demais no mesmo dia.
                try {
                    $processor->processOne($template);
                } catch (\Throwable $e) {
                    Log::error('Erro ao processar template de despesa recorrente', [
                        'recurring_expense_id' => $template->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }
}
