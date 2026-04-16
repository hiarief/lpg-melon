@extends('layouts.app')
@section('title','Cashflow Harian')
@section('content')

{{--
    ╔══════════════════════════════════════════════════════════════╗
    ║  VARIABEL PENTING (semua disiapkan oleh controller)         ║
    ║  $pred    — hasil buildPrediction() + calcHoltsDES()        ║
    ║  $ols     — hasil calcOlsProjection()                       ║
    ║  $mc      — params Monte Carlo untuk JS                     ║
    ║  $summary — ringkasan cashflow (avg, rasio, dll)            ║
    ║  $chartData — array data untuk Chart.js                     ║
    ╚══════════════════════════════════════════════════════════════╝
--}}

<div x-data="{ showForm: false }">

{{-- ══ HEADER ══ --}}
<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;margin-bottom:12px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:15px;font-weight:600;color:var(--text1)">💸 Cashflow Harian</span>
        <form method="GET" action="{{ route('cashflow.index') }}">
            <select name="period_id" onchange="this.form.submit()" class="field-select" style="padding:6px 10px;font-size:12px;width:auto">
                @foreach($periods as $periodItem)
                    <option value="{{ $periodItem->id }}" {{ $periodItem->id == $period->id ? 'selected' : '' }}>
                        {{ $periodItem->label }}
                    </option>
                @endforeach
            </select>
        </form>
        <span style="font-size:10px;color:var(--text3)">{{ $summary['cfActiveDays'] }} hari aktif</span>
    </div>
    @if($period->status === 'open')
        <button @click="showForm = !showForm" class="btn-primary btn-sm">+ Input Pengeluaran</button>
    @endif
</div>

{{-- ══ FORM INPUT ══ --}}
@if($period->status === 'open')
<div x-show="showForm" x-cloak x-transition style="margin-bottom:10px">
    <div class="s-card">
        <div class="s-card-header">+ Input Pengeluaran</div>
        <div style="padding:12px 14px">
            <form method="POST" action="{{ route('cashflow.store') }}" style="display:flex;flex-direction:column;gap:10px">
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
                <button type="submit" class="btn-primary">✅ Simpan</button>
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
        @include('cashflow._section-header', ['color' => 'var(--melon)', 'label' => 'Pemasukan'])
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Penjualan gas (kas diterima)</div>
                <div style="font-size:16px;font-weight:600;color:var(--melon-dark);margin-top:2px">Rp {{ number_format($totalIncome) }}</div>
                <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($summary['avgSales']) }}/hari</div>
            </div>
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Margin bersih (setelah HPP)</div>
                <div style="font-size:16px;font-weight:600;color:#0369a1;margin-top:2px">Rp {{ number_format($totalMargin) }}</div>
                <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($totalMargin > 0 ? round($totalMargin / $summary['cfActiveDays']) : 0) }}/hari</div>
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
                <div style="font-size:16px;font-weight:600;color:var(--text1);margin-top:2px">Rp {{ number_format($summary['totalAvailable']) }}</div>
                <div style="font-size:10px;color:var(--text3)">saldo awal + penjualan riil</div>
            </div>
        </div>

        {{-- Pengeluaran Operasional --}}
        @include('cashflow._section-header', ['color' => '#ef4444', 'label' => 'Pengeluaran Operasional'])
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Total pengeluaran</div>
                <div style="font-size:16px;font-weight:600;color:#dc2626;margin-top:2px">Rp {{ number_format($totalExpense) }}</div>
                <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($summary['avgExpense']) }}/hari</div>
            </div>
            @include('cashflow._rasio-card', [
                'label'     => 'Rasio ops / margin bersih',
                'note'      => 'biaya ÷ margin',
                'value'     => $summary['rasioOperasional'],
                'threshold' => 35,
                'ideal'     => 'ideal <35% dari margin',
            ])
            @include('cashflow._rasio-card', [
                'label'     => 'Rasio gross kas keluar',
                'note'      => '(ops+deposit+admin) ÷ kas tersedia',
                'value'     => $summary['rasioGross'],
                'threshold' => 80,
                'ideal'     => 'ideal <80% dari kas tersedia',
            ])
        </div>

        {{-- Transfer ke Penampung --}}
        @include('cashflow._section-header', ['color' => '#3b82f6', 'label' => 'Transfer ke Penampung'])
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">TF ke rekening penampung</div>
                <div style="font-size:16px;font-weight:600;color:#1d4ed8;margin-top:2px">Rp {{ number_format($totalDeposits) }}</div>
                <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($summary['avgDeposit']) }}/hari</div>
            </div>
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Admin TF penampung</div>
                <div style="font-size:16px;font-weight:600;color:#1d4ed8;margin-top:2px">{{ $totalAdminFees > 0 ? 'Rp '.number_format($totalAdminFees) : '—' }}</div>
                <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($summary['avgAdminFee']) }}/hari</div>
            </div>
        </div>

        {{-- Transfer ke Rekening Utama --}}
        @include('cashflow._section-header', ['color' => '#7c3aed', 'label' => 'Transfer ke Rekening Utama'])
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">TF ke rekening utama</div>
                <div style="font-size:16px;font-weight:600;color:#6d28d9;margin-top:2px">{{ $totalTransferred > 0 ? 'Rp '.number_format($totalTransferred) : '—' }}</div>
                <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($summary['avgNet']) }}/hari</div>
            </div>
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Surplus (tabungan)</div>
                <div style="font-size:16px;font-weight:600;color:#6d28d9;margin-top:2px">{{ $totalSurplus > 0 ? 'Rp '.number_format($totalSurplus) : '—' }}</div>
                <div style="font-size:10px;color:var(--text3)">tidak dihitung sebagai pengeluaran</div>
            </div>
        </div>

        {{-- Saldo Akhir --}}
        @include('cashflow._section-header', ['color' => '#f59e0b', 'label' => 'Saldo Akhir'])
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Saldo KAS (fisik)</div>
                <div style="font-size:16px;font-weight:600;color:{{ $netKas >= 0 ? '#b45309' : '#dc2626' }};margin-top:2px">Rp {{ number_format($netKas) }}</div>
                <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($summary['avgDailyCashBal']) }}/hari</div>
            </div>
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Saldo BANK (penampung)</div>
                <div style="font-size:16px;font-weight:600;color:{{ $finalBankBal >= 0 ? '#4338ca' : '#dc2626' }};margin-top:2px">Rp {{ number_format($finalBankBal) }}</div>
                <div style="font-size:10px;color:var(--text3)">avg Rp {{ number_format($summary['avgDailyBankBal']) }}/hari</div>
            </div>
        </div>

        {{-- Net cashflow banner --}}
        <div style="background:var(--melon-50);border:0.5px solid var(--border);border-radius:8px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px">
            <div>
                <div style="font-size:12px;font-weight:600;color:var(--text1)">Saldo KAS bersih periode ini / {{ number_format($netTotal / 16000) }} Tab</div>
                <div style="font-size:10px;color:var(--text3);margin-top:2px">kas masuk − pengeluaran − TF penampung − admin TF</div>
            </div>
            <div style="font-size:20px;font-weight:700;color:{{ $netTotal >= 0 ? 'var(--melon-dark)' : '#dc2626' }};white-space:nowrap">
                Rp {{ number_format($netTotal) }}
            </div>
        </div>
    </div>
