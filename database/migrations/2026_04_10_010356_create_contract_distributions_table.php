<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();

            // ── DATA DISTRIBUSI (auto-sync dari tabel distributions) ──────
            $table->integer('total_qty')->default(0);
            $table->integer('price_per_unit')->default(18000);
            $table->integer('tagihan_distribusi')->default(0);  // total_qty × price_per_unit
            $table->integer('total_setoran')->default(0);       // SUM(paid_amount) dari distributions

            // ── KHUSUS ANGGA (flat_monthly) ───────────────────────────────
            // Tabung yang "ditinggalkan" = tidak ditagih → offset kontrak flat
            $table->integer('qty_ditinggalkan')->default(0);
            $table->integer('nilai_ditinggalkan')->default(0);  // qty_ditinggalkan × price_per_unit
            $table->integer('bayar_kontrak_tunai')->default(0); // jika ada sisa kontrak dibayar cash

            // ── KONTRAK PANGKALAN ─────────────────────────────────────────
            // Angga: flat 600.000/bulan
            // Sukmedi: 1.000 × DO qty (hak Sukmedi dari kita)
            $table->integer('tagihan_kontrak')->default(0);

            // ── HASIL KALKULASI ───────────────────────────────────────────
            // Angga:  saldo = setoran - (tagihan_bersih + sisa_kontrak)
            // Sukmedi: saldo = hak_kontrak - piutang_distribusi
            // Positif = kita bayar ke customer | Negatif = customer masih kurang
            $table->integer('saldo_bersih')->default(0);
            $table->boolean('hutang_ke_customer')->default(false);

            // ── PENYELESAIAN ──────────────────────────────────────────────
            $table->boolean('sudah_diselesaikan')->default(false);
            $table->integer('nominal_diselesaikan')->default(0);

            // ── CATATAN ───────────────────────────────────────────────────
            $table->text('catatan_distribusi')->nullable();
            $table->text('catatan_kontrak')->nullable();
            $table->text('catatan_khusus')->nullable();

            // ── CUTOFF ────────────────────────────────────────────────────
            $table->boolean('is_cutoff')->default(false);
            $table->date('cutoff_date')->nullable();

            $table->timestamps();
            $table->unique(['period_id','customer_id']);
        });

        // Tambah kolom ke distributions
        Schema::table('distributions', function (Blueprint $table) {
            $table->boolean('is_contract')->default(false)->after('notes');
            $table->text('contract_note')->nullable()->after('is_contract');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_distributions');
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropColumn(['is_contract','contract_note']);
        });
    }
};