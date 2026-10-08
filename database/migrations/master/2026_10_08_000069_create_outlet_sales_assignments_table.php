<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PIC outlet: AM dan SPV yang bertanggung jawab atas outlet (pengganti legacy dpl_map_outlet_users, pakai user ID).
 * Dipakai untuk menentukan pemilik DPL anak konsolidasi mode Group RS: SPV, bila kosong AM.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('outlet_sales_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->unique()->constrained('outlets')->restrictOnDelete();
            $table->foreignId('am_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('spv_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE outlet_sales_assignments ADD CONSTRAINT outlet_sales_assignments_has_pic_check CHECK (num_nonnulls(am_user_id, spv_user_id) >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlet_sales_assignments');
    }
};
