<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Open decision #9: master outlet (public) tidak boleh bergantung ke schema dpl.
 * Region harga outlet dipindah ke mapping milik DPL: dpl.outlet_price_regions (satu region harga per outlet).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.outlet_price_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->unique()->constrained('outlets')->restrictOnDelete();
            $table->foreignId('price_region_id')->index()->constrained('dpl.price_regions')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement('INSERT INTO dpl.outlet_price_regions (outlet_id, price_region_id, created_at, updated_at) SELECT id, price_region_id, NOW(), NOW() FROM outlets WHERE price_region_id IS NOT NULL');

        Schema::table('outlets', function (Blueprint $table) {
            $table->dropForeign(['price_region_id']);
            $table->dropIndex(['price_region_id']);
            $table->dropColumn('price_region_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->foreignId('price_region_id')->nullable()->index()->constrained('dpl.price_regions')->restrictOnDelete();
        });

        DB::statement('UPDATE outlets o SET price_region_id = m.price_region_id FROM dpl.outlet_price_regions m WHERE m.outlet_id = o.id');

        Schema::dropIfExists('dpl.outlet_price_regions');
    }
};
