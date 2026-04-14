<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Distribusi harian oleh kurir ke customer
        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('dist_date');
            $table->integer('qty');
            $table->bigInteger('price_per_unit'); // 18000-20000
            $table->bigInteger('total_value')->storedAs('qty * price_per_unit');
            $table->enum('payment_status', ['paid', 'deferred', 'partial'])->default('paid');
            // deferred = ditunda (Angga & Sukmedi bisa menunda setoran)
            $table->bigInteger('paid_amount')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('distributions'); }
};
