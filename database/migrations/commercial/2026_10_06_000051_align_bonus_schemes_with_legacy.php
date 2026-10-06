<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Samakan skema bonus dengan legacy dpl_bonus (name, bonus, discount_invoiced, discount_bonus,
 * discount_rounded, total_discount). buy_quantity/bonus_quantity dihapus karena tidak ada di legacy;
 * effective_discount diganti nama menjadi total_discount. Nilai wajib (default 0) seperti legacy.
 */
return new class extends Migration
{
    /** Kolom angka legacy; semua numeric(10,4) NOT NULL default 0. */
    private const AMOUNTS = ['bonus', 'discount_invoice', 'discount_bonus', 'discount_rounded', 'total_discount'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dpl.bonus_schemes', function (Blueprint $table) {
            $table->renameColumn('effective_discount', 'total_discount');
        });

        Schema::table('dpl.bonus_schemes', function (Blueprint $table) {
            $table->dropColumn(['buy_quantity', 'bonus_quantity']);
            $table->decimal('bonus', 10, 4)->default(0)->after('name');
            $table->decimal('discount_rounded', 10, 4)->default(0)->after('discount_bonus');
        });

        foreach (self::AMOUNTS as $column) {
            DB::table('dpl.bonus_schemes')->whereNull($column)->update([$column => 0]);
        }

        Schema::table('dpl.bonus_schemes', function (Blueprint $table) {
            foreach (self::AMOUNTS as $column) {
                $table->decimal($column, 10, 4)->default(0)->nullable(false)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dpl.bonus_schemes', function (Blueprint $table) {
            foreach (['discount_invoice', 'discount_bonus', 'total_discount'] as $column) {
                $table->decimal($column, 10, 4)->nullable()->default(null)->change();
            }

            $table->dropColumn(['bonus', 'discount_rounded']);
            $table->decimal('buy_quantity', 12, 4)->nullable()->after('name');
            $table->decimal('bonus_quantity', 12, 4)->nullable()->after('buy_quantity');
        });

        Schema::table('dpl.bonus_schemes', function (Blueprint $table) {
            $table->renameColumn('total_discount', 'effective_discount');
        });
    }
};
