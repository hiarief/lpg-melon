<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Piutang / pinjaman modal dari pihak luar
        Schema::create('external_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->enum('type', ['in', 'out']); // in = terima modal, out = bayar balik
            $table->string('source_name');    // nama pemberi pinjaman
            $table->bigInteger('amount');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Pembayaran kontrak pangkalan per periode
        Schema::create('outlet_contract_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('calculated_amount'); // nilai seharusnya (auto hitung)
            $table->bigInteger('paid_amount')->default(0);
            $table->date('paid_date')->nullable();
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['period_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_contract_payments');
        Schema::dropIfExists('external_debts');
    }
};
