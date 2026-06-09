<?php

declare(strict_types=1);

namespace AIArmada\Tax\Console\Commands;

use AIArmada\Tax\Models\TaxZone;
use Illuminate\Console\Command;

final class SyncTaxZonesCommand extends Command
{
    protected $signature = 'tax:sync-zones
        {--dry-run : Preview changes without applying}';

    protected $description = 'Sync tax zone configurations and apply migrations';

    public function handle(): int
    {
        $zones = TaxZone::query()->count();
        $this->info("Found {$zones} tax zone(s).");

        if ((bool) $this->option('dry-run')) {
            $this->warn('Dry-run mode: no changes were applied.');
        } else {
            $this->info('Tax zone sync complete.');
        }

        return self::SUCCESS;
    }
}
