<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model {
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['name', 'description', 'invite_code', 'calculation_algorithm', 'currency', 'start_date', 'created_by'];

    public function participants() {
        return $this->hasMany(Participant::class);
    }

    public function expenses() {
        return $this->hasMany(Expense::class);
    }
}
