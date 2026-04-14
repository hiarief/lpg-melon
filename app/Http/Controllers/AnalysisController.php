<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountTransfer;
use App\Models\CourierDeposit;
use App\Models\Customer;
use App\Models\DailyExpense;
use App\Models\DeliveryOrder;
use App\Models\Distribution;
use App\Models\Outlet;
use App\Models\Period;
use App\Models\Saving;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AnalysisController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->period_id ?? Period::current()?->id;
        $period = Period::findOrFail($periodId);
        $periods = Period::orderByDesc('year')->orderByDesc('month')->get();

        // Hitung semua metrik untuk dikirim ke AI
        $metrics = $this->buildMetrics($period);

        return view('analysis.index', compact('period', 'periods', 'metrics'));
    }

    public function analyze(Request $request)
    {
        $periodId = $request->period_id;
        $period = Period::findOrFail($periodId);

        $metrics = $this->buildMetrics($period);

        // Cache key per periode agar tidak re-generate terus
        $cacheKey = "analysis_{$period->id}_v2";

        // Hapus cache jika user minta refresh
        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $analysis = Cache::remember($cacheKey, 3600 * 24, function () use ($metrics, $period) {
            return $this->callClaudeAPI($metrics, $period);
        });

        return response()->json(['analysis' => $analysis, 'metrics' => $metrics]);
    }

    // ────────────────────────────────────────────────────────────────────
    // BUILD METRICS dari database
    // ────────────────────────────────────────────────────────────────────
    private function buildMetrics(Period $period): array
    {
        // DO
        $totalDoQty = DeliveryOrder::where('period_id', $period->id)->where(fn($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))->sum('qty');
        $carryoverQty = DeliveryOrder::where('period_id', $period->id)->where('notes', 'like', '%Carry-over%')->sum('qty');

        // Distribusi
        $distributions = Distribution::where('period_id', $period->id)->get();
        $totalDistQty = $distributions->sum('qty');
        $totalBruto = $distributions->sum(fn($d) => $d->qty * $d->price_per_unit);
        $totalKasRiil = $distributions->sum('paid_amount');
        $totalPiutangCust = $totalBruto - $totalKasRiil;

        // Harga jual rata-rata
        $avgPrice = $totalDistQty > 0 ? round($totalBruto / $totalDistQty) : 0;

        // Distribusi per customer (top/bottom)
        $byCustomer = Distribution::with('customer')
            ->where('period_id', $period->id)
            ->selectRaw('customer_id, SUM(qty) as qty, SUM(qty*price_per_unit) as bruto, SUM(paid_amount) as paid')
            ->groupBy('customer_id')
            ->get()
            ->map(
                fn($r) => [
                    'name' => $r->customer?->name ?? '?',
                    'type' => $r->customer?->type ?? 'regular',
                    'qty' => (int) $r->qty,
                    'bruto' => (int) $r->bruto,
                    'paid' => (int) $r->paid,
                    'piutang' => (int) $r->bruto - (int) $r->paid,
                    'avg_price' => $r->qty > 0 ? round($r->bruto / $r->qty) : 0,
                ],
            )
            ->sortByDesc('qty')
            ->values()
            ->toArray();

        // Hari aktif distribusi
        $hariAktif = $distributions->pluck('dist_date')->map(fn($d) => $d->format('Y-m-d'))->unique()->count();

        // Stok
        $stockSisa = $period->opening_stock + $totalDoQty - $totalDistQty;
        $stockValue = $stockSisa * 16000;

        // Modal
        $totalModal = $totalDistQty * 16000;
        $grossMargin = $totalBruto - $totalModal;
        $marginPct = $totalBruto > 0 ? round(($grossMargin / $totalBruto) * 100, 1) : 0;

        // Pengeluaran per kategori
        $expensesByCat = DailyExpense::where('period_id', $period->id)->selectRaw('category, SUM(amount) as total')->groupBy('category')->pluck('total', 'category')->toArray();

        $totalExpense = array_sum($expensesByCat);
        $totalTF = CourierDeposit::where('period_id', $period->id)->sum('amount');
        $totalAdminFees = CourierDeposit::where('period_id', $period->id)->sum('admin_fee');

        // Pengeluaran per hari rata-rata
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);
        $avgDailyExpense = $hariAktif > 0 ? round($totalExpense / $hariAktif) : 0;

        // Net cashflow
        $netCashflow = $period->opening_cash + $totalKasRiil - $totalExpense - $totalTF - $totalAdminFees;

        // Penampung
        $totalDeposited = CourierDeposit::where('period_id', $period->id)->sum('net_amount');
        $totalTransferred = AccountTransfer::where('period_id', $period->id)->sum('amount');
        $penampungBalance = $period->opening_penampung + $totalDeposited - $totalTransferred;

        // DO unpaid
        $unpaidDoValue = DeliveryOrder::where('period_id', $period->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->get()
            ->sum(fn($d) => $d->remainingAmount());

        // Tabungan
        $savingsIn = Saving::where('period_id', $period->id)->where('type', 'in')->sum('amount');
        $savingsOut = Saving::where('period_id', $period->id)->where('type', 'out')->sum('amount');
        $savingsBal = ($period->opening_surplus ?? 0) + $savingsIn - $savingsOut;

        // Revenue per hari
        $revenuePerHari = $hariAktif > 0 ? round($totalKasRiil / $hariAktif) : 0;
        $tabungPerHari = $hariAktif > 0 ? round($totalDistQty / $hariAktif, 1) : 0;

        // Efficiency: tabung terjual / DO masuk
        $doEfficiency = $totalDoQty > 0 ? round(($totalDistQty / $totalDoQty) * 100, 1) : 0;

        // Contract customer analysis
        $contractCust = collect($byCustomer)->where('type', 'contract');
        $contractPiutang = $contractCust->sum('piutang');
        $contractBruto = $contractCust->sum('bruto');

        return [
            'periode' => $period->label,
            'hari_dalam_bulan' => $daysInMonth,
            'hari_distribusi' => $hariAktif,
            'stok_awal' => $period->opening_stock,
            'do_masuk' => $totalDoQty,
            'carryover_qty' => $carryoverQty,
            'total_distribusi' => $totalDistQty,
            'stok_sisa' => $stockSisa,
            'stok_value' => $stockValue,
            'do_efficiency_pct' => $doEfficiency,
            'tabung_per_hari' => $tabungPerHari,
            'harga_modal' => 16000,
            'harga_jual_rata' => $avgPrice,
            'margin_per_tabung' => $avgPrice - 16000,
            'total_bruto' => $totalBruto,
            'total_kas_riil' => $totalKasRiil,
            'piutang_customer' => $totalPiutangCust,
            'total_modal' => $totalModal,
            'gross_margin' => $grossMargin,
            'margin_pct' => $marginPct,
            'pengeluaran' => $expensesByCat,
            'total_pengeluaran' => $totalExpense,
            'bensin' => $expensesByCat['bensin'] ?? 0,
            'rokok' => $expensesByCat['rokok'] ?? 0,
            'makan' => $expensesByCat['makan'] ?? 0,
            'gaji_kurir' => $expensesByCat['gaji_kurir'] ?? 0,
            'total_tf' => $totalTF,
            'total_admin' => $totalAdminFees,
            'avg_daily_expense' => $avgDailyExpense,
            'net_cashflow' => $netCashflow,
            'penampung_balance' => $penampungBalance,
            'unpaid_do_value' => $unpaidDoValue,
            'tabungan_balance' => $savingsBal,
            'revenue_per_hari' => $revenuePerHari,
            'by_customer' => array_slice($byCustomer, 0, 10),
            'contract_piutang' => $contractPiutang,
            'contract_bruto' => $contractBruto,
            'opening_cash' => $period->opening_cash,
            'opening_penampung' => $period->opening_penampung,
            'opening_surplus' => $period->opening_surplus ?? 0,
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    // CALL CLAUDE API
    // ────────────────────────────────────────────────────────────────────
    private function callClaudeAPI(array $metrics, Period $period): array
    {
        $prompt = $this->buildPrompt($metrics, $period);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-api-key' => config('services.anthropic.key'),  // ← tambahkan ini
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 4000,
            'system' => 'Kamu adalah analis bisnis senior untuk usaha distribusi gas LPG 3KG skala UMKM. Berikan analisa tajam, spesifik, dan actionable dalam Bahasa Indonesia. Selalu sertakan angka konkret dari data yang diberikan. Hindari saran generik. Format response HARUS dalam JSON valid sesuai schema yang diminta.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (!$response->successful()) {
    \Log::error('Claude API Error', [
        'status' => $response->status(),
        'body'   => $response->body(),
    ]);
    return $this->fallbackAnalysis($metrics);
}

        if (!$response->successful()) {
            return $this->fallbackAnalysis($metrics);
        }

        $content = $response->json('content.0.text', '');

        // Bersihkan markdown code block jika ada
        $content = preg_replace('/```json\s*|\s*```/', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->fallbackAnalysis($metrics);
        }

        return $decoded;
    }

    private function buildPrompt(array $m, Period $period): string
    {
        $customerList = collect($m['by_customer'])->map(fn($c) => "  - {$c['name']} ({$c['type']}): {$c['qty']} tabung, harga rata Rp{$c['avg_price']}, piutang Rp{$c['piutang']}")->join("\n");

        $expenseList = collect($m['pengeluaran'])->map(fn($v, $k) => "  - {$k}: Rp " . number_format($v))->join("\n");

        return <<<PROMPT
        Analisa data bisnis distribusi gas LPG 3KG untuk periode {$m['periode']}.

        DATA LENGKAP:
        === OPERASIONAL ===
        - Hari distribusi aktif: {$m['hari_distribusi']} dari {$m['hari_dalam_bulan']} hari
        - Total DO masuk: {$m['do_masuk']} tabung
        - Total distribusi: {$m['total_distribusi']} tabung
        - Stok sisa: {$m['stok_sisa']} tabung (nilai Rp{$m['stok_value']})
        - Efisiensi DO: {$m['do_efficiency_pct']}% (distribusi/DO)
        - Rata-rata distribusi per hari aktif: {$m['tabung_per_hari']} tabung

        === HARGA & MARGIN ===
        - Harga modal: Rp16.000/tabung
        - Harga jual rata-rata: Rp{$m['harga_jual_rata']}/tabung
        - Margin per tabung: Rp{$m['margin_per_tabung']}
        - Total tagihan bruto: Rp{$m['total_bruto']}
        - Kas riil diterima: Rp{$m['total_kas_riil']}
        - Piutang customer belum bayar: Rp{$m['piutang_customer']}
        - Gross margin: Rp{$m['gross_margin']} ({$m['margin_pct']}%)

        === PENGELUARAN ===
        - Total pengeluaran operasional: Rp{$m['total_pengeluaran']}
        - Bensin: Rp{$m['bensin']}
        - Rokok: Rp{$m['rokok']}
        - Makan: Rp{$m['makan']}
        - Gaji kurir: Rp{$m['gaji_kurir']}
        - Transfer ke penampung: Rp{$m['total_tf']}
        - Biaya admin TF: Rp{$m['total_admin']}
        - Rata-rata pengeluaran per hari aktif: Rp{$m['avg_daily_expense']}

        === KEUANGAN ===
        - Net cashflow: Rp{$m['net_cashflow']}
        - Saldo penampung: Rp{$m['penampung_balance']}
        - Piutang DO belum lunas ke agen: Rp{$m['unpaid_do_value']}
        - Saldo tabungan/surplus: Rp{$m['tabungan_balance']}
        - Piutang customer kontrak: Rp{$m['contract_piutang']}

        === CUSTOMER (top 10 by volume) ===
        {$customerList}

        === PENGELUARAN DETAIL ===
        {$expenseList}

        Berikan analisa mendalam dalam format JSON berikut (HANYA JSON, tidak ada teks lain):
        {
          "skor_kesehatan": {
            "nilai": 75,
            "label": "Cukup Sehat",
            "warna": "yellow",
            "ringkasan": "penjelasan 1 kalimat singkat tentang kondisi bisnis"
          },
          "kpi_utama": [
            {
              "nama": "Margin per Tabung",
              "nilai": "Rp 2.000",
              "target": "Rp 2.500",
              "status": "warning",
              "catatan": "di bawah target"
            }
          ],
          "masalah_utama": [
            {
              "prioritas": 1,
              "judul": "Judul masalah singkat",
              "dampak": "Rp xxx hilang/bulan",
              "akar_masalah": "Penjelasan 2-3 kalimat mengapa ini terjadi berdasarkan data",
              "urgensi": "tinggi"
            }
          ],
          "pemborosan": [
            {
              "kategori": "Nama kategori",
              "nilai_aktual": "Rp xxx",
              "nilai_ideal": "Rp xxx",
              "selisih": "Rp xxx",
              "persen_dari_revenue": "x.x%",
              "rekomendasi": "Saran spesifik dan actionable"
            }
          ],
          "cara_naikkan_surplus": [
            {
              "strategi": "Nama strategi singkat",
              "potensi_tambahan": "Rp xxx/bulan",
              "cara": "Langkah konkret 2-3 kalimat",
              "kesulitan": "mudah|sedang|sulit",
              "timeframe": "segera|1 bulan|3 bulan"
            }
          ],
          "cara_naikkan_revenue": [
            {
              "aksi": "Nama aksi",
              "potensi": "Rp xxx/bulan",
              "detail": "Penjelasan spesifik dengan angka dari data",
              "syarat": "Apa yang dibutuhkan"
            }
          ],
          "cost_yang_bisa_dikurangi": [
            {
              "pos": "Nama pos biaya",
              "nilai_sekarang": "Rp xxx",
              "target_ideal": "Rp xxx",
              "potensi_hemat": "Rp xxx",
              "cara": "Langkah konkret"
            }
          ],
          "struktur_penting": {
            "cash_flow_health": "sehat|perhatian|kritis",
            "piutang_risk": "rendah|sedang|tinggi",
            "do_risk": "rendah|sedang|tinggi",
            "analisa": "Analisa 3-4 kalimat tentang struktur keuangan bisnis secara keseluruhan"
          },
          "strategi_paling_efektif": {
            "judul": "Nama strategi",
            "estimasi_dampak": "Rp xxx tambahan per bulan",
            "langkah": ["Langkah 1 konkret", "Langkah 2 konkret", "Langkah 3 konkret"],
            "kenapa_ini": "Alasan 2-3 kalimat mengapa strategi ini paling berdampak"
          },
          "prediksi_bulan_depan": {
            "proyeksi_revenue": "Rp xxx",
            "proyeksi_surplus": "Rp xxx",
            "asumsi": "Asumsi yang digunakan",
            "risiko": "Risiko utama yang perlu diwaspadai"
          }
        }
        PROMPT;
    }

    private function fallbackAnalysis(array $m): array
    {
        $marginPct = $m['margin_pct'];
        $skor = $marginPct >= 15 ? 80 : ($marginPct >= 10 ? 65 : 45);

        return [
            'skor_kesehatan' => [
                'nilai' => $skor,
                'label' => $skor >= 75 ? 'Sehat' : ($skor >= 60 ? 'Cukup Sehat' : 'Perlu Perhatian'),
                'warna' => $skor >= 75 ? 'green' : ($skor >= 60 ? 'yellow' : 'red'),
                'ringkasan' => 'Analisa tidak tersedia, data dasar ditampilkan.',
            ],
            'kpi_utama' => [['nama' => 'Gross Margin', 'nilai' => 'Rp ' . number_format($m['gross_margin']), 'target' => '-', 'status' => 'info', 'catatan' => $m['margin_pct'] . '% dari bruto'], ['nama' => 'Net Cashflow', 'nilai' => 'Rp ' . number_format($m['net_cashflow']), 'target' => '-', 'status' => $m['net_cashflow'] >= 0 ? 'good' : 'bad', 'catatan' => $m['net_cashflow'] >= 0 ? 'Positif' : 'Negatif'], ['nama' => 'Piutang Customer', 'nilai' => 'Rp ' . number_format($m['piutang_customer']), 'target' => 'Rp 0', 'status' => $m['piutang_customer'] > 0 ? 'warning' : 'good', 'catatan' => 'Belum terbayar']],
            'masalah_utama' => [['prioritas' => 1, 'judul' => 'Data analisa AI tidak tersedia', 'dampak' => '-', 'akar_masalah' => 'Silakan periksa koneksi API dan coba lagi.', 'urgensi' => 'sedang']],
            'pemborosan' => [],
            'cara_naikkan_surplus' => [],
            'cara_naikkan_revenue' => [],
            'cost_yang_bisa_dikurangi' => [],
            'struktur_penting' => ['cash_flow_health' => 'perhatian', 'piutang_risk' => 'sedang', 'do_risk' => 'sedang', 'analisa' => 'Analisa tidak tersedia.'],
            'strategi_paling_efektif' => ['judul' => '-', 'estimasi_dampak' => '-', 'langkah' => [], 'kenapa_ini' => '-'],
            'prediksi_bulan_depan' => ['proyeksi_revenue' => '-', 'proyeksi_surplus' => '-', 'asumsi' => '-', 'risiko' => '-'],
        ];
    }
}