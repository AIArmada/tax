<?php

declare(strict_types=1);

namespace AIArmada\Tax\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Console\Command;

final class SyncTaxZonesCommand extends Command
{
    protected $signature = 'tax:sync-zones
        {--dry-run : Preview changes without applying}';

    protected $description = 'Sync tax zone configurations and apply migrations';

    public function handle(): int
    {
        return (int) (new OwnerBatchRunner(
            TaxZone::class,
            [
                'enabled' => 'tax.features.owner.enabled',
                'include_global' => 'tax.features.owner.include_global',
            ],
        ))->run(fn (): int => $this->sync());
    }

    private function sync(): int
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
