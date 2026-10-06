<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Harga product per price region (legacy dpl_price_product).
 *
 * Belum ada exclusion constraint overlap periode: aturan histori harga belum dikonfirmasi.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('price_region_id')->constrained('dpl.price_regions')->restrictOnDelete();
            $table->decimal('price', 18, 4);
            $table->decimal('regular_price', 18, 4)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'price_region_id']);
            $table->index(['price_region_id', 'is_active']);
        });

        DB::statement('ALTER TABLE dpl.product_prices ADD CONSTRAINT product_prices_valid_period_check CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.product_prices');
    }
};
