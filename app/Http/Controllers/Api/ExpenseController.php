<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Trip;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Trip $trip)
    {
        // Verifica permissão (Policy seria o ideal)
        // Retorna despesas com os splits
        return response()->json($trip->expenses()->with('splits')->latest('date')->get());
    }

    public function store(Request $request, Trip $trip)
    {
        $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'payer_id' => 'required|exists:participants,id',
            'category' => 'required|string',
            'splits' => 'required|array|min:1',
            'splits.*.memberId' => 'required|exists:participants,id',
            'splits.*.amount' => 'required|numeric'
        ]);
        
        $sumSplits = collect($request->splits)->sum('amount');
        if (abs($sumSplits - $request->amount) > 0.01) { // Tolerância para ponto flutuante
             return response()->json([
                'message' => 'A soma das divisões não corresponde ao valor total da despesa',
                'total' => $request->amount,
                'sum' => $sumSplits
             ], 422);
        }

        DB::beginTransaction();

        try {
            $expense = Expense::create([
                'trip_id' => $trip->id,
                'description' => $request->description,
                'amount' => $request->amount,
                'payer_id' => $request->payer_id,
                'category' => $request->category,
                'date' => now()
            ]);

            foreach ($request->splits as $split) {
                ExpenseSplit::create([
                    'expense_id' => $expense->id,
                    'participant_id' => $split['memberId'],
                    'amount' => $split['amount']
                ]);
            }

            DB::commit();

            // Enviar Notificações para os outros membros do grupo
            try {
                $payer = \App\Models\Participant::find($request->payer_id);
                $payerName = $payer ? $payer->name : 'Alguém';
                
                $participants = $trip->participants()
                    ->whereNotNull('user_id')
                    ->where('user_id', '!=', \Illuminate\Support\Facades\Auth::id())
                    ->with('user')
                    ->get();

                $tokens = $participants->pluck('user.fcm_token')->filter()->unique()->toArray();

                if (!empty($tokens)) {
                    $firebaseService = new \App\Services\FirebaseNotificationService();
                    foreach ($tokens as $token) {
                        try {
                            $firebaseService->sendNotification(
                                $token,
                                "Nova despesa: {$trip->name}",
                                "{$payerName} adicionou: {$expense->description} - R$ " . number_format($expense->amount, 2, ',', '.')
                            );
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Erro ao enviar notificação FCM: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                // Logar erro mas não falhar a requisição de criação de despesa
                \Illuminate\Support\Facades\Log::error("Erro no processo de notificação: " . $e->getMessage());
            }

            return response()->json($expense->load('splits'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao salvar despesa', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return response()->json(['message' => 'Despesa removida']);
    }
}
