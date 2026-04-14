<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['regular', 'contract'])->default('regular');
            // contract = Angga & Sukmedi: harga jual tetap 18000, setoran bisa ditunda
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            // Link customer ke outlet jika customer tersebut adalah pemilik pangkalan
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('customers'); }
};
