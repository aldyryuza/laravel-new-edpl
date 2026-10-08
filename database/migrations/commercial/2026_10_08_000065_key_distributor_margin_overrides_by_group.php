<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Margin khusus outlet per grup distributor, bukan per cabang (user confirmation 2026-10-08): legacy
 * dpl_distributor_spesial_margin_outlet memakai KODEDIST = kode grup, sehingga satu baris berlaku untuk semua
 * cabang grup itu yang ditandai is_special_case_margin.
 *
 * Tabel baru dibuat di 000062 dan belum berisi data; migration berhenti bila ternyata sudah ada isinya.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->ensureEmpty();

        Schema::table('dpl.distributor_margin_overrides', function (Blueprint $table) {
            $table->dropUnique(['distributor_id', 'outlet_id']);
            $table->dropConstrainedForeignId('distributor_id');
            $table->string('distributor_group_code', 50)->after('id')->comment('distributors.group_code');
            $table->unique(['distributor_group_code', 'outlet_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->ensureEmpty();

        Schema::table('dpl.distributor_margin_overrides', function (Blueprint $table) {
            $table->dropUnique(['distributor_group_code', 'outlet_id']);
            $table->dropColumn('distributor_group_code');
            $table->foreignId('distributor_id')->after('id')->constrained('distributors')->restrictOnDelete();
            $table->unique(['distributor_id', 'outlet_id']);
        });
    }

    private function ensureEmpty(): void
    {
        if (DB::table('dpl.distributor_margin_overrides')->exists()) {
            throw new RuntimeException('dpl.distributor_margin_overrides sudah berisi data; ubah kunci secara manual (petakan distributor -> grup).');
        }
    }
};
