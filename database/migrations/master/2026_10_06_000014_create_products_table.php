<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master product (legacy product). Grouping 500/250/100/25 ml berasal dari volume;
 * tidak ada tabel product_groups.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Legacy PID');
            $table->string('name', 255)->comment('Legacy PNAME');
            $table->decimal('volume', 10, 4)->nullable();
            $table->boolean('is_branded')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('netsuite_id', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
