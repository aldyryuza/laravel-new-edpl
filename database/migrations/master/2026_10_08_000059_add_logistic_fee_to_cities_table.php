<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logistic fee (%) per kota (legacy dpl_country_city.logistic_fee), dasar logistic fee item DPL Direct
 * berdasarkan kota distributor.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->decimal('logistic_fee', 10, 4)->nullable()->after('province_id')->comment('Logistic fee DPL (%)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('logistic_fee');
        });
    }
};
