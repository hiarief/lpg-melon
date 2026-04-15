@extends('layouts.app')
@section('title','Cashflow Harian')
@section('content')

@php
/**
 * ══════════════════════════════════════════════════════
 * CATATAN VARIABEL PENTING
 * ══════════════════════════════════════════════════════
 * $pred   = array hasil buildPrediction() dari controller
 * $predObj = alias bersih untuk dipakai di blade
 *            (JANGAN pakai $p karena bentrok dengan loop $periods)
 *
 * $netKas       = sudah di-pass dari controller (dailyBalance[$daysInMonth])
 * $finalBankBal = sudah di-pass dari controller (dailyBankBalance[$daysInMonth])
 */
$predObj = $pred;   // alias aman — $p akan tertimpa loop foreach $periods di atas

$remainDays  = $predObj['remainDays'];
$todayDay    = $predObj['today'];

// ── Ringkasan cashflow ────────────────────────────────────────────────────
$totalAvailable  = $openingCash + $totalIncome;
$totalKasKeluar  = $totalExpense + $totalDeposits + $totalAdminFees;
$totalNet        = $netKas;   // Saldo KAS fisik saat ini = net cashflow

// Rata-rata harian surplus TF ke rek utama
$totalSurplusAll = array_sum(array_column($transfersByDay, 'surplus'));

// ── Hari aktif cashflow ───────────────────────────────────────────────────
$cfActiveDays = collect(range(1,$daysInMonth))->filter(fn($d) =>
    ($salesByDay[$d] ?? 0) > 0 || ($dayTotals[$d] ?? 0) > 0 ||
    ($depositsByDay[$d]['total'] ?? 0) > 0
)->count();
$cfActiveDays = max($cfActiveDays, 1);

// ── Rata-rata per hari aktif ──────────────────────────────────────────────
$avgSales      = round($totalIncome    / $cfActiveDays);
$avgExpense    = round($totalExpense   / $cfActiveDays);
$avgDeposit    = round($totalDeposits  / $cfActiveDays);
$avgAdminFee   = round($totalAdminFees / $cfActiveDays);
$avgNet        = round($totalNet       / $cfActiveDays);
$avgTfRekUtama = $totalTransferred > 0 ? round($totalTransferred / $cfActiveDays) : 0;

$avgCatByDay = [];
foreach ($categories as $cat) {
    $avgCatByDay[$cat] = ($categoryTotals[$cat] ?? 0) > 0
        ? round($categoryTotals[$cat] / $cfActiveDays) : 0;
}

// ── Rasio cashflow ────────────────────────────────────────────────────────
// Rasio operasional = biaya ops / MARGIN BERSIH (konsisten dengan controller)
$rasioOperasional = $totalMargin > 0 ? ($totalExpense / $totalMargin * 100) : 0;
// Rasio gross = semua kas keluar / total kas tersedia
$rasioGross       = $totalAvailable > 0 ? ($totalKasKeluar / $totalAvailable * 100) : 0;

// ── Rata-rata saldo harian ────────────────────────────────────────────────
$avgDailyCashBal = collect(range(1,$daysInMonth))
    ->filter(fn($d) => ($salesByDay[$d]??0)>0||($dayTotals[$d]??0)>0||($depositsByDay[$d]['total']??0)>0||($d===1&&$openingCash>0))
    ->map(fn($d) => $dailyBalance[$d])->avg() ?? 0;

$avgDailyBankBal = collect(range(1,$daysInMonth))
    ->filter(fn($d) => ($depositsByDay[$d]['total']??0)>0||($transfersByDay[$d]['total']??0)>0||($d===1&&$openingPenampung>0))
    ->map(fn($d) => $dailyBankBalance[$d])->avg() ?? 0;

// ── Data chart JS ─────────────────────────────────────────────────────────
$jsLabels    = range(1, $daysInMonth);
$jsSales     = array_map(fn($d) => $salesByDay[$d] ?? 0, $jsLabels);
$jsExpenses  = array_map(fn($d) => $dayTotals[$d] ?? 0, $jsLabels);
$jsDeposits  = array_map(fn($d) => $depositsByDay[$d]['total'] ?? 0, $jsLabels);
$jsTransfers = array_map(fn($d) => $transfersByDay[$d]['total'] ?? 0, $jsLabels);
$jsCashBal   = array_map(fn($d) => $dailyBalance[$d], $jsLabels);
$jsBankBal   = array_map(fn($d) => $dailyBankBalance[$d], $jsLabels);

$catLabelsSafe = array_values(\App\Models\DailyExpense::$categoryLabels);
$catTotalsSafe = array_values($categoryTotals);

// ════════════════════════════════════════════════════════════════════════
// REGRESI LINEAR OLS
// Dihitung di PHP menggunakan pasangan (hari, nilai) dari data aktual
// SEMUA berbasis MARGIN BERSIH — konsisten dengan WMA
// ════════════════════════════════════════════════════════════════════════

$olsMarginPairs = [];
$olsExpPairs    = [];
$olsDepPairs    = [];

for ($d = 1; $d <= $todayDay; $d++) {
    $margin = $marginByDay[$d]['margin'] ?? 0;
    $e      = $dayTotals[$d]  ?? 0;
    $dep    = $depositsByDay[$d]['total'] ?? 0;
    // Hanya masukkan hari dengan aktivitas agar slope tidak bias ke nol
    if ($margin > 0 || $e > 0 || $dep > 0) {
        $olsMarginPairs[] = [$d, $margin];
        $olsExpPairs[]    = [$d, $e];
        if ($dep > 0) $olsDepPairs[] = [$d, $dep];
    }
}

/**
 * OLS: mengembalikan [b0, b1, r2]
 * b1 = (n·Σxy − Σx·Σy) / (n·Σx² − (Σx)²)
 * b0 = (Σy − b1·Σx) / n
 * R² = 1 − (SS_res / SS_tot)
 */
function calcOLS(array $pairs): array {
    $n = count($pairs);
    if ($n < 2) return [0, 0, 0];

    $sumX = $sumY = $sumXY = $sumX2 = 0.0;
    foreach ($pairs as [$x, $y]) {
        $sumX  += $x; $sumY  += $y;
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }

    $denom = $n * $sumX2 - $sumX * $sumX;
    if (abs($denom) < 1e-10) return [round($sumY/$n), 0, 0];

    $b1 = ($n * $sumXY - $sumX * $sumY) / $denom;
    $b0 = ($sumY - $b1 * $sumX) / $n;

    $meanY = $sumY / $n;
    $ssTot = 0.0; $ssRes = 0.0;
    foreach ($pairs as [$x, $y]) {
        $yHat   = $b0 + $b1 * $x;
        $ssRes += ($y - $yHat) ** 2;
        $ssTot += ($y - $meanY)  ** 2;
    }
    $r2 = $ssTot > 0 ? max(0, 1 - $ssRes / $ssTot) : 0;

    return [round($b0), round($b1, 2), round($r2, 3)];
}

[$olsMarginB0, $olsMarginB1, $olsMarginR2] = calcOLS($olsMarginPairs);
[$olsExpB0,    $olsExpB1,    $olsExpR2]    = calcOLS($olsExpPairs);
[$olsDepB0,    $olsDepB1,    $olsDepR2]    = calcOLS($olsDepPairs);

// Proyeksikan hari $todayDay+1 s/d $daysInMonth
$olsProjMargin = 0;
$olsProjExp    = 0;
$olsProjAdmin  = 0;

for ($d = $todayDay + 1; $d <= $daysInMonth; $d++) {
    $olsProjMargin += max(0, $olsMarginB0 + $olsMarginB1 * $d);
    $olsProjExp    += max(0, $olsExpB0    + $olsExpB1    * $d);
    // Admin: pakai rate simpel (tidak cukup data utk OLS terpisah)
    $olsProjAdmin  += $predObj['adminRateSimple'];
}

$olsProjMargin = round($olsProjMargin);
$olsProjExp    = round($olsProjExp);
$olsProjAdmin  = round($olsProjAdmin);

// Gaji OLS: gunakan rate sama dengan WMA
$olsGajiAktual   = $predObj['gajiAktualSdIni'];
$olsPredGajiSisa = $predObj['predGajiSisa'];
$olsPredGajiTotal = $predObj['predGajiTotal'];

// Saldo KAS prediksi OLS
// = KAS kini + margin OLS + piutang - ops OLS - admin OLS
$olsPredKas = $netKas
    + $olsProjMargin
    + $predObj['piutangCairEstimasi']
    - $olsProjExp
    - $olsProjAdmin;

// Label tren slope
$olsMarginTrend = $olsMarginB1 > 500  ? '📈 Naik'  : ($olsMarginB1 < -500 ? '📉 Turun' : '➡ Stabil');
$olsExpTrend    = $olsExpB1    > 100  ? '📈 Naik'  : ($olsExpB1    < -100 ? '📉 Turun' : '➡ Stabil');

// R² quality label
$olsMarginQual = $olsMarginR2 >= 0.7 ? 'Baik' : ($olsMarginR2 >= 0.4 ? 'Cukup' : 'Lemah');
$olsExpQual    = $olsExpR2    >= 0.7 ? 'Baik' : ($olsExpR2    >= 0.4 ? 'Cukup' : 'Lemah');

// Confidence OLS
$olsDataPoints = count($olsMarginPairs);
$olsConfidence = min(95, round(
    $olsMarginR2 * 40
    + $olsExpR2  * 20
    + min($olsDataPoints, 20) / 20 * 40
));

// ════════════════════════════════════════════════════════════════════════
// MONTE CARLO — parameter dikirim ke JS
// Mean & StdDev dihitung PHP, simulasi 10.000 iter di browser
// Semua berbasis MARGIN BERSIH (konsisten)
// ════════════════════════════════════════════════════════════════════════
$mcMarginData = [];
$mcExpData    = [];
$mcAdminData  = [];

for ($d = 1; $d <= $todayDay; $d++) {
    $margin = $marginByDay[$d]['margin'] ?? 0;
    $e      = $dayTotals[$d] ?? 0;
    $admin  = $depositsByDay[$d]['admin'] ?? 0;
    if ($margin > 0 || $e > 0) {
        $mcMarginData[] = $margin;
        $mcExpData[]    = $e;
        $mcAdminData[]  = $admin;
    }
}

function calcMeanStd(array $arr): array {
    $n = count($arr);
    if ($n === 0) return [0, 0];
    $mean = array_sum($arr) / $n;
    if ($n === 1) return [$mean, 0];
    $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $arr)) / ($n - 1);
    return [$mean, sqrt($variance)];
}

[$mcMarginMean, $mcMarginStd] = calcMeanStd($mcMarginData);
[$mcExpMean,    $mcExpStd]    = calcMeanStd($mcExpData);
[$mcAdminMean,  $mcAdminStd]  = calcMeanStd($mcAdminData);

// Korelasi margin-expense (agar MC lebih realistis)
$mcN = count($mcMarginData);
$mcCorrCoef = 0;
if ($mcN > 2 && $mcMarginStd > 0 && $mcExpStd > 0) {
    $covSum = 0;
    for ($i = 0; $i < $mcN; $i++) {
        $covSum += ($mcMarginData[$i] - $mcMarginMean) * ($mcExpData[$i] - $mcExpMean);
    }
    $mcCorrCoef = max(-1, min(1, ($covSum / ($mcN - 1)) / ($mcMarginStd * $mcExpStd)));
}

@endphp

<div x-data="{ showForm: false }">

{{-- ══ HEADER ══ --}}
<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;margin-bottom:12px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:15px;font-weight:600;color:var(--text1)">💸 Cashflow Harian</span>
        <form method="GET" action="{{ route('cashflow.index') }}">
            <select name="period_id" onchange="this.form.submit()" class="field-select" style="padding:6px 10px;font-size:12px;width:auto">
                @foreach($periods as $periodItem)
                    <option value="{{ $periodItem->id }}" {{ $periodItem->id == $period->id ? 'selected' : '' }}>{{ $periodItem->label }}</option>
                @endforeach
            </select>
        </form>
        <span style="font-size:10px;color:var(--text3)">{{ $cfActiveDays }} hari aktif</span>
    </div>
    @if($period->status === 'open')
        <button @click="showForm = !showForm" class="btn-primary btn-sm">+ Input Pengeluaran</button>
    @endif
</div>

{{-- ══ FORM ══ --}}
@if($period->status === 'open')
<div x-show="showForm" x-cloak x-transition style="margin-bottom:10px">
    <div class="s-card">
        <div class="s-card-header">+ Input Pengeluaran</div>
        <div style="padding:12px 14px">
            <form method="POST" action="{{ route('cashflow.store') }}"
                  style="display:flex;flex-direction:column;gap:10px">
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <label class="field-label">Tanggal</label>
                        <input type="date" name="expense_date" value="{{ date('Y-m-d') }}" class="field-input" required>
                    </div>
                    <div>
                        <label class="field-label">Kategori</label>
                        <select name="category" class="field-select" required>
                            @foreach(\App\Models\DailyExpense::$categoryLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Nominal (Rp)</label>
                        <input type="number" name="amount" min="1" class="field-input" required>
                    </div>
                    <div>
                        <label class="field-label">Keterangan</label>
                        <input type="text" name="description" class="field-input" placeholder="Opsional">
                    </div>
                </div>
                <div>
                    <button type="submit" class="btn-primary">✅ Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ══ RINGKASAN CASHFLOW ══ --}}
