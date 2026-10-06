<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial terms saat ini, satu baris per dpl_item. Semua nilai NUMERIC.
 *
 * Belum ada CHECK persentase <= 100 sampai special case komersial divalidasi.
 * logistic_fee bukan berasal dari legacy dpl_logistic (tidak dimigrasikan).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.dpl_item_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dpl_item_id')->unique()->constrained('dpl.dpl_items')->restrictOnDelete();
            $table->decimal('principle_discount_on', 10, 4)->default(0);
            $table->decimal('principle_discount_off', 10, 4)->default(0);
            $table->decimal('distributor_discount_on', 10, 4)->default(0);
            $table->decimal('distributor_discount_off', 10, 4)->default(0);
            $table->decimal('negotiated_price', 18, 4)->nullable();
            $table->decimal('margin', 10, 4)->nullable();
            $table->decimal('logistic_fee', 18, 4)->nullable();
            $table->decimal('logistic_support', 18, 4)->nullable();
            $table->decimal('base_price', 18, 4)->nullable();
            $table->decimal('final_price', 18, 4)->nullable();
            $table->decimal('effective_discount', 10, 4)->nullable();
            $table->decimal('calculator_percentage', 10, 4)->nullable();
            $table->string('price_type', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.dpl_item_terms');
    }
};
