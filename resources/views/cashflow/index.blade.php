@extends('layouts.app')
@section('title','Cashflow Harian')
@section('content')

@php
$totalAvailable  = $openingCash + $totalIncome;
$netKas          = $openingCash + $totalIncome - $totalExpense - $totalDeposits - $totalAdminFees;
$finalBankBal    = $dailyBankBalance[$daysInMonth];
$totalNet        = $netKas;
$totalSurplusAll = array_sum(array_column($transfersByDay, 'surplus'));

$cfActiveDays = collect(range(1,$daysInMonth))->filter(fn($d) =>
    ($salesByDay[$d] ?? 0) > 0 || ($dayTotals[$d] ?? 0) > 0 ||
    ($depositsByDay[$d]['total'] ?? 0) > 0
)->count();
$cfActiveDays = max($cfActiveDays, 1);

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

$rasioOperasional = $totalIncome > 0 ? ($totalExpense / $totalIncome * 100) : 0;
$totalKasKeluar   = $totalExpense + $totalDeposits + $totalAdminFees;
$rasioGross       = $totalAvailable > 0 ? ($totalKasKeluar / $totalAvailable * 100) : 0;

$avgDailyCashBal = collect(range(1,$daysInMonth))
    ->filter(fn($d) => ($salesByDay[$d]??0)>0||($dayTotals[$d]??0)>0||($depositsByDay[$d]['total']??0)>0||($d===1&&$openingCash>0))
    ->map(fn($d) => $dailyBalance[$d])->avg() ?? 0;

$avgDailyBankBal = collect(range(1,$daysInMonth))
    ->filter(fn($d) => ($depositsByDay[$d]['total']??0)>0||($transfersByDay[$d]['total']??0)>0||($d===1&&$openingPenampung>0))
    ->map(fn($d) => $dailyBankBalance[$d])->avg() ?? 0;

$p           = $pred;
$remainDays  = $p['remainDays'];
$today       = $p['today'];

$jsLabels    = range(1, $daysInMonth);
$jsSales     = array_map(fn($d) => $salesByDay[$d] ?? 0, $jsLabels);
$jsExpenses  = array_map(fn($d) => $dayTotals[$d] ?? 0, $jsLabels);
$jsDeposits  = array_map(fn($d) => $depositsByDay[$d]['total'] ?? 0, $jsLabels);
$jsTransfers = array_map(fn($d) => $transfersByDay[$d]['total'] ?? 0, $jsLabels);
$jsCashBal   = array_map(fn($d) => $dailyBalance[$d], $jsLabels);
$jsBankBal   = array_map(fn($d) => $dailyBankBalance[$d], $jsLabels);

$catLabelsSafe = array_values(\App\Models\DailyExpense::$categoryLabels);
$catTotalsSafe = array_values($categoryTotals);
@endphp

<div x-data="{ showForm: false }">

