<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // DO dari agen ke pangkalan
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->date('do_date');
            $table->integer('qty'); // jumlah tabung
            $table->bigInteger('price_per_unit')->default(16000);
            $table->bigInteger('total_value')->storedAs('qty * price_per_unit');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->bigInteger('paid_amount')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('delivery_orders'); }
};
