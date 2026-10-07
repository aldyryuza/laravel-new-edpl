<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Counter nomor DPL per periode (YYMM) + zona. Format nomor: {YYMM}-{kode perusahaan}-{kode zona}-{urut 4 digit},
 * mis. 2610-SAI-E-0039; urutan mulai lagi dari 1 setiap bulan, terpisah per zona.
 * Dinaikkan secara atomik (INSERT ... ON CONFLICT DO UPDATE ... RETURNING) sehingga aman untuk request bersamaan.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.dpl_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->char('period', 4)->comment('YYMM, mis. 2610');
            $table->string('zone_code', 10);
            $table->integer('last_number');
            $table->timestamps();

            $table->unique(['period', 'zone_code']);
        });

        DB::statement('ALTER TABLE dpl.dpl_number_sequences ADD CONSTRAINT dpl_number_sequences_last_number_check CHECK (last_number > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.dpl_number_sequences');
    }
};