{{-- ══ HEADER ══ --}}
<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;margin-bottom:12px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:15px;font-weight:600;color:var(--text1)">💸 Cashflow Harian</span>
        <form method="GET" action="{{ route('cashflow.index') }}">
            <select name="period_id" onchange="this.form.submit()" class="field-select" style="padding:6px 10px;font-size:12px;width:auto">
                @foreach($periods as $p)
                    <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
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
                    <div style="font-size:10px;color:var(--text3)">Rasio biaya operasional</div>
                    <div style="font-size:16px;font-weight:600;color:{{ $rasioOperasional > 35 ? '#b45309' : 'var(--melon-dark)' }};margin-top:2px">{{ number_format($rasioOperasional,1) }}%</div>
                    <div style="height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden;margin:4px 0">
                        <div style="height:100%;width:{{ min($rasioOperasional,100) }}%;background:{{ $rasioOperasional > 35 ? '#f59e0b' : 'var(--melon)' }};border-radius:2px"></div>
                    </div>
                    <div style="font-size:10px;color:var(--text3)">ideal &lt;35%</div>
                </div>
                <div class="card" style="padding:10px 12px;{{ $rasioGross > 80 ? 'border-color:#fca5a5' : '' }}">
                    <div style="font-size:10px;color:var(--text3)">Rasio total kas keluar</div>
                    <div style="font-size:16px;font-weight:600;color:{{ $rasioGross > 80 ? '#dc2626' : 'var(--melon-dark)' }};margin-top:2px">{{ number_format($rasioGross,1) }}%</div>
                    <div style="height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden;margin:4px 0">
                        <div style="height:100%;width:{{ min($rasioGross,100) }}%;background:{{ $rasioGross > 80 ? '#ef4444' : 'var(--melon)' }};border-radius:2px"></div>
                    </div>
                    <div style="font-size:10px;color:var(--text3)">ideal &lt;80%</div>
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
                <div style="font-size:12px;font-weight:600;color:var(--text1)">Net cashflow periode ini</div>
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
@php $p = $pred; @endphp
<div class="s-card">
    <div class="s-card-header" style="background:#fff7ed;border-color:#fed7aa;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="color:#c2410c">🔮 Prediksi Saldo Akhir Bulan</span>
        @php $confColor = $p['confidence'] >= 75 ? 'badge-green' : ($p['confidence'] >= 50 ? 'badge-orange' : 'badge-red'); @endphp
        <span class="badge {{ $confColor }}">Keyakinan {{ $p['confidence'] }}%</span>
        <span style="margin-left:auto;font-size:10px;color:var(--text3)">
            Hari ke-{{ $p['today'] }} · sisa {{ $p['remainDays'] }} hari · ×{{ number_format($p['conserv'],2) }}
        </span>
    </div>
    <div style="padding:12px 14px;display:flex;flex-direction:column;gap:14px">

        {{-- Progress --}}
        <div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-bottom:4px">
                <span>Progress bulan berjalan</span>
                <span>{{ $p['progressPct'] }}% ({{ $p['today'] }}/{{ $daysInMonth }} hari)</span>
            </div>
            <div style="height:6px;background:#f0f0f0;border-radius:3px;overflow:hidden">
                <div style="height:100%;width:{{ $p['progressPct'] }}%;background:var(--melon);border-radius:3px"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;margin-top:4px">
                <span style="color:var(--melon-dark);font-weight:600">Aktual: {{ $p['today'] }} hari</span>
                <span style="color:var(--text3)">Estimasi: {{ $p['remainDays'] }} hari lagi</span>
            </div>
        </div>

        {{-- Engine Prediksi --}}
        <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">⚙ Engine Prediksi — Transparansi Metode</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                @foreach([
                    ['Rate Sales (WMA)', 'Rp '.number_format(round($p['salesRateWma'])), '5 hari terakhir ×2 bobot', 'var(--melon-dark)'],
                    ['Rate Sales (Simpel)', 'Rp '.number_format(round($p['salesRateSimple'])), 'total ÷ hari aktif', 'var(--text2)'],
                    ['Rate Final (60/40)', 'Rp '.number_format(round($p['salesRate'])), 'WMA×0.6 + simpel×0.4', '#1d4ed8'],
                    ['Faktor Konservatif', '×'.number_format($p['conserv'],2), 'basis 0.82 + tren adj', '#b45309'],
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
                ['Tren Kas Masuk (7h)', $p['salesMomentum'], true],
                ['Tren Pengeluaran (7h)', $p['expMomentum'], false],
            ] as [$lbl,$mom,$isIncome])
            @php
                $isGood = $isIncome ? $mom >= 0 : $mom <= 0;
                $arrow  = ($isIncome ? $mom >= 0 : $mom <= 0) ? '↑' : '↓';
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

        {{-- 3 Skenario --}}
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                Proyeksi Saldo KAS Akhir Bulan
                <span style="font-size:10px;font-weight:400;color:var(--text3)">(sudah dipotong gaji kurir)</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                <div style="background:#fef2f2;border:0.5px solid #fca5a5;border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;font-weight:700;color:#dc2626;text-transform:uppercase;margin-bottom:4px">Pesimis</div>
                    <div style="font-size:14px;font-weight:700;color:#991b1b">{{ number_format(round($p['predKasPesim'])) }}</div>
                    <div style="font-size:9px;color:#dc2626;margin-top:4px">Gaji: {{ number_format($p['gajiPesim']) }}</div>
                </div>
                <div style="background:#fff7ed;border:2px solid #f97316;border-radius:8px;padding:10px 12px;position:relative">
                    <div style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:var(--melon);color:#fff;font-size:8px;font-weight:700;padding:2px 8px;border-radius:10px;white-space:nowrap">UTAMA</div>
                    <div style="font-size:9px;font-weight:700;color:#c2410c;text-transform:uppercase;margin-bottom:4px;margin-top:4px">Konservatif</div>
                    <div style="font-size:16px;font-weight:700;color:{{ $p['predKas'] >= 0 ? '#c2410c' : '#dc2626' }}">{{ number_format(round($p['predKas'])) }}</div>
                    @if($p['piutangCairEstimasi'] > 0)
                    <div style="font-size:9px;color:var(--melon-dark);margin-top:3px">+Est. piutang: {{ number_format($p['piutangCairEstimasi']) }}</div>
                    @endif
                    <div style="font-size:9px;font-weight:600;color:{{ $p['predKas'] >= 0 ? 'var(--melon-dark)' : '#dc2626' }};margin-top:4px">
                        {{ $p['predKas'] >= 0 ? '✓ Aman' : '⚠ Defisit' }}
                    </div>
                </div>
                <div style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px">
                    <div style="font-size:9px;font-weight:700;color:var(--melon-dark);text-transform:uppercase;margin-bottom:4px">Optimis</div>
                    <div style="font-size:14px;font-weight:700;color:#166534">{{ number_format(round($p['predKasOptim'])) }}</div>
                    <div style="font-size:9px;color:var(--melon-dark);margin-top:4px">Gaji: {{ number_format($p['gajiOptim']) }}</div>
                </div>
            </div>
        </div>

        {{-- Tabel rincian asumsi --}}
        <div class="s-card" style="margin-bottom:0">
            <div class="s-card-header">Rincian Asumsi ({{ $p['remainDays'] }} hari ke depan — konservatif adaptif)</div>
            <div class="scroll-x">
                <table class="mob-table">
                    <thead>
                        <tr>
                            <th>Komponen</th>
                            <th class="r">Rate/Hari (WMA)</th>
                            <th class="r">Rate/Hari (Simpel)</th>
                            <th class="r">Asumsi/Hari</th>
                            <th class="r">Est. {{ $p['remainDays'] }} Hari</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="color:var(--melon-dark);font-weight:600">+ Kas Diterima</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format(round($p['salesRateWma'])) }}</td>
                            <td class="r" style="color:var(--text3)">Rp {{ number_format(round($p['salesRateSimple'])) }}</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format(round($p['salesRate'] * $p['conserv'])) }}</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($p['projSales']) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#dc2626;font-weight:600">− Pengeluaran</td>
                            <td class="r" style="color:#dc2626">Rp {{ number_format(round($p['expRate'])) }}</td>
                            <td class="r" style="color:var(--text3)">Rp {{ number_format($avgExpense) }}</td>
                            <td class="r bold" style="color:#dc2626">Rp {{ number_format(round($p['expRate'] / $p['conserv'])) }}</td>
                            <td class="r bold" style="color:#dc2626">Rp {{ number_format($p['projExp']) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#1d4ed8;font-weight:600">− TF Penampung</td>
                            <td class="r" style="color:var(--text3)" colspan="2">Rp {{ number_format(round($p['depRate'])) }}/hari</td>
                            <td class="r bold" style="color:#1d4ed8">Rp {{ number_format(round($p['depRate'] * $p['conserv'])) }}</td>
                            <td class="r bold" style="color:#1d4ed8">Rp {{ number_format($p['projDep']) }}</td>
                        </tr>
                        @if($p['adminRate'] > 0)
                        <tr>
                            <td style="color:#60a5fa;padding-left:20px">└ Admin TF</td>
                            <td class="r" style="color:var(--text3)" colspan="2">Rp {{ number_format(round($p['adminRate'])) }}/hari</td>
                            <td class="r" style="color:#60a5fa">Rp {{ number_format(round($p['adminRate'] * $p['conserv'])) }}</td>
                            <td class="r bold" style="color:#3b82f6">Rp {{ number_format($p['projAdmin']) }}</td>
                        </tr>
                        @endif
                        @if($p['piutangBelumBayar'] > 0)
                        <tr style="background:#f0fdf4">
                            <td style="color:var(--melon-dark);font-weight:600">
                                + Est. Piutang Cair
                                <div style="font-size:10px;font-weight:400;color:var(--text3)">total Rp {{ number_format($p['piutangBelumBayar']) }} · asumsi 30%</div>
                            </td>
                            <td class="r" style="color:var(--text3)" colspan="2">× 30%</td>
                            <td class="r" style="color:var(--melon-dark)">—</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($p['piutangCairEstimasi']) }}</td>
                        </tr>
                        @endif
                        <tr style="background:#fffbeb">
                            <td style="color:#92400e;font-weight:600">
                                − Gaji Kurir
                                <div style="font-size:10px;font-weight:400;color:var(--text3)">Rp 500/tab · akhir bulan</div>
                            </td>
                            <td class="r" style="color:var(--text3)">{{ number_format($avgTabungPerHari, 1) }} tab/hari × Rp 500</td>
                            <td class="r" style="color:#b45309">{{ number_format($p['predTabungSisa']) }} tab est.</td>
                            <td class="r" style="color:var(--text3)"></td>
                            <td class="r bold" style="color:#92400e">
                                Rp {{ number_format($p['predGajiTotal']) }}
                                <div style="font-size:9px;font-weight:400;color:#b45309">Hutang: Rp {{ number_format($p['gajiAktualSdIni']) }}<br>+ Est: Rp {{ number_format($p['predGajiSisa']) }}</div>
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
                            <td class="r bold" style="font-size:14px;color:{{ $p['predKas'] < 0 ? '#fca5a5' : '#fff' }}">
                                Rp {{ number_format(round($p['predKas'])) }}
                                <div style="font-size:10px;color:{{ $p['predKas'] >= 0 ? 'rgba(255,255,255,0.8)' : '#fca5a5' }}">
                                    {{ $p['predKas'] >= 0 ? '✓ Aman' : '⚠ Defisit' }}
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
        <div style="font-size:16px;font-weight:600;color:{{ $p['predBank'] >= 0 ? '#1e40af' : '#dc2626' }};margin-top:2px">
            Rp {{ number_format(round($p['predBank'])) }}
        </div>
        {{-- Penampung adalah rekening transit → saldo tidak bergerak jauh --}}
        <div style="font-size:10px;color:#3b82f6;margin-top:2px">
            saldo transit (deposit ≈ langsung TF ke agen)
        </div>
        @php
            $netBankChangeEst = $p['predBank'] - ($finalBankBal ?? 0);
        @endphp
        @if(abs($netBankChangeEst) < 200000)
        <div style="font-size:10px;color:#1d4ed8;font-weight:600;margin-top:2px">✓ Stabil (rekening transit)</div>
        @elseif($netBankChangeEst > 0)
        <div style="font-size:10px;color:var(--melon-dark);font-weight:600;margin-top:2px">↑ Est. menumpuk Rp {{ number_format($netBankChangeEst) }}</div>
        @else
        <div style="font-size:10px;color:#dc2626;font-weight:600;margin-top:2px">↓ Est. berkurang Rp {{ number_format(abs($netBankChangeEst)) }}</div>
        @endif
    </div>
 
    {{-- Proyeksi Rasio Biaya vs MARGIN --}}
    <div class="card" style="padding:10px 12px">
        <div style="font-size:10px;color:var(--text3)">Proyeksi Rasio Biaya Ops</div>
        <div style="font-size:16px;font-weight:600;color:{{ $p['predRasio'] > 35 ? '#b45309' : 'var(--melon-dark)' }};margin-top:2px">
            {{ number_format($p['predRasio'],1) }}%
        </div>
        <div style="height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden;margin:4px 0">
            <div style="height:100%;width:{{ min($p['predRasio'],100) }}%;background:{{ $p['predRasio'] > 35 ? '#f59e0b' : 'var(--melon)' }};border-radius:2px"></div>
        </div>
        {{-- Label pembanding --}}
        <div style="font-size:10px;color:var(--text3)">
            biaya ops ÷ margin bersih · ideal &lt;35%
        </div>
        <div style="font-size:10px;color:{{ $p['predRasio'] > 35 ? '#b45309' : 'var(--melon-dark)' }};font-weight:600;margin-top:2px">
            {{ $p['predRasio'] > 35 ? '⚠ perlu efisiensi' : '✓ sehat' }}
        </div>
        {{-- Rasio aktual s/d hari ini --}}
        @if(isset($p['rasioAktual']))
        <div style="font-size:10px;color:var(--text3);margin-top:4px;border-top:0.5px solid var(--border);padding-top:4px">
            Aktual s/d hari ini: <strong style="color:{{ $p['rasioAktual'] > 35 ? '#b45309' : 'var(--melon-dark)' }}">{{ number_format($p['rasioAktual'],1) }}%</strong>
        </div>
        @endif
    </div>
 
