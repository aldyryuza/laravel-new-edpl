<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan DPL per product:
 * - is_team_b_product: produk tim B yang boleh dijual tim A (legacy I0005, IV0001–IV0010): batas kalkulator
 *   memakai batas Tim B, dan logistic fee dibagi seperti produk 25ml.
 * - calculator_limit: batas kalkulator khusus product (legacy DPL_ITEM_BATAS_KALKULATOR_KHUSUS), menang atas batas team.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_team_b_product')->default(false)->after('product_group');
            $table->decimal('calculator_limit', 10, 4)->nullable()->after('is_team_b_product')->comment('Batas kalkulator DPL khusus (%)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_team_b_product', 'calculator_limit']);
        });
    }
};
