<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('contract_type', ['none', 'per_do', 'flat_monthly'])->default('none');
            // per_do = Sukmedi: bayar 1000 * total DO per bulan
            // flat_monthly = Angga: bayar 600000 per bulan
            $table->bigInteger('contract_rate')->default(0);
            // For per_do: rate per tabung (1000). For flat_monthly: fixed amount (600000)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('outlets'); }
};