</div>

        {{-- Metodologi --}}
        
<div style="font-size:10px;color:var(--text3);border-top:0.5px solid var(--border);padding-top:10px;line-height:1.6">
    <span style="font-weight:600;color:var(--text2)">Metodologi:</span>
    Rate harian = <strong>WMA 5 hari (bobot ×2) × 60% + rata simpel × 40%</strong> berbasis <strong>margin bersih</strong> (paid_amount − Rp 16.000×qty).
    Faktor konservatif adaptif: basis 0.88 ± penyesuaian tren.
    Tren margin {{ $p['salesMomentum'] >= 0 ? '+' : '' }}{{ number_format($p['salesMomentum'],1) }}% → ×{{ number_format($p['conserv'],2) }}.
    Rasio biaya dihitung terhadap <strong>margin bersih</strong> (bukan omzet), ideal &lt;35%.
    Bank penampung = rekening transit — saldo tidak berubah signifikan.
    @if($p['piutangBelumBayar'] > 0)
    Piutang (margin) Rp {{ number_format($p['piutangMarginBelumBayar']) }} — <strong>30% diasumsikan cair</strong>.
    @endif
    Gaji terhutang Rp {{ number_format($p['gajiAktualSdIni']) }} + est. Rp {{ number_format($p['predGajiSisa']) }} =
    <strong style="color:#b45309">Rp {{ number_format($p['predGajiTotal']) }}</strong> — dibayar tgl 1 bulan depan.
    Confidence <strong>{{ $p['confidence'] }}%</strong>.
    <span style="color:#c2410c;font-weight:600">Bukan jaminan — panduan perencanaan.</span>
