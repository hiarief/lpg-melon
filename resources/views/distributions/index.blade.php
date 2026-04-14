@extends('layouts.app')
@section('title','Distribusi Harian')

@section('content')
<style>
/* ── Page-level overrides ────────────────────────────────── */
.dist-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}
@media (min-width: 420px) { .dist-kpi-grid { grid-template-columns: repeat(4, 1fr); } }

.kpi-card {
    background: var(--surface);
    border: 0.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.kpi-label  { font-size: 10px; color: var(--text3); font-weight: 500; }
.kpi-value  { font-size: 18px; font-weight: 700; line-height: 1.1; }
.kpi-sub    { font-size: 10px; color: var(--text3); }

/* indicator bars */
.ind-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.ind-meta { flex: 1; }
.ind-title { font-size: 11px; color: var(--text2); }
.ind-track { width: 100%; height: 5px; background: var(--melon-light); border-radius: 3px; margin-top: 5px; overflow: hidden; }
.ind-fill  { height: 100%; border-radius: 3px; }
.ind-right { text-align: right; flex-shrink: 0; }
.ind-val   { font-size: 13px; font-weight: 700; }
.ind-note  { font-size: 10px; color: var(--text3); }

/* anomaly / reco pills */
.pill-box { display: flex; flex-direction: column; gap: 6px; }
.pill { display: flex; align-items: flex-start; gap: 8px; padding: 8px 10px; border-radius: 8px; font-size: 11px; }
.pill-icon { font-size: 13px; flex-shrink: 0; margin-top: 1px; }
.pill-green  { background: var(--melon-50);   color: var(--melon-deep); border: 0.5px solid var(--border); }
.pill-red    { background: #fef2f2; color: #991b1b; border: 0.5px solid #fca5a5; }
.pill-orange { background: #fff7ed; color: #9a3412; border: 0.5px solid #fed7aa; }
.pill-blue   { background: #eff6ff; color: #1e40af; border: 0.5px solid #bfdbfe; }

/* ranking piutang */
.rank-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.rank-table th { background: var(--surface2); color: var(--text3); font-size: 9px; font-weight: 700;
                 text-transform: uppercase; letter-spacing: 0.4px; padding: 7px 10px; text-align: left; }
.rank-table th.r { text-align: right; }
.rank-table td { padding: 9px 10px; border-bottom: 0.5px solid var(--border); font-size: 11px; }
.rank-table td.r { text-align: right; }
.rank-table tbody tr:last-child td { border-bottom: none; }
.rank-num { width: 22px; height: 22px; border-radius: 50%; display: inline-flex;
            align-items: center; justify-content: center; font-size: 10px; font-weight: 600; color: #fff; }
.progress-bar { width: 100%; height: 4px; background: var(--melon-light); border-radius: 2px; overflow: hidden; margin-top: 4px; }
.progress-fill { height: 100%; border-radius: 2px; }

/* bulk form */
.bulk-form { background: #eff6ff; border: 0.5px solid #bfdbfe; border-radius: var(--radius-sm); padding: 14px; margin-bottom: 14px; }
.bulk-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.bulk-table th { background: #dbeafe; color: #1e3a5f; font-size: 10px; font-weight: 600;
                 padding: 6px 8px; text-align: left; }
.bulk-table td { padding: 5px 4px; border-bottom: 0.5px solid #bfdbfe; vertical-align: middle; }
.bulk-table tbody tr:last-child td { border-bottom: none; }

/* scroll wrapper */
.scroll-x { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* inline payment */
.pay-form { display: flex; gap: 5px; margin-top: 5px; align-items: center; }
.pay-input { border: 0.5px solid var(--border2); border-radius: 6px; padding: 5px 8px;
             font-size: 11px; width: 100px; font-family: inherit; }
.pay-btn   { background: var(--melon); color: #fff; border: none; border-radius: 6px;
             padding: 5px 10px; font-size: 11px; font-weight: 600; cursor: pointer; white-space: nowrap; }

/* grid row header inside table */
.grid-hdr { font-size: 10px; font-weight: 700; color: var(--text2); }
.grid-sub  { font-size: 9px;  color: var(--text3); font-weight: 400; }

/* chart section title */
.chart-title { font-size: 10px; font-weight: 700; color: var(--text3);
               text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;
               display: flex; align-items: center; gap: 6px; }
.chart-dot   { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* legend chips */
.legend { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 8px; }
.legend-item { display: flex; align-items: center; gap: 5px; font-size: 10px; color: var(--text3); }
.legend-swatch { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; }
.legend-line   { width: 18px; height: 2px; flex-shrink: 0; }

/* analysis grid  */
.analysis-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
@media (min-width: 380px) { .analysis-grid { grid-template-columns: repeat(3, 1fr); } }

/* 2-col grid */
.two-col { display: grid; grid-template-columns: 1fr; gap: 10px; }
@media (min-width: 380px) { .two-col { grid-template-columns: 1fr 1fr; } }

/* page header */
.page-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.page-title  { font-size: 15px; font-weight: 700; color: var(--text1); }
.header-acts { display: flex; gap: 6px; flex-wrap: wrap; }

.mob-table {
    border-collapse: collapse;
    width: 100%;
}

.mob-table {
    border-collapse: collapse;
    width: 100%;
}

.mob-table th,
.mob-table td {
    border: 1px solid rgba(0, 0, 0, 0.03); /* lebih transparan */
}

.mob-table thead th {
    border-bottom: 1px solid rgba(0, 0, 0, 0.03); /* header sedikit lebih terlihat */
}

.mob-table .total-row td {
    border-top: 1px solid rgba(0, 0, 0, 0.03); /* tetap beda tapi soft */
}
</style>

<div x-data="{ showForm: false }">

{{-- ══════════════════════════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════════════════════════ --}}
<div class="page-header">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span class="page-title">🚚 Distribusi Harian</span>
        <form method="GET" action="{{ route('distributions.index') }}" style="display:inline">
            <select name="period_id" onchange="this.form.submit()" class="field-select" style="width:auto;padding:6px 10px;font-size:12px;">
                @foreach($periods as $p)
                <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                @endforeach
            </select>
        </form>
    </div>
    @if($period->status === 'open')
    <div class="header-acts">
        <a href="{{ route('distributions.create', ['period_id' => $period->id]) }}"
           class="btn-secondary btn-sm" style="background:#fff7ed;color:#9a3412;border-color:#fed7aa;">
            + Input 1 Baris
        </a>
        <button @click="showForm = !showForm" class="btn-primary btn-sm">
            + Input Bulk
        </button>
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════
     BULK INPUT FORM
══════════════════════════════════════════════════════════ --}}
<div x-show="showForm" x-cloak x-transition
     class="bulk-form"
     x-data="{
         date: '{{ date('Y-m-d') }}',
         courierId: '{{ $couriers->first()?->id }}',
         rows: [{ customer_id: '', qty: '', price_per_unit: 18000, payment_status: 'paid', paid_amount: '' }],
         addRow() { this.rows.push({ customer_id: '', qty: '', price_per_unit: 18000, payment_status: 'paid', paid_amount: '' }) },
         removeRow(i) { if(this.rows.length > 1) this.rows.splice(i,1) }
     }">

    <div style="font-size:13px;font-weight:700;color:#1e40af;margin-bottom:12px;">📋 Input Distribusi Bulk</div>

    <form method="POST" action="{{ route('distributions.bulk-store') }}">
        @csrf
        <input type="hidden" name="period_id" value="{{ $period->id }}">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
            <div>
                <label class="field-label">Tanggal</label>
                <input type="date" name="dist_date" x-model="date" class="field-input" required>
            </div>
            <div>
                <label class="field-label">Kurir</label>
                <select name="courier_id" x-model="courierId" class="field-select" required>
                    @foreach($couriers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="scroll-x" style="margin-bottom:10px;">
            <table class="bulk-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th style="width:60px;text-align:center;">Qty</th>
                        <th style="width:100px;text-align:center;">Harga/Tab</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:100px;text-align:center;">Bayar (Rp)</th>
                        <th style="width:28px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, i) in rows" :key="i">
                        <tr>
                            <td>
                                <select :name="'rows['+i+'][customer_id]'" x-model="row.customer_id" class="field-select" style="padding:6px 8px;font-size:12px;" required>
                                    <option value="">-- Customer --</option>
                                    @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}{{ $c->type === 'contract' ? ' ★' : '' }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="text-align:center;">
                                <input type="number" :name="'rows['+i+'][qty]'" x-model="row.qty" min="1"
                                       class="field-input" style="padding:6px 8px;font-size:12px;text-align:center;" required>
                            </td>
                            <td>
                                <input type="number" :name="'rows['+i+'][price_per_unit]'" x-model="row.price_per_unit" min="10000"
                                       class="field-input" style="padding:6px 8px;font-size:12px;text-align:center;">
                            </td>
                            <td>
                                <select :name="'rows['+i+'][payment_status]'" x-model="row.payment_status"
                                        class="field-select" style="padding:6px 8px;font-size:12px;">
                                    <option value="paid">Lunas</option>
                                    <option value="deferred">Tunda</option>
                                    <option value="partial">Sebagian</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" :name="'rows['+i+'][paid_amount]'" x-model="row.paid_amount"
                                       :disabled="row.payment_status === 'paid'" min="0"
                                       class="field-input" style="padding:6px 8px;font-size:12px;text-align:center;"
                                       :style="row.payment_status === 'paid' ? 'background:var(--surface2)' : ''">
                            </td>
                            <td style="text-align:center;">
                                <button type="button" @click="removeRow(i)"
                                        style="background:none;border:none;color:#ef4444;font-size:18px;line-height:1;cursor:pointer;padding:0;">×</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
            <button type="button" @click="addRow()"
                    style="background:none;border:none;font-size:12px;color:#1e40af;cursor:pointer;font-family:inherit;font-weight:500;">
                + Tambah Baris
            </button>
            <button type="submit" class="btn-primary btn-sm">Simpan Semua</button>
        </div>
    </form>
</div>

{{-- ══════════════════════════════════════════════════════════
     CHART ANALISIS DISTRIBUSI HARIAN
══════════════════════════════════════════════════════════ --}}
@php
$chartLabels = []; $chartQty = []; $chartVal = []; $chartPaid = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dq = collect($grid)->sum(fn($days) => $days[$d]['qty'] ?? 0);
    $dv = collect($grid)->sum(fn($days) => $days[$d]['total_value'] ?? 0);
    $dp = collect($grid)->sum(fn($days) => $days[$d]['paid_amount'] ?? 0);
    if ($dq > 0) { $chartLabels[] = $d; $chartQty[] = $dq; $chartVal[] = $dv; $chartPaid[] = $dp; }
}

$allQtyC  = array_sum(array_column($customerTotals,'qty'));
$allValC  = array_sum(array_column($customerTotals,'total_value'));
$allPaidC = array_sum(array_column($customerTotals,'paid'));
$allSelC  = $allValC - $allPaidC;
$activeDC = count(array_filter($chartQty, fn($q) => $q > 0));
$avgTabHar  = $activeDC > 0 ? round($allQtyC / $activeDC, 1) : 0;
$avgHargaC  = $allQtyC > 0 ? round($allValC / $allQtyC) : 0;
@endphp

    <div class="s-card">
        <div class="s-card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
            <span>📈 Analisis Distribusi Harian</span>
            <span style="font-size:9px;font-weight:500;color:var(--text3);">
                <span style="color:var(--melon);">■</span> normal &nbsp;
                <span style="color:var(--melon-dark);">■</span> tinggi &nbsp;
                <span style="color:#dc2626;">■</span> rendah
            </span>
        </div>
        <div style="padding:12px;">
            {{-- KPI chips --}}
            <div class="dist-kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-label">Total Distribusi</span>
                    <span class="kpi-value" style="color:#f10cfd;">{{ number_format($allQtyC) }}</span>
                    <span class="kpi-sub">bulan ini</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Stok Awal</span>
                    <span class="kpi-value" style="color:#f80b0b;">{{ number_format($period->opening_stock) }}</span>
                    <span class="kpi-sub">bulan lalu</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Total DO</span>
                    <span class="kpi-value" style="color:#fd990c;">{{ number_format($totalDoQty) }}</span>
                    <span class="kpi-sub">bulan ini</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Stok Tersedia</span>
                    <span class="kpi-value" style="color:var(--melon-dark);">{{ number_format($period->opening_stock + $totalDoQty - $allQtyC) }}</span>
                    <span class="kpi-sub">{{ number_format($period->opening_stock) }}+{{ number_format($totalDoQty) }}-{{ number_format($allQtyC) }}</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Avg / Hari Aktif</span>
                    <span class="kpi-value" style="color:#1d4ed8;">{{ $avgTabHar }} tab</span>
                    <span class="kpi-sub">{{ $activeDC }} hari aktif</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Avg Harga / Tab</span>
                    <span class="kpi-value" style="color:#7c3aed;">Rp {{ number_format($avgHargaC) }}</span>
                    <span class="kpi-sub">rata-rata bulan ini</span>
                </div>
                <div class="kpi-card" style="{{ $allSelC > 0 ? 'border-color:#fca5a5;' : '' }}">
                    <span class="kpi-label">Sisa Piutang</span>
                    <span class="kpi-value" style="color:{{ $allSelC > 0 ? '#dc2626' : 'var(--melon-dark)' }};">
                        {{ $allSelC > 0 ? 'Rp '.number_format($allSelC) : '✓ Lunas' }}
                    </span>
                    <span class="kpi-sub">belum terbayar</span>
                </div>
            </div>

            {{-- Chart canvas --}}
            <div style="position:relative;width:100%;height:220px;">
                <canvas id="distChartMain" aria-label="Bar chart distribusi tabung harian"></canvas>
            </div>
            <div id="anomalyResult" style="margin-top:10px;"></div>
            <div id="distChartData"
                data-labels='@json($chartLabels)'
                data-qty='@json($chartQty)'
                data-val='@json($chartVal)'
                data-paid='@json($chartPaid)'
                style="display:none;"></div>
        </div>
    </div>

{{-- ══════════════════════════════════════════════════════════
     RINGKASAN DISTRIBUSI HARIAN
══════════════════════════════════════════════════════════ --}}
@php
$totalTagihan     = array_sum(array_column($customerTotals, 'total_value'));
$totalKasDiterima = array_sum(array_column($customerTotals, 'paid'));
$totalPiutang     = $totalTagihan - $totalKasDiterima;
$rasioLunas       = $totalTagihan > 0 ? ($totalKasDiterima / $totalTagihan * 100) : 0;

$activeDaysD = collect(range(1,$daysInMonth))
    ->filter(fn($d) => collect($grid)->sum(fn($days) => $days[$d]['qty'] ?? 0) > 0)->count();
$activeDaysD = max($activeDaysD, 1);

$allQtyD   = array_sum(array_column($customerTotals,'qty'));
$avgQtyH   = round($allQtyD / $activeDaysD, 1);
$avgNilaiH = $activeDaysD > 0 ? round($totalTagihan / $activeDaysD) : 0;
$avgKasH   = $activeDaysD > 0 ? round($totalKasDiterima / $activeDaysD) : 0;

$hariLabels = []; $hariNilai = []; $hariKas = []; $hariPiutang = []; $hariQty = []; $hariHarga = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dq = collect($grid)->sum(fn($days) => $days[$d]['qty'] ?? 0);
    $dv = collect($grid)->sum(fn($days) => $days[$d]['total_value'] ?? 0);
    $dp = collect($grid)->sum(fn($days) => $days[$d]['paid_amount'] ?? 0);
    if ($dq > 0) { $hariLabels[] = $d; $hariNilai[] = $dv; $hariKas[] = $dp; $hariPiutang[] = $dv - $dp; $hariQty[] = $dq; $hariHarga[] = round($dv / $dq); }
}

$rankPiutang = $customers->map(function($c) use ($customerTotals) {
    $t = $customerTotals[$c->id] ?? ['qty'=>0,'total_value'=>0,'paid'=>0];
    return ['id'=>$c->id,'name'=>$c->name,'type'=>$c->type,'tagihan'=>$t['total_value'],'bayar'=>$t['paid'],'piutang'=>$t['total_value']-$t['paid'],'qty'=>$t['qty'],'pct_lunas'=>$t['total_value']>0?round($t['paid']/$t['total_value']*100):100];
})->filter(fn($c) => $c['piutang'] > 0)->sortByDesc('piutang')->values();

$jmlLunas    = $customers->filter(fn($c)=>($customerTotals[$c->id]['total_value']??0)>0 && ($customerTotals[$c->id]['paid']??0)>=($customerTotals[$c->id]['total_value']??0))->count();
$jmlSebagian = $customers->filter(fn($c)=>($customerTotals[$c->id]['paid']??0)>0 && ($customerTotals[$c->id]['paid']??0)<($customerTotals[$c->id]['total_value']??0))->count();
$jmlBelum    = $customers->filter(fn($c)=>($customerTotals[$c->id]['total_value']??0)>0 && ($customerTotals[$c->id]['paid']??0)==0)->count();

$custBarNames = $customers->filter(fn($c)=>($customerTotals[$c->id]['qty']??0)>0)->map(fn($c)=>['name'=>$c->name,'qty'=>$customerTotals[$c->id]['qty'],'type'=>$c->type])->sortByDesc('qty')->values();

$hargaMin = count($hariHarga) ? min($hariHarga) : 0;
$hargaMax = count($hariHarga) ? max($hariHarga) : 0;
$variasiHarga = $hargaMin > 0 ? round(($hargaMax - $hargaMin) / $hargaMin * 100, 1) : 0;

$maxQtyCust  = $custBarNames->max('qty') ?? 0;
$konsentrasi = $allQtyD > 0 ? round($maxQtyCust / $allQtyD * 100) : 0;
$topCustName = $custBarNames->first()['name'] ?? '-';
$rasioAktif  = round($activeDaysD / 26 * 100);
@endphp

{{-- KPI Ringkasan --}}
<div class="s-card">
    <div class="s-card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <span>Ringkasan Distribusi Harian</span>
        <span style="font-size:10px;font-weight:400;color:var(--text3);">{{ $activeDaysD }} hari aktif · {{ $daysInMonth }} hari/bln</span>
    </div>
    <div style="padding:12px;">
        <div class="dist-kpi-grid">
            <div class="kpi-card">
                <span class="kpi-label">Total Tabung</span>
                <span class="kpi-value" style="color:var(--melon-dark);">{{ number_format($allQtyD) }} tab</span>
                <span class="kpi-sub">avg {{ $avgQtyH }} tab/hari · {{ $activeDaysD }} hari aktif</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Total Tagihan</span>
                <span class="kpi-value" style="color:#1d4ed8;font-size:14px;">Rp {{ number_format($totalTagihan) }}</span>
                <span class="kpi-sub">avg Rp {{ number_format($avgNilaiH) }}/hari</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Kas Diterima</span>
                <span class="kpi-value" style="color:var(--melon-dark);font-size:14px;">Rp {{ number_format($totalKasDiterima) }}</span>
                <span class="kpi-sub">avg Rp {{ number_format($avgKasH) }}/hari</span>
            </div>
            <div class="kpi-card" style="{{ $totalPiutang > 0 ? 'border-color:#fca5a5;' : '' }}">
                <span class="kpi-label">Piutang Belum Lunas</span>
                <span class="kpi-value" style="color:{{ $totalPiutang > 0 ? '#dc2626' : 'var(--melon-dark)' }};font-size:14px;">
                    {{ $totalPiutang > 0 ? 'Rp '.number_format($totalPiutang) : '✓ Lunas semua' }}
                </span>
                <span class="kpi-sub">{{ number_format(100 - $rasioLunas, 1) }}% dari tagihan</span>
            </div>
        </div>

        {{-- Chart nilai harian --}}
        <div class="chart-title">
            <span class="chart-dot" style="background:#1d4ed8;"></span>Nilai Harian (Rp)
        </div>
        <div class="legend">
            <span class="legend-item"><span class="legend-swatch" style="background:#bfdbfe;"></span>Nilai tagihan</span>
            <span class="legend-item"><span class="legend-line" style="background:#059669;"></span>Kas diterima</span>
            <span class="legend-item"><span class="legend-line" style="background:#dc2626;border-top:2px dashed #dc2626;height:0;"></span>Piutang</span>
        </div>
        <div style="position:relative;width:100%;height:200px;margin-bottom:14px;">
            <canvas id="cHarianBlade"></canvas>
        </div>

        {{-- Ranking piutang --}}
        @if($rankPiutang->count() > 0)
        <div class="chart-title" style="margin-top:6px;">
            <span class="chart-dot" style="background:#dc2626;"></span>Ranking Piutang Customer
        </div>
        <div style="overflow:hidden;border:0.5px solid var(--border);border-radius:var(--radius-sm);">
            <div class="scroll-x">

            <table class="rank-table">
                <thead>
                    <tr>
                        <th style="width:32px;">#</th>
                        <th>Customer</th>
                        <th class="r">Tagihan</th>
                        <th class="r">Terbayar</th>
                        <th class="r">Piutang</th>
                        <th>Progres</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @php $rankColors = ['#A32D2D','#D85A30','#BA7517','#854F0B','#5F5E5A']; @endphp
                    @foreach($rankPiutang as $i => $rc)
                    @php
                        $pctShare = $totalPiutang > 0 ? round($rc['piutang']/$totalPiutang*100) : 0;
                        $rColor   = $rankColors[$i] ?? '#888';
                        $barColor = $rc['pct_lunas']>=70?'var(--melon)':($rc['pct_lunas']>=40?'#f59e0b':'#dc2626');
                        if ($rc['pct_lunas']==0)      { $sLbl='Belum bayar'; $sBg='#fef2f2'; $sCol='#991b1b'; }
                        elseif($rc['pct_lunas']<50)   { $sLbl='Sebagian kecil'; $sBg='#fff7ed'; $sCol='#9a3412'; }
                        elseif($rc['pct_lunas']<80)   { $sLbl='Sebagian'; $sBg='#eff6ff'; $sCol='#1e40af'; }
                        else                          { $sLbl='Hampir lunas'; $sBg='var(--melon-light)'; $sCol='var(--melon-deep)'; }
                    @endphp
                    <tr>
                        <td><span class="rank-num" style="background:{{ $rColor }};">{{ $i+1 }}</span></td>
                        <td style="font-weight:600;color:var(--text1);">
                            {{ $rc['name'] }}
                            @if($rc['type']==='contract') <span style="color:#d97706;">★</span> @endif
                        </td>
                        <td class="r" style="color:var(--text2);">Rp {{ number_format($rc['tagihan']) }}</td>
                        <td class="r" style="color:var(--melon-dark);">Rp {{ number_format($rc['bayar']) }}</td>
                        <td class="r" style="color:{{ $rColor }};font-weight:700;">Rp {{ number_format($rc['piutang']) }}</td>
                        <td>
                            <div style="font-size:9px;color:var(--text3);margin-bottom:3px;">{{ $rc['pct_lunas'] }}% · {{ $pctShare }}% total</div>
                            <div class="progress-bar"><div class="progress-fill" style="width:{{ $rc['pct_lunas'] }}%;background:{{ $barColor }};"></div></div>
                        </td>
                        <td><span class="badge" style="background:{{ $sBg }};color:{{ $sCol }};">{{ $sLbl }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        </div>
        @else
        <div class="pill pill-green" style="margin-top:6px;">
            <span class="pill-icon">✓</span>
            <span>Semua customer sudah lunas — tidak ada piutang bulan ini</span>
        </div>
        @endif

        {{-- Chart analisis strategi --}}
        <div class="chart-title" style="margin-top:16px;">
            <span class="chart-dot" style="background:#7c3aed;"></span>Analisis Strategi Distribusi
        </div>
        
    <div class="col">
        <div class="card" style="padding:12px;margin-top:10px">
            <div style="font-size:10px;font-weight:600;color:var(--text3);margin-bottom:8px;">Status pembayaran</div>
            <div style="position:relative;height:120px;"><canvas id="cDonutBlade"></canvas></div>
            <div style="margin-top:8px;display:flex;flex-direction:column;gap:4px;">
                @foreach([['Lunas',$jmlLunas,'var(--melon)'],['Sebagian',$jmlSebagian,'#3b82f6'],['Belum bayar',$jmlBelum,'#dc2626']] as [$lbl,$jml,$col])
                @if($jml > 0)
                <div style="display:flex;align-items:center;gap:6px;font-size:10px;">
                    <span style="width:8px;height:8px;border-radius:2px;background:{{ $col }};flex-shrink:0;"></span>
                    <span style="color:var(--text3);">{{ $lbl }}</span>
                    <span style="margin-left:auto;font-weight:600;color:var(--text1);">{{ $jml }}</span>
                </div>
                @endif
                @endforeach
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card" style="padding:12px;margin-top:10px">
            <div style="font-size:10px;font-weight:600;color:var(--text3);margin-bottom:8px;">Qty per customer (tab)</div>
            <div style="position:relative;height:120px;"><canvas id="cCustBlade"></canvas></div>
        </div>
    </div>
    <div class="col">
        <div class="card" style="padding:12px;margin-top:10px">
            <div style="font-size:10px;font-weight:600;color:var(--text3);margin-bottom:8px;">Avg harga/tab per hari</div>
            <div style="position:relative;height:120px;"><canvas id="cHargaBlade"></canvas></div>
        </div>
    </div>

        {{-- Indikator + Rekomendasi --}}
        <div class="col">

            <div class="card" style="padding:12px;margin-top:10px">

                <div style="font-size:10px;font-weight:600;color:var(--text3);margin-bottom:10px;">Indikator Kesehatan</div>

                @php
                $indikators = [
                    ['Tingkat pelunasan', number_format($rasioLunas,1).'%', 'dari total tagihan', $rasioLunas, $rasioLunas>=90?'var(--melon)':($rasioLunas>=70?'#f59e0b':'#dc2626')],
                    ['Hari aktif', $activeDaysD.' hari', $rasioAktif.'% hari kerja', $rasioAktif, 'var(--melon)'],
                    ['Variasi harga', $variasiHarga.'%', 'Rp '.number_format($hargaMin).' – '.number_format($hargaMax), min($variasiHarga*5,100), $variasiHarga<5?'var(--melon)':'#f59e0b'],
                    ['Konsentrasi 1 customer', $konsentrasi.'%', 'risiko ketergantungan', $konsentrasi, $konsentrasi<30?'var(--melon)':($konsentrasi<45?'#f59e0b':'#dc2626')],
                ];
                @endphp
                @foreach($indikators as [$lbl,$val,$note,$bar,$col])
                <div class="ind-row">
                    <div class="ind-meta">
                        <div class="ind-title">{{ $lbl }}</div>
                        <div class="ind-track"><div class="ind-fill" style="width:{{ $bar }}%;background:{{ $col }};"></div></div>
                    </div>
                    <div class="ind-right">
                        <div class="ind-val" style="color:{{ $col }};">{{ $val }}</div>
                        <div class="ind-note">{{ $note }}</div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="card" style="padding:12px;margin-top:10px">
                <div style="font-size:10px;font-weight:600;color:var(--text3);margin-bottom:10px;;">Rekomendasi Strategi</div>

                <div class="pill-box">
                    @if($rasioLunas < 90)
                    <div class="pill pill-red">
                        <span class="pill-icon">!</span>
                        <span>Pelunasan {{ number_format($rasioLunas,1) }}% — prioritaskan penagihan ke <strong>{{ $rankPiutang->first()['name'] ?? '-' }}</strong></span>
                    </div>
                    @endif
                    @if($konsentrasi > 40)
                    <div class="pill pill-orange">
                        <span class="pill-icon">⚠</span>
                        <span>{{ $konsentrasi }}% ke <strong>{{ $topCustName }}</strong> — pertimbangkan diversifikasi</span>
                    </div>
                    @endif
                    @if($variasiHarga > 5)
                    <div class="pill pill-orange">
                        <span class="pill-icon">⚠</span>
                        <span>Harga/tab bervariasi {{ $variasiHarga }}% — standarisasi perlu ditinjau</span>
                    </div>
                    @endif
                    @if($rasioAktif < 80)
                    <div class="pill pill-blue">
                        <span class="pill-icon">i</span>
                        <span>Distribusi aktif {{ $rasioAktif }}% hari kerja — potensi peningkatan frekuensi</span>
                    </div>
                    @endif
                    @if($rasioLunas >= 90 && $konsentrasi <= 40 && $variasiHarga <= 5 && $rasioAktif >= 80)
                    <div class="pill pill-green">
                        <span class="pill-icon">✓</span>
                        <span>Semua indikator sehat — pertahankan konsistensi distribusi</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     PROYEKSI DISTRIBUSI BULANAN
══════════════════════════════════════════════════════════ --}}
@php
$todayDay      = now()->day;
$sisaKalender  = $daysInMonth - $todayDay;

// Data aktual per hari (reuse dari $chartLabels / $chartQty di atas)
$projQtyList   = $chartQty;   // array qty per hari aktif
$projDayList   = $chartLabels; // array label hari (angka)

$totalAktual   = array_sum($projQtyList);
$activeDaysP   = count(array_filter($projQtyList));

$avgQtyHariP   = $activeDaysP > 0 ? $totalAktual / $activeDaysP : 0;
$meanP         = $avgQtyHariP;
$stdP          = $activeDaysP > 1
    ? sqrt(array_sum(array_map(fn($q) => ($q - $meanP) ** 2, $projQtyList)) / $activeDaysP)
    : 0;

$rasioAktifP   = $todayDay > 0 ? $activeDaysP / $todayDay : 0;
$estHariSisa   = max(round($rasioAktifP * $sisaKalender), 0);

$projTren      = round($totalAktual + $estHariSisa * $avgQtyHariP);
$projMaks      = round($totalAktual + $estHariSisa * ($avgQtyHariP + $stdP));
$projMin       = round($totalAktual + $estHariSisa * max($avgQtyHariP - $stdP, 0));
$projPct       = $projTren > 0 ? min(round($totalAktual / $projTren * 100), 100) : 0;

// Kumulatif per hari untuk chart
$cumByDay = [];
$cumRun   = 0;
foreach ($projDayList as $i => $d) {
    $cumRun += $projQtyList[$i];
    $cumByDay[$d] = $cumRun;
}

$chartProjLabels = [];
$chartProjAktual = [];
$chartProjTren   = [];
$chartProjMin    = [];
$chartProjMaks   = [];

$lastCum = 0;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $chartProjLabels[] = $d;
    if ($d <= $todayDay) {
        $v = $cumByDay[$d] ?? $lastCum;
        $lastCum = $v;
        $chartProjAktual[] = $v;
        $chartProjTren[]   = $v;
        $chartProjMin[]    = $v;
        $chartProjMaks[]   = $v;
    } else {
        $ahead     = $d - $todayDay;
        $estDays   = round($ahead * $rasioAktifP);
        $chartProjAktual[] = null;
        $chartProjTren[]   = round($totalAktual + $estDays * $avgQtyHariP);
        $chartProjMin[]    = round($totalAktual + $estDays * max($avgQtyHariP - $stdP, 0));
        $chartProjMaks[]   = round($totalAktual + $estDays * ($avgQtyHariP + $stdP));
    }
}
@endphp

<div class="s-card">
    <div class="s-card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <span>🔮 Proyeksi Distribusi Bulanan</span>
        <span style="font-size:10px;color:var(--text3);">per {{ now()->format('d M Y') }}</span>
    </div>
    <div style="padding:12px;">

        {{-- KPI --}}
        <div class="dist-kpi-grid">
            <div class="kpi-card">
                <span class="kpi-label">Aktual s.d. hari ini</span>
                <span class="kpi-value" style="color:#1d4ed8;">{{ number_format($totalAktual) }} tab</span>
                <span class="kpi-sub">{{ $activeDaysP }} hari aktif</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Proyeksi akhir bulan</span>
                <span class="kpi-value" style="color:#7c3aed;">{{ number_format($projTren) }} tab</span>
                <span class="kpi-sub">skenario tren saat ini</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Avg / hari aktif</span>
                <span class="kpi-value" style="color:#059669;">{{ number_format($avgQtyHariP, 1) }} tab</span>
                <span class="kpi-sub">±{{ number_format($stdP, 1) }} std dev</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Sisa hari kalender</span>
                <span class="kpi-value" style="color:#d97706;">{{ $sisaKalender }} hari</span>
                <span class="kpi-sub">~{{ $estHariSisa }} hari aktif est.</span>
            </div>
        </div>

        {{-- Progress bar --}}
        <div style="margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text3);margin-bottom:5px;">
                <span>Progres bulan ini</span>
                <span>{{ $projPct }}%</span>
            </div>
            <div class="ind-track" style="height:7px;"><div class="ind-fill" style="width:{{ $projPct }}%;background:#1d4ed8;"></div></div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-top:4px;">
                <span>Aktual: <strong>{{ number_format($totalAktual) }}</strong></span>
                <span>Proyeksi: <strong>{{ number_format($projTren) }}</strong></span>
            </div>
        </div>

        {{-- Skenario --}}
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;">
            @foreach([
                ['Pesimis',        $projMin,  '#dc2626', 'jika distribusi melambat'],
                ['Tren saat ini',  $projTren, '#1d4ed8', 'berdasarkan rata-rata aktual'],
                ['Optimis',        $projMaks, '#059669', 'jika distribusi meningkat'],
            ] as [$sLabel, $sQty, $sCol, $sDesc])
            @php $sPct = $projTren > 0 ? min(round($totalAktual / max($sQty,1) * 100), 100) : 0; @endphp
            <div style="border:0.5px solid var(--border);border-radius:var(--radius-sm);padding:10px 12px;">
                <div style="font-size:10px;font-weight:600;color:var(--text2);margin-bottom:4px;">{{ $sLabel }}</div>
                <div style="font-size:17px;font-weight:700;color:{{ $sCol }};">{{ number_format($sQty) }}</div>
                <div style="font-size:10px;color:var(--text3);">{{ $sDesc }}</div>
                <div class="ind-track" style="margin-top:6px;"><div class="ind-fill" style="width:{{ $sPct }}%;background:{{ $sCol }};"></div></div>
            </div>
            @endforeach
        </div>

        {{-- Chart kumulatif --}}
        <div class="legend">
            <span class="legend-item"><span class="legend-swatch" style="background:#bfdbfe;"></span>Aktual</span>
            <span class="legend-item"><span class="legend-line" style="background:#1d4ed8;"></span>Proyeksi tren</span>
            <span class="legend-item"><span class="legend-line" style="background:#dc2626;border-top:2px dashed #dc2626;height:0;"></span>Min</span>
            <span class="legend-item"><span class="legend-line" style="background:#059669;border-top:2px dashed #059669;height:0;"></span>Maks</span>
        </div>
        <div style="position:relative;width:100%;height:220px;margin-bottom:14px;">
            <canvas id="cProjBlade"></canvas>
        </div>

        {{-- Simulator --}}
        <div style="background:var(--surface2);border-radius:var(--radius-sm);padding:12px;"
             x-data="{ extraDays: 0, qtyHari: {{ max(round($avgQtyHariP), 10) }}, avgHarga: {{ $avgHargaC ?? 18000 }} }">
            <div style="font-size:10px;font-weight:600;color:var(--text3);margin-bottom:10px;">Simulator "Bagaimana Jika?"</div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:12px;">
                <span style="min-width:130px;color:var(--text2);">Hari aktif tambahan</span>
                <input type="range" min="0" max="10" step="1" x-model.number="extraDays" style="flex:1;">
                <span x-text="'+'+extraDays+' hari'" style="min-width:55px;text-align:right;font-weight:600;color:var(--text1);font-size:12px;"></span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:12px;">
                <span style="min-width:130px;color:var(--text2);">Avg tabung / hari</span>
                <input type="range" min="10" max="200" step="5" x-model.number="qtyHari" style="flex:1;">
                <span x-text="qtyHari+' tab'" style="min-width:55px;text-align:right;font-weight:600;color:var(--text1);font-size:12px;"></span>
            </div>
            <div style="border-top:0.5px solid var(--border);padding-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <div>
                    <div style="font-size:10px;color:var(--text3);">Estimasi total tabung</div>
                    <div x-text="({{ $totalAktual }} + ({{ $estHariSisa }} + extraDays) * qtyHari).toLocaleString('id') + ' tab'"
                         style="font-size:20px;font-weight:700;color:#1d4ed8;"></div>
                </div>
                <div>
                    <div style="font-size:10px;color:var(--text3);">Estimasi nilai</div>
                    <div x-text="'Rp ' + (({{ $totalAktual }} + ({{ $estHariSisa }} + extraDays) * qtyHari) * avgHarga).toLocaleString('id')"
                         style="font-size:14px;font-weight:700;color:#7c3aed;line-height:1.4;margin-top:4px;"></div>
                </div>
            </div>
        </div>

        <div style="font-size:10px;color:var(--text3);margin-top:10px;">
            Proyeksi menggunakan rata-rata harian aktual × estimasi hari aktif sisa. Confidence interval ±{{ number_format($stdP, 1) }} tab/hari (1 std dev).
        </div>
    </div>
</div>

{{-- Data untuk chart proyeksi --}}
<div id="projChartData"
     data-labels='@json($chartProjLabels)'
     data-aktual='@json($chartProjAktual)'
     data-tren='@json($chartProjTren)'
     data-min='@json($chartProjMin)'
     data-maks='@json($chartProjMaks)'
     data-today="{{ $todayDay }}"
     style="display:none;"></div>

{{-- ══════════════════════════════════════════════════════════
     REKAP PER CUSTOMER PER TANGGAL
══════════════════════════════════════════════════════════ --}}
<div class="s-card">
    <div class="s-card-header">📊 Rekap Distribusi per Customer</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th style="min-width:110px;position:sticky;left:0;background:#f8faf8;z-index:10;">Customer</th>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                    <th class="r" style="min-width:28px;">{{ $d }}</th>
                    @endfor
                    <th class="r" style="background:var(--melon-50);">Tab</th>
                    <th class="r" style="background:var(--melon-50);">Nilai (Rp)</th>
                    <th class="r" style="background:var(--melon-50);">Terbayar</th>
                    <th class="r" style="background:#f5f3ff;">Avg/Tab</th>
                    <th class="r" style="background:#fef2f2;">Piutang</th>
                </tr>
            </thead>
            <tbody>
                @foreach($customers as $c)
                @php
                $t = $customerTotals[$c->id] ?? ['qty'=>0,'total_value'=>0,'paid'=>0];
                $avgHarga = $t['qty'] > 0 ? round($t['total_value']/$t['qty']) : 0;
                $selisih  = $t['total_value'] - $t['paid'];
                @endphp
                <tr style="{{ $c->type==='contract' ? 'background:#fffbeb;' : '' }}">
                    <td class="bold" style="position:sticky;left:0;z-index:5;background:{{ $c->type==='contract'?'#fffbeb':'#fff' }};min-width:110px;">
                        {{ $c->name }}
                        @if($c->type === 'contract') <span style="color:#d97706;">★</span> @endif
                    </td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php $cell = $grid[$c->id][$day] ?? null; @endphp
                    <td class="r" style="padding:6px 2px;font-size:10px;">
                        @if($cell)
                        <span style="color:{{ $cell['payment_status']==='deferred'?'#ca8a04':($cell['payment_status']==='partial'?'#2563eb':'var(--melon-dark)') }};font-weight:600;"
                              title="Nilai: Rp {{ number_format($cell['total_value']) }} | Bayar: Rp {{ number_format($cell['paid_amount']) }}">
                            {{ $cell['qty'] }}{{ count($cell['ids'])>1?'*':'' }}
                        </span>
                        @else
                        <span style="color:var(--border);">–</span>
                        @endif
                    </td>
                    @endfor
                    <td class="r bold" style="color:{{ $t['qty']>0?'var(--melon-dark)':'var(--text3)' }};">
                        {{ $t['qty']>0?number_format($t['qty']):'-' }}
                    </td>
                    <td class="r">{{ $t['total_value']>0?'Rp '.number_format($t['total_value']):'-' }}</td>
                    <td class="r" style="color:{{ $t['paid']<$t['total_value']&&$t['total_value']>0?'#dc2626':'var(--melon-dark)' }};">
                        {{ $t['total_value']>0?'Rp '.number_format($t['paid']):'-' }}
                    </td>
                    <td class="r" style="color:#7c3aed;font-weight:600;">
                        {{ $avgHarga>0?'Rp '.number_format($avgHarga):'-' }}
                    </td>
                    <td class="r" style="font-weight:600;color:{{ $selisih>0?'#dc2626':($t['total_value']>0?'var(--melon-dark)':'var(--text3)') }};">
                        {{ $t['total_value']>0?($selisih>0?'Rp '.number_format($selisih):'✓ Lunas'):'-' }}
                    </td>
                </tr>
                @endforeach

                @php
                $allQty  = array_sum(array_column($customerTotals,'qty'));
                $allVal  = array_sum(array_column($customerTotals,'total_value'));
                $allPaid = array_sum(array_column($customerTotals,'paid'));
                $allSel  = $allVal - $allPaid;
                $avgPriceByDay = [];
                foreach (range(1,$daysInMonth) as $day) {
                    $dqS = collect($grid)->sum(fn($days)=>$days[$day]['qty']??0);
                    $dvS = collect($grid)->sum(fn($days)=>$days[$day]['total_value']??0);
                    $avgPriceByDay[$day] = $dqS>0?round($dvS/$dqS):0;
                }
                $avgPriceTotal = $allQty>0?round($allVal/$allQty):0;
                $activeDaysD2  = collect(range(1,$daysInMonth))->filter(fn($d)=>collect($grid)->sum(fn($days)=>$days[$d]['qty']??0)>0)->count();
                $avgQtyPerDay  = $activeDaysD2>0?round($allQty/$activeDaysD2,1):0;
                $avgValPerDay  = $activeDaysD2>0?round($allVal/$activeDaysD2):0;
                $avgPaidPerDay = $activeDaysD2>0?round($allPaid/$activeDaysD2):0;
                @endphp

                {{-- Total row --}}
                <tr class="total-row">
                    <td style="position:sticky;left:0;background:var(--melon);z-index:5;min-width:110px;">TOTAL</td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php $dq=collect($grid)->sum(fn($days)=>$days[$day]['qty']??0); @endphp
                    <td class="r" style="font-size:10px;">{{ $dq?:'-' }}</td>
                    @endfor
                    <td class="r">{{ number_format($allQty) }}</td>
                    <td class="r">Rp {{ number_format($allVal) }}</td>
                    <td class="r">Rp {{ number_format($allPaid) }}</td>
                    <td class="r">Rp {{ number_format($avgPriceTotal) }}</td>
                    <td class="r">{{ $allSel>0?'Rp '.number_format($allSel):'✓ Lunas' }}</td>
                </tr>

                {{-- Avg harga/tab per hari --}}
                <tr style="background:#f5f3ff;">
                    <td style="position:sticky;left:0;z-index:5;background:#f5f3ff;min-width:110px;">
                        <span class="grid-hdr" style="color:#7c3aed;">Avg Harga/Tab</span>
                        <div class="grid-sub">rata-rata per hari</div>
                    </td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php $avg=$avgPriceByDay[$day]??0; @endphp
                    <td class="r" style="font-size:10px;color:{{ $avg>0?'#7c3aed':'var(--border)' }};font-weight:{{ $avg>0?600:400 }};">
                        {{ $avg>0?number_format($avg/1000,1).'k':'-' }}
                    </td>
                    @endfor
                    <td class="r" colspan="2" style="color:#7c3aed;font-weight:700;font-size:11px;">Rp {{ number_format($avgPriceTotal) }}/tab</td>
                    <td></td><td></td><td></td>
                </tr>

                {{-- Selisih piutang per hari --}}
                <tr style="background:#fef2f2;">
                    <td style="position:sticky;left:0;z-index:5;background:#fef2f2;min-width:110px;">
                        <span class="grid-hdr" style="color:#dc2626;">Selisih (Piutang)</span>
                        <div class="grid-sub">nilai − terbayar</div>
                    </td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    @php
                    $dvD  = collect($grid)->sum(fn($days)=>$days[$day]['total_value']??0);
                    $dpD  = collect($grid)->sum(fn($days)=>$days[$day]['paid_amount']??0);
                    $dSel = $dvD - $dpD;
                    @endphp
                    <td class="r" style="font-size:10px;color:{{ $dSel>0?'#dc2626':($dvD>0?'var(--melon-dark)':'var(--border)') }};font-weight:{{ $dSel>0||$dvD>0?600:400 }};">
                        {{ $dvD>0?($dSel>0?number_format($dSel/1000).'k':'✓'):'-' }}
                    </td>
                    @endfor
                    <td class="r muted" style="font-size:10px;">tagihan</td>
                    <td class="r muted" style="font-size:10px;">terbayar</td>
                    <td class="r" style="font-weight:700;font-size:12px;color:{{ $allSel>0?'#dc2626':'var(--melon-dark)' }};">
                        {{ $allSel>0?'Rp '.number_format($allSel):'✓ Lunas' }}
                    </td>
                    <td></td><td></td>
                </tr>

                {{-- Avg per hari aktif --}}
                <tr>
                    <td style="position:sticky;left:0;z-index:5;background:var(--surface2);min-width:110px;">
                        <span class="grid-hdr" style="color:var(--text2);">Rata-rata/Hari</span>
                        <div class="grid-sub">dari {{ $activeDaysD2 }} hari aktif</div>
                    </td>
                    @for($day=1;$day<=$daysInMonth;$day++)
                    <td class="r" style="color:var(--border);font-size:10px;">–</td>
                    @endfor
                    <td class="r" style="font-size:11px;color:var(--text2);font-weight:600;">{{ $avgQtyPerDay }} tab</td>
                    <td class="r" style="font-size:11px;color:var(--text2);">Rp {{ number_format($avgValPerDay) }}</td>
                    <td class="r" style="font-size:11px;color:var(--text2);">Rp {{ number_format($avgPaidPerDay) }}</td>
                    <td></td><td></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div style="padding:8px 12px;font-size:10px;color:var(--text3);display:flex;flex-wrap:wrap;gap:8px;">
        <span><span style="color:var(--melon-dark);font-weight:600;">Hijau</span> = Lunas</span>
        <span><span style="color:#ca8a04;font-weight:600;">Kuning</span> = Ditunda</span>
        <span><span style="color:#2563eb;font-weight:600;">Biru</span> = Sebagian</span>
        <span><span style="color:#d97706;font-weight:600;">★</span> = Kontrak</span>
        <span><span style="color:#7c3aed;font-weight:600;">Ungu</span> = Avg Harga</span>
        <span><span style="color:#dc2626;font-weight:600;">Merah</span> = Piutang</span>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     DETAIL DISTRIBUSI
══════════════════════════════════════════════════════════ --}}
<div class="s-card">
    <div class="s-card-header">📋 Detail Distribusi</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Tgl</th>
                    <th>Customer</th>
                    <th>Kurir</th>
                    <th class="r">Qty</th>
                    <th class="r">Harga</th>
                    <th class="r">Nilai</th>
                    <th class="r">Bayar</th>
                    <th class="r">Selisih</th>
                    <th>Status</th>
                    @if($period->status === 'open') <th>Aksi</th> @endif
                </tr>
            </thead>
            <tbody>
                @forelse($distributions as $dist)
                @php
                $distNilai   = $dist->qty * $dist->price_per_unit;
                $distSelisih = $distNilai - $dist->paid_amount;
                @endphp
                <tr style="{{ $dist->customer->type==='contract'?'background:#fffbeb;':'' }}">
                    <td>{{ $dist->dist_date->format('d/m') }}</td>
                    <td class="bold">{{ $dist->customer->name }}</td>
                    <td style="color:var(--text3);">{{ $dist->courier->name }}</td>
                    <td class="r bold">{{ $dist->qty }}</td>
                    <td class="r">{{ number_format($dist->price_per_unit) }}</td>
                    <td class="r bold">{{ number_format($distNilai) }}</td>
                    <td class="r">{{ number_format($dist->paid_amount) }}</td>
                    <td class="r bold" style="color:{{ $distSelisih>0?'#dc2626':'var(--melon-dark)' }};">
                        {{ $distSelisih>0?number_format($distSelisih):'✓' }}
                    </td>
                    <td>
                        @if($dist->payment_status==='paid')
                        <span class="badge badge-green">Lunas</span>
                        @elseif($dist->payment_status==='deferred')
                        <span class="badge badge-orange">Tunda</span>
                        @else
                        <span class="badge badge-blue">Sebagian</span>
                        @endif
                    </td>
                    @if($period->status === 'open')
                    <td>
                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                            <a href="{{ route('distributions.edit', $dist) }}" class="link-btn-sm">Edit</a>
                            @if(in_array($dist->payment_status,['deferred','partial']))
                            <button onclick="document.getElementById('pay-{{ $dist->id }}').classList.toggle('hidden')"
                                    class="link-btn-sm" style="color:var(--melon-dark);">Bayar</button>
                            @endif
                            <form method="POST" action="{{ route('distributions.destroy', $dist) }}"
                                  style="display:inline;" onsubmit="return confirm('Hapus?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-btn-sm" style="color:#dc2626;">Hapus</button>
                            </form>
                        </div>
                        @if(in_array($dist->payment_status,['deferred','partial']))
                        <div id="pay-{{ $dist->id }}" class="hidden">
                            <form method="POST" action="{{ route('distributions.payment', $dist) }}" class="pay-form">
                                @csrf
                                <input type="number" name="paid_amount" placeholder="Nominal"
                                       min="1" max="{{ $dist->remainingAmount() }}" class="pay-input">
                                <button type="submit" class="pay-btn">OK</button>
                            </form>
                        </div>
                        @endif
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="text-align:center;padding:24px;color:var(--text3);">Belum ada distribusi.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
(function(){
    /* ── shared style tokens ── */
    const GREEN  = getComputedStyle(document.documentElement).getPropertyValue('--melon').trim()        || '#43A047';
    const GDARK  = getComputedStyle(document.documentElement).getPropertyValue('--melon-dark').trim()   || '#2E7D32';
    const GDEEP  = getComputedStyle(document.documentElement).getPropertyValue('--melon-deep').trim()   || '#1B5E20';
    const RED    = '#dc2626';
    const BLUE   = '#2563eb';
    const GRAY   = 'rgba(0,0,0,0.05)';
    const TICK   = { font: { size: 10 }, color: '#9CA3AF' };
    const fmtRp  = v => 'Rp ' + Math.round(Math.abs(v) / 1000).toLocaleString('id') + 'k';

    /* ════════════════════════════════
       1. BAR CHART — distribusi harian
    ════════════════════════════════ */
    const el = document.getElementById('distChartData');
    if (el) {
        const labels = JSON.parse(el.dataset.labels);
        const qty    = JSON.parse(el.dataset.qty);
        const val    = JSON.parse(el.dataset.val);
        const paid   = JSON.parse(el.dataset.paid);

        if (labels.length) {
            const mean = qty.reduce((a,b)=>a+b,0) / qty.length;
            const std  = Math.sqrt(qty.map(x=>(x-mean)**2).reduce((a,b)=>a+b,0) / qty.length);

            const bgColors = qty.map(q =>
                std > 0 && q > mean + 1.5*std ? GDEEP+'cc' :
                std > 0 && q < mean - 1.5*std ? RED+'cc' :
                BLUE+'cc'
            );
            const piutangK = val.map((v,i) => Math.round((v - paid[i]) / 1000));

            new Chart(document.getElementById('distChartMain'), {
                type: 'bar',
                data: {
                    labels: labels.map(l => 'Tgl '+l),
                    datasets: [
                        { label:'Tabung',          data:qty,       backgroundColor:bgColors, borderRadius:4, order:2 },
                        { label:'Piutang (rb Rp)', data:piutangK,  type:'line', borderColor:RED,
                          backgroundColor:'transparent', pointBackgroundColor:piutangK.map(p=>p>0?RED:GREEN),
                          pointRadius:piutangK.map(p=>p>0?4:3), tension:0.35, yAxisID:'y2', order:1, borderWidth:1.5 }
                    ]
                },
                options: {
                    responsive:true, maintainAspectRatio:false,
                    plugins: { legend:{display:false}, tooltip:{ callbacks:{ label(ctx){
                        if(ctx.datasetIndex===0){ const z=std>0?((ctx.parsed.y-mean)/std).toFixed(1):'0.0'; return `Tabung: ${ctx.parsed.y}  (z=${z})`; }
                        return `Piutang: Rp ${(ctx.parsed.y*1000).toLocaleString('id-ID')}`;
                    }}}},
                    scales:{
                        x:{ ticks:{...TICK,autoSkip:true,maxTicksLimit:16,maxRotation:0}, grid:{display:false}},
                        y:{ beginAtZero:true, ticks:TICK, title:{display:true,text:'Tabung',font:{size:10},color:'#888'}},
                        y2:{ position:'right', beginAtZero:true, ticks:TICK,
                             title:{display:true,text:'Piutang (rb Rp)',font:{size:10},color:RED},
                             grid:{drawOnChartArea:false}}
                    }
                }
            });

            /* anomali text */
            const anomalies = [];
            qty.forEach((q,i)=>{
                const z = std>0?(q-mean)/std:0;
                if(z>1.5)  anomalies.push({type:'high',tgl:labels[i],msg:`Distribusi <strong>tinggi</strong> (${q} tab, z=${z.toFixed(1)})`});
                if(z<-1.5) anomalies.push({type:'low', tgl:labels[i],msg:`Distribusi <strong>rendah</strong> (${q} tab, z=${z.toFixed(1)})`});
            });
            val.forEach((v,i)=>{
                const pct=v>0?Math.round((v-paid[i])/v*100):0;
                if(pct>50) anomalies.push({type:'debt',tgl:labels[i],msg:`Piutang besar — <strong>${pct}%</strong> belum terbayar`});
            });

            const box = document.getElementById('anomalyResult');
            if(box){
                if(!anomalies.length){
                    box.innerHTML=`<div class="pill pill-green"><span class="pill-icon">✓</span><span>Distribusi normal — tidak ada anomali signifikan bulan ini.</span></div>`;
                } else {
                    const bgMap  ={high:'var(--melon-50)',low:'#fef2f2',debt:'#fff7ed'};
                    const clsMap ={high:'pill-green',low:'pill-red',debt:'pill-orange'};
                    box.innerHTML=`<div style="font-size:10px;font-weight:600;color:var(--text3);margin-bottom:6px;">⚠ Perlu perhatian:</div><div class="pill-box">`
                        +anomalies.map(a=>`<div class="pill ${clsMap[a.type]}"><span class="pill-icon">!</span><span>Tgl ${a.tgl}: ${a.msg}</span></div>`).join('')
                        +`</div>`;
                }
            }
        }
    }

    /* ════════════════════════════════
       2. CHART NILAI HARIAN
    ════════════════════════════════ */
    const hariLabels  = @json($hariLabels);
    const hariNilai   = @json($hariNilai);
    const hariKas     = @json($hariKas);
    const hariPiutang = @json($hariPiutang);
    const hariHarga   = @json($hariHarga);
    const custNames   = @json($custBarNames->pluck('name'));
    const custQty     = @json($custBarNames->pluck('qty'));
    const custTypes   = @json($custBarNames->pluck('type'));

    if(document.getElementById('cHarianBlade') && hariLabels.length){
        new Chart(document.getElementById('cHarianBlade'),{
            data:{ labels:hariLabels.map(d=>''+d), datasets:[
                {type:'bar',  label:'Nilai tagihan',data:hariNilai.map(v=>v/1000),   backgroundColor:'#bfdbfe99', borderRadius:3, order:2},
                {type:'line', label:'Kas diterima', data:hariKas.map(v=>v/1000),     borderColor:'#059669',  borderWidth:2, pointRadius:2.5, tension:0.3, fill:false, backgroundColor:'transparent', order:1},
                {type:'line', label:'Piutang',      data:hariPiutang.map(v=>v/1000), borderColor:RED,        borderWidth:1.5, borderDash:[4,3], pointRadius:2, tension:0.3, fill:false, backgroundColor:'transparent', order:1},
            ]},
            options:{ responsive:true, maintainAspectRatio:false,
                plugins:{legend:{display:false}, tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${fmtRp(ctx.parsed.y*1000)}`}}},
                scales:{
                    x:{grid:{color:GRAY},ticks:TICK},
                    y:{grid:{color:GRAY},ticks:{...TICK,callback:v=>v+'k'},title:{display:true,text:'Ribuan Rp',color:'#9CA3AF',font:{size:10}}}
                }
            }
        });
    }

    /* ════════════════════════════════
       3. DONUT STATUS
    ════════════════════════════════ */
    if(document.getElementById('cDonutBlade')){
        new Chart(document.getElementById('cDonutBlade'),{
            type:'doughnut',
            data:{ labels:['Lunas','Sebagian','Belum bayar'],
                datasets:[{ data:[{{ $jmlLunas }},{{ $jmlSebagian }},{{ $jmlBelum }}],
                    backgroundColor:[GREEN,'#3b82f6',RED], borderWidth:1, borderColor:'#fff'}]},
            options:{ responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{legend:{display:false}}}
        });
    }

    /* ════════════════════════════════
       4. BAR CUSTOMER
    ════════════════════════════════ */
    if(document.getElementById('cCustBlade') && custNames.length){
        new Chart(document.getElementById('cCustBlade'),{
            type:'bar',
            data:{ labels:custNames.map(n=>n.split(' ').slice(0,2).join(' ')),
                datasets:[{ label:'Tabung', data:custQty,
                    backgroundColor:custTypes.map(t=>t==='contract'?'#d9770699':''+BLUE+'99'), borderRadius:3}]},
            options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y',
                plugins:{legend:{display:false}},
                scales:{x:{grid:{color:GRAY},ticks:TICK},y:{grid:{display:false},ticks:{...TICK,font:{size:9}}}}}
        });
    }

    /* ════════════════════════════════
       5. AVG HARGA PER HARI
    ════════════════════════════════ */
    if(document.getElementById('cHargaBlade') && hariLabels.length){
        new Chart(document.getElementById('cHargaBlade'),{
            type:'line',
            data:{ labels:hariLabels.map(d=>''+d),
                datasets:[{ label:'Avg harga/tab', data:hariHarga,
                    borderColor:'#7c3aed', borderWidth:2, pointRadius:2, tension:0.3,
                    fill:true, backgroundColor:'#7c3aed11'}]},
            options:{ responsive:true, maintainAspectRatio:false,
                plugins:{legend:{display:false}, tooltip:{callbacks:{label:ctx=>`Rp ${Math.round(ctx.parsed.y).toLocaleString('id')}/tab`}}},
                scales:{
                    x:{grid:{display:false},ticks:TICK},
                    y:{grid:{color:GRAY},ticks:{...TICK,callback:v=>'Rp '+Math.round(v/1000)+'k'}}
                }
            }
        });
    }

    /* ════════════════════════════════
   PROYEKSI — chart kumulatif
════════════════════════════════ */
const pelD = document.getElementById('projChartData');
if (pelD) {
    const pLabels = JSON.parse(pelD.dataset.labels);
    const pAktual = JSON.parse(pelD.dataset.aktual);
    const pTren   = JSON.parse(pelD.dataset.tren);
    const pMin    = JSON.parse(pelD.dataset.min);
    const pMaks   = JSON.parse(pelD.dataset.maks);
    const pToday  = parseInt(pelD.dataset.today);

    new Chart(document.getElementById('cProjBlade'), {
        data: {
            labels: pLabels,
            datasets: [
                { type:'bar',  label:'Aktual (kumulatif)', data: pAktual,
                  backgroundColor:'#bfdbfe', borderRadius:2, order:3 },
                { type:'line', label:'Proyeksi tren', data: pTren,
                  borderColor:'#1d4ed8', borderWidth:2, pointRadius:0,
                  tension:0.4, fill:false, order:1 },
                { type:'line', label:'Min', data: pMin,
                  borderColor:'#dc2626', borderWidth:1.5, borderDash:[4,3],
                  pointRadius:0, tension:0.4, fill:false, order:2 },
                { type:'line', label:'Maks', data: pMaks,
                  borderColor:'#059669', borderWidth:1.5, borderDash:[4,3],
                  pointRadius:0, tension:0.4, fill:false, order:2 },
            ]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            interaction:{ mode:'index', intersect:false },
            plugins:{
                legend:{ display:false },
                tooltip:{ callbacks:{ label: ctx => {
                    if (ctx.parsed.y === null) return null;
                    return `${ctx.dataset.label}: ${Math.round(ctx.parsed.y).toLocaleString('id-ID')} tab`;
                }}}
            },
            scales:{
                x:{ ticks:{ font:{size:10}, color:'#9CA3AF', autoSkip:true, maxTicksLimit:15 }, grid:{display:false}},
                y:{ beginAtZero:true, grid:{ color:'rgba(0,0,0,0.05)' },
                    ticks:{ font:{size:10}, color:'#9CA3AF',
                            callback: v => Number.isInteger(v) ? v.toLocaleString('id') : '' }}
            }
        }
    });
}
})();
</script>
@endpush