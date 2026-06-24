<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringExpenseOccurrence extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'recurring_expense_id',
        'occurrence_date',
        'expense_id',
    ];

    public function recurringExpense()
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
