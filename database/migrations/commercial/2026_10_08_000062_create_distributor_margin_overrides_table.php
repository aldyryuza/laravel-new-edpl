<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Margin khusus outlet (legacy dpl_distributor_spesial_margin_outlet): berlaku bila distributor
 * is_special_case_margin, team pengaju tidak dikecualikan, dan product bukan 25ml.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.distributor_margin_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained('distributors')->restrictOnDelete();
            $table->foreignId('outlet_id')->index()->constrained('outlets')->restrictOnDelete();
            $table->decimal('margin', 10, 4);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['distributor_id', 'outlet_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.distributor_margin_overrides');
    }
};
