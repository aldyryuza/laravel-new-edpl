<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item DPL "Ambil Dari Pabrik" (logistic fee otomatis 0). Tidak boleh bersamaan dengan Direct
 * (user confirmation 2026-10-07); boleh keduanya tidak dicentang (pengiriman normal via distributor).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dpl.dpl_items', function (Blueprint $table) {
            $table->boolean('is_factory_pickup')->default(false)->after('is_direct_delivery');
        });

        DB::statement('ALTER TABLE dpl.dpl_items ADD CONSTRAINT dpl_items_delivery_mode_check CHECK (NOT (is_direct_delivery AND is_factory_pickup))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE dpl.dpl_items DROP CONSTRAINT IF EXISTS dpl_items_delivery_mode_check');

        Schema::table('dpl.dpl_items', function (Blueprint $table) {
            $table->dropColumn('is_factory_pickup');
        });
    }
};
