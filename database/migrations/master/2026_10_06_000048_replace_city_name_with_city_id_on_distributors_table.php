<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kota distributor merujuk master kota (city_id), menggantikan teks bebas city_name agar tidak ada
 * dua sumber data kota. Teks lama dicocokkan ke cities.name (tanpa beda huruf besar/kecil); bila ada
 * teks yang tidak cocok, migration dihentikan supaya data tidak hilang diam-diam.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('group_code')->index()->constrained('cities')->restrictOnDelete();
        });

        DB::statement('UPDATE distributors d SET city_id = c.id FROM cities c WHERE d.city_name IS NOT NULL AND UPPER(TRIM(d.city_name)) = UPPER(c.name)');

        $unmatched = DB::table('distributors')
            ->whereNotNull('city_name')
            ->where('city_name', '<>', '')
            ->whereNull('city_id')
            ->distinct()
            ->pluck('city_name');

        if ($unmatched->isNotEmpty()) {
            throw new RuntimeException('Nama kota distributor tidak cocok dengan master kota: '.$unmatched->implode(', ').'. Lengkapi master kota (import Excel) lalu jalankan ulang.');
        }

        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn('city_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->string('city_name', 150)->nullable()->after('group_code');
        });

        DB::statement('UPDATE distributors d SET city_name = c.name FROM cities c WHERE c.id = d.city_id');

        Schema::table('distributors', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropIndex(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
