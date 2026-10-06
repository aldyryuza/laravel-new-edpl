<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Region harga distributor untuk keperluan DPL (legacy distributor.PRICE_REGION_ID), satu per distributor.
 * Harga DPL memakai region harga outlet dan distributor; aturan prioritas ditentukan di modul Commercial.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.distributor_price_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->unique()->constrained('distributors')->restrictOnDelete();
            $table->foreignId('price_region_id')->index()->constrained('dpl.price_regions')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.distributor_price_regions');
    }
};
