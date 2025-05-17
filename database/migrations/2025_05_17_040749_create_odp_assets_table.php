<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('odp_assets', function (Blueprint $table) {
            $table->id();
            $table->string('odp_unique_identifier', 100)->unique();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->text('address_detail')->nullable();
            $table->enum('status', ['PLANNED', 'ACTIVE', 'MAINTENANCE', 'DECOMMISSIONED', 'LEGACY_ACTIVE']);
            $table->date('installation_date')->nullable();
            $table->integer('capacity_ports')->nullable();
            $table->string('odp_type', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_legacy_data')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('last_updated_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['latitude', 'longitude'], 'idx_odp_assets_coordinates');
            $table->index('status', 'idx_odp_assets_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odp_assets');
    }
};
