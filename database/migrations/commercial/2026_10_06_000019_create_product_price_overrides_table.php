<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Harga khusus (legacy dpl_price_product_special), mis. JKN/PADMA.
 * price_region_id null = berlaku semua region (jika aturan legacy dipertahankan).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.product_price_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('price_type', 50);
            $table->foreignId('outlet_group_id')->nullable()->index()->constrained('outlet_groups')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('price_region_id')->nullable()->constrained('dpl.price_regions')->restrictOnDelete();
            $table->decimal('price', 18, 4);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'price_region_id']);
        });

        DB::statement('ALTER TABLE dpl.product_price_overrides ADD CONSTRAINT product_price_overrides_valid_period_check CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.product_price_overrides');
    }
};
