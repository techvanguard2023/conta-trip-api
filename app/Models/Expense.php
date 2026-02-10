<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model {
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['trip_id', 'description', 'amount', 'payer_id', 'category', 'date', 'split_type'];

    public function splits() {
        return $this->hasMany(ExpenseSplit::class);
    }
    
    // Para carregar os dados completos na resposta JSON
    protected $with = ['splits']; 
}
