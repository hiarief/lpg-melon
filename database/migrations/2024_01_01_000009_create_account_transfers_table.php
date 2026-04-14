<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Transfer dari rek penampung ke rek utama (untuk bayar agen / DO)
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->date('transfer_date');
            $table->bigInteger('amount');           // nominal transfer (bisa lebih dari nilai DO)
            $table->bigInteger('do_equivalent_qty')->default(0); // setara berapa tabung DO
            $table->bigInteger('surplus')->default(0); // kelebihan → tabungan di rek utama
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Pivot: transfer ini melunasi DO mana
        Schema::create('account_transfer_do', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_allocated'); // berapa dari transfer ini dialokasikan ke DO ini
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfer_do');
        Schema::dropIfExists('account_transfers');
    }
};
