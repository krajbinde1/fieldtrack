<?php

namespace App\Console\Commands;

use App\Services\MobilizerPresenceService;
use Illuminate\Console\Command;

class MonitorMobilizerPresence extends Command
{
    protected $signature = 'presence:monitor';

    protected $description = 'Alert Center Managers when a punched-in mobilizer heartbeat or GPS update is missing';

    public function handle(MobilizerPresenceService $presence): int
    {
        $checked = $presence->monitorDue();
        $this->info("Mobilizer presence check complete ({$checked} open sessions).");

        return self::SUCCESS;
    }
}
