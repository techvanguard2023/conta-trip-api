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
            'monthly' => $from->copy()->addMonth()->day($frequency['dayOfMonth']),
            'yearly'  => $from->copy()->addYear()->month($frequency['month'])->day($frequency['dayOfMonth']),
            default   => $from->copy()->addMonth(),
        };
    }
}
