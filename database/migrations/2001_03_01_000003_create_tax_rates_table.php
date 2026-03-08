<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create((string) config('tax.database.tables.tax_rates', 'tax_rates'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->uuid('zone_id');
            $table->string('tax_class')->default('standard');
            $table->string('name');
            $table->text('description')->nullable();

            // Rate as basis points (600 = 6.00%)
            $table->unsignedInteger('rate');

            // Compound tax (applied after other taxes)
            $table->boolean('is_compound')->default(false);

            // Applies to shipping calculations
            $table->boolean('is_shipping')->default(true);

            // Priority for compound tax ordering
            $table->integer('priority')->default(0);

            // Flags
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['zone_id', 'tax_class', 'is_active']);
            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('tax.database.tables.tax_rates', 'tax_rates'));
    }
};
