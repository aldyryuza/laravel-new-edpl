<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skema bonus (legacy dpl_bonus). Semua nilai kuantitas/diskon NUMERIC.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.bonus_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable()->unique();
            $table->string('name', 255);
            $table->decimal('buy_quantity', 12, 4)->nullable();
            $table->decimal('bonus_quantity', 12, 4)->nullable();
            $table->decimal('discount_invoice', 10, 4)->nullable();
            $table->decimal('discount_bonus', 10, 4)->nullable();
            $table->decimal('effective_discount', 10, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.bonus_schemes');
    }
};
