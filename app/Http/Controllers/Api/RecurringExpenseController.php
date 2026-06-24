<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseOccurrence;
use App\Models\Trip;
use App\Services\RecurringExpenseProcessor;
use App\Traits\SendsNotifications;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecurringExpenseController extends Controller
{
    use SendsNotifications;

    public function index(Trip $trip)
    {
        $isParticipant = $trip->participants()->where('user_id', Auth::id())->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Você não tem permissão para visualizar este grupo.'], 403);
        }

        return response()->json($trip->recurringExpenses()->latest()->get());
    }

    public function store(Request $request, Trip $trip)
    {
        $this->authorize('create', [RecurringExpense::class, $trip]);

        $request->validate([
            'description'                   => 'required|string|max:255',
            'category'                      => 'required|string',
            'amount'                        => 'required|numeric|min:0.01',
            'is_variable_amount'            => 'boolean',
            'split_type'                    => 'sometimes|string|in:equal,custom',
            'split_config'                  => 'required|array|min:1',
            'split_config.*.participant_id' => 'required|exists:participants,id',
            'split_config.*.amount'         => 'required|numeric',
            'frequency'                     => 'required|array',
            'frequency.type'                => 'required|string|in:weekly,monthly,yearly',
            'frequency.dayOfMonth'          => 'required_if:frequency.type,monthly|integer|min:1|max:31',
            'frequency.dayOfWeek'           => 'required_if:frequency.type,weekly|integer|min:0|max:6',
            'frequency.month'               => 'required_if:frequency.type,yearly|integer|min:1|max:12',
            'start_date'                    => 'required|date',
            'end_date'                      => 'nullable|date|after:start_date',
            'requires_confirmation'         => 'boolean',
        ]);

        $sumSplits = collect($request->split_config)->sum('amount');
        if (abs($sumSplits - $request->amount) > 0.01) {
            return response()->json([
                'message' => 'A soma das divisões não corresponde ao valor total.',
                'total'   => $request->amount,
                'sum'     => $sumSplits,
            ], 422);
        }

        $requiresConfirmation = true;
        if ($request->has('requires_confirmation') && !$request->requires_confirmation) {
            $planFeatures = config("billing.plans.{$request->user()->subscription?->plan}.features", []);
            if ($planFeatures['auto_post'] ?? false) {
                $requiresConfirmation = false;
            }
        }

        $recurringExpense = RecurringExpense::create([
            'trip_id'               => $trip->id,
            'description'           => $request->description,
            'category'              => $request->category,
            'amount'                => $request->amount,
            'is_variable_amount'    => $request->boolean('is_variable_amount', false),
            'split_type'            => $request->input('split_type', 'equal'),
            'split_config'          => $request->split_config,
            'frequency'             => $request->frequency,
            'start_date'            => $request->start_date,
            'end_date'              => $request->end_date,
            'next_occurrence_at'    => $request->start_date,
            'requires_confirmation' => $requiresConfirmation,
            'status'                => 'active',
            'created_by'            => Auth::id(),
        ]);

        // Se a data de início é hoje, processa imediatamente sem esperar o job noturno
        if (Carbon::parse($recurringExpense->next_occurrence_at)->isToday()) {
            app(RecurringExpenseProcessor::class)->processOne($recurringExpense->fresh(['trip']));
        }

        return response()->json($recurringExpense->fresh(), 201);
    }

    public function pendingOccurrences(Trip $trip)
    {
        $isParticipant = $trip->participants()->where('user_id', Auth::id())->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        $pending = RecurringExpenseOccurrence::whereHas('recurringExpense', function ($q) use ($trip) {
                $q->where('trip_id', $trip->id)->where('status', 'active');
            })
            ->whereNull('expense_id')
            ->with('recurringExpense')
            ->get()
            ->map(fn ($o) => [
                'occurrence_id'   => $o->id,
                'occurrence_date' => $o->occurrence_date,
                'description'     => $o->recurringExpense->description,
                'category'        => $o->recurringExpense->category,
                'amount'          => $o->recurringExpense->amount,
                'is_variable_amount' => $o->recurringExpense->is_variable_amount,
                'split_config'    => $o->recurringExpense->split_config,
                'recurring_expense_id' => $o->recurring_expense_id,
            ]);

        return response()->json($pending);
    }

    public function update(Request $request, RecurringExpense $recurringExpense)
    {
        $this->authorize('update', $recurringExpense);

        $request->validate([
            'description'                   => 'sometimes|string|max:255',
            'category'                      => 'sometimes|string',
            'amount'                        => 'sometimes|numeric|min:0.01',
            'is_variable_amount'            => 'sometimes|boolean',
            'split_type'                    => 'sometimes|string|in:equal,custom',
            'split_config'                  => 'sometimes|array|min:1',
            'split_config.*.participant_id' => 'required_with:split_config|exists:participants,id',
            'split_config.*.amount'         => 'required_with:split_config|numeric',
            'status'                        => 'sometimes|string|in:active,paused,cancelled',
        ]);

        $recurringExpense->update($request->only([
            'description', 'category', 'amount', 'is_variable_amount',
            'split_type', 'split_config', 'status',
        ]));

        return response()->json($recurringExpense->fresh());
    }

    public function destroy(RecurringExpense $recurringExpense)
    {
        $this->authorize('delete', $recurringExpense);
        $recurringExpense->delete();

        return response()->json(['message' => 'Despesa recorrente removida.']);
    }

    public function activate(Trip $trip)
    {
        $this->authorize('activate', [RecurringExpense::class, $trip]);

        $trip->update(['recurring_expenses_enabled' => true]);

        return response()->json([
            'message'                    => 'Despesas recorrentes ativadas nesta trip.',
            'recurring_expenses_enabled' => true,
        ]);
    }

    public function deactivate(Trip $trip)
    {
        $this->authorize('deactivate', [RecurringExpense::class, $trip]);

        $trip->update(['recurring_expenses_enabled' => false]);

        return response()->json([
            'message'                    => 'Despesas recorrentes desativadas nesta trip.',
            'recurring_expenses_enabled' => false,
        ]);
    }

    public function confirmOccurrence(Request $request, RecurringExpenseOccurrence $occurrence)
    {
        $this->authorize('confirmOccurrence', $occurrence->recurringExpense);

        if ($occurrence->expense_id !== null) {
            return response()->json(['message' => 'Esta ocorrência já foi confirmada.'], 409);
        }

        $template    = $occurrence->recurringExpense;
        $trip        = $template->trip;
        $amount      = $request->input('amount', $template->amount);
        $splitConfig = $request->input('split_config', $template->split_config);

        $sumSplits = collect($splitConfig)->sum('amount');
        if (abs($sumSplits - $amount) > 0.01) {
            return response()->json([
                'message' => 'A soma das divisões não corresponde ao valor total.',
                'total'   => $amount,
                'sum'     => $sumSplits,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $expense = Expense::create([
                'trip_id'     => $trip->id,
                'description' => $template->description,
                'amount'      => $amount,
                'payer_id'    => $splitConfig[0]['participant_id'],
                'category'    => $template->category,
                'date'        => now(),
                'split_type'  => $template->split_type,
            ]);

            foreach ($splitConfig as $split) {
                ExpenseSplit::create([
                    'expense_id'     => $expense->id,
                    'participant_id' => $split['participant_id'],
                    'amount'         => $split['amount'],
                ]);
            }

            $occurrence->update(['expense_id' => $expense->id]);

            DB::commit();

            $this->notifyNewExpense($trip, $expense);

            return response()->json($expense->load('splits'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao confirmar ocorrência.', 'error' => $e->getMessage()], 500);
        }
    }
}
