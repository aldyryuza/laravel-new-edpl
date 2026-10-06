<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group rumah sakit / group beberapa outlet (legacy GRUP3, dpl_master_group_outlet).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('outlet_groups', function (Blueprint $table) {
            $table->id();
            // Nullable: tidak semua group legacy punya kode. UNIQUE tetap berlaku untuk yang terisi.
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlet_groups');
    }
};
