<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('stripe_customer_id')->unique();
            $table->string('stripe_subscription_id')->unique()->nullable();
            $table->string('plan'); // basico|intermediario|avancado
            $table->string('status'); // active|trialing|past_due|canceled|incomplete
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
