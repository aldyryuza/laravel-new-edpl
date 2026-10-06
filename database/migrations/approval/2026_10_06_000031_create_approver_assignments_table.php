<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master resolusi approver:
 * - RM : region_id terisi, zone_id null
 * - NSM: zone_id terisi, region_id null
 * - GM/SMD/DMD: keduanya null (global)
 *
 * Bukan users_level_order (itu hanya visibility KAE). Pencegahan assignment aktif ganda untuk
 * scope/role yang sama dilakukan di aplikasi sampai semantik overlap periode difinalkan.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.approver_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('sales_regions')->restrictOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('sales_zones')->restrictOnDelete();
            $table->foreignId('user_id')->index()->constrained('users')->restrictOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Query resolusi: role + region (RM) / role + zone (NSM) / role saja (global).
            $table->index(['role_id', 'region_id', 'zone_id']);
        });

        DB::statement('ALTER TABLE dpl.approver_assignments ADD CONSTRAINT approver_assignments_single_scope_check CHECK (region_id IS NULL OR zone_id IS NULL)');
        DB::statement('ALTER TABLE dpl.approver_assignments ADD CONSTRAINT approver_assignments_valid_period_check CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.approver_assignments');
    }
};