</div>

@if($remainDays > 0 && isset($pred['predGajiTotal']) && $pred['predGajiTotal'] > 0)
<div style="background:#fffbeb;border:0.5px solid #fde68a;border-radius:8px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px">
    <div>
        <div style="font-size:11px;font-weight:600;color:#92400e">⏰ Kewajiban Gaji Kurir (tgl 1 bulan depan)</div>
        <div style="font-size:10px;color:#b45309;margin-top:2px">
            Hutang s/d hari ini: Rp {{ number_format($pred['gajiAktualSdIni']) }}
            + Est. sisa: Rp {{ number_format($pred['predGajiSisa']) }}
        </div>
    </div>
    <div style="font-size:16px;font-weight:700;color:#92400e;white-space:nowrap">
        Rp {{ number_format($pred['predGajiTotal']) }}
    </div>
</div>
<div style="font-size:10px;color:var(--text3);margin-top:4px;padding:0 2px">
    Prediksi KAS setelah gaji dibayar:
    <strong style="color:{{ $pred['predKasSetelahGaji'] >= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
        Rp {{ number_format(round($pred['predKasSetelahGaji'])) }}
        {{ $pred['predKasSetelahGaji'] >= 0 ? '✓' : '⚠' }}
    </strong>
</div>
@endif
    </div>