<div class="s-card">
    <div class="s-card-header">📊 Ringkasan Cashflow</div>
    <div style="padding:12px 14px;display:flex;flex-direction:column;gap:14px">

        {{-- Pemasukan --}}
        <div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                <span style="width:7px;height:7px;border-radius:50%;background:var(--melon);flex-shrink:0"></span>
                <span style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px">Pemasukan</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">Penjualan gas (kas diterima)</div>
                    <div style="font-size:16px;font-weight:600;color:var(--melon-dark);margin-top:2px">Rp {{ number_format($totalIncome) }}</div>
                    <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($avgSales) }}/hari</div>
                </div>
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">Margin bersih (setelah HPP)</div>
                    <div style="font-size:16px;font-weight:600;color:#0369a1;margin-top:2px">Rp {{ number_format($totalMargin) }}</div>
                    <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($totalMargin > 0 && $cfActiveDays > 0 ? round($totalMargin/$cfActiveDays) : 0) }}/hari</div>
                </div>
                @if($openingCash > 0)
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">Saldo awal KAS</div>
                    <div style="font-size:16px;font-weight:600;color:#b45309;margin-top:2px">Rp {{ number_format($openingCash) }}</div>
                    <div style="font-size:10px;color:var(--text3)">cutoff periode lalu</div>
                </div>
                @endif
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">Total kas tersedia</div>
                    <div style="font-size:16px;font-weight:600;color:var(--text1);margin-top:2px">Rp {{ number_format($totalAvailable) }}</div>
                    <div style="font-size:10px;color:var(--text3)">saldo awal + penjualan riil</div>
                </div>
            </div>
        </div>

        {{-- Pengeluaran --}}
        <div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                <span style="width:7px;height:7px;border-radius:50%;background:#ef4444;flex-shrink:0"></span>
                <span style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px">Pengeluaran Operasional</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">Total pengeluaran</div>
                    <div style="font-size:16px;font-weight:600;color:#dc2626;margin-top:2px">Rp {{ number_format($totalExpense) }}</div>
                    <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($avgExpense) }}/hari</div>
                </div>
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">
                        Rasio ops / margin bersih
                        <span style="font-size:9px;color:var(--text3);font-style:italic">biaya ÷ margin</span>
                    </div>
                    <div style="font-size:16px;font-weight:600;color:{{ $rasioOperasional > 35 ? '#b45309' : 'var(--melon-dark)' }};margin-top:2px">{{ number_format($rasioOperasional,1) }}%</div>
                    <div style="height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden;margin:4px 0">
                        <div style="height:100%;width:{{ min($rasioOperasional,100) }}%;background:{{ $rasioOperasional > 35 ? '#f59e0b' : 'var(--melon)' }};border-radius:2px"></div>
                    </div>
                    <div style="font-size:10px;color:var(--text3)">ideal &lt;35% dari margin</div>
                </div>
                <div class="card" style="padding:10px 12px;{{ $rasioGross > 80 ? 'border-color:#fca5a5' : '' }}">
                    <div style="font-size:10px;color:var(--text3)">
                        Rasio gross kas keluar
                        <span style="font-size:9px;color:var(--text3);font-style:italic">(ops+deposit+admin) ÷ kas tersedia</span>
                    </div>
                    <div style="font-size:16px;font-weight:600;color:{{ $rasioGross > 80 ? '#dc2626' : 'var(--melon-dark)' }};margin-top:2px">{{ number_format($rasioGross,1) }}%</div>
                    <div style="height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden;margin:4px 0">
                        <div style="height:100%;width:{{ min($rasioGross,100) }}%;background:{{ $rasioGross > 80 ? '#ef4444' : 'var(--melon)' }};border-radius:2px"></div>
                    </div>
                    <div style="font-size:10px;color:var(--text3)">ideal &lt;80% dari kas tersedia</div>
                </div>
            </div>
        </div>

        {{-- TF ke Penampung --}}
        <div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                <span style="width:7px;height:7px;border-radius:50%;background:#3b82f6;flex-shrink:0"></span>
                <span style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px">Transfer ke Penampung</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">TF ke rekening penampung</div>
                    <div style="font-size:16px;font-weight:600;color:#1d4ed8;margin-top:2px">Rp {{ number_format($totalDeposits) }}</div>
                    <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($avgDeposit) }}/hari</div>
                </div>
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">Admin TF penampung</div>
                    <div style="font-size:16px;font-weight:600;color:#1d4ed8;margin-top:2px">{{ $totalAdminFees > 0 ? 'Rp '.number_format($totalAdminFees) : '—' }}</div>
                    <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($avgAdminFee) }}/hari</div>
                </div>
            </div>
        </div>

        {{-- TF ke Rek Utama --}}
        <div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                <span style="width:7px;height:7px;border-radius:50%;background:#7c3aed;flex-shrink:0"></span>
                <span style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px">Transfer ke Rekening Utama</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">TF ke rekening utama</div>
                    <div style="font-size:16px;font-weight:600;color:#6d28d9;margin-top:2px">{{ $totalTransferred > 0 ? 'Rp '.number_format($totalTransferred) : '—' }}</div>
                    <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($avgTfRekUtama) }}/hari</div>
                </div>
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">Surplus (tabungan)</div>
                    <div style="font-size:16px;font-weight:600;color:#6d28d9;margin-top:2px">{{ $totalSurplusAll > 0 ? 'Rp '.number_format($totalSurplusAll) : '—' }}</div>
                    <div style="font-size:10px;color:var(--text3)">tidak dihitung sebagai pengeluaran</div>
                </div>
            </div>
        </div>

        {{-- Saldo Akhir --}}
        <div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                <span style="width:7px;height:7px;border-radius:50%;background:#f59e0b;flex-shrink:0"></span>
                <span style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px">Saldo Akhir</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">Saldo KAS (fisik)</div>
                    <div style="font-size:16px;font-weight:600;color:{{ $netKas >= 0 ? '#b45309' : '#dc2626' }};margin-top:2px">Rp {{ number_format($netKas) }}</div>
                    <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format(round($avgDailyCashBal)) }}/hari</div>
                </div>
                <div class="card" style="padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3)">Saldo BANK (penampung)</div>
                    <div style="font-size:16px;font-weight:600;color:{{ $finalBankBal >= 0 ? '#4338ca' : '#dc2626' }};margin-top:2px">Rp {{ number_format($finalBankBal) }}</div>
                    <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format(round($avgDailyBankBal)) }}/hari</div>
                </div>
            </div>
        </div>

        {{-- Net Cashflow banner --}}
        <div style="background:var(--melon-50);border:0.5px solid var(--border);border-radius:8px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px">
            <div>
                <div style="font-size:12px;font-weight:600;color:var(--text1)">Saldo KAS bersih periode ini</div>
                <div style="font-size:10px;color:var(--text3);margin-top:2px">kas masuk − pengeluaran − TF penampung − admin TF</div>
            </div>
            <div style="font-size:20px;font-weight:700;color:{{ $totalNet >= 0 ? 'var(--melon-dark)' : '#dc2626' }};white-space:nowrap">
                Rp {{ number_format($totalNet) }}
            </div>
        </div>
    </div>
</div>

