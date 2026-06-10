<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table((string) config('tax.database.tables.tax_exemptions', 'tax_exemptions'), function (Blueprint $table): void {
            $table->timestampTz('revoked_at')->nullable()->after('expires_at');
        });
    }
};
