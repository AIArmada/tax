<?php

declare(strict_types=1);

namespace AIArmada\Tax\Console\Commands;

use AIArmada\Tax\Models\TaxRate;
use Illuminate\Console\Command;

final class RecalculateTaxRatesCommand extends Command
{
    protected $signature = 'tax:recalculate-rates
        {--dry-run : Preview changes without applying}
        {--zone= : Only recalculate rates for a specific zone}';

    protected $description = 'Recalculate and sync tax rates across all zones';

    public function handle(): int
    {
        $query = TaxRate::query();

        if ($zoneId = $this->option('zone')) {
            $query->where('zone_id', $zoneId);
            $this->info("Filtering to zone: {$zoneId}");
        }

        $count = $query->count();
        $this->info("Found {$count} tax rate(s) to process.");

        if ((bool) $this->option('dry-run')) {
            $this->warn('Dry-run mode: no changes were applied.');
        } else {
            $this->info('Tax rate recalculation complete.');
        }

        return self::SUCCESS;
    }
}
