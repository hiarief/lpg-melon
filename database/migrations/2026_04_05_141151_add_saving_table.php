<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tambah kolom opening_surplus ke periods
        Schema::table('periods', function (Blueprint $table) {
            $table->bigInteger('opening_surplus')->default(0)->after('opening_do_unpaid_qty');
            // Saldo tabungan di rek utama yang dibawa dari periode sebelumnya
        });

        // Tabel tabungan terpisah — mencatat akumulasi surplus per transfer
        Schema::create('savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_transfer_id')->nullable()->constrained()->nullOnDelete();
            // null = entri manual (misal opening_surplus)
            $table->date('entry_date');
            $table->enum('type', ['in', 'out']);
            // in  = surplus dari transfer masuk sebagai tabungan
            // out = tabungan diambil / dipakai
            $table->bigInteger('amount');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings');
        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn('opening_surplus');
        });
    }
};