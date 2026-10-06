<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat keluar per DPL + distributor (legacy dpl_exit_letter).
 * Nama/jabatan Kacab disimpan sebagai snapshot agar dokumen tidak berubah saat master berubah.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.exit_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dpl_proposal_id')->index()->constrained('dpl.dpl_proposals')->restrictOnDelete();
            $table->foreignId('distributor_id')->index()->constrained('distributors')->restrictOnDelete();
            $table->string('letter_number', 100)->nullable();
            $table->date('letter_date')->nullable();
            $table->string('branch_head_name_snapshot', 255)->nullable();
            $table->string('branch_head_position_snapshot', 150)->nullable();
            $table->string('status', 30);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.exit_letters');
    }
};
