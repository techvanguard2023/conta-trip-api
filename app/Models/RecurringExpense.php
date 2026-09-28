<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringExpense extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'trip_id',
        'description',
        'category',
        'amount',
        'is_variable_amount',
        'split_type',
        'split_config',
        'frequency',
        'start_date',
        'end_date',
        'next_occurrence_at',
        'requires_confirmation',
        'status',
        'created_by',
    ];

    protected $casts = [
        'split_config'          => 'array',
        'frequency'             => 'array',
        'is_variable_amount'    => 'boolean',
        'requires_confirmation' => 'boolean',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function occurrences()
    {
        return $this->hasMany(RecurringExpenseOccurrence::class);
    }

    public function calculateNextOccurrence(Carbon $from): Carbon
    {
        $frequency = $this->frequency;

        return match ($frequency['type']) {
            'weekly'  => $from->copy()->addWeek(),
            'monthly' => $this->withSafeDay($from->copy()->startOfMonth()->addMonth(), $frequency['dayOfMonth']),
            'yearly'  => $this->withSafeDay($from->copy()->startOfMonth()->addYear()->month($frequency['month']), $frequency['dayOfMonth']),
            default   => $from->copy()->addMonth(),
        };
    }

    /**
     * Aplica o dia desejado sem estourar pro mês seguinte quando o mês alvo
     * for mais curto (ex.: dia 31 configurado, mês alvo é fevereiro).
     */
    private function withSafeDay(Carbon $date, int $dayOfMonth): Carbon
    {
        return $date->day(min($dayOfMonth, $date->daysInMonth));
    }
}
