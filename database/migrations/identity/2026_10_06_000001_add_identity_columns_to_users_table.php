<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identitas canonical users (docs/Schema.md bagian users).
 *
 * - uuid: identifier publik/opaque untuk integrasi & JWT `sub`. Bukan PK; FK internal tetap users.id.
 * - is_active: status akun.
 * - last_login_at: waktu login terakhir.
 *
 * Tabel users bisa sudah berisi data, jadi uuid ditambah nullable -> backfill -> NOT NULL + UNIQUE.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->boolean('is_active')->default(true)->after('password');
        });

        DB::table('users')->whereNull('uuid')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'last_login_at', 'is_active']);
        });
    }
};
