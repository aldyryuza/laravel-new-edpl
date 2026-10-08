<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konsolidasi / Group RS (user confirmation 2026-10-08):
 * - DPL induk = dpl_proposals bertipe CONSOLIDATION tanpa outlet (nomor ...-C), tidak pernah aktif;
 * - dpl_consolidations menautkan DPL induk, mode (MANUAL / GROUP), group RS, dan wilayah pengaju (routing approver);
 * - dpl_consolidation_outlets menyimpan Kontrak, Bed, "25% Bed" per outlet dan pemilik DPL anak (diisi saat dipecah).
 *
 * Tabel konsolidasi belum berisi data; migration berhenti bila ternyata sudah ada isinya.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->ensureEmpty();

        Schema::table('dpl.dpl_proposals', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable()->change();
        });

        DB::statement("ALTER TABLE dpl.dpl_proposals ADD CONSTRAINT dpl_proposals_outlet_required_check CHECK (outlet_id IS NOT NULL OR proposal_type = 'CONSOLIDATION')");

        Schema::table('dpl.dpl_consolidations', function (Blueprint $table) {
            $table->foreignId('dpl_proposal_id')->after('id')->unique()->constrained('dpl.dpl_proposals')->restrictOnDelete();
            $table->string('mode', 10)->after('status')->comment('MANUAL, GROUP');
            $table->foreignId('outlet_group_id')->nullable()->after('mode')->constrained('outlet_groups')->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->after('outlet_group_id')->comment('Region pengaju (routing approver)')->constrained('sales_regions')->restrictOnDelete();
            $table->foreignId('zone_id')->nullable()->after('region_id')->comment('Zona pengaju (routing approver, nomor DPL induk)')->constrained('sales_zones')->restrictOnDelete();
        });

        Schema::table('dpl.dpl_consolidation_outlets', function (Blueprint $table) {
            $table->boolean('is_contract')->default(false)->after('outlet_id');
            $table->integer('bed_count_snapshot')->nullable()->after('is_contract');
            $table->decimal('bed_order_minimum', 14, 2)->nullable()->after('bed_count_snapshot')->comment('25% Bed');
            $table->foreignId('owner_user_id')->nullable()->after('bed_order_minimum')->comment('Pemilik DPL anak, diisi saat dipecah')->constrained('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->ensureEmpty();

        Schema::table('dpl.dpl_consolidation_outlets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn(['is_contract', 'bed_count_snapshot', 'bed_order_minimum']);
        });

        Schema::table('dpl.dpl_consolidations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropConstrainedForeignId('outlet_group_id');
            $table->dropColumn('mode');
            $table->dropConstrainedForeignId('dpl_proposal_id');
        });

        DB::statement('ALTER TABLE dpl.dpl_proposals DROP CONSTRAINT IF EXISTS dpl_proposals_outlet_required_check');

        Schema::table('dpl.dpl_proposals', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable(false)->change();
        });
    }

    private function ensureEmpty(): void
    {
        if (DB::table('dpl.dpl_consolidations')->exists() || DB::table('dpl.dpl_proposals')->whereNull('outlet_id')->exists()) {
            throw new RuntimeException('Data konsolidasi sudah ada; ubah struktur secara manual.');
        }
    }
};
