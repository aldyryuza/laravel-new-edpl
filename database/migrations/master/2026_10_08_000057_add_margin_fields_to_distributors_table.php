<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Margin dasar distributor (legacy distributor.margin / margin_tim_b / is_special_case_margin).
 * Dipakai untuk mengisi otomatis margin item DPL (App\Services\Dpl\DplItemDefaults).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->decimal('margin', 10, 4)->nullable()->after('meeting_radius_meters')->comment('Margin dasar (%)');
            $table->decimal('margin_team_b', 10, 4)->nullable()->after('margin')->comment('Margin untuk DPL Tim B (%); kosong = pakai margin');
            $table->boolean('is_special_case_margin')->default(false)->after('margin_team_b')->comment('Punya margin khusus per outlet');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn(['margin', 'margin_team_b', 'is_special_case_margin']);
        });
    }
};
