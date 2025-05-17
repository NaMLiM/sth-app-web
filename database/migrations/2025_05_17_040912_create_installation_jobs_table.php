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
        Schema::create('installation_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_title');
            $table->string('job_reference_id', 50)->nullable()->unique();
            $table->foreignId('technician_id')->constrained('users');
            $table->foreignId('odp_asset_id')->nullable()->constrained('odp_assets')->onDelete('set null');
            $table->enum('job_type', ['NEW_INSTALLATION_PROPOSAL', 'EXISTING_ODP_MAINTENANCE', 'CAPACITY_UPGRADE', 'SURVEY']);
            $table->enum('status', [
                'DRAFT_PROPOSAL',
                'PENDING_APPROVAL',
                'APPROVED',
                'REJECTED',
                'REVISION_REQUESTED',
                'INSTALLATION_IN_PROGRESS',
                'INSTALLATION_COMPLETED',
                'VERIFIED_CLOSED',
                'CANCELLED'
            ]);
            $table->decimal('proposed_latitude', 10, 8)->nullable();
            $table->decimal('proposed_longitude', 11, 8)->nullable();
            $table->decimal('rab_estimated_total_cost', 17, 2)->default(0.00);
            $table->decimal('actual_total_cost', 17, 2)->nullable();
            $table->text('justification')->nullable();
            $table->foreignId('admin_approver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approval_rejection_timestamp')->nullable();
            $table->text('admin_comments')->nullable();
            $table->date('scheduled_installation_date')->nullable();
            $table->timestamp('actual_completion_date')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_installation_jobs_status');
            $table->index('technician_id', 'idx_installation_jobs_technician');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installation_jobs');
    }
};
