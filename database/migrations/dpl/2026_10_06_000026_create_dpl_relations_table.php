<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relasi antar DPL: AMENDMENT (legacy Make a Copy), RENEWAL (legacy Duplicate),
 * CONSOLIDATION_CHILD. Menggantikan flag legacy ref_id_dpl / from_dpl_action / tag_make_a_copy.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.dpl_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_dpl_id')->constrained('dpl.dpl_proposals')->restrictOnDelete();
            $table->foreignId('target_dpl_id')->index()->constrained('dpl.dpl_proposals')->restrictOnDelete();
            $table->string('relation_type', 30)->comment('AMENDMENT, RENEWAL, CONSOLIDATION_CHILD');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['source_dpl_id', 'target_dpl_id', 'relation_type'], 'dpl_relations_source_target_type_unique');
            $table->index(['source_dpl_id', 'relation_type']);
        });

        DB::statement('ALTER TABLE dpl.dpl_relations ADD CONSTRAINT dpl_relations_not_self_check CHECK (source_dpl_id <> target_dpl_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.dpl_relations');
    }
};
