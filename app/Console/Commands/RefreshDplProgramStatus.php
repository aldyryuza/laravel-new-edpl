<?php

namespace App\Console\Commands;

use App\Services\Dpl\DplApprovalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Aktifkan DPL approved yang Date Start-nya sudah tiba dan expire DPL aktif yang lewat Date End.
 * Dijadwalkan harian (routes/console.php); aman dijalankan berulang.
 */
#[Signature('dpl:refresh-program-status')]
#[Description('Aktifkan DPL approved yang periodenya mulai dan expire DPL yang periodenya berakhir')]
class RefreshDplProgramStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DplApprovalService $approvals): int
    {
        $result = $approvals->refreshProgramStatuses();

        $this->info("DPL diaktifkan: {$result['activated']}, DPL expired: {$result['expired']}.");

        return self::SUCCESS;
    }
}
