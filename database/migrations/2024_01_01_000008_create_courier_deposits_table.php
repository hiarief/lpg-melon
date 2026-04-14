<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Setoran kurir ke rekening penampung
        Schema::create('courier_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained()->cascadeOnDelete();
            $table->date('deposit_date');
            $table->bigInteger('amount');         // nominal aktual ditransfer
            $table->bigInteger('admin_fee')->default(0); // biaya admin transfer
            $table->bigInteger('net_amount')->storedAs('amount - admin_fee');
            $table->string('reference_no')->nullable(); // nomor referensi transfer
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('courier_deposits'); }
};
