<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Margin khusus product per grup distributor (legacy dpl_distributor_margin), mis. produk 25ml margin 7.
 * city_id null = berlaku semua kota; bila ada aturan untuk kota distributor, aturan kota itu yang dipakai.
 * Menang atas margin dasar distributor (termasuk margin Tim B).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.distributor_margin_rules', function (Blueprint $table) {
            $table->id();
            $table->string('distributor_group_code', 50)->comment('distributors.group_code');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->restrictOnDelete()->comment('null = semua kota');
            $table->decimal('margin', 10, 4);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['distributor_group_code', 'product_id']);
        });

        // Satu aturan per grup + product + kota; "semua kota" (null) juga hanya satu.
        DB::statement('CREATE UNIQUE INDEX distributor_margin_rules_scope_unique ON dpl.distributor_margin_rules (distributor_group_code, product_id, COALESCE(city_id, 0))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.distributor_margin_rules');
    }
};
