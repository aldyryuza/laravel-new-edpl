<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Open decision #3: satu outlet hanya boleh punya satu keanggotaan group aktif.
 * Keanggotaan aktif = valid_until IS NULL (terbuka). Pindah group menutup keanggotaan lama
 * (isi valid_until) lalu membuka yang baru, sehingga histori tetap ada.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX outlet_group_members_one_open_per_outlet_unique ON outlet_group_members (outlet_id) WHERE valid_until IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS outlet_group_members_one_open_per_outlet_unique');
    }
};
