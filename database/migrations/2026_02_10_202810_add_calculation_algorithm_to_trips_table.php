<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('trips', function (Blueprint $table) {
            // 'optimized' será o padrão (algoritmo atual)
            $table->string('calculation_algorithm')->default('optimized')->after('invite_code');
        });
    }

    public function down()
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('calculation_algorithm');
        });
    }
};
