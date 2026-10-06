<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relasi visibility antar user (transformasi legacy users_level_order).
 * Hanya untuk visibility/akses KAE, BUKAN hierarchy approval.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_visibility_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viewer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('relation_type', 30)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['viewer_user_id', 'owner_user_id']);
        });

        DB::statement('ALTER TABLE user_visibility_edges ADD CONSTRAINT user_visibility_edges_not_self_check CHECK (viewer_user_id <> owner_user_id)');
        DB::statement('ALTER TABLE user_visibility_edges ADD CONSTRAINT user_visibility_edges_valid_period_check CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_visibility_edges');
    }
};
