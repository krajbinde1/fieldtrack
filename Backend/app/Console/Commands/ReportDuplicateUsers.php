<?php

namespace App\Console\Commands;

use App\Services\DuplicateUserReport;
use Illuminate\Console\Command;

class ReportDuplicateUsers extends Command
{
    protected $signature = 'users:duplicate-report';

    protected $description = 'Report duplicate Login IDs and name plus mobile accounts without changing them';

    public function handle(DuplicateUserReport $report): int
    {
        $groups = $report->groups();
        if ($groups === []) {
            $this->info('No duplicate Login IDs or name plus mobile accounts were found.');

            return self::SUCCESS;
        }

        foreach ($groups as $group) {
            $this->newLine();
            $this->warn($group['match'].': '.$group['key']);
            foreach ($group['accounts'] as $account) {
                $this->line(sprintf(
                    'user %s, employee %s, %s, login %s, centers %s, related %s',
                    $account['user_id'] ?? 'none',
                    $account['employee_id'] ?? 'none',
                    $account['name'],
                    $account['login_id'] ?? 'none',
                    implode(', ', $account['centers']) ?: 'none',
                    $account['has_related_records'] ? 'yes' : 'no',
                ));
            }
            foreach ($group['resolution'] as $option) {
                $this->line('- '.$option);
            }
        }

        return self::SUCCESS;
    }
}
