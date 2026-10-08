<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif logistic fee (%) khusus per grup distributor + kota (pengganti legacy DPL_UDC_LOGISTIC_PER_AREA).
 * Menang atas logistic fee kota; distributor nasional tetap memakai tarif nasional.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.distributor_logistic_rates', function (Blueprint $table) {
            $table->id();
            $table->string('distributor_group_code', 50)->comment('distributors.group_code');
            $table->foreignId('city_id')->constrained('cities')->restrictOnDelete();
            $table->decimal('logistic_fee', 10, 4);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['distributor_group_code', 'city_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.distributor_logistic_rates');
    }
};
