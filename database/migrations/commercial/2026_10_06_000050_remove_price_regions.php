<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan user (2026-10-06): harga DPL tidak dibedakan per region. Region harga beserta mapping
 * outlet/distributor dihapus; harga product menjadi satu harga per product (+ periode berlaku).
 *
 * Saat migration ini dibuat semua tabel terkait masih kosong. down() membuat ulang struktur, tetapi
 * data mapping/region yang sempat ada tidak bisa dikembalikan, dan product_prices.price_region_id
 * dikembalikan sebagai nullable.
 */
return new class extends Migration
{
    /** Menu yang route-nya ikut dihapus; dibersihkan agar sidebar tidak merujuk route yang tidak ada. */
    private const MENU_CODES = ['price-regions', 'outlet-price-regions', 'distributor-price-regions'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('dpl.outlet_price_regions');
        Schema::dropIfExists('dpl.distributor_price_regions');

        Schema::table('dpl.product_prices', function (Blueprint $table) {
            $table->dropForeign(['price_region_id']);
            $table->dropIndex(['product_id', 'price_region_id']);
            $table->dropIndex(['price_region_id', 'is_active']);
            $table->dropColumn('price_region_id');

            // Harga per product + periode berlaku (kunci upsert import).
            $table->index(['product_id', 'valid_from']);
        });

        Schema::table('dpl.product_price_overrides', function (Blueprint $table) {
            $table->dropForeign(['price_region_id']);
            $table->dropIndex(['product_id', 'price_region_id']);
            $table->dropColumn('price_region_id');
            $table->index('product_id');
        });

        Schema::dropIfExists('dpl.price_regions');

        // role_menu_accesses ikut terhapus (FK cascade).
        DB::table('menus')->whereIn('code', self::MENU_CODES)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('dpl.price_regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('dpl.product_price_overrides', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->foreignId('price_region_id')->nullable()->after('product_id')->constrained('dpl.price_regions')->restrictOnDelete();
            $table->index(['product_id', 'price_region_id']);
        });

        Schema::table('dpl.product_prices', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'valid_from']);
            $table->foreignId('price_region_id')->nullable()->after('product_id')->constrained('dpl.price_regions')->restrictOnDelete();
            $table->index(['product_id', 'price_region_id']);
            $table->index(['price_region_id', 'is_active']);
        });

        Schema::create('dpl.outlet_price_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->unique()->constrained('outlets')->restrictOnDelete();
            $table->foreignId('price_region_id')->index()->constrained('dpl.price_regions')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('dpl.distributor_price_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->unique()->constrained('distributors')->restrictOnDelete();
            $table->foreignId('price_region_id')->index()->constrained('dpl.price_regions')->restrictOnDelete();
            $table->timestamps();
        });
    }
};
