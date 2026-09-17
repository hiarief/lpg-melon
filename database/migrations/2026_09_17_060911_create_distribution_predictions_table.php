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
        // database/migrations/2026_09_17_000000_create_distribution_predictions_table.php
        Schema::create('distribution_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_period_id')->constrained('periods')->cascadeOnDelete(); // periode basis (mis. Agustus)
            $table->foreignId('target_period_id')->constrained('periods')->cascadeOnDelete(); // periode yg diprediksi (mis. September)
            $table->unsignedTinyInteger('predicted_day');
            $table->date('predicted_date');
            $table->unsignedInteger('predicted_qty');
            $table->decimal('avg_interval', 6, 1)->nullable();
            $table->enum('confidence', ['high', 'medium', 'low']);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['customer_id', 'target_period_id', 'predicted_day'], 'pred_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_predictions');
    }
};
