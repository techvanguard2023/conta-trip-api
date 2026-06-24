<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->string('description');
            $table->string('category');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_variable_amount')->default(false);
            $table->string('split_type')->default('equal'); // equal|custom
            $table->json('split_config'); // [{ "participant_id": "uuid", "amount": 0.00 }]
            $table->json('frequency');    // { "type": "monthly", "dayOfMonth": 5 }
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_occurrence_at');
            $table->boolean('requires_confirmation')->default(true);
            $table->string('status')->default('active'); // active|paused|cancelled
            $table->uuid('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