</div>

{{-- ══ PREDIKSI / REALISASI ══ --}}
@if($pred['remainDays'] > 0)
    @include('cashflow._prediction-card')
@else
    @include('cashflow._realisasi-card')
@endif

{{-- ══ CHART TREN ══ --}}
<div class="s-card">
    <div class="s-card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <span>📈 Tren Harian — Kas, Pengeluaran & Saldo</span>
        <div style="display:flex;gap:6px" id="cfTabBtns">
            <button onclick="cfSwitch('arus',this)" class="btn-secondary btn-sm" style="background:var(--text1);color:#fff;border-color:var(--text1)">Arus Kas</button>
            <button onclick="cfSwitch('saldo',this)" class="btn-secondary btn-sm">Saldo</button>
            <button onclick="cfSwitch('net',this)" class="btn-secondary btn-sm">Net</button>
        </div>
    </div>
    <div style="padding:12px 14px">
        {{-- Legenda --}}
        <div id="cf-leg-arus" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:10px">
            @foreach([['#3B6D11','Kas diterima'],['#E24B4A','Pengeluaran'],['#378ADD','TF ke penampung'],['#7F77DD','TF ke rek utama']] as [$col,$lbl])
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3)">
                <span style="width:10px;height:10px;border-radius:2px;background:{{ $col }};flex-shrink:0"></span>{{ $lbl }}
            </span>
            @endforeach
        </div>
        <div id="cf-leg-saldo" style="display:none;flex-wrap:wrap;gap:12px;margin-bottom:10px">
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3)"><span style="width:18px;border-top:2.5px dashed #BA7517"></span>Saldo KAS</span>
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3)"><span style="width:18px;border-top:2.5px dashed #378ADD"></span>Saldo BANK</span>
        </div>
        <div id="cf-leg-net" style="display:none;flex-wrap:wrap;gap:12px;margin-bottom:10px">
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3)"><span style="width:10px;height:10px;border-radius:50%;background:#3B6D11;flex-shrink:0"></span>Surplus</span>
            <span style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3)"><span style="width:10px;height:10px;border-radius:50%;background:#E24B4A;flex-shrink:0"></span>Defisit</span>
        </div>
        <div style="position:relative;width:100%;height:280px">
            <canvas id="cfTrendChart"></canvas>
        </div>
        {{-- Summary grid --}}
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:14px;padding:12px;background:var(--surface2);border-radius:var(--radius-sm)">
            @foreach([
                ['Total kas diterima',  $totalIncome,  '#3B6D11'],
                ['Total pengeluaran',   $totalExpense, '#E24B4A'],
                ['Total TF penampung',  $totalDeposits,'#378ADD'],
                ['Net cashflow',        $netKas,       $netKas >= 0 ? '#3B6D11' : '#E24B4A'],
            ] as [$lbl, $val, $col])
            <div>
                <div style="font-size:10px;color:var(--text3)">{{ $lbl }}</div>
                <div style="font-size:16px;font-weight:600;color:{{ $col }}">Rp {{ number_format($val) }}</div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ══ ANOMALI & KESEHATAN ══ --}}
<div class="s-card" style="margin-bottom:10px">
    <div class="s-card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>🔍 Anomali & Kesehatan</span>
        <span id="cf-score-badge" style="font-size:10px;padding:2px 8px;border-radius:6px;font-weight:600"></span>
    </div>
    <div id="cf-anomaly-grid" style="padding:10px 12px;display:flex;flex-direction:column;gap:6px"></div>
</div>

