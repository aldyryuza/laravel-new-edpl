<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kontak dan lokasi distributor dari legacy (ALAMAT, TELP, FAX, EMAIL, NPWP, LATITUDE, LONGITUDE, RADIUS).
 * Lokasi + radius (meter) dipakai monthly meeting CRM, jadi disimpan di master bersama (public).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->text('address')->nullable()->after('city_name');
            $table->string('phone', 50)->nullable()->after('address');
            $table->string('fax', 50)->nullable()->after('phone');
            $table->string('email', 255)->nullable()->after('fax');
            $table->string('npwp', 30)->nullable()->after('email')->comment('Nomor pokok wajib pajak');
            $table->decimal('latitude', 10, 7)->nullable()->after('npwp');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->integer('meeting_radius_meters')->nullable()->after('longitude')->comment('Radius lokasi monthly meeting CRM, dalam meter');
        });

        DB::statement('ALTER TABLE distributors ADD CONSTRAINT distributors_meeting_radius_check CHECK (meeting_radius_meters IS NULL OR meeting_radius_meters >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE distributors DROP CONSTRAINT IF EXISTS distributors_meeting_radius_check');

        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn(['address', 'phone', 'fax', 'email', 'npwp', 'latitude', 'longitude', 'meeting_radius_meters']);
        });
    }
};
