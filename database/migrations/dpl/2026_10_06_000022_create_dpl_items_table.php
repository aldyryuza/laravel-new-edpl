<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item DPL (product + distributor). Commercial terms ada di dpl_item_terms.
 *
 * Belum ada UNIQUE product+distributor per proposal sampai bisnis mengonfirmasi
 * tidak ada skenario duplikat yang valid.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.dpl_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dpl_proposal_id')->constrained('dpl.dpl_proposals')->restrictOnDelete();
            $table->foreignId('product_id')->index()->constrained('products')->restrictOnDelete();
            $table->foreignId('distributor_id')->index()->constrained('distributors')->restrictOnDelete();
            $table->integer('minimum_order')->nullable();
            $table->foreignId('bonus_scheme_id')->nullable()->constrained('dpl.bonus_schemes')->restrictOnDelete();
            $table->boolean('is_direct_delivery')->default(false);
            $table->boolean('is_buyer_reward')->default(false);
            $table->boolean('is_bonus_conversion')->default(false);
            $table->boolean('is_support_satoria')->default(false);
            $table->boolean('is_returnable')->default(false);
            $table->text('remark')->nullable();
            $table->string('netsuite_id', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Juga melayani lookup per dpl_proposal_id.
            $table->index(['dpl_proposal_id', 'distributor_id']);
        });

        DB::statement('ALTER TABLE dpl.dpl_items ADD CONSTRAINT dpl_items_minimum_order_check CHECK (minimum_order IS NULL OR minimum_order >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.dpl_items');
    }
};
