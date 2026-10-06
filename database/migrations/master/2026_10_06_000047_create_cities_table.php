<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master kota/kabupaten (master bersama lintas sistem). Kode mengikuti kode wilayah BPS tanpa titik,
 * mis. "8271" = Kota Ternate; sama dengan legacy distributor.city_id sehingga bisa dipetakan langsung.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('Kode wilayah BPS, mis. 8271');
            $table->string('name', 100);
            $table->foreignId('province_id')->index()->constrained('provinces')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
