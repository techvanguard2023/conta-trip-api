<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->uuid('payer_id'); // Referencia a tabela PARTICIPANTS, não users
            $table->enum('category', ['food', 'transport', 'accommodation', 'entertainment', 'other']);
            $table->dateTime('date');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            $table->foreign('payer_id')->references('id')->on('participants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
