<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create((string) config('tax.database.tables.tax_exemptions', 'tax_exemptions'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            // Polymorphic: Customer, User, etc.
            $table->uuidMorphs('exemptable');

            // Optional: specific zone or null for all zones
            $table->foreignUuid('tax_zone_id')->nullable();

            // Exemption details
            $table->string('reason');
            $table->string('certificate_number')->nullable();
            $table->string('document_path')->nullable();

            // Status: pending, approved, rejected
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();

            // Verification
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();

            // Validity period
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['exemptable_type', 'exemptable_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['tax_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('tax.database.tables.tax_exemptions', 'tax_exemptions'));
    }
};
