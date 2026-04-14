<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->year('year');
            $table->tinyInteger('month'); // 1-12
            $table->string('label'); // "Maret 2024"
            $table->enum('status', ['open', 'closed'])->default('open');

            // Opening balances carried from previous period
            $table->bigInteger('opening_stock')->default(0);           // tabung sisa bulan lalu
            $table->bigInteger('opening_cash')->default(0);            // saldo kas fisik awal
            $table->bigInteger('opening_penampung')->default(0);       // saldo rek penampung awal
            $table->bigInteger('opening_external_debt')->default(0);   // piutang external dibawa
            $table->bigInteger('opening_do_unpaid_qty')->default(0);   // tabung cutoff belum bayar

            $table->timestamps();
            $table->unique(['year', 'month']);
        });
    }

    public function down(): void { Schema::dropIfExists('periods'); }
};
