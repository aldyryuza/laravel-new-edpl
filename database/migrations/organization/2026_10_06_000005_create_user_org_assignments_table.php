<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scope organisasi user (area + team) secara historis.
 * Role tetap di user_has_roles; jangan diduplikasi ke sini.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_org_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->index()->constrained('sales_areas')->restrictOnDelete();
            $table->string('team_code', 20)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Juga melayani lookup per user_id.
            $table->index(['user_id', 'is_active']);
        });

        DB::statement('ALTER TABLE user_org_assignments ADD CONSTRAINT user_org_assignments_valid_period_check CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_org_assignments');
    }
};
