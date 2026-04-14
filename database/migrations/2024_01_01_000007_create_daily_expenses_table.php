<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pengeluaran operasional harian
        Schema::create('daily_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->date('expense_date');
            $table->enum('category', [
                'bensin',
                'rokok',
                'makan',
                'sopir',
                'oli_servis',
                'ruko',         // sewa ruko / kontrak Angga flat
                'thr',
                'dll',          // pengeluaran tak terduga lainnya
                'gaji_kurir',   // gaji Epul per bulan
                'kontrak_pangkalan', // bayar Sukmedi/Angga
                'tabungan',
                'rumah',
            ]);
            $table->string('description')->nullable();
            $table->bigInteger('amount');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('daily_expenses'); }
};
