<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot distributor <-> sales region (legacy distributor_region).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('distributor_regions', function (Blueprint $table) {
            $table->foreignId('distributor_id')->constrained('distributors')->restrictOnDelete();
            $table->foreignId('region_id')->index()->constrained('sales_regions')->restrictOnDelete();

            $table->primary(['distributor_id', 'region_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributor_regions');
    }
};