{{-- ══ DONUT KOMPOSISI PENGELUARAN ══ --}}
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
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        <th style="text-align:center;width:28px;padding:6px 2px">{{ $d }}</th>
                    @endfor
                    <th class="r" style="background:var(--melon-50)">Total</th>
                    <th class="r" style="background:#fffbeb;position:sticky;right:0;z-index:2;white-space:nowrap">Avg/Hari</th>
                </tr>
            </thead>
            <tbody>
                {{-- Saldo awal --}}
                @if($openingCash > 0)
                <tr style="background:#f0fdf4">
                    <td class="bold" style="position:sticky;left:0;background:#f0fdf4;z-index:1;color:var(--melon-dark)">
                        Saldo Awal KAS
                        <div style="font-size:9px;font-weight:400;color:var(--melon)">cutoff periode lalu</div>
                    </td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                        <td style="text-align:center;padding:6px 2px;{{ $day === 1 ? 'color:var(--melon-dark);font-weight:600' : 'color:#d1d5db' }}">
                            {{ $day === 1 ? number_format($openingCash/1000).'k' : '—' }}
                        </td>
                    @endfor
                    <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($openingCash) }}</td>
                    <td class="r" style="position:sticky;right:0;background:#f0fdf4;color:var(--text3)">—</td>
                </tr>
                @endif

                {{-- Kategori pengeluaran --}}
                @foreach($categories as $cat)
                @php $catLabel = \App\Models\DailyExpense::$categoryLabels[$cat] ?? $cat; @endphp
                <tr>
                    <td class="bold" style="position:sticky;left:0;background:#fff;z-index:1;color:var(--text2)">{{ $catLabel }}</td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php $val = $expenseGrid[$cat][$day] ?? 0; @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $val > 0 ? 'color:#dc2626;font-weight:600' : 'color:#d1d5db' }}">
                        {{ $val > 0 ? number_format($val/1000).'k' : '—' }}
                    </td>
                    @endfor
                    <td class="r bold" style="color:{{ ($categoryTotals[$cat] ?? 0) > 0 ? '#dc2626' : 'var(--text3)' }}">
                        {{ ($categoryTotals[$cat] ?? 0) > 0 ? 'Rp '.number_format($categoryTotals[$cat]) : '—' }}
                    </td>
                    @php $avgCat = ($categoryTotals[$cat] ?? 0) > 0 ? round($categoryTotals[$cat] / $summary['cfActiveDays']) : 0; @endphp
                    <td class="r" style="position:sticky;right:0;background:#fff;color:{{ $avgCat > 0 ? '#ef4444' : 'var(--text3)' }};font-weight:{{ $avgCat > 0 ? '600' : '400' }}">
                        {{ $avgCat > 0 ? 'Rp '.number_format($avgCat) : '—' }}
                    </td>
                </tr>
                @endforeach

                {{-- Total pengeluaran --}}
                <tr style="background:#dc2626;color:#fff;font-weight:600">
                    <td style="position:sticky;left:0;background:#dc2626;z-index:1;padding:7px 10px">Total Pengeluaran</td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php $val = $dayTotals[$day] ?? 0; @endphp
                    <td style="text-align:center;padding:6px 2px;color:{{ $val > 0 ? '#fecaca' : '#ef9999' }}">{{ $val > 0 ? number_format($val/1000).'k' : '—' }}</td>
                    @endfor
                    <td class="r" style="padding:7px 10px">Rp {{ number_format($totalExpense) }}</td>
                    <td class="r" style="position:sticky;right:0;background:#dc2626;padding:7px 10px;color:#fecaca">Rp {{ number_format($summary['avgExpense']) }}</td>
                </tr>

                {{-- TF ke Penampung --}}
                <tr style="background:#eff6ff">
                    <td class="bold" style="position:sticky;left:0;background:#eff6ff;z-index:1;color:#1e40af">TF ke Penampung</td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php $val = $depositsByDay[$day]['total'] ?? 0; @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $val > 0 ? 'color:#1d4ed8;font-weight:600' : 'color:#d1d5db' }}">{{ $val > 0 ? number_format($val/1000).'k' : '—' }}</td>
                    @endfor
                    <td class="r bold" style="color:#1e40af">Rp {{ number_format($totalDeposits) }}</td>
                    <td class="r bold" style="position:sticky;right:0;background:#eff6ff;color:#1e40af">Rp {{ number_format($summary['avgDeposit']) }}</td>
                </tr>

                {{-- Admin TF --}}
                <tr style="background:#eff6ff">
                    <td style="position:sticky;left:0;background:#eff6ff;z-index:1;color:#3b82f6;font-size:10px;padding-left:18px">└ Admin TF Penampung</td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php $val = $depositsByDay[$day]['admin'] ?? 0; @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $val > 0 ? 'color:#3b82f6;font-weight:600' : 'color:#d1d5db' }}">{{ $val > 0 ? number_format($val/1000).'k' : '—' }}</td>
                    @endfor
                    <td class="r" style="color:{{ $totalAdminFees > 0 ? '#3b82f6' : 'var(--text3)' }};font-weight:600">{{ $totalAdminFees > 0 ? 'Rp '.number_format($totalAdminFees) : '—' }}</td>
                    <td class="r" style="position:sticky;right:0;background:#eff6ff;color:{{ $summary['avgAdminFee'] > 0 ? '#3b82f6' : 'var(--text3)' }};font-weight:600">{{ $summary['avgAdminFee'] > 0 ? 'Rp '.number_format($summary['avgAdminFee']) : '—' }}</td>
                </tr>

                {{-- TF ke Rek Utama --}}
                <tr style="background:#f5f3ff">
                    <td class="bold" style="position:sticky;left:0;background:#f5f3ff;z-index:1;color:#6d28d9">
                        TF ke Rek Utama
                        <div style="font-size:9px;font-weight:400;color:#a78bfa">penampung → rek utama</div>
                    </td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php $tf = ($transfersByDay[$day]['total'] ?? 0) + ($transfersByDay[$day]['surplus'] ?? 0); @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $tf > 0 ? 'color:#6d28d9;font-weight:600' : 'color:#d1d5db' }}">{{ $tf > 0 ? number_format($tf/1000).'k' : '—' }}</td>
                    @endfor
                    <td class="r bold" style="color:{{ $totalTransferred > 0 ? '#6d28d9' : 'var(--text3)' }}">{{ $totalTransferred > 0 ? 'Rp '.number_format($totalTransferred) : '—' }}</td>
                    @php $avgTf = $totalTransferred > 0 ? round($totalTransferred / $summary['cfActiveDays']) : 0; @endphp
                    <td class="r" style="position:sticky;right:0;background:#f5f3ff;color:{{ $avgTf > 0 ? '#6d28d9' : 'var(--text3)' }};font-weight:600">{{ $avgTf > 0 ? 'Rp '.number_format($avgTf) : '—' }}</td>
                </tr>

                {{-- Kas Diterima --}}
                <tr style="background:#f0fdf4">
                    <td class="bold" style="position:sticky;left:0;background:#f0fdf4;z-index:1;color:var(--melon-dark)">
                        Kas Diterima
                        <div style="font-size:9px;font-weight:400;color:var(--melon)">penjualan gas riil</div>
                    </td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php $val = $salesByDay[$day] ?? 0; @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $val > 0 ? 'color:var(--melon-dark);font-weight:600' : 'color:#d1d5db' }}">{{ $val > 0 ? number_format($val/1000).'k' : '—' }}</td>
                    @endfor
                    <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($totalIncome) }}</td>
                    <td class="r bold" style="position:sticky;right:0;background:#f0fdf4;color:var(--melon-dark)">Rp {{ number_format($summary['avgSales']) }}</td>
                </tr>

                {{-- Saldo KAS --}}
                <tr style="background:#fffbeb">
                    <td class="bold" style="position:sticky;left:0;background:#fffbeb;z-index:1;color:#92400e">
                        Saldo KAS (Fisik)
                        <div style="font-size:9px;font-weight:400;color:#b45309">uang di tangan</div>
                    </td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $bal    = $dailyBalance[$day];
                        $hasAny = ($salesByDay[$day] ?? 0) > 0 || ($dayTotals[$day] ?? 0) > 0
                            || ($depositsByDay[$day]['total'] ?? 0) > 0
                            || ($day === 1 && $openingCash > 0);
                    @endphp
                    <td style="text-align:center;padding:6px 2px;font-size:10px;{{ !$hasAny ? 'color:#d1d5db' : ($bal > 0 ? 'color:#92400e;font-weight:600' : ($bal < 0 ? 'color:#dc2626;font-weight:700' : 'color:var(--text3)')) }}"
                        title="{{ $hasAny ? 'KAS tgl '.$day.': Rp '.number_format($bal) : '' }}">
                        {{ $hasAny ? number_format($bal/1000).'k' : '—' }}
                    </td>
                    @endfor
                    <td class="r bold" style="color:{{ $netKas >= 0 ? '#92400e' : '#dc2626' }}">Rp {{ number_format($netKas) }}</td>
                    <td class="r" style="position:sticky;right:0;background:#fffbeb;color:var(--text3)">—</td>
                </tr>

                {{-- Saldo BANK --}}
                <tr style="background:#eff6ff">
                    <td class="bold" style="position:sticky;left:0;background:#eff6ff;z-index:1;color:#1e40af">
                        Saldo BANK (Penampung)
                        <div style="font-size:9px;font-weight:400;color:#3b82f6">rek penampung</div>
                    </td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $bbal    = $dailyBankBalance[$day];
                        $hasBank = ($depositsByDay[$day]['total'] ?? 0) > 0
                            || ($transfersByDay[$day]['total'] ?? 0) > 0
                            || ($day === 1 && $openingPenampung > 0);
                    @endphp
                    <td style="text-align:center;padding:6px 2px;font-size:10px;{{ !$hasBank ? 'color:#d1d5db' : ($bbal > 0 ? 'color:#1e40af;font-weight:600' : ($bbal < 0 ? 'color:#dc2626;font-weight:700' : 'color:var(--text3)')) }}"
                        title="{{ $hasBank ? 'BANK tgl '.$day.': Rp '.number_format($bbal) : '' }}">
                        {{ $hasBank ? number_format($bbal/1000).'k' : '—' }}
                    </td>
                    @endfor
                    <td class="r bold" style="color:{{ $finalBankBal >= 0 ? '#1e40af' : '#dc2626' }}">Rp {{ number_format($finalBankBal) }}</td>
                    <td class="r" style="position:sticky;right:0;background:#eff6ff;color:var(--text3)">—</td>
                </tr>

                {{-- Net Harian --}}
                <tr style="background:#374151;color:#fff;font-weight:600">
                    <td style="position:sticky;left:0;background:#374151;z-index:1;padding:7px 10px">
                        Net Harian
                        <div style="font-size:9px;font-weight:400;color:#9ca3af">selisih hari ini saja</div>
                    </td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $inc  = ($salesByDay[$day] ?? 0) + ($day === 1 ? $openingCash : 0);
                        $out  = ($dayTotals[$day] ?? 0) + ($depositsByDay[$day]['total'] ?? 0) + ($depositsByDay[$day]['admin'] ?? 0);
                        $net2 = $inc - $out;
                        $hasD = ($salesByDay[$day] ?? 0) > 0 || ($dayTotals[$day] ?? 0) > 0
                            || ($depositsByDay[$day]['total'] ?? 0) > 0
                            || ($day === 1 && $openingCash > 0);
                    @endphp
                    <td style="text-align:center;padding:6px 2px;color:{{ $net2 > 0 ? '#86efac' : ($net2 < 0 ? '#fca5a5' : '#9ca3af') }}">
                        {{ $hasD ? number_format($net2/1000).'k' : '—' }}
                    </td>
                    @endfor
                    <td class="r" style="padding:7px 10px;color:{{ $netKas >= 0 ? '#86efac' : '#fca5a5' }}">Rp {{ number_format($netKas) }}</td>
                    <td class="r" style="position:sticky;right:0;background:#374151;padding:7px 10px;color:{{ $summary['avgNet'] >= 0 ? '#86efac' : '#fca5a5' }}">Rp {{ number_format($summary['avgNet']) }}</td>
                </tr>

                {{-- Rata-rata --}}
                <tr style="background:var(--surface2);border-top:2px solid var(--border)">
                    <td style="position:sticky;left:0;background:var(--surface2);z-index:1;padding:7px 10px;font-weight:600;color:var(--text2)">
                        Rata-rata/Hari
                        <div style="font-size:9px;font-weight:400;color:var(--text3)">{{ $summary['cfActiveDays'] }} hari aktif</div>
                    </td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                        <td style="text-align:center;padding:6px 2px;color:var(--text3)">—</td>
                    @endfor
                    <td class="r bold" style="color:var(--text2)">Rp {{ number_format($summary['avgNet']) }}</td>
                    <td class="r bold" style="position:sticky;right:0;background:var(--surface2);color:var(--text2)">Rp {{ number_format($summary['avgNet']) }}</td>
                </tr>

                {{-- Sub-rata-rata --}}
                @foreach([
                    ['└ Avg Kas Diterima',    $summary['avgSales'],   'var(--melon-dark)'],
                    ['└ Avg Pengeluaran',     $summary['avgExpense'], '#dc2626'],
                    ['└ Avg TF ke Penampung', $summary['avgDeposit'], '#1d4ed8'],
                ] as [$lbl, $val, $col])
                <tr style="background:var(--surface2)">
                    <td style="position:sticky;left:0;background:var(--surface2);z-index:1;color:var(--text3);padding-left:18px;font-size:10px">{{ $lbl }}</td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
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
        Nilai dalam ribuan (k).
        <strong style="color:var(--melon-dark)">Kas Diterima</strong> = paid_amount riil.
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
                <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text3)">Belum ada pengeluaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>{{-- end x-data --}}

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    /* ═══════════════════════════════════════════════════════════════
       DATA dari PHP controller
    ═══════════════════════════════════════════════════════════════ */
    const DATA = {
        labels:    @json($chartData['labels']),
        sales:     @json($chartData['sales']),
        expenses:  @json($chartData['expenses']),
        deposits:  @json($chartData['deposits']),
        transfers: @json($chartData['transfers']),
        cashBal:   @json($chartData['cashBal']),
        bankBal:   @json($chartData['bankBal']),
        catLabels: @json($chartData['catLabels']),
        catTotals: @json($chartData['catTotals']),
    };

    // Prediksi & MC params (disiapkan controller)
    const PRED = {
        wma:   {{ round($pred['predKas']) }},
        ols:   {{ round($ols['predKas']) }},
        holt:  {{ round($pred['holtsPredKas']) }},
        wmaConf:  {{ $pred['confidence'] }},
        olsConf:  {{ $ols['confidence'] }},
        holtConf: {{ $pred['holtsConfidence'] }},
    };

    const MC = {
        marginMean: {{ $mc['marginMean'] }},
        marginStd:  {{ $mc['marginStd'] }},
        expMean:    {{ $mc['expMean'] }},
        expStd:     {{ $mc['expStd'] }},
        adminMean:  {{ $mc['adminMean'] }},
        adminStd:   {{ $mc['adminStd'] }},
        corrCoef:   {{ $mc['corrCoef'] }},
        remainDays: {{ $pred['remainDays'] }},
        netKasNow:  {{ $netKas }},
        gajiTotal:  {{ $pred['predGajiTotal'] ?? 0 }},
        piutang:    {{ $pred['piutangCairEstimasi'] ?? 0 }},
    };

    /* ═══════════════════════════════════════════════════════════════
       FORMAT HELPERS
    ═══════════════════════════════════════════════════════════════ */
    const fmtK = v => {
        const abs = Math.abs(v);
        const s = abs >= 1_000_000
            ? (Math.round(abs / 100_000) / 10).toFixed(1) + 'jt'
            : Math.round(abs / 1_000) + 'k';
        return (v < 0 ? '-' : '') + 'Rp ' + s;
    };
    const fmtFull = v => (v < 0 ? '-' : '') + 'Rp ' + Math.abs(Math.round(v)).toLocaleString('id');
    const GRAY = 'rgba(0,0,0,0.05)';
    const TICK = { font: { size: 10 }, color: '#9CA3AF' };

    /* ═══════════════════════════════════════════════════════════════
       TAB SWITCHER — METODE PREDIKSI
    ═══════════════════════════════════════════════════════════════ */
    const PRED_TABS = ['wma', 'ols', 'holt', 'mc', 'konsensus'];

    window.switchPredTab = function (tab, btn) {
        PRED_TABS.forEach(t => {
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
            mcMedianResult !== null ? renderConsensusChart(mcMedianResult) : updateConsensusPartial();
        }
    };

    /* ═══════════════════════════════════════════════════════════════
       CHART TREN HARIAN
    ═══════════════════════════════════════════════════════════════ */
    (function () {
        const net = DATA.labels.map((_, i) => DATA.sales[i] - DATA.expenses[i] - DATA.deposits[i]);

        const mkBar = (label, data, bg, border, stack) => ({
            label, data: data.map(v => v / 1000), type: 'bar',
            backgroundColor: bg, borderColor: border,
            borderWidth: 1, borderRadius: 3, stack, order: 2,
        });
        const mkLine = (label, data, color, dash = []) => ({
            label, data: data.map(v => v / 1000), type: 'line',
            borderColor: color, backgroundColor: color.replace(')', ',0.07)').replace('rgb', 'rgba'),
            borderWidth: 2.5, borderDash: dash.length ? dash : [],
            pointRadius: 2, tension: 0.35, fill: true, order: 1,
        });

        const arusData  = { labels: DATA.labels, datasets: [
            mkBar('Kas diterima', DATA.sales,     '#3B6D1155', '#3B6D11', 'income'),
            mkBar('Pengeluaran',  DATA.expenses,  '#E24B4A88', '#E24B4A', 'out'),
            mkBar('TF penampung', DATA.deposits,  '#378ADD77', '#378ADD', 'out'),
            mkBar('TF rek utama', DATA.transfers, '#7F77DD77', '#7F77DD', 'out'),
        ]};
        const saldoData = { labels: DATA.labels, datasets: [
            mkLine('Saldo KAS',  DATA.cashBal, 'rgb(186,117,23)', [6, 3]),
            mkLine('Saldo BANK', DATA.bankBal, 'rgb(55,138,221)', [6, 3]),
        ]};
        const netColors  = net.map(v => v >= 0 ? '#3B6D1166' : '#E24B4A88');
        const netBorders = net.map(v => v >= 0 ? '#3B6D11'   : '#E24B4A');
        const netData = { labels: DATA.labels, datasets: [
            { label:'Net', data: net.map(v => v / 1000), type:'bar',
              backgroundColor: netColors, borderColor: netBorders, borderWidth:1, borderRadius:4 },
        ]};

        const mkOpts = yTitle => ({
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${fmtK(ctx.parsed.y * 1000)}` } },
            },
            scales: {
                x: { grid: { color: GRAY }, ticks: { ...TICK, autoSkip: true, maxTicksLimit: 16, maxRotation: 0 } },
                y: { grid: { color: GRAY }, ticks: { ...TICK, callback: v => fmtK(v * 1000) },
                     title: { display: true, text: yTitle, color: '#9CA3AF', font: { size: 10 } } },
            },
        });

        const chart = new Chart(document.getElementById('cfTrendChart'), { data: arusData, options: mkOpts('Ribuan Rp') });

        window.cfSwitch = function (tab, btn) {
            document.querySelectorAll('#cfTabBtns button').forEach(b => {
                b.style.background = ''; b.style.color = ''; b.style.borderColor = '';
            });
            btn.style.background = 'var(--text1)'; btn.style.color = '#fff'; btn.style.borderColor = 'var(--text1)';
            ['arus', 'saldo', 'net'].forEach(t => {
                document.getElementById('cf-leg-' + t).style.display = t === tab ? 'flex' : 'none';
            });
            chart.data    = tab === 'arus' ? arusData : tab === 'saldo' ? saldoData : netData;
            chart.options = mkOpts(tab === 'saldo' ? 'Saldo (ribu Rp)' : tab === 'net' ? 'Net (ribu Rp)' : 'Ribuan Rp');
            chart.update('none');
        };
    })();

    /* ═══════════════════════════════════════════════════════════════
       ANOMALI + DONUT
    ═══════════════════════════════════════════════════════════════ */
    (function () {
        const activeDays = Math.max(1, DATA.labels.filter(i => DATA.sales[i-1] > 0 || DATA.expenses[i-1] > 0 || DATA.deposits[i-1] > 0).length);
        const totalExp   = DATA.expenses.reduce((a, b) => a + b, 0);
        const totalInc   = DATA.sales.reduce((a, b) => a + b, 0);
        const avgExpD    = totalExp / activeDays;

        const spikeDays   = DATA.labels.filter(d => (DATA.expenses[d-1] || 0) > avgExpD * 2.0);
        const negCashDays = DATA.labels.filter(d => DATA.cashBal[d-1] < 0);
        const negBankDays = DATA.labels.filter(d => DATA.bankBal[d-1] < 0);
        const ratio       = totalInc > 0 ? totalExp / totalInc : 0;

        const anomalies = [];
        if (spikeDays.length)    anomalies.push({ sev:'danger', title:'Lonjakan pengeluaran',  body:`Tgl: ${spikeDays.join(', ')} (>2× rata-rata)` });
        if (negCashDays.length)  anomalies.push({ sev:'danger', title:'Saldo KAS negatif',     body:`Tgl: ${negCashDays.join(', ')}` });
        if (negBankDays.length)  anomalies.push({ sev:'danger', title:'Saldo BANK negatif',    body:`Tgl: ${negBankDays.join(', ')}` });
        if (ratio > 0.4)         anomalies.push({ sev:'warn',   title:'Rasio biaya tinggi',    body:`Pengeluaran ${(ratio*100).toFixed(0)}% dari kas diterima` });
        if (!anomalies.length)   anomalies.push({ sev:'ok',     title:'Cashflow normal',       body:'Tidak ada anomali signifikan' });

        const score  = Math.max(0, Math.min(100, 100 - spikeDays.length*15 - negCashDays.length*20 - negBankDays.length*20 - (ratio > 0.4 ? 10 : 0)));
        const badge  = document.getElementById('cf-score-badge');
        badge.textContent = score + '/100 ' + (score >= 75 ? '🟢' : score >= 50 ? '🟡' : '🔴');
        badge.style.cssText = score >= 75 ? 'background:#dcfce7;color:#166534' : score >= 50 ? 'background:#fef9c3;color:#92400e' : 'background:#fee2e2;color:#991b1b';

        const bgMap  = { danger: '#fef2f2', warn: '#fffbeb', ok: '#f0fdf4' };
        const colMap = { danger: '#991b1b', warn: '#92400e', ok: 'var(--melon-dark)' };
        const grid   = document.getElementById('cf-anomaly-grid');
        anomalies.forEach(a => {
            const el = document.createElement('div');
            el.style.cssText = `padding:8px 10px;border-radius:8px;font-size:11px;background:${bgMap[a.sev]};border:0.5px solid #e5e7eb`;
            el.innerHTML = `<div style="font-weight:700;color:${colMap[a.sev]};margin-bottom:2px">${a.title}</div><div style="color:#374151">${a.body}</div>`;
            grid.appendChild(el);
        });

        // Donut
        const palette = ['#ef4444','#3b82f6','#f59e0b','#10b981','#8b5cf6','#f97316','#06b6d4','#84cc16','#ec4899','#6366f1','#14b8a6','#a855f7'];
        const nonZero = DATA.catTotals.map((v, i) => ({ v, i })).filter(x => x.v > 0);
        const dL = nonZero.map(x => DATA.catLabels[x.i]);
        const dD = nonZero.map(x => x.v);
        const dC = nonZero.map((_, i) => palette[i % palette.length]);
        const tot = dD.reduce((a, b) => a + b, 0);

        new Chart(document.getElementById('cfDonutChart'), {
            type: 'doughnut',
            data: { labels: dL, datasets: [{ data: dD, backgroundColor: dC, borderWidth: 1, borderColor: '#fff' }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: { legend: { display: false },
                    tooltip: { callbacks: { label: ctx => `${ctx.label}: Rp ${Math.round(ctx.raw/1000)}k (${(ctx.raw/tot*100).toFixed(1)}%)` } } } },
        });

        const leg = document.getElementById('cf-donut-legend');
        dL.forEach((lbl, i) => {
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;gap:6px;font-size:10px';
            row.innerHTML = `<span style="width:9px;height:9px;border-radius:2px;flex-shrink:0;background:${dC[i]}"></span>
                <span style="color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1">${lbl}</span>
                <span style="font-weight:700;color:var(--text1);flex-shrink:0">${tot > 0 ? (dD[i]/tot*100).toFixed(1) : 0}%</span>`;
            leg.appendChild(row);
        });
    })();

    /* ═══════════════════════════════════════════════════════════════
       MONTE CARLO — 10.000 iterasi, Box-Muller, basis margin bersih
    ═══════════════════════════════════════════════════════════════ */
    let mcHistChart    = null;
    let mcMedianResult = null;

    function boxMuller () {
        let u, v, s;
        do { u = Math.random() * 2 - 1; v = Math.random() * 2 - 1; s = u*u + v*v; } while (s >= 1 || s === 0);
        const m = Math.sqrt(-2 * Math.log(s) / s);
        return [u * m, v * m];
    }

    function sampleCorrelated (meanA, stdA, meanB, stdB, rho) {
        const [z1, zI] = boxMuller();
        const z2 = rho * z1 + Math.sqrt(Math.max(0, 1 - rho * rho)) * zI;
        return [
            Math.max(0, stdA > 0 ? meanA + z1 * stdA : meanA),
            Math.max(0, stdB > 0 ? meanB + z2 * stdB : meanB),
        ];
    }

    window.runMonteCarlo = function () {
        if (MC.remainDays <= 0) {
            document.getElementById('mc-status').textContent = 'Tidak ada hari tersisa.';
            return;
        }
        const btn  = document.getElementById('mc-run-btn');
        const icon = document.getElementById('mc-btn-icon');
        btn.disabled = true; icon.textContent = '⏳';
        document.getElementById('mc-status').textContent = 'Menjalankan 10.000 simulasi...';

        setTimeout(() => {
            const N = 10_000;
            const results = new Float64Array(N);

            for (let i = 0; i < N; i++) {
                let projM = 0, projE = 0, projA = 0;
                for (let d = 0; d < MC.remainDays; d++) {
                    const [m, e] = sampleCorrelated(MC.marginMean, MC.marginStd, MC.expMean, MC.expStd, MC.corrCoef);
                    const a = MC.adminStd > 0
                        ? Math.max(0, MC.adminMean + boxMuller()[0] * MC.adminStd)
                        : MC.adminMean;
                    projM += m; projE += e; projA += a;
                }
                results[i] = MC.netKasNow + projM + MC.piutang - projE - projA - MC.gajiTotal;
            }
            results.sort();

            const pct = p => results[Math.min(Math.floor(p / 100 * N), N - 1)];
            const p5 = pct(5), p25 = pct(25), p50 = pct(50), p75 = pct(75), p95 = pct(95);
            const pDeficit = results.filter(v => v < 0).length / N * 100;
            mcMedianResult = p50;

            // Render persentil
            const pc = document.getElementById('mc-percentiles');
            pc.innerHTML = '';
            [
                { label:'P5 (Worst 5%)', val:p5,  bg:'#fef2f2', border:'#fca5a5', col:'#991b1b' },
                { label:'P25 (Pesimis)', val:p25, bg:'#fff7ed', border:'#fed7aa', col:'#92400e' },
                { label:'P50 (Median)',  val:p50, bg:'#faf5ff', border:'#d8b4fe', col:'#6d28d9', bold:true },
                { label:'P75 (Optimis)', val:p75, bg:'#f0fdf4', border:'#86efac', col:'#166534' },
                { label:'P95 (Best 5%)', val:p95, bg:'#ecfdf5', border:'#6ee7b7', col:'#065f46' },
            ].forEach(({ label, val, bg, border, col, bold }) => {
                pc.innerHTML += `<div style="background:${bg};border:0.5px solid ${border};border-radius:8px;padding:8px 10px;text-align:center">
                    <div style="font-size:9px;font-weight:700;color:${col};text-transform:uppercase;margin-bottom:3px">${label}</div>
                    <div style="font-size:${bold?'14px':'12px'};font-weight:${bold?'700':'600'};color:${col}">${fmtK(val)}</div>
                    <div style="font-size:9px;color:${col};margin-top:2px">${val >= 0 ? '✓' : '⚠'}</div>
                </div>`;
            });

            document.getElementById('mc-prob-deficit').textContent = pDeficit.toFixed(1) + '%';
            document.getElementById('mc-prob-deficit').style.color = pDeficit > 30 ? '#dc2626' : pDeficit > 10 ? '#b45309' : '#166534';
            document.getElementById('mc-median').textContent = fmtFull(p50);
            document.getElementById('mc-median').style.color = p50 >= 0 ? '#6d28d9' : '#dc2626';
            document.getElementById('mc-ci90').textContent = `${fmtK(p5)} s/d ${fmtK(p95)}`;
            document.getElementById('mc-ci50').textContent = `${fmtK(p25)} s/d ${fmtK(p75)}`;

            const interp = document.getElementById('mc-interpretation');
            let msg = pDeficit < 5  ? `✅ <strong>Risiko sangat rendah</strong> (${pDeficit.toFixed(1)}%). Cashflow sangat sehat.`
                    : pDeficit < 20 ? `🟡 <strong>Risiko moderat</strong> (${pDeficit.toFixed(1)}%). Mayoritas skenario positif.`
                    : pDeficit < 40 ? `🟠 <strong>Risiko signifikan</strong> (${pDeficit.toFixed(1)}%). Pertimbangkan efisiensi biaya.`
                    :                 `🔴 <strong>Risiko tinggi</strong> (${pDeficit.toFixed(1)}%). Tindakan korektif diperlukan.`;
            msg += ` Median P50 = ${fmtFull(p50)}. CI 90%: ${fmtK(p5)} − ${fmtK(p95)}.`;
            interp.innerHTML = msg;
            interp.style.background   = pDeficit < 10 ? '#f0fdf4' : pDeficit < 30 ? '#fffbeb' : '#fef2f2';
            interp.style.borderColor  = pDeficit < 10 ? '#86efac' : pDeficit < 30 ? '#fde68a' : '#fca5a5';
            interp.style.color        = pDeficit < 10 ? '#166534' : pDeficit < 30 ? '#92400e' : '#991b1b';

            // Histogram
            const BINS = 20;
            const minV = results[0], maxV = results[N - 1];
            const bSz  = (maxV - minV) / BINS;
            const bins = new Array(BINS).fill(0);
            for (let i = 0; i < N; i++) bins[Math.min(Math.floor((results[i] - minV) / bSz), BINS - 1)]++;
            const bLabels  = bins.map((_, i) => fmtK(minV + (i + 0.5) * bSz));
            const bColors  = bins.map((_, i) => (minV + (i + 0.5) * bSz) >= 0 ? '#7c3aed88' : '#dc262688');
            const bBorders = bins.map((_, i) => (minV + (i + 0.5) * bSz) >= 0 ? '#7c3aed'   : '#dc2626');

            if (mcHistChart) mcHistChart.destroy();
            mcHistChart = new Chart(document.getElementById('mcHistChart'), {
                type: 'bar',
                data: { labels: bLabels, datasets: [{ label:'Frekuensi', data: bins, backgroundColor: bColors, borderColor: bBorders, borderWidth:1, borderRadius:2 }] },
                options: { responsive:true, maintainAspectRatio:false,
                    plugins: { legend:{display:false}, tooltip:{callbacks:{label:ctx=>`${ctx.parsed.y}x (${(ctx.parsed.y/N*100).toFixed(1)}%)`}} },
                    scales: { x:{grid:{color:GRAY},ticks:{...TICK,maxRotation:45,font:{size:9}}}, y:{grid:{color:GRAY},ticks:{...TICK,callback:v=>v+'x'}} } },
            });

            document.getElementById('mc-results').style.cssText = 'display:flex;flex-direction:column';
            document.getElementById('mc-status').textContent = `✅ ${N.toLocaleString('id')} iterasi selesai.`;
            btn.disabled = false; icon.textContent = '↺'; btn.style.background = '#5b21b6';

            updateConsensus(p50, Math.max(0, 100 - Math.round(pDeficit)));
        }, 20);
    };

    /* ═══════════════════════════════════════════════════════════════
       KONSENSUS — rata-rata tertimbang 4 metode
    ═══════════════════════════════════════════════════════════════ */
    let consChart = null;

    function updateConsensus (mcP50, mcConf) {
        const totalConf = PRED.wmaConf + PRED.olsConf + PRED.holtConf + mcConf;
        const wWma  = (PRED.wmaConf  / totalConf * 100).toFixed(0);
        const wOls  = (PRED.olsConf  / totalConf * 100).toFixed(0);
        const wHolt = (PRED.holtConf / totalConf * 100).toFixed(0);
        const wMc   = (mcConf        / totalConf * 100).toFixed(0);
        const consensus = (PRED.wma * PRED.wmaConf + PRED.ols * PRED.olsConf + PRED.holt * PRED.holtConf + mcP50 * mcConf) / totalConf;
        const aggConf   = Math.min(95, Math.round(totalConf / 4 + 5));

        setEl('cons-mc-val',   fmtFull(mcP50), mcP50 >= 0 ? '#6d28d9' : '#dc2626');
        setEl('cons-mc-conf',  mcConf + '%');
        setEl('cons-w-wma',    wWma + '%');
        setEl('cons-w-ols',    wOls + '%');
        setEl('cons-w-holt',   wHolt + '%');
        setEl('cons-w-mc',     wMc + '%');
        setEl('cons-total-w',  '100%');

        const resEl  = document.getElementById('cons-result');
        resEl.innerHTML = `<div style="color:${consensus >= 0 ? '#fff' : '#fca5a5'};font-size:14px">${fmtFull(consensus)}</div>
            <div style="font-size:9px;color:${consensus >= 0 ? 'rgba(255,255,255,0.8)' : '#fca5a5'}">
                Confidence agregat: ${aggConf}%<br>${consensus >= 0 ? '✓ Aman' : '⚠ Defisit'}</div>`;

        const noteEl = document.getElementById('cons-note');
        if (noteEl) noteEl.textContent = `Bobot WMA:${wWma}% / OLS:${wOls}% / Holt:${wHolt}% / MC:${wMc}%`;

        const vals    = [PRED.wma, PRED.ols, PRED.holt, mcP50];
        const spread  = Math.max(...vals) - Math.min(...vals);
        const sPct    = Math.abs(consensus) > 0 ? (spread / Math.abs(consensus) * 100).toFixed(0) : 0;
        document.getElementById('cons-insight').innerHTML =
            `<span style="font-weight:600">Insight (4 metode):</span> WMA:${fmtK(PRED.wma)}, OLS:${fmtK(PRED.ols)}, Holt:${fmtK(PRED.holt)}, MC:${fmtK(mcP50)}. `
            + (spread < Math.abs(consensus) * 0.15
                ? `📊 <strong>Spread rendah (${sPct}%)</strong> — keempat metode sangat sepakat.`
                : spread < Math.abs(consensus) * 0.35
                    ? `🔸 <strong>Spread sedang (${sPct}%)</strong> — ada perbedaan asumsi.`
                    : `⚠ <strong>Spread tinggi (${sPct}%)</strong> — cashflow kurang pasti.`);

        renderConsensusChart(mcP50, aggConf);
    }

    function updateConsensusPartial () {
        const total3 = PRED.wmaConf + PRED.olsConf + PRED.holtConf;
        const cons3  = (PRED.wma * PRED.wmaConf + PRED.ols * PRED.olsConf + PRED.holt * PRED.holtConf) / total3;
        setEl('cons-w-wma',  (PRED.wmaConf  / total3 * 100).toFixed(0) + '%');
        setEl('cons-w-ols',  (PRED.olsConf  / total3 * 100).toFixed(0) + '%');
        setEl('cons-w-holt', (PRED.holtConf / total3 * 100).toFixed(0) + '%');
        setEl('cons-w-mc',   '—');
        setEl('cons-total-w','~100%');
        document.getElementById('cons-result').innerHTML =
            `<div style="color:${cons3 >= 0 ? '#fff' : '#fca5a5'};font-size:13px">${fmtFull(cons3)}</div>
            <div style="font-size:9px;color:rgba(255,255,255,0.7)">Tanpa MC · ${cons3 >= 0 ? '✓ Aman' : '⚠ Defisit'}</div>`;
        document.getElementById('cons-insight').innerHTML =
            `<span style="font-weight:600">Sementara (3 metode):</span> WMA:${fmtK(PRED.wma)}, OLS:${fmtK(PRED.ols)}, Holt:${fmtK(PRED.holt)}. `
            + `<em style="color:#0369a1">Jalankan Monte Carlo untuk konsensus lengkap 4 metode.</em>`;
        renderConsensusChart(null, null, cons3);
    }

    function renderConsensusChart (mcP50 = null, aggConf = null, fallback = null) {
        const canvas = document.getElementById('consCompChart');
        if (!canvas) return;

        const mcVal  = mcP50 ?? mcMedianResult;
        const vals   = [PRED.wma, PRED.ols, PRED.holt, mcVal ?? 0];
        const labels = ['WMA Adaptif', 'Regresi Linear', 'Holt DES', mcVal != null ? 'MC P50' : 'MC (belum)'];
        const colors = vals.map((v, i) =>
            (i === 3 && mcVal == null) ? 'rgba(156,163,175,0.4)' : v >= 0 ? 'rgba(124,58,237,0.7)' : 'rgba(220,38,38,0.7)'
        );

        let consensus;
        if (mcVal != null) {
            const tc = PRED.wmaConf + PRED.olsConf + PRED.holtConf + (aggConf ?? 70);
            consensus = (PRED.wma * PRED.wmaConf + PRED.ols * PRED.olsConf + PRED.holt * PRED.holtConf + mcVal * (aggConf ?? 70)) / tc;
        } else {
            consensus = fallback ?? (PRED.wma * PRED.wmaConf + PRED.ols * PRED.olsConf + PRED.holt * PRED.holtConf) / (PRED.wmaConf + PRED.olsConf + PRED.holtConf);
        }

        if (consChart) consChart.destroy();
        consChart = new Chart(canvas, {
            type: 'bar',
            data: { labels, datasets: [
                { label:'Prediksi KAS', data: vals, backgroundColor: colors, borderWidth:1.5, borderRadius:6 },
                { label:'Konsensus',    data: vals.map(() => consensus), type:'line',
                  borderColor:'#f97316', borderWidth:2, borderDash:[6,3], pointRadius:0, fill:false },
            ]},
            options: {
                responsive:true, maintainAspectRatio:false,
                plugins: {
                    legend: { display:true, position:'top', labels:{font:{size:10}, boxWidth:12} },
                    tooltip: { callbacks:{ label:ctx=>`${ctx.dataset.label}: ${fmtFull(ctx.parsed.y)}` } },
                },
                scales: {
                    x: { grid:{color:GRAY}, ticks:TICK },
                    y: { grid:{color:GRAY}, ticks:{...TICK, callback: v=>fmtK(v)},
                         title:{ display:true, text:'Prediksi Saldo KAS (Rp)', color:'#9CA3AF', font:{size:10} } },
                },
            },
        });
    }

    // Helper: set text + optional color
    function setEl (id, text, color = null) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = text;
        if (color) el.style.color = color;
    }

    // Init konsensus partial (sebelum MC dijalankan)
    renderConsensusChart();
})();
</script>
@endpush
