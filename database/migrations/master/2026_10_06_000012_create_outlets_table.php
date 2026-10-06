<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master outlet. Region/zone outlet diturunkan dari area_id (area -> region -> zone).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 255);
            $table->text('address')->nullable();
            $table->foreignId('area_id')->nullable()->index()->constrained('sales_areas')->restrictOnDelete();
            $table->foreignId('outlet_category_id')->nullable()->index()->constrained('outlet_categories')->restrictOnDelete();
            $table->foreignId('ownership_type_id')->nullable()->index()->constrained('ownership_types')->restrictOnDelete();
            $table->integer('bed_count')->nullable();
            $table->foreignId('price_region_id')->nullable()->index()->constrained('dpl.price_regions')->restrictOnDelete();
            $table->unsignedBigInteger('crm_id')->nullable()->comment('Referensi eksternal CRM/legacy, bukan FK');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE outlets ADD CONSTRAINT outlets_bed_count_check CHECK (bed_count IS NULL OR bed_count >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
