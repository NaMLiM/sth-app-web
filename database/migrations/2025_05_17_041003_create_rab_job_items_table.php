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
        Schema::create('rab_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_job_id')->constrained('installation_jobs')->onDelete('cascade');
            $table->foreignId('rab_item_id')->constrained('rab_items')->restrictOnDelete(); // atau onDelete('set null') jika ingin lebih fleksibel tapi perlu penanganan di aplikasi
            $table->decimal('quantity', 10, 2);
            $table->decimal('price_at_creation', 15, 2);
            $table->decimal('line_total', 17, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rab_job_items');
    }
};
