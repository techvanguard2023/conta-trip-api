<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Trip;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Trip $trip)
    {
        // 1. Total Gasto
        $totalSpent = $trip->expenses()->sum('amount');

        // Se não houver gastos, retorne zerado para evitar erro de divisão por zero
        if ($totalSpent == 0) {
            return response()->json([
                'total_spent' => 0,
                'average_daily' => 0,
                'biggest_expense' => null,
                'by_category' => [],
                'by_member' => [],
                'daily_trend' => []
            ]);
        }

        // 2. Gastos por Categoria
        // SQL: SELECT category, SUM(amount) as total FROM expenses WHERE trip_id = ? GROUP BY category
        $byCategory = $trip->expenses()
            ->select('category', DB::raw('sum(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) use ($totalSpent) {
                return [
                    'category' => $item->category,
                    'total' => (float) $item->total,
                    'percentage' => round(($item->total / $totalSpent) * 100, 2)
                ];
            });

        // 3. Gastos por Membro (Consumo)
        // Precisamos somar a tabela expense_splits, não a expenses
        $byMember = DB::table('expense_splits')
            ->join('expenses', 'expense_splits.expense_id', '=', 'expenses.id')
            ->join('participants', 'expense_splits.participant_id', '=', 'participants.id')
            ->where('expenses.trip_id', $trip->id)
            ->select(
                'participants.id as member_id',
                'participants.name',
                DB::raw('sum(expense_splits.amount) as total')
            )
            ->groupBy('participants.id', 'participants.name')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) use ($totalSpent) {
                return [
                    'id' => $item->member_id,
                    'name' => $item->name,
                    'total' => (float) $item->total,
                    'percentage' => round(($item->total / $totalSpent) * 100, 2)
                ];
            });

        // 4. Tendência Diária (Gráfico de Barras)
        // Agrupa por DATA (sem hora)
        $dailyTrend = $trip->expenses()
            ->select(DB::raw('DATE(date) as date'), DB::raw('sum(amount) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date, // YYYY-MM-DD
                    'total' => (float) $item->total
                ];
            });

        // 5. Estatísticas Gerais
        $daysCount = $dailyTrend->count() ?: 1;
        $averageDaily = $totalSpent / $daysCount;

        $biggestExpense = $trip->expenses()
            ->orderByDesc('amount')
            ->select('description', 'amount', 'category')
            ->first();

        return response()->json([
            'total_spent' => (float) $totalSpent,
            'average_daily' => round($averageDaily, 2),
            'biggest_expense' => $biggestExpense,
            'by_category' => $byCategory,
            'by_member' => $byMember,
            'daily_trend' => $dailyTrend
        ]);
    }
}