</div>

@else
{{-- ══ REALISASI AKHIR BULAN ══ --}}
@php
$gajiRealisasi  = $totalTabungAktual * 500;
$kasSetelahGaji = $netKas - $gajiRealisasi;
$netBersih      = $netKas + $finalBankBal - $gajiRealisasi;
$rasioReal      = $totalIncome > 0 ? ($totalExpense / $totalIncome * 100) : 0;
$rasioGrosReal  = ($totalIncome + $openingCash) > 0 ? (($totalExpense + $totalDeposits + $totalAdminFees + $gajiRealisasi) / ($totalIncome + $openingCash) * 100) : 0;
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
                <div style="font-size:10px;color:var(--text3)">Rasio Biaya Operasional</div>
                <div style="font-size:15px;font-weight:600;color:{{ $rasioReal > 35 ? '#b45309' : 'var(--melon-dark)' }};margin-top:2px">{{ number_format($rasioReal,1) }}%</div>
            </div>
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Rasio Gross Kas Keluar</div>
                <div style="font-size:15px;font-weight:600;color:{{ $rasioGrosReal > 80 ? '#dc2626' : 'var(--melon-dark)' }};margin-top:2px">{{ number_format($rasioGrosReal,1) }}%</div>
            </div>
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Total Aset (KAS + BANK)</div>
                <div style="font-size:15px;font-weight:600;color:{{ $netBersih >= 0 ? 'var(--text1)' : '#dc2626' }};margin-top:2px">Rp {{ number_format($netBersih) }}</div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══ CHART TREN ══ --}}
{{-- ══ CHART TREN — versi baru ══ --}}
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

        {{-- Legend arus --}}
        <div id="cf-leg-arus" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:10px;">
            @foreach([
                ['#3B6D11','Kas diterima','sq'],
                ['#E24B4A','Pengeluaran','sq'],
                ['#378ADD','TF ke penampung','sq'],
                ['#7F77DD','TF ke rek utama','sq'],
            ] as [$col,$lbl,$t])
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3);">
                <span style="width:10px;height:10px;border-radius:2px;background:{{ $col }};flex-shrink:0;"></span>{{ $lbl }}
            </span>
            @endforeach
        </div>
        {{-- Legend saldo --}}
        <div id="cf-leg-saldo" style="display:none;flex-wrap:wrap;gap:12px;margin-bottom:10px;">
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3);"><span style="width:18px;border-top:2.5px dashed #BA7517;"></span>Saldo KAS</span>
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3);"><span style="width:18px;border-top:2.5px dashed #378ADD;"></span>Saldo BANK</span>
        </div>
        {{-- Legend net --}}
        <div id="cf-leg-net" style="display:none;flex-wrap:wrap;gap:12px;margin-bottom:10px;">
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3);"><span style="width:10px;height:10px;border-radius:50%;background:#3B6D11;flex-shrink:0;"></span>Surplus</span>
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3);"><span style="width:10px;height:10px;border-radius:50%;background:#E24B4A;flex-shrink:0;"></span>Defisit</span>
        </div>

        <div style="position:relative;width:100%;height:280px;">
            <canvas id="cfTrendChart"></canvas>
        </div>

        {{-- Summary strip --}}
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
    const salesByDay    = @json($jsSales);
    const dayTotals     = @json($jsExpenses);
    const depositsByDay = @json($jsDeposits);
    const transfersByDay= @json($jsTransfers);
    const cashBal       = @json($jsCashBal);
    const bankBal       = @json($jsBankBal);
    const daysInMonth   = {{ $daysInMonth }};
    const catLabels     = @json($catLabelsSafe);
    const catTotals     = @json($catTotalsSafe);

    const labels = Array.from({length:daysInMonth},(_,i)=>i+1);
    const fmtK   = v=>'Rp '+Math.round(Math.abs(v)/1000).toLocaleString('id')+'k';
    const GRAY   = 'rgba(0,0,0,0.05)';
    const TICK   = {font:{size:10},color:'#9CA3AF'};

    /* ════ CHART TREN HARIAN — versi baru ════ */
