<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wilayah user fleksibel: satu penugasan berscope zona, region, ATAU area; ketiganya kosong = Nasional
 * (melihat semua wilayah). team_code teks diganti team_id (master teams).
 * Tabel masih kosong saat migration ini dibuat.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_org_assignments', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->after('user_id')->index()->constrained('sales_zones')->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->after('zone_id')->index()->constrained('sales_regions')->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->after('area_id')->index()->constrained('teams')->restrictOnDelete();
            $table->dropColumn('team_code');
        });

        DB::statement('ALTER TABLE user_org_assignments ADD CONSTRAINT user_org_assignments_single_scope_check CHECK (num_nonnulls(zone_id, region_id, area_id) <= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE user_org_assignments DROP CONSTRAINT IF EXISTS user_org_assignments_single_scope_check');

        Schema::table('user_org_assignments', function (Blueprint $table) {
            $table->string('team_code', 20)->nullable()->after('area_id');
            $table->dropForeign(['team_id']);
            $table->dropForeign(['region_id']);
            $table->dropForeign(['zone_id']);
            $table->dropColumn(['team_id', 'region_id', 'zone_id']);
        });
    }
};
