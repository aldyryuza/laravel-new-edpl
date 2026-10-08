<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanda provinsi di Pulau Jawa, untuk margin item Direct ke outlet INSTANSI (Jawa 3, luar Jawa 5).
 * Backfill mengikuti legacy: nama provinsi mengandung JAWA/BANTEN/JAKARTA/YOGYAKARTA.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->boolean('is_java')->default(false)->after('name');
        });

        DB::table('provinces')->where('name', '~*', 'JAWA|BANTEN|JAKARTA|YOGYAKARTA')->update(['is_java' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->dropColumn('is_java');
        });
    }
};