{{-- ══ PREDIKSI / REALISASI ══ --}}
@if($remainDays > 0)
<div class="s-card">
    {{-- ══ HEADER CARD PREDIKSI ══ --}}
    <div class="s-card-header" style="background:#fff7ed;border-color:#fed7aa;flex-direction:column;gap:10px;padding-bottom:0">

        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="color:#c2410c">🔮 Prediksi Saldo KAS Akhir Bulan</span>
            @php $confColor = $predObj['confidence'] >= 75 ? 'badge-green' : ($predObj['confidence'] >= 50 ? 'badge-orange' : 'badge-red'); @endphp
            <span class="badge {{ $confColor }}">Keyakinan {{ $predObj['confidence'] }}%</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3)">
                Hari ke-{{ $todayDay }} · sisa {{ $remainDays }} hari
            </span>
        </div>

        {{-- Catatan penting basis perhitungan --}}
        <div style="background:#fef3c7;border:0.5px solid #fde68a;border-radius:6px;padding:7px 10px;font-size:10px;color:#92400e;margin:0 0 0 0;margin-top:4px">
            ℹ️ <strong>Semua prediksi berbasis margin bersih</strong> (penjualan − HPP Rp 16.000/tabung), bukan gross omzet.
            Rasio operasional ideal &lt;35% dari margin.
        </div>

        {{-- TAB SWITCHER --}}
        <div id="predMethodTabs" style="display:flex;gap:0;border-bottom:0;margin:0 -14px;padding:0 14px;overflow-x:auto;margin-top:4px">
            <button onclick="switchPredTab('wma',this)" id="pred-tab-wma"
                style="padding:4px 10px;font-size:10px;font-weight:600;border:none;border-bottom:2px solid #f97316;background:transparent;color:#c2410c;cursor:pointer;white-space:nowrap;border-radius:0">
                WMA Adaptif
            </button>
            <button onclick="switchPredTab('ols',this)" id="pred-tab-ols"
                style="padding:4px 10px;font-size:10px;font-weight:600;border:none;border-bottom:2px solid transparent;background:transparent;color:var(--text3);cursor:pointer;white-space:nowrap;border-radius:0">
                Regresi Linear
            </button>
            <button onclick="switchPredTab('holt',this)" id="pred-tab-holt"
                style="padding:4px 10px;font-size:10px;font-weight:600;border:none;border-bottom:2px solid transparent;background:transparent;color:var(--text3);cursor:pointer;white-space:nowrap;border-radius:0">
                Holt DES
            </button>
            <button onclick="switchPredTab('mc',this)" id="pred-tab-mc"
                style="padding:4px 10px;font-size:10px;font-weight:600;border:none;border-bottom:2px solid transparent;background:transparent;color:var(--text3);cursor:pointer;white-space:nowrap;border-radius:0">
                Monte Carlo
            </button>
            <button onclick="switchPredTab('konsensus',this)" id="pred-tab-konsensus"
                style="padding:4px 10px;font-size:10px;font-weight:600;border:none;border-bottom:2px solid transparent;background:transparent;color:var(--text3);cursor:pointer;white-space:nowrap;border-radius:0">
                🎯 Konsensus
            </button>
        </div>
    </div>

    {{-- ════════════════════════════════════════
         TAB 1 — WMA ADAPTIF
         ════════════════════════════════════════ --}}
    <div id="pred-panel-wma" style="padding:12px 14px;display:flex;flex-direction:column;gap:14px">

        {{-- Progress --}}
        <div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-bottom:4px">
                <span>Progress bulan berjalan</span>
                <span>{{ $predObj['progressPct'] }}% ({{ $todayDay }}/{{ $daysInMonth }} hari)</span>
            </div>
            <div style="height:6px;background:#f0f0f0;border-radius:3px;overflow:hidden">
                <div style="height:100%;width:{{ $predObj['progressPct'] }}%;background:var(--melon);border-radius:3px"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;margin-top:4px">
                <span style="color:var(--melon-dark);font-weight:600">Aktual: {{ $todayDay }} hari</span>
                <span style="color:var(--text3)">Estimasi: {{ $remainDays }} hari lagi</span>
            </div>
        </div>

        {{-- Engine Prediksi --}}
        <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">⚙ Engine Prediksi — Transparansi Metode</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                @foreach([
                    ['Rate Margin WMA', 'Rp '.number_format(round($predObj['marginRateWma'])), '5 hari terakhir ×2 bobot', 'var(--melon-dark)'],
                    ['Rate Margin Simpel', 'Rp '.number_format(round($predObj['marginRateSimple'])), 'total margin ÷ hari aktif', 'var(--text2)'],
                    ['Rate Final (60/40)', 'Rp '.number_format(round($predObj['marginRate'])), 'WMA×0.6 + simpel×0.4', '#1d4ed8'],
                    ['Faktor Konservatif', '×'.number_format($predObj['conserv'],2), 'basis 0.88 + tren adj', '#b45309'],
                ] as [$lbl,$val,$note,$col])
                <div>
                    <div style="font-size:10px;color:var(--text3)">{{ $lbl }}</div>
                    <div style="font-size:13px;font-weight:600;color:{{ $col }}">{{ $val }}</div>
                    <div style="font-size:10px;color:var(--text3)">{{ $note }}</div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Momentum --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            @foreach([
                ['Tren Margin Bersih (7h)', $predObj['salesMomentum'], true],
                ['Tren Pengeluaran (7h)',   $predObj['expMomentum'],   false],
            ] as [$lbl,$mom,$isIncome])
            @php
                $isGood = $isIncome ? $mom >= 0 : $mom <= 0;
                $arrow  = $isGood ? '↑' : '↓';
            @endphp
            <div class="card" style="padding:10px 12px;display:flex;align-items:center;gap:8px">
                <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;background:{{ $isGood ? '#f0fdf4' : '#fef2f2' }};color:{{ $isGood ? 'var(--melon-dark)' : '#dc2626' }}">{{ $arrow }}</div>
                <div>
                    <div style="font-size:10px;color:var(--text3)">{{ $lbl }}</div>
                    <div style="font-size:13px;font-weight:600;color:{{ $isGood ? 'var(--melon-dark)' : '#dc2626' }}">
                        {{ $mom >= 0 ? '+' : '' }}{{ number_format($mom,1) }}%
                    </div>
                    <div style="font-size:10px;color:var(--text3)">vs 7 hari sebelumnya</div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- 3 Skenario WMA --}}
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                Proyeksi Saldo KAS Akhir Bulan
                <span style="font-size:10px;font-weight:400;color:var(--text3)">(sudah dipotong gaji kurir)</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                <div style="background:#fef2f2;border:0.5px solid #fca5a5;border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;font-weight:700;color:#dc2626;text-transform:uppercase;margin-bottom:4px">Pesimis</div>
                    <div style="font-size:14px;font-weight:700;color:#991b1b">{{ number_format(round($predObj['predKasPesim'])) }}</div>
                    <div style="font-size:9px;color:#dc2626;margin-top:4px">Gaji est.: {{ number_format($predObj['gajiPesim']) }}</div>
                </div>
                <div style="background:#fff7ed;border:2px solid #f97316;border-radius:8px;padding:10px 12px;position:relative">
                    <div style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:var(--melon);color:#fff;font-size:8px;font-weight:700;padding:2px 8px;border-radius:10px;white-space:nowrap">UTAMA</div>
                    <div style="font-size:9px;font-weight:700;color:#c2410c;text-transform:uppercase;margin-bottom:4px;margin-top:4px">Konservatif</div>
                    <div style="font-size:16px;font-weight:700;color:{{ $predObj['predKas'] >= 0 ? '#c2410c' : '#dc2626' }}">{{ number_format(round($predObj['predKas'])) }}</div>
                    @if($predObj['piutangCairEstimasi'] > 0)
                    <div style="font-size:9px;color:var(--melon-dark);margin-top:3px">+Est. piutang: {{ number_format($predObj['piutangCairEstimasi']) }}</div>
                    @endif
                    <div style="font-size:9px;font-weight:600;color:{{ $predObj['predKas'] >= 0 ? 'var(--melon-dark)' : '#dc2626' }};margin-top:4px">
                        {{ $predObj['predKas'] >= 0 ? '✓ Aman' : '⚠ Defisit' }}
                    </div>
                </div>
                <div style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;font-weight:700;color:var(--melon-dark);text-transform:uppercase;margin-bottom:4px">Optimis</div>
                    <div style="font-size:14px;font-weight:700;color:#166534">{{ number_format(round($predObj['predKasOptim'])) }}</div>
                    <div style="font-size:9px;color:var(--melon-dark);margin-top:4px">Gaji est.: {{ number_format($predObj['gajiOptim']) }}</div>
                </div>
            </div>
        </div>

        {{-- Tabel rincian WMA --}}
        <div class="s-card" style="margin-bottom:0">
            <div class="s-card-header">Rincian Asumsi ({{ $remainDays }} hari ke depan — konservatif adaptif)</div>
            <div class="scroll-x">
                <table class="mob-table">
                    <thead>
                        <tr>
                            <th>Komponen</th>
                            <th class="r">Rate/Hari (WMA)</th>
                            <th class="r">Rate/Hari (Simpel)</th>
                            <th class="r">Asumsi/Hari</th>
                            <th class="r">Est. {{ $remainDays }} Hari</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="color:var(--melon-dark);font-weight:600">
                                + Margin Bersih
                                <div style="font-size:9px;font-weight:400;color:var(--text3)">penjualan − HPP × konservatif</div>
                            </td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format(round($predObj['marginRateWma'])) }}</td>
                            <td class="r" style="color:var(--text3)">Rp {{ number_format(round($predObj['marginRateSimple'])) }}</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format(round($predObj['marginRate'] * $predObj['conserv'])) }}</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($predObj['projMargin']) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#dc2626;font-weight:600">
                                − Pengeluaran Ops
                                <div style="font-size:9px;font-weight:400;color:var(--text3)">biaya tetap — tidak dikali conserv</div>
                            </td>
                            <td class="r" style="color:#dc2626">Rp {{ number_format(round($predObj['expRateWma'])) }}</td>
                            <td class="r" style="color:var(--text3)">Rp {{ number_format(round($predObj['expRateSimple'])) }}</td>
                            <td class="r bold" style="color:#dc2626">Rp {{ number_format(round($predObj['expRate'])) }}</td>
                            <td class="r bold" style="color:#dc2626">Rp {{ number_format($predObj['projExp']) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#3b82f6;font-weight:600">
                                − Admin TF penampung
                                <div style="font-size:9px;font-weight:400;color:var(--text3)">rata-rata per hari aktif</div>
                            </td>
                            <td class="r" style="color:var(--text3)" colspan="2">Rp {{ number_format(round($predObj['adminRateSimple'])) }}/hari</td>
                            <td class="r bold" style="color:#3b82f6">Rp {{ number_format(round($predObj['adminRateSimple'])) }}</td>
                            <td class="r bold" style="color:#3b82f6">Rp {{ number_format($predObj['projAdmin']) }}</td>
                        </tr>
                        @if($predObj['piutangBelumBayar'] > 0)
                        <tr style="background:#f0fdf4">
                            <td style="color:var(--melon-dark);font-weight:600">
                                + Est. Piutang Cair
                                <div style="font-size:10px;font-weight:400;color:var(--text3)">piutang margin Rp {{ number_format($predObj['piutangMarginBelumBayar']) }} · asumsi 30%</div>
                            </td>
                            <td class="r" style="color:var(--text3)" colspan="2">× 30%</td>
                            <td class="r" style="color:var(--melon-dark)">—</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($predObj['piutangCairEstimasi']) }}</td>
                        </tr>
                        @endif
                        <tr style="background:#fffbeb">
                            <td style="color:#92400e;font-weight:600">
                                − Gaji Kurir (kewajiban)
                                <div style="font-size:10px;font-weight:400;color:var(--text3)">Rp 500/tab · dibayar tgl 1 bulan depan</div>
                            </td>
                            <td class="r" style="color:var(--text3)">{{ number_format($avgTabungPerHari, 1) }} tab/hari × Rp 500</td>
                            <td class="r" style="color:#b45309">{{ number_format($predObj['predTabungSisa']) }} tab est.</td>
                            <td class="r" style="color:var(--text3)"></td>
                            <td class="r bold" style="color:#92400e">
                                Rp {{ number_format($predObj['predGajiTotal']) }}
                                <div style="font-size:9px;font-weight:400;color:#b45309">Terhutang: Rp {{ number_format($predObj['gajiAktualSdIni']) }}<br>+ Est. sisa: Rp {{ number_format($predObj['predGajiSisa']) }}</div>
                            </td>
                        </tr>
                        <tr style="background:var(--melon-50)">
                            <td class="bold">Saldo KAS saat ini</td>
                            <td class="r" style="color:var(--text3)" colspan="3">posisi aktual hari ini</td>
                            <td class="r bold" style="color:#c2410c">Rp {{ number_format($netKas) }}</td>
                        </tr>
                        <tr class="total-row">
                            <td class="bold">
                                = Prediksi KAS Akhir Bulan
                                <div style="font-size:9px;font-weight:400;color:rgba(255,255,255,0.7)">setelah semua kewajiban</div>
                            </td>
                            <td colspan="3"></td>
                            <td class="r bold" style="font-size:14px;color:{{ $predObj['predKas'] < 0 ? '#fca5a5' : '#fff' }}">
                                Rp {{ number_format(round($predObj['predKas'])) }}
                                <div style="font-size:10px;color:{{ $predObj['predKas'] >= 0 ? 'rgba(255,255,255,0.8)' : '#fca5a5' }}">
                                    {{ $predObj['predKas'] >= 0 ? '✓ Aman' : '⚠ Defisit' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            {{-- Prediksi Bank Penampung --}}
            <div class="card" style="padding:10px 12px;background:#eff6ff;border-color:#bfdbfe">
                <div style="font-size:10px;color:#3b82f6">Prediksi Saldo BANK Penampung</div>
                <div style="font-size:16px;font-weight:600;color:{{ $predObj['predBank'] >= 0 ? '#1e40af' : '#dc2626' }};margin-top:2px">
                    Rp {{ number_format(round($predObj['predBank'])) }}
                </div>
                <div style="font-size:10px;color:#3b82f6;margin-top:2px">rekening transit — deposit masuk ≈ langsung TF ke agen</div>
                @php $netBankChangeEst = $predObj['predBank'] - $finalBankBal; @endphp
                @if(abs($netBankChangeEst) < 200000)
                <div style="font-size:10px;color:#1d4ed8;font-weight:600;margin-top:2px">✓ Stabil (transit)</div>
                @elseif($netBankChangeEst > 0)
                <div style="font-size:10px;color:var(--melon-dark);font-weight:600;margin-top:2px">↑ Est. menumpuk Rp {{ number_format($netBankChangeEst) }}</div>
                @else
                <div style="font-size:10px;color:#dc2626;font-weight:600;margin-top:2px">↓ Est. berkurang Rp {{ number_format(abs($netBankChangeEst)) }}</div>
                @endif
            </div>

            {{-- Proyeksi Rasio Biaya --}}
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">
                    Proyeksi Rasio Ops / Margin
                    <span style="font-size:9px;font-style:italic">(akhir bulan)</span>
                </div>
                <div style="font-size:16px;font-weight:600;color:{{ $predObj['predRasio'] > 35 ? '#b45309' : 'var(--melon-dark)' }};margin-top:2px">
                    {{ number_format($predObj['predRasio'],1) }}%
                </div>
                <div style="height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden;margin:4px 0">
                    <div style="height:100%;width:{{ min($predObj['predRasio'],100) }}%;background:{{ $predObj['predRasio'] > 35 ? '#f59e0b' : 'var(--melon)' }};border-radius:2px"></div>
                </div>
                <div style="font-size:10px;color:var(--text3)">biaya ops ÷ margin bersih · ideal &lt;35%</div>
                <div style="font-size:10px;color:{{ $predObj['predRasio'] > 35 ? '#b45309' : 'var(--melon-dark)' }};font-weight:600;margin-top:2px">
                    {{ $predObj['predRasio'] > 35 ? '⚠ perlu efisiensi' : '✓ sehat' }}
                </div>
                <div style="font-size:10px;color:var(--text3);margin-top:4px;border-top:0.5px solid var(--border);padding-top:4px">
                    Aktual s/d hari ini: <strong style="color:{{ $predObj['rasioAktual'] > 35 ? '#b45309' : 'var(--melon-dark)' }}">{{ number_format($predObj['rasioAktual'],1) }}%</strong>
                </div>
            </div>
        </div>

        {{-- Metodologi WMA --}}
        <div style="font-size:10px;color:var(--text3);border-top:0.5px solid var(--border);padding-top:10px;line-height:1.6">
            <span style="font-weight:600;color:var(--text2)">Metodologi:</span>
            Rate harian = <strong>WMA 5 hari (bobot ×2) × 60% + rata simpel × 40%</strong> berbasis <strong>margin bersih</strong>.
            Faktor konservatif adaptif: basis 0.88 ± penyesuaian tren.
            Tren margin {{ $predObj['salesMomentum'] >= 0 ? '+' : '' }}{{ number_format($predObj['salesMomentum'],1) }}% → ×{{ number_format($predObj['conserv'],2) }}.
            Pengeluaran operasional <strong>tidak dikali conserv</strong> (biaya cenderung tetap/naik).
            @if($predObj['piutangBelumBayar'] > 0)
            Piutang margin Rp {{ number_format($predObj['piutangMarginBelumBayar']) }} — <strong>30% diasumsikan cair</strong>.
            @endif
            Gaji terhutang Rp {{ number_format($predObj['gajiAktualSdIni']) }} + est. Rp {{ number_format($predObj['predGajiSisa']) }} =
            <strong style="color:#b45309">Rp {{ number_format($predObj['predGajiTotal']) }}</strong>.
            Confidence <strong>{{ $predObj['confidence'] }}%</strong>.
            <span style="color:#c2410c;font-weight:600">Bukan jaminan — panduan perencanaan.</span>
        </div>

        @if($predObj['predGajiTotal'] > 0)
        <div style="background:#fffbeb;border:0.5px solid #fde68a;border-radius:8px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px">
            <div>
                <div style="font-size:11px;font-weight:600;color:#92400e">⏰ Kewajiban Gaji Kurir (tgl 1 bulan depan)</div>
                <div style="font-size:10px;color:#b45309;margin-top:2px">
                    Terhutang s/d hari ini: Rp {{ number_format($predObj['gajiAktualSdIni']) }}
                    + Est. sisa: Rp {{ number_format($predObj['predGajiSisa']) }}
                </div>
            </div>
            <div style="font-size:16px;font-weight:700;color:#92400e;white-space:nowrap">
                Rp {{ number_format($predObj['predGajiTotal']) }}
            </div>
        </div>
        <div style="font-size:10px;color:var(--text3);margin-top:4px;padding:0 2px">
            Prediksi KAS setelah gaji dibayar:
            <strong style="color:{{ $predObj['predKasSetelahGaji'] >= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                Rp {{ number_format(round($predObj['predKasSetelahGaji'])) }}
                {{ $predObj['predKasSetelahGaji'] >= 0 ? '✓' : '⚠' }}
            </strong>
        </div>
        @endif
    </div>{{-- end panel WMA --}}


    {{-- ════════════════════════════════════════
         TAB 2 — REGRESI LINEAR OLS
         ════════════════════════════════════════ --}}
    <div id="pred-panel-ols" style="display:none;padding:12px 14px;display:none;flex-direction:column;gap:14px">

        <div style="background:#f0f9ff;border:0.5px solid #bae6fd;border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">📐 Regresi Linear OLS — Least Squares</div>
            <div style="font-size:10px;color:#0c4a6e;line-height:1.6">
                Menghitung <strong>slope (b₁)</strong> dan <strong>intercept (b₀)</strong> dari seluruh data historis bulan ini.
                Formula: <code style="background:#e0f2fe;padding:1px 4px;border-radius:3px">ŷ = b₀ + b₁·hari</code>.
                Berbasis <strong>margin bersih</strong> (konsisten dengan WMA).
                Unggul saat pola penjualan memiliki <strong>tren linear konsisten</strong>.
            </div>
        </div>

        {{-- Koefisien Regresi --}}
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">⚙ Koefisien Regresi (basis margin bersih)</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                {{-- Margin --}}
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Margin Bersih Harian</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <div>
                            <div style="font-size:9px;color:var(--text3)">b₀ (intercept)</div>
                            <div style="font-size:12px;font-weight:600;color:var(--melon-dark)">Rp {{ number_format($olsMarginB0) }}</div>
                        </div>
                        <div>
                            <div style="font-size:9px;color:var(--text3)">b₁ (slope/hari)</div>
                            <div style="font-size:12px;font-weight:600;color:{{ $olsMarginB1 >= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                                {{ $olsMarginB1 >= 0 ? '+' : '' }}Rp {{ number_format($olsMarginB1) }}
                            </div>
                        </div>
                    </div>
                    <div style="margin-top:6px;display:flex;align-items:center;gap:6px">
                        <div style="font-size:10px;color:var(--text3)">R² = <strong style="color:{{ $olsMarginR2 >= 0.7 ? 'var(--melon-dark)' : ($olsMarginR2 >= 0.4 ? '#b45309' : '#dc2626') }}">{{ number_format($olsMarginR2,3) }}</strong></div>
                        <span style="font-size:9px;padding:1px 6px;border-radius:10px;font-weight:600;background:{{ $olsMarginR2 >= 0.7 ? '#dcfce7' : ($olsMarginR2 >= 0.4 ? '#fef9c3' : '#fee2e2') }};color:{{ $olsMarginR2 >= 0.7 ? '#166534' : ($olsMarginR2 >= 0.4 ? '#92400e' : '#991b1b') }}">{{ $olsMarginQual }}</span>
                        <span style="font-size:10px;color:{{ str_contains($olsMarginTrend,'Naik') ? 'var(--melon-dark)' : (str_contains($olsMarginTrend,'Turun') ? '#dc2626' : 'var(--text3)') }}">{{ $olsMarginTrend }}</span>
                    </div>
                    <div style="font-size:9px;color:var(--text3);margin-top:2px">{{ count($olsMarginPairs) }} titik data aktif</div>
                </div>

                {{-- Expense --}}
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Pengeluaran Ops Harian</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <div>
                            <div style="font-size:9px;color:var(--text3)">b₀ (intercept)</div>
                            <div style="font-size:12px;font-weight:600;color:#dc2626">Rp {{ number_format($olsExpB0) }}</div>
                        </div>
                        <div>
                            <div style="font-size:9px;color:var(--text3)">b₁ (slope/hari)</div>
                            <div style="font-size:12px;font-weight:600;color:{{ $olsExpB1 <= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                                {{ $olsExpB1 >= 0 ? '+' : '' }}Rp {{ number_format($olsExpB1) }}
                            </div>
                        </div>
                    </div>
                    <div style="margin-top:6px;display:flex;align-items:center;gap:6px">
                        <div style="font-size:10px;color:var(--text3)">R² = <strong style="color:{{ $olsExpR2 >= 0.7 ? 'var(--melon-dark)' : ($olsExpR2 >= 0.4 ? '#b45309' : '#dc2626') }}">{{ number_format($olsExpR2,3) }}</strong></div>
                        <span style="font-size:9px;padding:1px 6px;border-radius:10px;font-weight:600;background:{{ $olsExpR2 >= 0.7 ? '#dcfce7' : ($olsExpR2 >= 0.4 ? '#fef9c3' : '#fee2e2') }};color:{{ $olsExpR2 >= 0.7 ? '#166534' : ($olsExpR2 >= 0.4 ? '#92400e' : '#991b1b') }}">{{ $olsExpQual }}</span>
                        <span style="font-size:10px;color:{{ str_contains($olsExpTrend,'Naik') ? '#dc2626' : (str_contains($olsExpTrend,'Turun') ? 'var(--melon-dark)' : 'var(--text3)') }}">{{ $olsExpTrend }}</span>
                    </div>
                    <div style="font-size:9px;color:var(--text3);margin-top:2px">{{ count($olsExpPairs) }} titik data aktif</div>
                </div>
            </div>
        </div>

        {{-- Proyeksi OLS --}}
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                Proyeksi Komponen — {{ $remainDays }} hari ke depan
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                <div style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;font-weight:700;color:var(--melon-dark);text-transform:uppercase;margin-bottom:4px">Est. Margin Bersih</div>
                    <div style="font-size:14px;font-weight:700;color:var(--melon-dark)">Rp {{ number_format($olsProjMargin) }}</div>
                    <div style="font-size:9px;color:var(--text3);margin-top:3px">proyeksi {{ $remainDays }} hari</div>
                </div>
                <div style="background:#fef2f2;border:0.5px solid #fca5a5;border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;font-weight:700;color:#dc2626;text-transform:uppercase;margin-bottom:4px">Est. Pengeluaran</div>
                    <div style="font-size:14px;font-weight:700;color:#dc2626">Rp {{ number_format($olsProjExp) }}</div>
                    <div style="font-size:9px;color:var(--text3);margin-top:3px">proyeksi {{ $remainDays }} hari</div>
                </div>
                <div style="background:#eff6ff;border:0.5px solid #bfdbfe;border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;font-weight:700;color:#1d4ed8;text-transform:uppercase;margin-bottom:4px">Est. Admin TF</div>
                    <div style="font-size:14px;font-weight:700;color:#1d4ed8">Rp {{ number_format($olsProjAdmin) }}</div>
                    <div style="font-size:9px;color:var(--text3);margin-top:3px">rate simpel × {{ $remainDays }} hari</div>
                </div>
            </div>
        </div>

        {{-- Tabel rincian OLS --}}
        <div class="s-card" style="margin-bottom:0">
            <div class="s-card-header">Rincian Proyeksi OLS</div>
            <div class="scroll-x">
                <table class="mob-table">
                    <thead>
                        <tr>
                            <th>Komponen</th>
                            <th class="r">b₀</th>
                            <th class="r">b₁/hari</th>
                            <th class="r">R²</th>
                            <th class="r">Est. {{ $remainDays }} Hari</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="color:var(--melon-dark);font-weight:600">+ Margin Bersih</td>
                            <td class="r">Rp {{ number_format($olsMarginB0) }}</td>
                            <td class="r" style="color:{{ $olsMarginB1 >= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                                {{ $olsMarginB1 >= 0 ? '+' : '' }}{{ number_format($olsMarginB1) }}
                            </td>
                            <td class="r">{{ number_format($olsMarginR2,3) }}</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($olsProjMargin) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#dc2626;font-weight:600">− Pengeluaran</td>
                            <td class="r">Rp {{ number_format($olsExpB0) }}</td>
                            <td class="r" style="color:{{ $olsExpB1 <= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                                {{ $olsExpB1 >= 0 ? '+' : '' }}{{ number_format($olsExpB1) }}
                            </td>
                            <td class="r">{{ number_format($olsExpR2,3) }}</td>
                            <td class="r bold" style="color:#dc2626">Rp {{ number_format($olsProjExp) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#3b82f6;font-weight:600">− Admin TF</td>
                            <td class="r" colspan="3" style="color:var(--text3)">rate simpel ({{ number_format(round($predObj['adminRateSimple'])) }}/hari)</td>
                            <td class="r bold" style="color:#3b82f6">Rp {{ number_format($olsProjAdmin) }}</td>
                        </tr>
                        @if($predObj['piutangCairEstimasi'] > 0)
                        <tr style="background:#f0fdf4">
                            <td style="color:var(--melon-dark);font-weight:600">+ Est. Piutang Cair</td>
                            <td class="r" colspan="3" style="color:var(--text3)">sama dengan WMA (30%)</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($predObj['piutangCairEstimasi']) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td style="color:#92400e;font-weight:600">− Gaji Kurir (est.)</td>
                            <td class="r" style="color:var(--text3)" colspan="3">sama dengan asumsi WMA</td>
                            <td class="r bold" style="color:#92400e">Rp {{ number_format($olsPredGajiTotal) }}</td>
                        </tr>
                        <tr style="background:var(--melon-50)">
                            <td class="bold">Saldo KAS saat ini</td>
                            <td class="r" colspan="3" style="color:var(--text3)">posisi aktual</td>
                            <td class="r bold" style="color:#c2410c">Rp {{ number_format($netKas) }}</td>
                        </tr>
                        <tr class="total-row">
                            <td class="bold">
                                = Prediksi KAS Akhir Bulan (OLS)
                                <div style="font-size:9px;font-weight:400;color:rgba(255,255,255,0.7)">setelah semua kewajiban</div>
                            </td>
                            <td colspan="3"></td>
                            <td class="r bold" style="font-size:14px;color:{{ $olsPredKas < 0 ? '#fca5a5' : '#fff' }}">
                                Rp {{ number_format(round($olsPredKas)) }}
                                <div style="font-size:10px;color:{{ $olsPredKas >= 0 ? 'rgba(255,255,255,0.8)' : '#fca5a5' }}">
                                    {{ $olsPredKas >= 0 ? '✓ Aman' : '⚠ Defisit' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="font-size:10px;color:var(--text3);border-top:0.5px solid var(--border);padding-top:10px;line-height:1.6">
            <span style="font-weight:600;color:var(--text2)">Metodologi OLS:</span>
            Persamaan <code style="background:var(--surface2);padding:1px 4px;border-radius:3px">ŷ = b₀ + b₁·d</code> via
            <strong>Ordinary Least Squares</strong>. Proyeksi untuk hari {{ $todayDay + 1 }}–{{ $daysInMonth }}.
            R² ≥0.7 = baik, 0.4–0.7 = cukup, &lt;0.4 = tren lemah.
            Confidence <strong>{{ $olsConfidence }}%</strong>.
            <span style="color:#0369a1;font-weight:600">Terbaik saat ada tren linear jelas.</span>
        </div>
    </div>{{-- end panel OLS --}}

    {{-- ════════════════════════════════════════
     TAB 5 — HOLT'S DOUBLE EXPONENTIAL SMOOTHING
     Sisipkan SETELAH </div>{{-- end panel OLS --}}
    {{--  dan SEBELUM panel Monte Carlo
    ════════════════════════════════════════ --}} 
    <div id="pred-panel-holt" style="display:none;padding:12px 14px;display:none;flex-direction:column;gap:14px">

        {{-- Penjelasan metode --}}
        <div style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">📉 Holt&#39;s Double Exponential Smoothing (DES)</div>

            <div style="font-size:10px;color:#14532d;line-height:1.6">
                Memperhalus dua komponen sekaligus:
                <strong>Level L(t)</strong> (baseline nilai saat ini) dan
                <strong>Trend T(t)</strong> (laju perubahan per hari).
                <br>
                Formula: <code style="background:#dcfce7;padding:1px 4px;border-radius:3px">L(t) = α·y + (1−α)·[L(t−1)+T(t−1)]</code> &nbsp;
                <code style="background:#dcfce7;padding:1px 4px;border-radius:3px">T(t) = β·ΔL + (1−β)·T(t−1)</code>
                <br>
                Proyeksi: <code style="background:#dcfce7;padding:1px 4px;border-radius:3px">ŷ(t+h) = L(t) + h·T(t)</code>
                <br>
                Parameter α &amp; β dioptimasi otomatis via <strong>grid-search SSE minimum</strong> (25 kombinasi).
                Unggul saat data memiliki <strong>tren yang berubah-ubah</strong> — lebih adaptif dari OLS.
            </div>
        </div>

        {{-- Parameter hasil optimasi --}}
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">⚙ Parameter Optimal (grid-search α×β)</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">

                {{-- Margin --}}
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:6px;font-weight:600">Margin Bersih Harian</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px">
                        <div>
                            <div style="font-size:9px;color:var(--text3)">α (level)</div>
                            <div style="font-size:13px;font-weight:600;color:var(--melon-dark)">{{ number_format($predObj['holtsAlphaMargin'],2) }}</div>
                        </div>
                        <div>
                            <div style="font-size:9px;color:var(--text3)">β (trend)</div>
                            <div style="font-size:13px;font-weight:600;color:var(--melon-dark)">{{ number_format($predObj['holtsBetaMargin'],2) }}</div>
                        </div>
                        <div>
                            <div style="font-size:9px;color:var(--text3)">Level L(t)</div>
                            <div style="font-size:12px;font-weight:600;color:var(--melon-dark)">Rp {{ number_format(round($predObj['holtsLevelMargin'])) }}</div>
                        </div>
                        <div>
                            <div style="font-size:9px;color:var(--text3)">Trend T(t)/hari</div>
                            <div style="font-size:12px;font-weight:600;color:{{ $predObj['holtsTrendMargin'] >= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                                {{ $predObj['holtsTrendMargin'] >= 0 ? '+' : '' }}Rp {{ number_format(round($predObj['holtsTrendMargin'])) }}
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        <span style="font-size:10px;color:{{ str_contains($predObj['holtsMarginTrend'],'Naik') ? 'var(--melon-dark)' : (str_contains($predObj['holtsMarginTrend'],'Turun') ? '#dc2626' : 'var(--text3)') }}">{{ $predObj['holtsMarginTrend'] }}</span>
                        <span style="font-size:9px;color:var(--text3)">MAE: Rp {{ number_format($predObj['holtsMaeMargin']) }}/hari</span>
                    </div>
                </div>

                {{-- Expense --}}
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:6px;font-weight:600">Pengeluaran Ops Harian</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px">
                        <div>
                            <div style="font-size:9px;color:var(--text3)">α (level)</div>
                            <div style="font-size:13px;font-weight:600;color:#dc2626">{{ number_format($predObj['holtsAlphaExp'],2) }}</div>
                        </div>
                        <div>
                            <div style="font-size:9px;color:var(--text3)">β (trend)</div>
                            <div style="font-size:13px;font-weight:600;color:#dc2626">{{ number_format($predObj['holtsBetaExp'],2) }}</div>
                        </div>
                        <div>
                            <div style="font-size:9px;color:var(--text3)">Level L(t)</div>
                            <div style="font-size:12px;font-weight:600;color:#dc2626">Rp {{ number_format(round($predObj['holtsLevelExp'])) }}</div>
                        </div>
                        <div>
                            <div style="font-size:9px;color:var(--text3)">Trend T(t)/hari</div>
                            <div style="font-size:12px;font-weight:600;color:{{ $predObj['holtsTrendExp'] <= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                                {{ $predObj['holtsTrendExp'] >= 0 ? '+' : '' }}Rp {{ number_format(round($predObj['holtsTrendExp'])) }}
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        <span style="font-size:10px;color:{{ str_contains($predObj['holtsExpTrend'],'Naik') ? '#dc2626' : (str_contains($predObj['holtsExpTrend'],'Turun') ? 'var(--melon-dark)' : 'var(--text3)') }}">{{ $predObj['holtsExpTrend'] }}</span>
                        <span style="font-size:9px;color:var(--text3)">MAE: Rp {{ number_format($predObj['holtsMaeExp']) }}/hari</span>
                    </div>
                </div>
            </div>

            {{-- Data points badge --}}
            <div style="margin-top:8px;font-size:10px;color:var(--text3)">
                {{ $predObj['holtsDataPoints'] }} titik data aktif digunakan ·
                Confidence <strong style="color:{{ $predObj['holtsConfidence'] >= 70 ? 'var(--melon-dark)' : ($predObj['holtsConfidence'] >= 50 ? '#b45309' : '#dc2626') }}">{{ $predObj['holtsConfidence'] }}%</strong> ·
                Interval prediksi ±{{ number_format(round($predObj['holtsSEMargin']/1000)) }}k (margin) / ±{{ number_format(round($predObj['holtsSEExp']/1000)) }}k (ops)
            </div>
        </div>

        {{-- 3 Skenario --}}
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                Proyeksi Saldo KAS Akhir Bulan
                <span style="font-size:10px;font-weight:400;color:var(--text3)">(sudah dipotong gaji kurir)</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                <div style="background:#fef2f2;border:0.5px solid #fca5a5;border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;font-weight:700;color:#dc2626;text-transform:uppercase;margin-bottom:4px">Pesimis (−1.5σ)</div>
                    <div style="font-size:14px;font-weight:700;color:#991b1b">{{ number_format($predObj['holtsPredKasPesim']) }}</div>
                    <div style="font-size:9px;color:#dc2626;margin-top:4px">margin −1.5σ, ops +1σ</div>
                </div>
                <div style="background:#f0fdf4;border:2px solid #22c55e;border-radius:8px;padding:10px 12px;position:relative">
                    <div style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:#16a34a;color:#fff;font-size:8px;font-weight:700;padding:2px 8px;border-radius:10px;white-space:nowrap">UTAMA</div>
                    <div style="font-size:9px;font-weight:700;color:#166534;text-transform:uppercase;margin-bottom:4px;margin-top:4px">Dasar</div>
                    <div style="font-size:16px;font-weight:700;color:{{ $predObj['holtsPredKas'] >= 0 ? '#166534' : '#dc2626' }}">{{ number_format(round($predObj['holtsPredKas'])) }}</div>
                    @if($predObj['piutangCairEstimasi'] > 0)
                    <div style="font-size:9px;color:#16a34a;margin-top:3px">+Est. piutang: {{ number_format($predObj['piutangCairEstimasi']) }}</div>
                    @endif
                    <div style="font-size:9px;font-weight:600;color:{{ $predObj['holtsPredKas'] >= 0 ? '#166534' : '#dc2626' }};margin-top:4px">
                        {{ $predObj['holtsPredKas'] >= 0 ? '✓ Aman' : '⚠ Defisit' }}
                    </div>
                </div>
                <div style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;font-weight:700;color:var(--melon-dark);text-transform:uppercase;margin-bottom:4px">Optimis (+1.5σ)</div>
                    <div style="font-size:14px;font-weight:700;color:#166534">{{ number_format($predObj['holtsPredKasOptim']) }}</div>
                    <div style="font-size:9px;color:var(--melon-dark);margin-top:4px">margin +1.5σ, ops −1σ</div>
                </div>
            </div>
        </div>

        {{-- Tabel rincian --}}
        <div class="s-card" style="margin-bottom:0">
            <div class="s-card-header">Rincian Proyeksi Holt DES ({{ $remainDays }} hari ke depan)</div>
            <div class="scroll-x">
                <table class="mob-table">
                    <thead>
                        <tr>
                            <th>Komponen</th>
                            <th class="r">Level L(t)</th>
                            <th class="r">Trend T(t)/hari</th>
                            <th class="r">α · β</th>
                            <th class="r">Est. {{ $remainDays }} Hari</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="color:var(--melon-dark);font-weight:600">
                                + Margin Bersih
                                <div style="font-size:9px;font-weight:400;color:var(--text3)">proyeksi kumulatif Σ(L+h·T)</div>
                            </td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format(round($predObj['holtsLevelMargin'])) }}</td>
                            <td class="r" style="color:{{ $predObj['holtsTrendMargin'] >= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                                {{ $predObj['holtsTrendMargin'] >= 0 ? '+' : '' }}Rp {{ number_format(round($predObj['holtsTrendMargin'])) }}
                            </td>
                            <td class="r" style="color:var(--text3)">
                                {{ number_format($predObj['holtsAlphaMargin'],2) }} · {{ number_format($predObj['holtsBetaMargin'],2) }}
                            </td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($predObj['holtsProjMargin']) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#dc2626;font-weight:600">
                                − Pengeluaran Ops
                                <div style="font-size:9px;font-weight:400;color:var(--text3)">proyeksi kumulatif Σ(L+h·T)</div>
                            </td>
                            <td class="r bold" style="color:#dc2626">Rp {{ number_format(round($predObj['holtsLevelExp'])) }}</td>
                            <td class="r" style="color:{{ $predObj['holtsTrendExp'] <= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                                {{ $predObj['holtsTrendExp'] >= 0 ? '+' : '' }}Rp {{ number_format(round($predObj['holtsTrendExp'])) }}
                            </td>
                            <td class="r" style="color:var(--text3)">
                                {{ number_format($predObj['holtsAlphaExp'],2) }} · {{ number_format($predObj['holtsBetaExp'],2) }}
                            </td>
                            <td class="r bold" style="color:#dc2626">Rp {{ number_format($predObj['holtsProjExp']) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#3b82f6;font-weight:600">
                                − Admin TF penampung
                                <div style="font-size:9px;font-weight:400;color:var(--text3)">DES α=0.40 β=0.20</div>
                            </td>
                            <td class="r" style="color:#3b82f6">Rp {{ number_format(round($predObj['holtsLevelAdmin'])) }}</td>
                            <td class="r" style="color:{{ $predObj['holtsTrendAdmin'] <= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                                {{ $predObj['holtsTrendAdmin'] >= 0 ? '+' : '' }}Rp {{ number_format(round($predObj['holtsTrendAdmin'])) }}
                            </td>
                            <td class="r" style="color:var(--text3)">0.40 · 0.20</td>
                            <td class="r bold" style="color:#3b82f6">Rp {{ number_format($predObj['holtsProjAdmin']) }}</td>
                        </tr>
                        @if($predObj['piutangCairEstimasi'] > 0)
                        <tr style="background:#f0fdf4">
                            <td style="color:var(--melon-dark);font-weight:600">
                                + Est. Piutang Cair
                                <div style="font-size:9px;font-weight:400;color:var(--text3)">sama dengan WMA/OLS (30%)</div>
                            </td>
                            <td class="r" colspan="3" style="color:var(--text3)">× 30%</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($predObj['piutangCairEstimasi']) }}</td>
                        </tr>
                        @endif
                        <tr style="background:#fffbeb">
                            <td style="color:#92400e;font-weight:600">
                                − Gaji Kurir (kewajiban)
                                <div style="font-size:10px;font-weight:400;color:var(--text3)">sama dengan asumsi WMA</div>
                            </td>
                            <td class="r" colspan="3" style="color:var(--text3)">deterministik</td>
                            <td class="r bold" style="color:#92400e">Rp {{ number_format($predObj['predGajiTotal']) }}</td>
                        </tr>
                        <tr style="background:var(--melon-50)">
                            <td class="bold">Saldo KAS saat ini</td>
                            <td class="r" colspan="3" style="color:var(--text3)">posisi aktual hari ini</td>
                            <td class="r bold" style="color:#c2410c">Rp {{ number_format($netKas) }}</td>
                        </tr>
                        <tr class="total-row">
                            <td class="bold">
                                = Prediksi KAS Akhir Bulan (Holt DES)
                                <div style="font-size:9px;font-weight:400;color:rgba(255,255,255,0.7)">setelah semua kewajiban</div>
                            </td>
                            <td colspan="3"></td>
                            <td class="r bold" style="font-size:14px;color:{{ $predObj['holtsPredKas'] < 0 ? '#fca5a5' : '#fff' }}">
                                Rp {{ number_format(round($predObj['holtsPredKas'])) }}
                                <div style="font-size:10px;color:{{ $predObj['holtsPredKas'] >= 0 ? 'rgba(255,255,255,0.8)' : '#fca5a5' }}">
                                    {{ $predObj['holtsPredKas'] >= 0 ? '✓ Aman' : '⚠ Defisit' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Perbandingan vs metode lain --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div class="card" style="padding:10px 12px;background:#f0fdf4;border-color:#86efac">
                <div style="font-size:10px;color:#166534">Perbandingan vs WMA Adaptif</div>
                @php $diffWma = $predObj['holtsPredKas'] - round($predObj['predKas']); @endphp
                <div style="font-size:15px;font-weight:600;color:{{ abs($diffWma) < 500000 ? '#166534' : ($diffWma > 0 ? 'var(--melon-dark)' : '#dc2626') }};margin-top:4px">
                    {{ $diffWma >= 0 ? '+' : '' }}Rp {{ number_format($diffWma) }}
                </div>
                <div style="font-size:10px;color:var(--text3);margin-top:2px">
                    {{ abs($diffWma) < 500000 ? '✓ Konsisten' : ($diffWma > 0 ? 'Holt lebih optimis' : 'Holt lebih konservatif') }}
                </div>
            </div>
            <div class="card" style="padding:10px 12px;background:#f0f9ff;border-color:#bae6fd">
                <div style="font-size:10px;color:#0369a1">Perbandingan vs OLS</div>
                @php $diffOls = $predObj['holtsPredKas'] - round($olsPredKas); @endphp
                <div style="font-size:15px;font-weight:600;color:{{ abs($diffOls) < 500000 ? '#0369a1' : ($diffOls > 0 ? 'var(--melon-dark)' : '#dc2626') }};margin-top:4px">
                    {{ $diffOls >= 0 ? '+' : '' }}Rp {{ number_format($diffOls) }}
                </div>
                <div style="font-size:10px;color:var(--text3);margin-top:2px">
                    {{ abs($diffOls) < 500000 ? '✓ Konsisten' : ($diffOls > 0 ? 'Holt lebih optimis' : 'Holt lebih konservatif') }}
                </div>
            </div>
        </div>

        {{-- Metodologi --}}
        <div style="font-size:10px;color:var(--text3);border-top:0.5px solid var(--border);padding-top:10px;line-height:1.6">
            <span style="font-weight:600;color:var(--text2)">Metodologi Holt DES:</span>
            Smoothing ganda: <strong>level</strong> (α={{ number_format($predObj['holtsAlphaMargin'],2) }}) + <strong>trend</strong> (β={{ number_format($predObj['holtsBetaMargin'],2) }}) dari {{ $predObj['holtsDataPoints'] }} hari aktif.
            Proyeksi per hari: <code style="background:var(--surface2);padding:1px 4px;border-radius:3px">L + h·T</code>, diakumulasikan untuk {{ $remainDays }} hari.
            Interval ±1.5σ × √n menghasilkan rentang skenario pesimis/optimis.
            MAE margin: Rp {{ number_format($predObj['holtsMaeMargin']) }}/hari.
            Confidence <strong>{{ $predObj['holtsConfidence'] }}%</strong>.
            <span style="color:#166534;font-weight:600">Terbaik saat tren berubah secara gradual dalam bulan berjalan.</span>
        </div>

    </div>{{-- end panel holt --}}


    {{-- ════════════════════════════════════════
         TAB 3 — MONTE CARLO
         ════════════════════════════════════════ --}}
    <div id="pred-panel-mc" style="display:none;padding:12px 14px;display:none;flex-direction:column;gap:14px">

        <div style="background:#faf5ff;border:0.5px solid #d8b4fe;border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">🎲 Monte Carlo Simulation — 10.000 Iterasi</div>
            <div style="font-size:10px;color:#4c1d95;line-height:1.6">
                Mensimulasikan <strong>10.000 skenario acak</strong> untuk sisa {{ $remainDays }} hari.
                Setiap iterasi: sampling nilai harian dari <strong>distribusi normal</strong> (mean ± std-dev dari data aktual)
                menggunakan <strong>Box-Muller transform</strong>.
                Semua berbasis <strong>margin bersih</strong> + admin fee terpisah.
                Hasilnya: distribusi saldo akhir → P5, P25, P50 (median), P75, P95, dan
                <strong>probabilitas defisit</strong>.
            </div>
        </div>

        {{-- Parameter MC --}}
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">⚙ Parameter Simulasi (dari data aktual)</div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;color:var(--text3);margin-bottom:3px">Margin Bersih/Hari</div>
                    <div style="font-size:11px;font-weight:600;color:var(--melon-dark)">μ = Rp {{ number_format(round($mcMarginMean)) }}</div>
                    <div style="font-size:11px;color:var(--text3)">σ = Rp {{ number_format(round($mcMarginStd)) }}</div>
                    <div style="font-size:9px;color:var(--text3);margin-top:3px">CV = {{ $mcMarginMean > 0 ? number_format($mcMarginStd/$mcMarginMean*100,1) : '0' }}%</div>
                </div>
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;color:var(--text3);margin-bottom:3px">Pengeluaran Ops/Hari</div>
                    <div style="font-size:11px;font-weight:600;color:#dc2626">μ = Rp {{ number_format(round($mcExpMean)) }}</div>
                    <div style="font-size:11px;color:var(--text3)">σ = Rp {{ number_format(round($mcExpStd)) }}</div>
                    <div style="font-size:9px;color:var(--text3);margin-top:3px">CV = {{ $mcExpMean > 0 ? number_format($mcExpStd/$mcExpMean*100,1) : '0' }}%</div>
                </div>
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;color:var(--text3);margin-bottom:3px">Admin TF/Hari</div>
                    <div style="font-size:11px;font-weight:600;color:#1d4ed8">μ = Rp {{ number_format(round($mcAdminMean)) }}</div>
                    <div style="font-size:11px;color:var(--text3)">σ = Rp {{ number_format(round($mcAdminStd)) }}</div>
                    <div style="font-size:9px;color:var(--text3);margin-top:3px">Korelasi M-E: {{ number_format($mcCorrCoef,2) }}</div>
                </div>
            </div>
        </div>

        {{-- Tombol Run --}}
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <button id="mc-run-btn" onclick="runMonteCarlo()"
                style="padding:8px 18px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px">
                <span id="mc-btn-icon">▶</span> Jalankan Simulasi
            </button>
            <div id="mc-status" style="font-size:10px;color:var(--text3)">Belum dijalankan. Klik tombol untuk simulasi.</div>
        </div>

        {{-- Hasil MC (muncul setelah simulasi) --}}
        <div id="mc-results" style="display:none;flex-direction:column;gap:14px">
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                    Distribusi Saldo KAS Akhir Bulan (10.000 skenario)
                </div>
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px" id="mc-percentiles"></div>
            </div>
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Histogram Distribusi</div>
                <div style="position:relative;width:100%;height:160px">
                    <canvas id="mcHistChart"></canvas>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <div style="background:#faf5ff;border:0.5px solid #d8b4fe;border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:#6d28d9;margin-bottom:4px">Probabilitas Defisit</div>
                    <div id="mc-prob-deficit" style="font-size:22px;font-weight:700;color:#7c3aed"></div>
                    <div style="font-size:9px;color:#6d28d9;margin-top:3px">saldo akhir &lt; 0</div>
                </div>
                <div style="background:#faf5ff;border:0.5px solid #d8b4fe;border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:#6d28d9;margin-bottom:4px">Median (P50)</div>
                    <div id="mc-median" style="font-size:18px;font-weight:700;color:#6d28d9"></div>
                    <div style="font-size:9px;color:#6d28d9;margin-top:3px">ekspektasi terbaik</div>
                </div>
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Range 90% CI (P5–P95)</div>
                    <div id="mc-ci90" style="font-size:11px;font-weight:600;color:var(--text1)"></div>
                    <div style="font-size:9px;color:var(--text3);margin-top:3px">90% kemungkinan dalam range ini</div>
                </div>
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Range 50% CI (P25–P75)</div>
                    <div id="mc-ci50" style="font-size:11px;font-weight:600;color:var(--text1)"></div>
                    <div style="font-size:9px;color:var(--text3);margin-top:3px">IQR — core scenario</div>
                </div>
            </div>
            <div id="mc-interpretation" style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px;font-size:10px;color:#166534;line-height:1.6"></div>
        </div>

        <div style="font-size:10px;color:var(--text3);border-top:0.5px solid var(--border);padding-top:10px;line-height:1.6">
            <span style="font-weight:600;color:var(--text2)">Metodologi Monte Carlo:</span>
            10.000 iterasi × {{ $remainDays }} hari tersisa.
            Setiap hari: sampling Gaussian(μ, σ) via <strong>Box-Muller</strong>.
            Margin di-floor ke 0. Admin TF disimulasikan terpisah.
            Korelasi margin–expense = {{ number_format($mcCorrCoef,2) }}
            ({{ abs($mcCorrCoef) > 0.5 ? 'kuat' : (abs($mcCorrCoef) > 0.25 ? 'sedang' : 'lemah') }}).
            Gaji dihitung deterministik.
            <span style="color:#6d28d9;font-weight:600">Menghasilkan tail-risk realistis.</span>
        </div>
    </div>{{-- end panel MC --}}


    {{-- ════════════════════════════════════════
         TAB 4 — KONSENSUS
         ════════════════════════════════════════ --}}
    <div id="pred-panel-konsensus" style="display:none;padding:12px 14px;display:none;flex-direction:column;gap:14px">

        <div style="background:#f8fafc;border:0.5px solid #cbd5e1;border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">🎯 Konsensus — Rata-rata Tertimbang 3 Metode</div>
            <div style="font-size:10px;color:#334155;line-height:1.6">
                Menggabungkan hasil WMA Adaptif, Regresi Linear OLS, dan Monte Carlo (median P50)
                dengan bobot berbasis confidence masing-masing metode.
                Semua metode sudah konsisten berbasis <strong>margin bersih</strong>.
            </div>
        </div>

        <div class="s-card" style="margin-bottom:0">
            <div class="s-card-header">Perbandingan Prediksi Saldo KAS Akhir Bulan</div>
            <div class="scroll-x">
                <table class="mob-table">
                    <thead>
                        <tr>
                            <th>Metode</th>
                            <th class="r">Prediksi KAS</th>
                            <th class="r">Confidence</th>
                            <th class="r">Bobot</th>
                            <th>Kelebihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="font-weight:600;color:#c2410c">WMA Adaptif</td>
                            <td class="r bold" style="color:{{ $predObj['predKas'] >= 0 ? '#c2410c' : '#dc2626' }}">Rp {{ number_format(round($predObj['predKas'])) }}</td>
                            <td class="r">{{ $predObj['confidence'] }}%</td>
                            <td class="r" id="cons-w-wma">—</td>
                            <td style="font-size:10px;color:var(--text3)">Responsif tren terkini, faktor konservatif</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;color:#0369a1">Regresi Linear</td>
                            <td class="r bold" style="color:{{ $olsPredKas >= 0 ? '#0369a1' : '#dc2626' }}">Rp {{ number_format(round($olsPredKas)) }}</td>
                            <td class="r">{{ $olsConfidence }}%</td>
                            <td class="r" id="cons-w-ols">—</td>
                            <td style="font-size:10px;color:var(--text3)">Menangkap tren arah linear jangka panjang</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;color:#166534">Holt DES</td>
                            <td class="r bold" style="color:{{ $predObj['holtsPredKas'] >= 0 ? '#166534' : '#dc2626' }}">
                                Rp {{ number_format(round($predObj['holtsPredKas'])) }}
                            </td>
                            <td class="r">{{ $predObj['holtsConfidence'] }}%</td>
                            <td class="r" id="cons-w-holt">—</td>
                            <td style="font-size:10px;color:var(--text3)">Tren berubah gradual, smoothing ganda α+β</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;color:#6d28d9">Monte Carlo P50</td>
                            <td class="r bold" id="cons-mc-val" style="color:#6d28d9">— (belum simulasi)</td>
                            <td class="r" id="cons-mc-conf">—</td>
                            <td class="r" id="cons-w-mc">—</td>
                            <td style="font-size:10px;color:var(--text3)">Distribusi probabilistik, tail-risk realistis</td>
                        </tr>
                        <tr style="background:var(--melon-50)">
                            <td class="bold">Saldo KAS Saat Ini</td>
                            <td class="r bold" style="color:#c2410c">Rp {{ number_format($netKas) }}</td>
                            <td class="r" style="color:var(--text3)">aktual</td>
                            <td class="r">—</td>
                            <td style="font-size:10px;color:var(--text3)">Posisi nyata hari ini</td>
                        </tr>
                        <tr class="total-row" id="cons-total-row">
                            <td class="bold">
                                = Konsensus Tertimbang
                                <div style="font-size:9px;font-weight:400;color:rgba(255,255,255,0.7)" id="cons-note">Jalankan Monte Carlo dulu</div>
                            </td>
                            <td colspan="2"></td>
                            <td class="r" style="color:rgba(255,255,255,0.7)" id="cons-total-w">—</td>
                            <td class="r bold" style="font-size:14px" id="cons-result">
                                <span style="color:rgba(255,255,255,0.5)">Menunggu...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Visualisasi Perbandingan</div>
            <div style="position:relative;width:100%;height:180px">
                <canvas id="consCompChart"></canvas>
            </div>
        </div>

        <div id="cons-insight" style="background:#f0f9ff;border:0.5px solid #bae6fd;border-radius:8px;padding:10px 12px;font-size:10px;color:#0c4a6e;line-height:1.6">
            <span style="font-weight:600">Insight:</span>
            Jalankan simulasi Monte Carlo terlebih dahulu untuk mendapatkan konsensus lengkap.
        </div>
    </div>{{-- end panel konsensus --}}

</div>{{-- end card prediksi --}}

@else
{{-- ══ REALISASI AKHIR BULAN ══ --}}
@php
$gajiRealisasi  = $totalTabungAktual * 500;
$kasSetelahGaji = $netKas - $gajiRealisasi;
$netBersih      = $netKas + $finalBankBal - $gajiRealisasi;
$rasioRealOps   = $totalMargin > 0 ? ($totalExpense / $totalMargin * 100) : 0;
$rasioGrosReal  = $totalAvailable > 0 ? (($totalExpense + $totalDeposits + $totalAdminFees + $gajiRealisasi) / $totalAvailable * 100) : 0;
@endphp
<div class="s-card">
    <div class="s-card-header" style="background:#f0fdf4;border-color:#86efac;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="color:#166534">✅ Realisasi Akhir Bulan</span>
        <span class="badge badge-green">Data 100% aktual</span>
        <span style="margin-left:auto;font-size:10px;color:var(--text3)">{{ $daysInMonth }} hari · {{ $cfActiveDays }} hari aktif</span>
    </div>
    <div style="padding:12px 14px;display:flex;flex-direction:column;gap:10px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
            <div style="background:#fffbeb;border:0.5px solid #fde68a;border-radius:8px;padding:10px 12px">
                <div style="font-size:10px;color:#b45309">Saldo KAS (sebelum gaji)</div>
                <div style="font-size:16px;font-weight:600;color:{{ $netKas >= 0 ? '#92400e' : '#dc2626' }};margin-top:2px">Rp {{ number_format($netKas) }}</div>
            </div>
            <div style="background:#f0fdf4;border:2px solid var(--melon);border-radius:8px;padding:10px 12px;position:relative">
                <div style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:var(--melon);color:#fff;font-size:8px;font-weight:700;padding:2px 8px;border-radius:10px;white-space:nowrap">BERSIH</div>
                <div style="font-size:10px;color:var(--melon-dark);margin-top:4px">KAS setelah gaji kurir</div>
                <div style="font-size:16px;font-weight:600;color:{{ $kasSetelahGaji >= 0 ? 'var(--melon-dark)' : '#dc2626' }};margin-top:2px">Rp {{ number_format($kasSetelahGaji) }}</div>
                <div style="font-size:9px;color:var(--melon-dark);margin-top:3px">Gaji: Rp {{ number_format($gajiRealisasi) }} ({{ number_format($totalTabungAktual) }} tab × 500)</div>
            </div>
            <div style="background:#eff6ff;border:0.5px solid #bfdbfe;border-radius:8px;padding:10px 12px">
                <div style="font-size:10px;color:#3b82f6">Saldo BANK Penampung</div>
                <div style="font-size:16px;font-weight:600;color:{{ $finalBankBal >= 0 ? '#1e40af' : '#dc2626' }};margin-top:2px">Rp {{ number_format($finalBankBal) }}</div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Rasio Ops / Margin Bersih</div>
                <div style="font-size:15px;font-weight:600;color:{{ $rasioRealOps > 35 ? '#b45309' : 'var(--melon-dark)' }};margin-top:2px">{{ number_format($rasioRealOps,1) }}%</div>
                <div style="font-size:9px;color:var(--text3)">ideal &lt;35%</div>
            </div>
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Rasio Gross Kas Keluar</div>
                <div style="font-size:15px;font-weight:600;color:{{ $rasioGrosReal > 80 ? '#dc2626' : 'var(--melon-dark)' }};margin-top:2px">{{ number_format($rasioGrosReal,1) }}%</div>
                <div style="font-size:9px;color:var(--text3)">ideal &lt;80%</div>
            </div>
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Total Aset (KAS + BANK − Gaji)</div>
                <div style="font-size:15px;font-weight:600;color:{{ $netBersih >= 0 ? 'var(--text1)' : '#dc2626' }};margin-top:2px">Rp {{ number_format($netBersih) }}</div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══ CHART TREN ══ --}}
<div class="s-card">
    <div class="s-card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <span>📈 Tren Harian — Kas, Pengeluaran & Saldo</span>
        <div style="display:flex;gap:6px;" id="cfTabBtns">
            <button onclick="cfSwitch('arus',this)" class="btn-secondary btn-sm" style="background:var(--text1);color:#fff;border-color:var(--text1);">Arus Kas</button>
            <button onclick="cfSwitch('saldo',this)" class="btn-secondary btn-sm">Saldo</button>
            <button onclick="cfSwitch('net',this)" class="btn-secondary btn-sm">Net</button>
        </div>
    </div>
    <div style="padding:12px 14px;">
        <div id="cf-leg-arus" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:10px;">
            @foreach([['#3B6D11','Kas diterima'],['#E24B4A','Pengeluaran'],['#378ADD','TF ke penampung'],['#7F77DD','TF ke rek utama']] as [$col,$lbl])
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3);">
                <span style="width:10px;height:10px;border-radius:2px;background:{{ $col }};flex-shrink:0;"></span>{{ $lbl }}
            </span>
            @endforeach
        </div>
        <div id="cf-leg-saldo" style="display:none;flex-wrap:wrap;gap:12px;margin-bottom:10px;">
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3);"><span style="width:18px;border-top:2.5px dashed #BA7517;"></span>Saldo KAS</span>
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3);"><span style="width:18px;border-top:2.5px dashed #378ADD;"></span>Saldo BANK</span>
        </div>
        <div id="cf-leg-net" style="display:none;flex-wrap:wrap;gap:12px;margin-bottom:10px;">
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3);"><span style="width:10px;height:10px;border-radius:50%;background:#3B6D11;flex-shrink:0;"></span>Surplus</span>
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3);"><span style="width:10px;height:10px;border-radius:50%;background:#E24B4A;flex-shrink:0;"></span>Defisit</span>
        </div>
        <div style="position:relative;width:100%;height:280px;">
            <canvas id="cfTrendChart"></canvas>
        </div>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:14px;padding:12px;background:var(--surface2);border-radius:var(--radius-sm);">
            <div>
                <div style="font-size:10px;color:var(--text3);">Total kas diterima</div>
                <div style="font-size:16px;font-weight:600;color:#3B6D11;">Rp {{ number_format($totalIncome) }}</div>
            </div>
            <div>
                <div style="font-size:10px;color:var(--text3);">Total pengeluaran</div>
                <div style="font-size:16px;font-weight:600;color:#E24B4A;">Rp {{ number_format($totalExpense) }}</div>
            </div>
            <div>
                <div style="font-size:10px;color:var(--text3);">Total TF penampung</div>
                <div style="font-size:16px;font-weight:600;color:#378ADD;">Rp {{ number_format($totalDeposits) }}</div>
            </div>
            <div>
                <div style="font-size:10px;color:var(--text3);">Net cashflow</div>
                <div style="font-size:16px;font-weight:600;color:{{ $totalNet >= 0 ? '#3B6D11' : '#E24B4A' }};">Rp {{ number_format($totalNet) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- ══ ANOMALI + DONUT ══ --}}
<div class="s-card" style="margin-bottom:10px">
    <div class="s-card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>🔍 Anomali & Kesehatan</span>
        <span id="cf-score-badge" style="font-size:10px;padding:2px 8px;border-radius:6px;font-weight:600"></span>
    </div>
    <div id="cf-anomaly-grid" style="padding:10px 12px;display:flex;flex-direction:column;gap:6px"></div>
</div>
<div class="s-card" style="margin-bottom:10px">
    <div class="s-card-header">📊 Komposisi Pengeluaran</div>
    <div style="padding:10px 12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <div style="width:110px;height:110px;flex-shrink:0"><canvas id="cfDonutChart"></canvas></div>
        <div id="cf-donut-legend" style="flex:1;display:flex;flex-direction:column;gap:4px;min-width:0"></div>
    </div>
</div>

{{-- ══ REKAP GRID ══ --}}
<div class="s-card">
    <div class="s-card-header">📊 Rekap Cashflow (Uang Keluar &amp; Masuk)</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th style="position:sticky;left:0;background:#f8faf8;z-index:2;min-width:110px">Kategori</th>
                    @for($d=1;$d<=$daysInMonth;$d++)<th style="text-align:center;width:28px;padding:6px 2px">{{ $d }}</th>@endfor
                    <th class="r" style="background:var(--melon-50)">Total</th>
                    <th class="r" style="background:#fffbeb;position:sticky;right:0;z-index:2;white-space:nowrap">Avg/Hari</th>
                </tr>
            </thead>
            <tbody>
                @if($openingCash > 0)
                <tr style="background:#f0fdf4">
                    <td class="bold" style="position:sticky;left:0;background:#f0fdf4;z-index:1;color:var(--melon-dark)">
                        Saldo Awal KAS
                        <div style="font-size:9px;font-weight:400;color:var(--melon)">cutoff periode lalu</div>
                    </td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    <td style="text-align:center;padding:6px 2px;{{ $day===1 ? 'color:var(--melon-dark);font-weight:600' : 'color:#d1d5db' }}">
                        {{ $day===1 ? number_format($openingCash/1000).'k' : '—' }}
                    </td>
                    @endfor
                    <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($openingCash) }}</td>
                    <td class="r" style="position:sticky;right:0;background:#f0fdf4;color:var(--text3)">—</td>
                </tr>
                @endif

                @foreach($categories as $cat)
                @php $catLabel = \App\Models\DailyExpense::$categoryLabels[$cat] ?? $cat; @endphp
                <tr>
                    <td class="bold" style="position:sticky;left:0;background:#fff;z-index:1;color:var(--text2)">{{ $catLabel }}</td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php $val=$expenseGrid[$cat][$day]??0; @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $val>0 ? 'color:#dc2626;font-weight:600' : 'color:#d1d5db' }}">
                        {{ $val>0 ? number_format($val/1000).'k' : '—' }}
                    </td>
                    @endfor
                    <td class="r bold" style="color:{{ ($categoryTotals[$cat]??0)>0 ? '#dc2626' : 'var(--text3)' }}">
                        {{ ($categoryTotals[$cat]??0)>0 ? 'Rp '.number_format($categoryTotals[$cat]) : '—' }}
                    </td>
                    <td class="r" style="position:sticky;right:0;background:#fff;color:{{ $avgCatByDay[$cat]>0 ? '#ef4444' : 'var(--text3)' }};font-weight:{{ $avgCatByDay[$cat]>0 ? '600' : '400' }}">
                        {{ $avgCatByDay[$cat]>0 ? 'Rp '.number_format($avgCatByDay[$cat]) : '—' }}
                    </td>
                </tr>
                @endforeach

                <tr style="background:#dc2626;color:#fff;font-weight:600">
                    <td style="position:sticky;left:0;background:#dc2626;z-index:1;padding:7px 10px">Total Pengeluaran</td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php $val=$dayTotals[$day]??0; @endphp
                    <td style="text-align:center;padding:6px 2px;color:{{ $val>0 ? '#fecaca' : '#ef9999' }}">{{ $val>0 ? number_format($val/1000).'k' : '—' }}</td>
                    @endfor
                    <td class="r" style="padding:7px 10px">Rp {{ number_format($totalExpense) }}</td>
                    <td class="r" style="position:sticky;right:0;background:#dc2626;padding:7px 10px;color:#fecaca">Rp {{ number_format($avgExpense) }}</td>
                </tr>

                <tr style="background:#eff6ff">
                    <td class="bold" style="position:sticky;left:0;background:#eff6ff;z-index:1;color:#1e40af">TF ke Penampung</td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php $val=$depositsByDay[$day]['total']??0; @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $val>0 ? 'color:#1d4ed8;font-weight:600' : 'color:#d1d5db' }}">{{ $val>0 ? number_format($val/1000).'k' : '—' }}</td>
                    @endfor
                    <td class="r bold" style="color:#1e40af">Rp {{ number_format($totalDeposits) }}</td>
                    <td class="r bold" style="position:sticky;right:0;background:#eff6ff;color:#1e40af">Rp {{ number_format($avgDeposit) }}</td>
                </tr>

                <tr style="background:#eff6ff">
                    <td style="position:sticky;left:0;background:#eff6ff;z-index:1;color:#3b82f6;font-size:10px;padding-left:18px">└ Admin TF Penampung</td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php $val=$depositsByDay[$day]['admin']??0; @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $val>0 ? 'color:#3b82f6;font-weight:600' : 'color:#d1d5db' }}">{{ $val>0 ? number_format($val/1000).'k' : '—' }}</td>
                    @endfor
                    <td class="r" style="color:{{ $totalAdminFees>0 ? '#3b82f6' : 'var(--text3)' }};font-weight:600">{{ $totalAdminFees>0 ? 'Rp '.number_format($totalAdminFees) : '—' }}</td>
                    <td class="r" style="position:sticky;right:0;background:#eff6ff;color:{{ $avgAdminFee>0 ? '#3b82f6' : 'var(--text3)' }};font-weight:600">{{ $avgAdminFee>0 ? 'Rp '.number_format($avgAdminFee) : '—' }}</td>
                </tr>

                <tr style="background:#f5f3ff">
                    <td class="bold" style="position:sticky;left:0;background:#f5f3ff;z-index:1;color:#6d28d9">
                        TF ke Rek Utama
                        <div style="font-size:9px;font-weight:400;color:#a78bfa">penampung → rek utama</div>
                    </td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php $tf=($transfersByDay[$day]['total']??0)+($transfersByDay[$day]['surplus']??0); @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $tf>0 ? 'color:#6d28d9;font-weight:600' : 'color:#d1d5db' }}">{{ $tf>0 ? number_format($tf/1000).'k' : '—' }}</td>
                    @endfor
                    @php $avgTf2=$totalTransferred>0?round($totalTransferred/$cfActiveDays):0; @endphp
                    <td class="r bold" style="color:{{ $totalTransferred>0 ? '#6d28d9' : 'var(--text3)' }}">{{ $totalTransferred>0 ? 'Rp '.number_format($totalTransferred) : '—' }}</td>
                    <td class="r" style="position:sticky;right:0;background:#f5f3ff;color:{{ $avgTf2>0 ? '#6d28d9' : 'var(--text3)' }};font-weight:600">{{ $avgTf2>0 ? 'Rp '.number_format($avgTf2) : '—' }}</td>
                </tr>

                <tr style="background:#f0fdf4">
                    <td class="bold" style="position:sticky;left:0;background:#f0fdf4;z-index:1;color:var(--melon-dark)">
                        Kas Diterima
                        <div style="font-size:9px;font-weight:400;color:var(--melon)">penjualan gas riil</div>
                    </td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php $val=$salesByDay[$day]??0; @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $val>0 ? 'color:var(--melon-dark);font-weight:600' : 'color:#d1d5db' }}">{{ $val>0 ? number_format($val/1000).'k' : '—' }}</td>
                    @endfor
                    <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($totalIncome) }}</td>
                    <td class="r bold" style="position:sticky;right:0;background:#f0fdf4;color:var(--melon-dark)">Rp {{ number_format($avgSales) }}</td>
                </tr>

                <tr style="background:#fffbeb">
                    <td class="bold" style="position:sticky;left:0;background:#fffbeb;z-index:1;color:#92400e">
                        Saldo KAS (Fisik)
                        <div style="font-size:9px;font-weight:400;color:#b45309">uang di tangan</div>
                    </td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php
                        $bal    = $dailyBalance[$day];
                        $hasAny = ($salesByDay[$day]??0)>0||($dayTotals[$day]??0)>0||($depositsByDay[$day]['total']??0)>0||($day===1&&$openingCash>0);
                    @endphp
                    <td style="text-align:center;padding:6px 2px;font-size:10px;{{ !$hasAny ? 'color:#d1d5db' : ($bal>0 ? 'color:#92400e;font-weight:600' : ($bal<0 ? 'color:#dc2626;font-weight:700' : 'color:var(--text3)')) }}"
                        title="{{ $hasAny ? 'KAS tgl '.$day.': Rp '.number_format($bal) : '' }}">
                        {{ $hasAny ? number_format($bal/1000).'k' : '—' }}
                    </td>
                    @endfor
                    <td class="r bold" style="color:{{ $netKas>=0 ? '#92400e' : '#dc2626' }}">Rp {{ number_format($netKas) }}</td>
                    <td class="r" style="position:sticky;right:0;background:#fffbeb;color:var(--text3)">—</td>
                </tr>

                <tr style="background:#eff6ff">
                    <td class="bold" style="position:sticky;left:0;background:#eff6ff;z-index:1;color:#1e40af">
                        Saldo BANK (Penampung)
                        <div style="font-size:9px;font-weight:400;color:#3b82f6">rek penampung</div>
                    </td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php
                        $bbal    = $dailyBankBalance[$day];
                        $hasBank = ($depositsByDay[$day]['total']??0)>0||($transfersByDay[$day]['total']??0)>0||($day===1&&$openingPenampung>0);
                    @endphp
                    <td style="text-align:center;padding:6px 2px;font-size:10px;{{ !$hasBank ? 'color:#d1d5db' : ($bbal>0 ? 'color:#1e40af;font-weight:600' : ($bbal<0 ? 'color:#dc2626;font-weight:700' : 'color:var(--text3)')) }}"
                        title="{{ $hasBank ? 'BANK tgl '.$day.': Rp '.number_format($bbal) : '' }}">
                        {{ $hasBank ? number_format($bbal/1000).'k' : '—' }}
                    </td>
                    @endfor
                    <td class="r bold" style="color:{{ $finalBankBal>=0 ? '#1e40af' : '#dc2626' }}">Rp {{ number_format($finalBankBal) }}</td>
                    <td class="r" style="position:sticky;right:0;background:#eff6ff;color:var(--text3)">—</td>
                </tr>

                <tr style="background:#374151;color:#fff;font-weight:600">
                    <td style="position:sticky;left:0;background:#374151;z-index:1;padding:7px 10px">
                        Net Harian
                        <div style="font-size:9px;font-weight:400;color:#9ca3af">selisih hari ini saja</div>
                    </td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php
                        $inc  = ($salesByDay[$day]??0)+($day===1?$openingCash:0);
                        $exp2 = $dayTotals[$day]??0;
                        $dep2 = ($depositsByDay[$day]['total']??0)+($depositsByDay[$day]['admin']??0);
                        $net2 = $inc-$exp2-$dep2;
                        $hasD = ($salesByDay[$day]??0)>0||($dayTotals[$day]??0)>0||($depositsByDay[$day]['total']??0)>0||($day===1&&$openingCash>0);
                    @endphp
                    <td style="text-align:center;padding:6px 2px;color:{{ $net2>0 ? '#86efac' : ($net2<0 ? '#fca5a5' : '#9ca3af') }}">
                        {{ $hasD ? number_format($net2/1000).'k' : '—' }}
                    </td>
                    @endfor
                    <td class="r" style="padding:7px 10px;color:{{ $totalNet>=0 ? '#86efac' : '#fca5a5' }}">Rp {{ number_format($totalNet) }}</td>
                    <td class="r" style="position:sticky;right:0;background:#374151;padding:7px 10px;color:{{ $avgNet>=0 ? '#86efac' : '#fca5a5' }}">Rp {{ number_format($avgNet) }}</td>
                </tr>

                <tr style="background:var(--surface2);border-top:2px solid var(--border)">
                    <td style="position:sticky;left:0;background:var(--surface2);z-index:1;padding:7px 10px;font-weight:600;color:var(--text2)">
                        Rata-rata/Hari
                        <div style="font-size:9px;font-weight:400;color:var(--text3)">{{ $cfActiveDays }} hari aktif</div>
                    </td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    <td style="text-align:center;padding:6px 2px;color:var(--text3)">—</td>
                    @endfor
                    <td class="r bold" style="color:var(--text2)">Rp {{ number_format($avgNet) }}</td>
                    <td class="r bold" style="position:sticky;right:0;background:var(--surface2);color:var(--text2)">Rp {{ number_format($avgNet) }}</td>
                </tr>

                @foreach([['└ Avg Kas Diterima',$avgSales,'var(--melon-dark)'],['└ Avg Pengeluaran',$avgExpense,'#dc2626'],['└ Avg TF ke Penampung',$avgDeposit,'#1d4ed8']] as [$lbl,$val,$col])
                <tr style="background:var(--surface2)">
                    <td style="position:sticky;left:0;background:var(--surface2);z-index:1;color:var(--text3);padding-left:18px;font-size:10px">{{ $lbl }}</td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    <td style="text-align:center;padding:6px 2px;color:var(--border)">—</td>
                    @endfor
                    <td class="r bold" style="color:{{ $col }}">Rp {{ number_format($val) }}</td>
                    <td class="r bold" style="position:sticky;right:0;background:var(--surface2);color:{{ $col }}">Rp {{ number_format($val) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="padding:8px 14px;font-size:10px;color:var(--text3);border-top:0.5px solid var(--border)">
        Nilai dalam ribuan (k). <strong style="color:var(--melon-dark)">Kas Diterima</strong> = paid_amount riil.
        <strong style="color:#92400e">Saldo KAS</strong> = uang fisik.
        <strong style="color:#1e40af">Saldo BANK</strong> = rek penampung.
    </div>
</div>

{{-- ══ DETAIL PENGELUARAN ══ --}}
<div class="s-card">
    <div class="s-card-header">📋 Detail Pengeluaran</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Kategori</th>
                    <th>Keterangan</th>
                    <th class="r">Nominal</th>
                    @if($period->status === 'open') <th>Aksi</th> @endif
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $exp)
                <tr>
                    <td>{{ $exp->expense_date->format('d/m/Y') }}</td>
                    <td><span class="badge badge-orange">{{ $exp->categoryLabel() }}</span></td>
                    <td style="color:var(--text3)">{{ $exp->description ?: '—' }}</td>
                    <td class="r bold" style="color:#dc2626">Rp {{ number_format($exp->amount) }}</td>
                    @if($period->status === 'open')
                    <td>
                        <form method="POST" action="{{ route('cashflow.destroy', $exp) }}" style="display:inline" onsubmit="return confirm('Hapus?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="link-btn" style="color:#dc2626">Hapus</button>
                        </form>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center;padding:20px;color:var(--text3)">Belum ada pengeluaran.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
    /* ═══════════════════════════════════
       DATA dari PHP
       ═══════════════════════════════════ */
    const salesByDay     = @json($jsSales);
    const dayTotals      = @json($jsExpenses);
    const depositsByDay  = @json($jsDeposits);
    const transfersByDay = @json($jsTransfers);
    const cashBal        = @json($jsCashBal);
    const bankBal        = @json($jsBankBal);
    const daysInMonth    = {{ $daysInMonth }};
    const catLabels      = @json($catLabelsSafe);
    const catTotals      = @json($catTotalsSafe);
    const labels         = @json($jsLabels);

    // Monte Carlo params dari PHP — semua basis MARGIN BERSIH
    const MC_MARGIN_MEAN = {{ $mcMarginMean }};
    const MC_MARGIN_STD  = {{ $mcMarginStd }};
    const MC_EXP_MEAN    = {{ $mcExpMean }};
    const MC_EXP_STD     = {{ $mcExpStd }};
    const MC_ADMIN_MEAN  = {{ $mcAdminMean }};
    const MC_ADMIN_STD   = {{ $mcAdminStd }};
    const MC_CORR        = {{ $mcCorrCoef }};
    const MC_REMAIN_DAYS = {{ $remainDays }};
    const NET_KAS_NOW    = {{ $netKas }};
    const GAJI_TOTAL     = {{ $predObj['predGajiTotal'] ?? 0 }};
    const PIUTANG_CAIR   = {{ $predObj['piutangCairEstimasi'] ?? 0 }};
    const WMA_PRED       = {{ round($predObj['predKas']) }};
    const OLS_PRED       = {{ round($olsPredKas) }};
    const WMA_CONF       = {{ $predObj['confidence'] }};
    const HOLT_PRED      = {{ round($predObj['holtsPredKas']) }};
    const HOLT_CONF      = {{ $predObj['holtsConfidence'] }};
    const OLS_CONF       = {{ $olsConfidence }};

    const fmtK = v => {
        const abs = Math.abs(v);
        const neg = v < 0;
        let s;
        if (abs >= 1000000) s = (Math.round(abs/100000)/10).toFixed(1)+'jt';
        else if (abs >= 1000) s = Math.round(abs/1000)+'k';
        else s = abs.toString();
        return (neg ? '-' : '') + 'Rp ' + s;
    };
    const fmtFull = v => (v < 0 ? '-' : '') + 'Rp ' + Math.abs(Math.round(v)).toLocaleString('id');

    const GRAY = 'rgba(0,0,0,0.05)';
    const TICK = { font:{size:10}, color:'#9CA3AF' };

    /* ═══════════════════════════════════════════
       TAB SWITCHER — METODE PREDIKSI
       ═══════════════════════════════════════════ */
    window.switchPredTab = function(tab, btn) {
        ['wma','ols','holt','mc','konsensus'].forEach(t => {  // ← tambah 'holt'
            const p = document.getElementById('pred-panel-' + t);
            if (p) p.style.display = 'none';
        });
        document.querySelectorAll('#predMethodTabs button').forEach(b => {
            b.style.borderBottomColor = 'transparent';
            b.style.color = 'var(--text3)';
        });
        const panel = document.getElementById('pred-panel-' + tab);
        if (panel) { panel.style.display = 'flex'; panel.style.flexDirection = 'column'; }
        btn.style.borderBottomColor = '#f97316';
        btn.style.color = '#c2410c';

        if (tab === 'konsensus') {
            if (mcMedianResult !== null) {
                renderConsensusChart(mcMedianResult, null);
            } else {
                updateConsensusPartial();  // ← konsensus 3 metode sebelum MC
            }
        }
    };

    /* ═══════════════════════════════════════════
       CHART TREN HARIAN
       ═══════════════════════════════════════════ */
    (function(){
        const fmtKLocal = v => {
            const abs = Math.abs(v);
            const s = abs >= 1000000 ? (Math.round(abs/100000)/10).toFixed(1)+'jt' : Math.round(abs/1000)+'k';
            return (v < 0 ? '-' : '') + s;
        };
        const net = labels.map((_,i) => salesByDay[i] - dayTotals[i] - depositsByDay[i]);
        const arusData = {
            labels,
            datasets:[
                { label:'Kas diterima', data:salesByDay.map(v=>v/1000),     type:'bar', backgroundColor:'#3B6D1155', borderColor:'#3B6D11', borderWidth:1, borderRadius:3, stack:'income', order:2 },
                { label:'Pengeluaran',  data:dayTotals.map(v=>v/1000),      type:'bar', backgroundColor:'#E24B4A88', borderColor:'#E24B4A', borderWidth:1, borderRadius:3, stack:'out',    order:2 },
                { label:'TF penampung', data:depositsByDay.map(v=>v/1000),  type:'bar', backgroundColor:'#378ADD77', borderColor:'#378ADD', borderWidth:1, borderRadius:3, stack:'out',    order:2 },
                { label:'TF rek utama', data:transfersByDay.map(v=>v/1000), type:'bar', backgroundColor:'#7F77DD77', borderColor:'#7F77DD', borderWidth:1, borderRadius:3, stack:'out',    order:2 },
            ]
        };
        const saldoData = {
            labels,
            datasets:[
                { label:'Saldo KAS',  data:cashBal.map(v=>v/1000), type:'line', borderColor:'#BA7517', backgroundColor:'rgba(186,117,23,0.07)', borderWidth:2.5, borderDash:[6,3], pointRadius:cashBal.map(v=>v<0?5:2), pointBackgroundColor:cashBal.map(v=>v<0?'#E24B4A':'#BA7517'), tension:0.35, fill:true },
                { label:'Saldo BANK', data:bankBal.map(v=>v/1000),  type:'line', borderColor:'#378ADD', backgroundColor:'rgba(55,138,221,0.07)', borderWidth:2.5, borderDash:[6,3], pointRadius:2, pointBackgroundColor:'#378ADD', tension:0.35, fill:true },
            ]
        };
        const netColors  = net.map(v => v>=0 ? '#3B6D1166' : '#E24B4A88');
        const netBorders = net.map(v => v>=0 ? '#3B6D11'   : '#E24B4A');
        const netData = {
            labels,
            datasets:[{ label:'Net harian', data:net.map(v=>v/1000), type:'bar', backgroundColor:netColors, borderColor:netBorders, borderWidth:1, borderRadius:4 }]
        };
        const makeOpts = (yTitle) => ({
            responsive:true, maintainAspectRatio:false,
            interaction:{ mode:'index', intersect:false },
            plugins:{ legend:{display:false}, tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${fmtKLocal(ctx.parsed.y * 1000)}`}} },
            scales:{
                x:{ grid:{color:GRAY}, ticks:{...TICK, autoSkip:true, maxTicksLimit:16, maxRotation:0} },
                y:{ grid:{color:GRAY}, ticks:{...TICK, callback:v=>fmtKLocal(v*1000)}, title:{display:true,text:yTitle,color:'#9CA3AF',font:{size:10}} }
            }
        });
        const chart = new Chart(document.getElementById('cfTrendChart'), { data: arusData, options: makeOpts('Ribuan Rp') });
        window.cfSwitch = function(tab, btn) {
            document.querySelectorAll('#cfTabBtns button').forEach(b => { b.style.background=''; b.style.color=''; b.style.borderColor=''; });
            btn.style.background = 'var(--text1)'; btn.style.color = '#fff'; btn.style.borderColor = 'var(--text1)';
            ['arus','saldo','net'].forEach(t => { document.getElementById('cf-leg-'+t).style.display = t===tab ? 'flex' : 'none'; });
            chart.data = tab==='arus' ? arusData : tab==='saldo' ? saldoData : netData;
            chart.options = makeOpts(tab==='arus' ? 'Ribuan Rp' : tab==='saldo' ? 'Saldo (ribu Rp)' : 'Net (ribu Rp)');
            chart.update('none');
        };
    })();

    /* ═══════════════════════════════════════════
       ANOMALI + DONUT
       ═══════════════════════════════════════════ */
    const activeDays = labels.filter(i=>salesByDay[i-1]>0||dayTotals[i-1]>0||depositsByDay[i-1]>0).length||1;
    const totalExp   = dayTotals.reduce((a,b)=>a+b,0);
    const totalInc   = salesByDay.reduce((a,b)=>a+b,0);
    const avgExpD    = totalExp/activeDays;
    const spikeDays  = labels.filter(d=>(dayTotals[d-1]||0)>avgExpD*2.0);
    const negCashDays= labels.filter(d=>cashBal[d-1]<0);
    const negBankDays= labels.filter(d=>bankBal[d-1]<0);
    const anomalies  = [];
    if(spikeDays.length)   anomalies.push({sev:'danger',title:'Lonjakan pengeluaran',body:`Tgl: ${spikeDays.join(', ')} (>2× rata-rata)`});
    if(negCashDays.length) anomalies.push({sev:'danger',title:'Saldo KAS negatif',body:`Tgl: ${negCashDays.join(', ')}`});
    if(negBankDays.length) anomalies.push({sev:'danger',title:'Saldo BANK negatif',body:`Tgl: ${negBankDays.join(', ')}`});
    const ratio = totalInc>0?totalExp/totalInc:0;
    if(ratio>0.4) anomalies.push({sev:'warn',title:'Rasio biaya tinggi (vs gross)',body:`Pengeluaran ${(ratio*100).toFixed(0)}% dari kas diterima`});
    if(!anomalies.length) anomalies.push({sev:'ok',title:'Cashflow normal',body:'Tidak ada anomali signifikan'});
    const sc = Math.max(0,Math.min(100,100-spikeDays.length*15-negCashDays.length*20-negBankDays.length*20-(ratio>0.4?10:0)));
    const badge = document.getElementById('cf-score-badge');
    badge.textContent = sc+'/100 '+(sc>=75?'🟢':sc>=50?'🟡':'🔴');
    badge.style.cssText = sc>=75?'background:#dcfce7;color:#166534;':sc>=50?'background:#fef9c3;color:#92400e;':'background:#fee2e2;color:#991b1b;';
    const bgMap  = {danger:'#fef2f2',warn:'#fffbeb',ok:'#f0fdf4'};
    const colMap = {danger:'#991b1b',warn:'#92400e',ok:'var(--melon-dark)'};
    const grid   = document.getElementById('cf-anomaly-grid');
    anomalies.forEach(a=>{
        const d = document.createElement('div');
        d.style.cssText = `padding:8px 10px;border-radius:8px;font-size:11px;background:${bgMap[a.sev]};border:0.5px solid #e5e7eb;`;
        d.innerHTML = `<div style="font-weight:700;color:${colMap[a.sev]};margin-bottom:2px">${a.title}</div><div style="color:#374151">${a.body}</div>`;
        grid.appendChild(d);
    });

    const palette = ['#ef4444','#3b82f6','#f59e0b','#10b981','#8b5cf6','#f97316','#06b6d4','#84cc16','#ec4899','#6366f1','#14b8a6','#a855f7'];
    const nonZero = catTotals.map((v,i)=>({v,i})).filter(x=>x.v>0);
    const dL=nonZero.map(x=>catLabels[x.i]),dD=nonZero.map(x=>x.v),dC=nonZero.map((_,i)=>palette[i%palette.length]);
    const tot=dD.reduce((a,b)=>a+b,0);
    new Chart(document.getElementById('cfDonutChart'),{
        type:'doughnut',
        data:{labels:dL,datasets:[{data:dD,backgroundColor:dC,borderWidth:1,borderColor:'#fff'}]},
        options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>`${ctx.label}: Rp ${Math.round(ctx.raw/1000)}k (${(ctx.raw/tot*100).toFixed(1)}%)`}}}}
    });
    const leg=document.getElementById('cf-donut-legend');
    dL.forEach((lbl,i)=>{
        const pct=tot>0?(dD[i]/tot*100).toFixed(1):0;
        const row=document.createElement('div');
        row.style.cssText='display:flex;align-items:center;gap:6px;font-size:10px;';
        row.innerHTML=`<span style="width:9px;height:9px;border-radius:2px;flex-shrink:0;background:${dC[i]}"></span><span style="color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1">${lbl}</span><span style="font-weight:700;color:var(--text1);flex-shrink:0">${pct}%</span>`;
        leg.appendChild(row);
    });

    /* ═══════════════════════════════════════════════════════
       MONTE CARLO SIMULATION — basis MARGIN BERSIH
       Box-Muller transform, 10.000 iterasi, browser-side
       ═══════════════════════════════════════════════════════ */
    function boxMuller() {
        let u, v, s;
        do { u = Math.random()*2-1; v = Math.random()*2-1; s = u*u+v*v; } while (s>=1||s===0);
        const mul = Math.sqrt(-2*Math.log(s)/s);
        return [u*mul, v*mul];
    }
    function sampleGaussian(mean, std, floor=null) {
        if (std<=0) return floor!==null ? Math.max(floor,mean) : mean;
        const [z] = boxMuller();
        const val = mean + z*std;
        return floor!==null ? Math.max(floor,val) : val;
    }
    // Cholesky 2×2: sales & expense berkorelasi
    function sampleCorrelated(meanA, stdA, meanB, stdB, rho) {
        const [z1, zIndep] = boxMuller();
        const z2 = rho*z1 + Math.sqrt(Math.max(0,1-rho*rho))*zIndep;
        const a = stdA>0 ? meanA+z1*stdA : meanA;
        const b = stdB>0 ? meanB+z2*stdB : meanB;
        return [Math.max(0,a), Math.max(0,b)];
    }

    let mcHistChartInstance = null;
    let mcMedianResult = null;

    window.runMonteCarlo = function() {
        if (MC_REMAIN_DAYS <= 0) {
            document.getElementById('mc-status').textContent = 'Tidak ada hari tersisa untuk disimulasikan.';
            return;
        }
        const btn = document.getElementById('mc-run-btn');
        const icon = document.getElementById('mc-btn-icon');
        btn.disabled = true; icon.textContent = '⏳';
        document.getElementById('mc-status').textContent = 'Menjalankan 10.000 simulasi...';

        setTimeout(() => {
            const N_ITER  = 10000;
            const results = new Float64Array(N_ITER);

            for (let i = 0; i < N_ITER; i++) {
                let projMargin = 0, projExp = 0, projAdmin = 0;
                for (let d = 0; d < MC_REMAIN_DAYS; d++) {
                    // Margin & expense berkorelasi (Cholesky)
                    const [m, e] = sampleCorrelated(MC_MARGIN_MEAN, MC_MARGIN_STD, MC_EXP_MEAN, MC_EXP_STD, MC_CORR);
                    // Admin fee: distribusi sendiri
                    const admin = sampleGaussian(MC_ADMIN_MEAN, MC_ADMIN_STD, 0);
                    projMargin += m;
                    projExp    += e;
                    projAdmin  += admin;
                }
                // Saldo akhir = KAS kini + margin + piutang - ops - admin - gaji
                results[i] = NET_KAS_NOW + projMargin + PIUTANG_CAIR - projExp - projAdmin - GAJI_TOTAL;
            }

            results.sort();

            const pct = (p) => results[Math.min(Math.floor(p/100*N_ITER), N_ITER-1)];
            const p5=pct(5), p25=pct(25), p50=pct(50), p75=pct(75), p95=pct(95);
            const pDeficit = results.filter(v=>v<0).length / N_ITER * 100;
            mcMedianResult = p50;

            // Render persentil
            const percContainer = document.getElementById('mc-percentiles');
            percContainer.innerHTML = '';
            [
                {label:'P5 (Worst 5%)', val:p5,  bg:'#fef2f2',border:'#fca5a5',col:'#991b1b'},
                {label:'P25 (Pesimis)', val:p25, bg:'#fff7ed',border:'#fed7aa',col:'#92400e'},
                {label:'P50 (Median)',  val:p50, bg:'#faf5ff',border:'#d8b4fe',col:'#6d28d9',bold:true},
                {label:'P75 (Optimis)', val:p75, bg:'#f0fdf4',border:'#86efac',col:'#166534'},
                {label:'P95 (Best 5%)', val:p95, bg:'#ecfdf5',border:'#6ee7b7',col:'#065f46'},
            ].forEach(({label,val,bg,border,col,bold})=>{
                percContainer.innerHTML += `<div style="background:${bg};border:0.5px solid ${border};border-radius:8px;padding:8px 10px;text-align:center">
                    <div style="font-size:9px;font-weight:700;color:${col};text-transform:uppercase;margin-bottom:3px">${label}</div>
                    <div style="font-size:${bold?'14px':'12px'};font-weight:${bold?'700':'600'};color:${col}">${fmtK(val)}</div>
                    <div style="font-size:9px;color:${col};margin-top:2px">${val>=0?'✓':'⚠'}</div>
                </div>`;
            });

            document.getElementById('mc-prob-deficit').textContent = pDeficit.toFixed(1)+'%';
            document.getElementById('mc-prob-deficit').style.color = pDeficit>30?'#dc2626':pDeficit>10?'#b45309':'#166534';
            document.getElementById('mc-median').textContent = fmtFull(p50);
            document.getElementById('mc-median').style.color = p50>=0?'#6d28d9':'#dc2626';
            document.getElementById('mc-ci90').textContent = `${fmtK(p5)} s/d ${fmtK(p95)}`;
            document.getElementById('mc-ci50').textContent = `${fmtK(p25)} s/d ${fmtK(p75)}`;

            let interp = '';
            if (pDeficit<5)       interp = `✅ <strong>Risiko sangat rendah</strong> (${pDeficit.toFixed(1)}%). Cashflow sangat sehat.`;
            else if (pDeficit<20) interp = `🟡 <strong>Risiko moderat</strong> (${pDeficit.toFixed(1)}%). Mayoritas skenario masih positif.`;
            else if (pDeficit<40) interp = `🟠 <strong>Risiko signifikan</strong> (${pDeficit.toFixed(1)}%). Pertimbangkan efisiensi biaya.`;
            else                  interp = `🔴 <strong>Risiko tinggi</strong> (${pDeficit.toFixed(1)}%). Tindakan korektif diperlukan.`;
            interp += ` Median P50 = ${fmtFull(p50)}. CI 90%: ${fmtK(p5)} − ${fmtK(p95)}.`;
            const interpEl = document.getElementById('mc-interpretation');
            interpEl.innerHTML = interp;
            interpEl.style.background = pDeficit<10?'#f0fdf4':pDeficit<30?'#fffbeb':'#fef2f2';
            interpEl.style.borderColor = pDeficit<10?'#86efac':pDeficit<30?'#fde68a':'#fca5a5';
            interpEl.style.color = pDeficit<10?'#166534':pDeficit<30?'#92400e':'#991b1b';

            // Histogram
            const NUM_BINS=20, minVal=results[0], maxVal=results[N_ITER-1];
            const binSize=(maxVal-minVal)/NUM_BINS;
            const bins=new Array(NUM_BINS).fill(0);
            for(let i=0;i<N_ITER;i++){
                const binIdx=Math.min(Math.floor((results[i]-minVal)/binSize),NUM_BINS-1);
                bins[binIdx]++;
            }
            const binLabels=bins.map((_,i)=>fmtK(minVal+(i+0.5)*binSize));
            const binColors=bins.map((_,i)=>(minVal+(i+0.5)*binSize)>=0?'#7c3aed88':'#dc262688');
            const binBorders=bins.map((_,i)=>(minVal+(i+0.5)*binSize)>=0?'#7c3aed':'#dc2626');
            if(mcHistChartInstance) mcHistChartInstance.destroy();
            mcHistChartInstance = new Chart(document.getElementById('mcHistChart'),{
                type:'bar',
                data:{labels:binLabels,datasets:[{label:'Frekuensi',data:bins,backgroundColor:binColors,borderColor:binBorders,borderWidth:1,borderRadius:2}]},
                options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>`${ctx.parsed.y}x (${(ctx.parsed.y/N_ITER*100).toFixed(1)}%)`}}},scales:{x:{grid:{color:GRAY},ticks:{...TICK,maxRotation:45,font:{size:9}}},y:{grid:{color:GRAY},ticks:{...TICK,callback:v=>v+'x'}}}}
            });

            document.getElementById('mc-results').style.display='flex';
            document.getElementById('mc-results').style.flexDirection='column';
            document.getElementById('mc-status').textContent=`✅ ${N_ITER.toLocaleString('id')} iterasi selesai.`;
            btn.disabled=false; icon.textContent='↺'; btn.style.background='#5b21b6';

            updateConsensus(p50, Math.max(0,100-Math.round(pDeficit)));
        }, 20);
    };

    /* ═══════════════════════════════════════════════════
       KONSENSUS
       ═══════════════════════════════════════════════════ */
    function updateConsensus(mcP50, mcConf) {
        const totalConf = WMA_CONF + OLS_CONF + HOLT_CONF + mcConf;
        const wWma  = (WMA_CONF  / totalConf * 100).toFixed(0);
        const wOls  = (OLS_CONF  / totalConf * 100).toFixed(0);
        const wHolt = (HOLT_CONF / totalConf * 100).toFixed(0);
        const wMc   = (mcConf    / totalConf * 100).toFixed(0);
        const consensus = (WMA_PRED*WMA_CONF + OLS_PRED*OLS_CONF + HOLT_PRED*HOLT_CONF + mcP50*mcConf) / totalConf;
        const aggConf   = Math.min(95, Math.round(totalConf / 4 + 5));

        document.getElementById('cons-mc-val').textContent  = fmtFull(mcP50);
        document.getElementById('cons-mc-val').style.color  = mcP50 >= 0 ? '#6d28d9' : '#dc2626';
        document.getElementById('cons-mc-conf').textContent = mcConf + '%';
        document.getElementById('cons-w-wma').textContent   = wWma  + '%';
        document.getElementById('cons-w-ols').textContent   = wOls  + '%';
        document.getElementById('cons-w-holt').textContent  = wHolt + '%';
        document.getElementById('cons-w-mc').textContent    = wMc   + '%';
        document.getElementById('cons-total-w').textContent = '100%';
        document.getElementById('cons-note').textContent    =
            `Bobot WMA:${wWma}% / OLS:${wOls}% / Holt:${wHolt}% / MC:${wMc}%`;

        const resultEl = document.getElementById('cons-result');
        resultEl.innerHTML = `<div style="color:${consensus>=0?'#fff':'#fca5a5'};font-size:14px">${fmtFull(consensus)}</div>
            <div style="font-size:9px;color:${consensus>=0?'rgba(255,255,255,0.8)':'#fca5a5'}">
                Confidence agregat: ${aggConf}%<br>${consensus>=0?'✓ Aman':'⚠ Defisit'}</div>`;

        const allPreds = [WMA_PRED, OLS_PRED, HOLT_PRED, mcP50];
        const spread   = Math.max(...allPreds) - Math.min(...allPreds);
        const spreadPct = Math.abs(consensus)>0 ? (spread/Math.abs(consensus)*100).toFixed(0) : 0;
        let insightText = `<span style="font-weight:600">Insight (4 metode):</span> ` +
            `WMA:${fmtK(WMA_PRED)}, OLS:${fmtK(OLS_PRED)}, Holt:${fmtK(HOLT_PRED)}, MC:${fmtK(mcP50)}. `;
        insightText += spread < Math.abs(consensus)*0.15
            ? `📊 <strong>Spread rendah (${spreadPct}%)</strong> — keempat metode sangat sepakat.`
            : spread < Math.abs(consensus)*0.35
            ? `🔸 <strong>Spread sedang (${spreadPct}%)</strong> — ada perbedaan asumsi antar metode.`
            : `⚠ <strong>Spread tinggi (${spreadPct}%)</strong> — cashflow kurang pasti.`;
        document.getElementById('cons-insight').innerHTML = insightText;
        renderConsensusChart(mcP50, aggConf);
    }

    function updateConsensusPartial() {
        const totalConf3 = WMA_CONF + OLS_CONF + HOLT_CONF;
        const cons3 = (WMA_PRED*WMA_CONF + OLS_PRED*OLS_CONF + HOLT_PRED*HOLT_CONF) / totalConf3;
        const wWma  = (WMA_CONF/totalConf3*100).toFixed(0);
        const wOls  = (OLS_CONF/totalConf3*100).toFixed(0);
        const wHolt = (HOLT_CONF/totalConf3*100).toFixed(0);

        document.getElementById('cons-w-wma').textContent  = wWma+'%';
        document.getElementById('cons-w-ols').textContent  = wOls+'%';
        document.getElementById('cons-w-holt').textContent = wHolt+'%';
        document.getElementById('cons-w-mc').textContent   = '—';
        document.getElementById('cons-total-w').textContent= '~100%';
        document.getElementById('cons-note').textContent   =
            `Sementara (3 metode): WMA:${wWma}% / OLS:${wOls}% / Holt:${wHolt}%`;

        document.getElementById('cons-result').innerHTML =
            `<div style="color:${cons3>=0?'#fff':'#fca5a5'};font-size:13px">${fmtFull(cons3)}</div>
            <div style="font-size:9px;color:rgba(255,255,255,0.7)">Tanpa MC · ${cons3>=0?'✓ Aman':'⚠ Defisit'}</div>`;

        document.getElementById('cons-insight').innerHTML =
            `<span style="font-weight:600">Sementara (3 metode):</span> ` +
            `WMA:${fmtK(WMA_PRED)}, OLS:${fmtK(OLS_PRED)}, Holt:${fmtK(HOLT_PRED)}. ` +
            `<em style="color:#0369a1">Jalankan Monte Carlo untuk konsensus lengkap 4 metode.</em>`;

        renderConsensusChart(null, null, cons3);
    }

    let consChartInstance = null;
    function renderConsensusChart(mcP50, aggConf, fallbackConsensus) {
        const canvas = document.getElementById('consCompChart');
        if (!canvas) return;
        const mcVal = (mcP50 != null) ? mcP50 : mcMedianResult;
        const lbls  = ['WMA Adaptif','Regresi Linear','Holt DES', mcVal!=null?'Monte Carlo P50':'MC (belum)'];
        const vals  = [WMA_PRED, OLS_PRED, HOLT_PRED, mcVal!=null?mcVal:0];
        const colors  = vals.map((v,i) => (i===3&&mcVal===null) ? 'rgba(156,163,175,0.4)' : v>=0?'rgba(124,58,237,0.7)':'rgba(220,38,38,0.7)');
        const borders = vals.map((v,i) => (i===3&&mcVal===null) ? '#9ca3af' : v>=0?'#7c3aed':'#dc2626');

        let consensus;
        if (mcVal !== null) {
            const tc = WMA_CONF + OLS_CONF + HOLT_CONF + (aggConf||70);
            consensus = (WMA_PRED*WMA_CONF + OLS_PRED*OLS_CONF + HOLT_PRED*HOLT_CONF + mcVal*(aggConf||70)) / tc;
        } else {
            consensus = fallbackConsensus ?? (WMA_PRED*WMA_CONF+OLS_PRED*OLS_CONF+HOLT_PRED*HOLT_CONF)/(WMA_CONF+OLS_CONF+HOLT_CONF);
        }

        if (consChartInstance) consChartInstance.destroy();
        consChartInstance = new Chart(canvas, {
            type:'bar',
            data:{ labels:lbls, datasets:[
                { label:'Prediksi KAS', data:vals, backgroundColor:colors, borderColor:borders, borderWidth:1.5, borderRadius:6 },
                { label:'Konsensus', data:[consensus,consensus,consensus,consensus], type:'line', borderColor:'#f97316', borderWidth:2, borderDash:[6,3], pointRadius:0, fill:false }
            ]},
            options:{ responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{display:true,position:'top',labels:{font:{size:10},boxWidth:12}},
                    tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${fmtFull(ctx.parsed.y)}`}} },
                scales:{ x:{grid:{color:GRAY},ticks:TICK},
                    y:{grid:{color:GRAY},ticks:{...TICK,callback:v=>fmtK(v)},
                        title:{display:true,text:'Prediksi Saldo KAS (Rp)',color:'#9CA3AF',font:{size:10}}} }
            }
        });
    }

    renderConsensusChart();
})();
</script>
@endpush