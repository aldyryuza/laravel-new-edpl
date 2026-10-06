<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master distributor beserta cabangnya (self reference parent_distributor_id).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('distributors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 255);
            $table->string('group_code', 50)->nullable();
            $table->string('city_name', 150)->nullable();
            $table->boolean('is_branch')->default(false);
            $table->foreignId('parent_distributor_id')->nullable()->index()->constrained('distributors')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('netsuite_id', 255)->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE distributors ADD CONSTRAINT distributors_not_self_parent_check CHECK (parent_distributor_id IS NULL OR parent_distributor_id <> id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributors');
    }
};
