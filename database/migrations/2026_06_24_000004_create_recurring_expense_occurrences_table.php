<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expense_occurrences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('recurring_expense_id');
            $table->date('occurrence_date');
            $table->uuid('expense_id')->nullable(); // null = pendente de confirmação
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['recurring_expense_id', 'occurrence_date'], 'reo_recurring_date_unique'); // idempotência do job
            $table->foreign('recurring_expense_id')
                  ->references('id')->on('recurring_expenses')->onDelete('cascade');
            $table->foreign('expense_id')->references('id')->on('expenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expense_occurrences');
    }
};