(function(){
    const labels    = @json($jsLabels);
    const sales     = @json($jsSales);
    const expenses  = @json($jsExpenses);
    const deposits  = @json($jsDeposits);
    const transfers = @json($jsTransfers);
    const cashBal   = @json($jsCashBal);
    const bankBal   = @json($jsBankBal);

    const fmtK = v => {
        const abs = Math.abs(v);
        const s = abs >= 1000000 ? (Math.round(abs/100000)/10).toFixed(1)+'jt' : Math.round(abs/1000)+'k';
        return (v < 0 ? '-' : '') + s;
    };

    const net = labels.map((_,i) => sales[i] - expenses[i] - deposits[i] - (deposits[i] > 0 ? 0 : 0));

    const GRAY = 'rgba(0,0,0,0.05)';
    const TICK = { font:{size:10}, color:'#9CA3AF' };
    const tooltipLabel = ctx => `${ctx.dataset.label}: ${fmtK(ctx.parsed.y * 1000)}`;

    const arusData = {
        labels,
        datasets:[
            { label:'Kas diterima', data:sales.map(v=>v/1000),     type:'bar', backgroundColor:'#3B6D1155', borderColor:'#3B6D11', borderWidth:1, borderRadius:3, stack:'income', order:2 },
            { label:'Pengeluaran',  data:expenses.map(v=>v/1000),   type:'bar', backgroundColor:'#E24B4A88', borderColor:'#E24B4A', borderWidth:1, borderRadius:3, stack:'out',    order:2 },
            { label:'TF penampung', data:deposits.map(v=>v/1000),   type:'bar', backgroundColor:'#378ADD77', borderColor:'#378ADD', borderWidth:1, borderRadius:3, stack:'out',    order:2 },
            { label:'TF rek utama', data:transfers.map(v=>v/1000),  type:'bar', backgroundColor:'#7F77DD77', borderColor:'#7F77DD', borderWidth:1, borderRadius:3, stack:'out',    order:2 },
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
        datasets:[{
            label:'Net harian', data:net.map(v=>v/1000), type:'bar',
            backgroundColor:netColors, borderColor:netBorders, borderWidth:1, borderRadius:4
        }]
    };

    const makeOpts = (yTitle) => ({
        responsive:true, maintainAspectRatio:false,
        interaction:{ mode:'index', intersect:false },
        plugins:{ legend:{display:false}, tooltip:{callbacks:{label:tooltipLabel}} },
        scales:{
            x:{ grid:{color:GRAY}, ticks:{...TICK, autoSkip:true, maxTicksLimit:16, maxRotation:0} },
            y:{ grid:{color:GRAY}, ticks:{...TICK, callback:v=>fmtK(v*1000)}, title:{display:true,text:yTitle,color:'#9CA3AF',font:{size:10}} }
        }
    });

    const chart = new Chart(document.getElementById('cfTrendChart'), {
        data: arusData,
        options: makeOpts('Ribuan Rp')
    });

    window.cfSwitch = function(tab, btn) {
        document.querySelectorAll('#cfTabBtns button').forEach(b => {
            b.style.background = ''; b.style.color = ''; b.style.borderColor = '';
        });
        btn.style.background = 'var(--text1)';
        btn.style.color = '#fff';
        btn.style.borderColor = 'var(--text1)';

        ['arus','saldo','net'].forEach(t => {
            document.getElementById('cf-leg-'+t).style.display = t===tab ? 'flex' : 'none';
        });

        chart.data    = tab==='arus' ? arusData : tab==='saldo' ? saldoData : netData;
        chart.options = makeOpts(tab==='arus' ? 'Ribuan Rp' : tab==='saldo' ? 'Saldo (ribu Rp)' : 'Net (ribu Rp)');
        chart.update('none');
    };
})();

    const activeDays = labels.filter(i=>salesByDay[i-1]>0||dayTotals[i-1]>0||depositsByDay[i-1]>0).length||1;
    const totalExp   = dayTotals.reduce((a,b)=>a+b,0);
    const totalInc   = salesByDay.reduce((a,b)=>a+b,0);
    const avgExpD    = totalExp/activeDays;
    const spikeDays  = labels.filter(d=>(dayTotals[d-1]||0)>avgExpD*2.0);
    const negCashDays= labels.filter(d=>cashBal[d-1]<0);
    const negBankDays= labels.filter(d=>bankBal[d-1]<0);
    const anomalies  = [];
    if(spikeDays.length)  anomalies.push({sev:'danger',title:'Lonjakan pengeluaran',body:`Tgl: ${spikeDays.join(', ')} (>2× rata-rata)`});
    if(negCashDays.length) anomalies.push({sev:'danger',title:'Saldo KAS negatif',body:`Tgl: ${negCashDays.join(', ')}`});
    if(negBankDays.length) anomalies.push({sev:'danger',title:'Saldo BANK negatif',body:`Tgl: ${negBankDays.join(', ')}`});
    const ratio = totalInc>0?totalExp/totalInc:0;
    if(ratio>0.4) anomalies.push({sev:'warn',title:'Rasio biaya tinggi',body:`Pengeluaran ${(ratio*100).toFixed(0)}% dari kas diterima`});
    if(!anomalies.length) anomalies.push({sev:'ok',title:'Cashflow normal',body:'Tidak ada anomali signifikan'});

    const sc = Math.max(0,Math.min(100,100-spikeDays.length*15-negCashDays.length*20-negBankDays.length*20-(ratio>0.4?10:0)));
    const badge = document.getElementById('cf-score-badge');
    badge.textContent = sc+'/100 '+(sc>=75?'🟢':sc>=50?'🟡':'🔴');
    badge.style.cssText = sc>=75?'background:#dcfce7;color:#166534;':sc>=50?'background:#fef9c3;color:#92400e;':'background:#fee2e2;color:#991b1b;';

    const bgMap  = {danger:'#fef2f2',warn:'#fffbeb',ok:'#f0fdf4'};
    const colMap = {danger:'#991b1b',warn:'#92400e',ok:  'var(--melon-dark)'};
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
})();
</script>
@endpush