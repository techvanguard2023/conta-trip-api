<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Participant extends Model {
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['trip_id', 'user_id', 'name'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
