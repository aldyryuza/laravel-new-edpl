<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parameter bisnis yang configurable (mis. MAX_DPL_DISCOUNT), pengganti legacy dpl_config.
 * Nilai tidak di-seed di sini; seed hanya setelah review bisnis.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.commercial_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->jsonb('value');
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
        Schema::dropIfExists('dpl.commercial_parameters');
    }
};
