<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "25% Bed" (legacy dpl.order_min): order minimum header DPL = bed outlet × BED_ORDER_RATIO (0,25) × BED_ORDER_DAYS (90).
 * Dihitung dari bed_count_snapshot; bila bed outlet 0/kosong diisi manual.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dpl.dpl_proposals', function (Blueprint $table) {
            $table->decimal('bed_order_minimum', 14, 2)->nullable()->after('bed_count_snapshot')->comment('25% Bed (legacy order_min)');
        });

        DB::statement('ALTER TABLE dpl.dpl_proposals ADD CONSTRAINT dpl_proposals_bed_order_minimum_check CHECK (bed_order_minimum IS NULL OR bed_order_minimum >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dpl.dpl_proposals', function (Blueprint $table) {
            $table->dropColumn('bed_order_minimum');
        });
    }
};
