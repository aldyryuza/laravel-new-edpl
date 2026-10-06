<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schema PostgreSQL `dpl` untuk tabel transaksi/aturan milik sistem E-DPL.
 * Master bersama lintas sistem (users, akses, organisasi, outlet, product, distributor) tetap di `public`.
 * Schema perlu terdaftar di database.connections.pgsql.search_path agar ikut di-wipe oleh migrate:fresh.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS dpl');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tanpa CASCADE: gagal bila schema masih berisi objek, mencegah kehilangan data tak sengaja.
        DB::statement('DROP SCHEMA IF EXISTS dpl');
    }
};
