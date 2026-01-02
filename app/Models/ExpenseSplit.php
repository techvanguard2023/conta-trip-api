<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseSplit extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'expense_id',
        'participant_id',
        'amount',
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
