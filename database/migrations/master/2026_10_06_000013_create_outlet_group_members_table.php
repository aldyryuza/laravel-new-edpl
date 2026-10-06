<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keanggotaan outlet pada outlet group, dengan periode berlaku.
 *
 * Belum ada unique membership aktif: apakah satu outlet boleh berada di >1 group aktif
 * masih open decision (docs/Schema.md bagian 22).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('outlet_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_group_id')->constrained('outlet_groups')->restrictOnDelete();
            $table->foreignId('outlet_id')->index()->constrained('outlets')->restrictOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['outlet_group_id', 'outlet_id']);
        });

        DB::statement('ALTER TABLE outlet_group_members ADD CONSTRAINT outlet_group_members_valid_period_check CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlet_group_members');
    }
};
