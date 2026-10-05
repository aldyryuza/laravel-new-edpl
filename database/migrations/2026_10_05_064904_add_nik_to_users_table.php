<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NIK dipakai sebagai identitas login.
 *
 * Langsung NOT NULL karena tabel users masih kosong saat migration ini dibuat.
 * Jika dijalankan pada database yang sudah berisi user, isi NIK lebih dulu
 * (tambah nullable -> backfill -> enforce NOT NULL).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nik', 30)->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropColumn('nik');
        });
    }
};
