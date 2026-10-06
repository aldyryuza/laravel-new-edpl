<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kota outlet merujuk master kota. Terpisah dari area penjualan (area_id) yang dipakai untuk resolusi approver.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('address')->index()->constrained('cities')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropIndex(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
