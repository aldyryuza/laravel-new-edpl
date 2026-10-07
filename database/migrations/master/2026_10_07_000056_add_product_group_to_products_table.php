<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group product manual (mis. IV CAT WING, IV CAT PEN) untuk product yang tidak di-group berdasarkan volume.
 * Daftar nilai yang diizinkan ada di config('dpl.product_groups'); divalidasi di aplikasi.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_group', 50)->nullable()->after('volume')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['product_group']);
            $table->dropColumn('product_group');
        });
    }
};
