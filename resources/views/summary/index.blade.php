@extends('layouts.app')
@section('title', 'Ringkasan')
@section('content')

<style>
    /* ── LAYOUT & BASE ── */
    .sum-page { padding-bottom: 16px; }
    .page-top { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
    .page-title { font-size:15px; font-weight:600; color:var(--text1); }
    .period-select {
        font-size:12px; font-family:'Plus Jakarta Sans',sans-serif; font-weight:500;
        color:var(--text2); background:var(--surface); border:0.5px solid var(--border);
        border-radius:20px; padding:5px 10px; appearance:none; -webkit-appearance:none; cursor:pointer;
        max-width:160px; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237a9a7a'/%3E%3C/svg%3E");
        background-repeat:no-repeat; background-position:right 10px center; padding-right:26px;
    }
    .export-btn {
        display:flex; align-items:center; gap:5px; background:var(--melon); color:#fff;
        font-size:11px; font-weight:600; font-family:'Plus Jakarta Sans',sans-serif;
        border:none; border-radius:20px; padding:7px 14px; cursor:pointer;
        text-decoration:none; white-space:nowrap;
    }

    /* ── SECTION CARD ── */
    .s-card { background:var(--surface); border-radius:var(--radius-sm); border:0.5px solid var(--border); margin-bottom:10px; overflow:hidden; }
    .s-card-header {
        background:var(--melon-50); border-bottom:0.5px solid var(--border);
        padding:10px 14px; font-size:12px; font-weight:600; color:var(--text1);
        display:flex; align-items:center; justify-content:space-between; gap:6px; flex-wrap:wrap;
    }

    /* ── METRIC GRID ── */
    .metric-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px; }
    .metric-grid-4 { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
    @media (min-width:420px) { .metric-grid-4 { grid-template-columns:repeat(4,1fr); } }
    .metric-card { background:var(--surface); border-radius:var(--radius-sm); border:0.5px solid var(--border); padding:12px; border-top-width:3px; }
    .metric-card.c-orange { border-top-color:#f97316; }
    .metric-card.c-blue   { border-top-color:#3b82f6; }
    .metric-card.c-green  { border-top-color:var(--melon); }
    .metric-card.c-purple { border-top-color:#a855f7; }
    .metric-card.c-red    { border-top-color:#dc2626; }
    .metric-card.c-amber  { border-top-color:#d97706; }
    .metric-label { font-size:10px; color:var(--text3); font-weight:500; margin-bottom:3px; }
    .metric-value { font-size:18px; font-weight:600; color:var(--text1); line-height:1.15; }
    .metric-value.orange { color:#c2410c; }
    .metric-value.blue   { color:#1d4ed8; }
    .metric-value.green  { color:var(--melon-dark); }
    .metric-value.purple { color:#7e22ce; }
    .metric-value.red    { color:#b91c1c; }
    .metric-value.amber  { color:#b45309; }
    .metric-sub  { font-size:9px; color:var(--text3); margin-top:2px; }
    .metric-note { font-size:9px; color:#f97316; margin-top:4px; border-top:0.5px solid #fed7aa; padding-top:4px; }

    /* ── TABLES ── */
    .mob-table { width:100%; border-collapse:collapse; font-size:11px; }
    .mob-table th { background:#f8faf8; color:var(--text3); font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; padding:7px 10px; text-align:left; white-space:nowrap; }
    .mob-table th.r { text-align:right; }
    .mob-table td { padding:8px 10px; border-bottom:0.5px solid #eef5ee; color:var(--text1); white-space:nowrap; }
    .mob-table td.r { text-align:right; }
    .mob-table td.bold { font-weight:600; }
    .mob-table tr:last-child td { border-bottom:none; }
    .mob-table tr.total-row td { background:var(--melon); color:#fff; font-weight:600; font-size:11px; }
    .mob-table tr.total-row td.muted { color:rgba(255,255,255,0.75); font-size:10px; }
    .mob-table tr.highlight td { background:#fffbeb; }
    .scroll-x { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .scroll-y { max-height:240px; overflow-y:auto; -webkit-overflow-scrolling:touch; }

    /* ── CASHFLOW ROWS ── */
    .cf-row { display:flex; align-items:center; justify-content:space-between; padding:9px 14px; border-bottom:0.5px solid #eef5ee; gap:8px; }
    .cf-row:last-child { border-bottom:none; }
    .cf-label { font-size:11px; color:var(--text2); flex:1; }
    .cf-label.bold { font-weight:600; color:var(--text1); }
    .cf-label.indent { padding-left:12px; color:var(--text3); font-size:10px; }
    .cf-amount { font-size:12px; font-weight:600; white-space:nowrap; }
    .cf-amount.green  { color:var(--melon-dark); }
    .cf-amount.red    { color:#b91c1c; }
    .cf-amount.purple { color:#7e22ce; }
    .cf-amount.blue   { color:#1d4ed8; }
    .cf-amount.gray   { color:var(--text3); font-size:10px; font-weight:400; }
    .cf-row.bg-green  { background:#f0fdf4; }
    .cf-row.bg-purple { background:#faf5ff; }
    .cf-row.bg-blue   { background:#eff6ff; }
    .cf-row.bg-yellow { background:#fffbeb; }
    .cf-row.net { background:var(--melon-light); border-top:1.5px solid var(--melon-mid); margin-top:2px; }
    .cf-net-amount { font-size:18px; font-weight:600; }

    /* ── INDIKATOR ── */
    .ind-row { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
    .ind-row:last-child { margin-bottom:0; }
    .ind-meta { flex:1; }
    .ind-title { font-size:11px; color:var(--text2); }
    .ind-track { width:100%; height:5px; background:var(--melon-light); border-radius:3px; margin-top:4px; overflow:hidden; }
    .ind-fill { height:100%; border-radius:3px; }
    .ind-right { text-align:right; flex-shrink:0; }
    .ind-val  { font-size:13px; font-weight:600; }
    .ind-note { font-size:9px; color:var(--text3); }

    /* ── PILL / REKOMENDASI ── */
    .pill-box { display:flex; flex-direction:column; gap:6px; }
    .pill { display:flex; align-items:flex-start; gap:8px; padding:8px 10px; border-radius:8px; font-size:11px; }
    .pill-icon { font-size:13px; flex-shrink:0; margin-top:1px; }
    .pill-green  { background:var(--melon-50); color:var(--melon-deep); border:0.5px solid var(--border); }
    .pill-red    { background:#fef2f2; color:#991b1b; border:0.5px solid #fca5a5; }
    .pill-orange { background:#fff7ed; color:#9a3412; border:0.5px solid #fed7aa; }
    .pill-blue   { background:#eff6ff; color:#1e40af; border:0.5px solid #bfdbfe; }

    /* ── FLOW STRIP ── */
    .tf-flow-strip { display:flex; align-items:center; border:0.5px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; margin-bottom:14px; }
    .tf-flow-node { flex:1; padding:10px 8px; text-align:center; }
    .tf-flow-sep { font-size:15px; color:var(--text3); flex-shrink:0; padding:0 2px; }
    .tf-flow-label { font-size:10px; font-weight:600; margin-bottom:3px; }
    .tf-flow-val { font-size:12px; font-weight:600; }

    /* ── PENAMPUNG ── */
    .penampung-grid { display:grid; grid-template-columns:1fr 1fr; gap:1px; background:var(--border); }
    .penampung-cell { background:var(--surface); padding:12px; text-align:center; }
    .penampung-label { font-size:10px; color:var(--text3); margin-bottom:3px; }
    .penampung-value { font-size:14px; font-weight:600; color:var(--text1); }
    .penampung-value.green  { color:var(--melon-dark); }
    .penampung-value.red    { color:#b91c1c; }
    .penampung-value.orange { color:#c2410c; }

    /* ── BADGE ── */
    .badge { display:inline-flex; align-items:center; gap:3px; border-radius:6px; padding:2px 7px; font-size:9px; font-weight:600; }
    .badge-green  { background:#dcfce7; color:#166534; }
    .badge-red    { background:#fee2e2; color:#991b1b; }
    .badge-orange { background:#fff7ed; color:#c2410c; border:0.5px solid #fed7aa; }
    .badge-blue   { background:#eff6ff; color:#1e40af; border:0.5px solid #bfdbfe; }

    /* ── DEBT / CONTRACT ── */
    .debt-section { padding:12px 14px; border-bottom:0.5px solid #eef5ee; }
    .debt-section:last-child { border-bottom:none; }
    .debt-section-title { font-size:10px; font-weight:600; color:var(--text3); margin-bottom:6px; text-transform:uppercase; letter-spacing:0.3px; }
    .debt-row { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; padding:5px 0; border-bottom:0.5px solid #eef5ee; font-size:11px; }
    .debt-row:last-child { border-bottom:none; }
    .debt-row-label { color:var(--text2); flex:1; line-height:1.4; }
    .debt-row-amount { font-weight:600; white-space:nowrap; }
    .debt-row-amount.red    { color:#b91c1c; }
    .debt-row-amount.orange { color:#c2410c; }
    .debt-row-amount.indigo { color:#4338ca; }
    .debt-total-row { display:flex; justify-content:space-between; padding:6px 0 0; font-size:11px; font-weight:600; }

    /* ── LINK BUTTONS ── */
    .link-btn    { background:none; border:none; font-size:10px; font-family:'Plus Jakarta Sans',sans-serif; color:#2563eb; cursor:pointer; padding:0; text-decoration:underline; }
    .link-btn-sm { font-size:10px; color:#2563eb; text-decoration:underline; cursor:pointer; font-weight:500; }

    /* ── SECTION DIVIDER ── */
    .sec-divider { display:flex; align-items:center; gap:8px; margin:18px 0 10px; }
    .sec-divider-title { font-size:11px; font-weight:600; color:var(--text3); white-space:nowrap; text-transform:uppercase; letter-spacing:0.5px; }
    .sec-divider-line { flex:1; height:0.5px; background:var(--border); }

    /* ── PROGRESS BAR ── */
    .prog-track { width:100%; height:5px; background:var(--melon-light); border-radius:3px; overflow:hidden; margin-top:4px; }
    .prog-fill  { height:100%; border-radius:3px; }

    /* ── CONTRACT ROWS ── */
    .contract-row { padding:7px 0; border-bottom:0.5px solid #eef5ee; }
    .contract-row:last-child { border-bottom:none; }
    .contract-row-top { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:4px; }
    .contract-name { font-size:11px; font-weight:600; color:var(--text1); }
    .contract-type { font-size:9px; color:var(--text3); }
    .contract-form { background:var(--melon-50); border:0.5px solid var(--border); border-radius:8px; padding:8px; margin-top:5px; display:none; }
    .contract-form.open { display:block; }
    .form-row { display:flex; gap:6px; align-items:flex-end; flex-wrap:wrap; }
    .form-field { display:flex; flex-direction:column; gap:2px; }
    .form-field label { font-size:9px; color:var(--text3); font-weight:500; }
    .form-field input { font-size:11px; font-family:'Plus Jakarta Sans',sans-serif; border:0.5px solid var(--border); border-radius:6px; padding:5px 8px; background:var(--surface); color:var(--text1); width:100%; }
    .form-field input[type="number"] { width:110px; }
    .form-field input[type="date"]   { width:120px; }
    .form-submit { font-size:11px; font-weight:600; font-family:'Plus Jakarta Sans',sans-serif; background:var(--melon); color:#fff; border:none; border-radius:6px; padding:6px 14px; cursor:pointer; }
</style>

@php
/* ════════════════════════════════════════════════════
   DATA AGGREGATION — kompilasi semua modul
════════════════════════════════════════════════════ */

/* ── Stok ── */
$totalDoQty       = $totalDoQty ?? 0;
$totalCarryoverQty= $totalCarryoverQty ?? 0;
$totalDistQty     = $totalDistQty ?? 0;
$stockSisa        = $period->opening_stock + $totalDoQty - $totalDistQty;

/* ── Penjualan & Margin ── */
$totalSales       = $totalSales ?? 0;
$totalModal       = $totalDistQty * 16000;
$grossMargin      = $totalSales - $totalModal;

/* ── Cashflow Harian ── */
$totalIncome      = $totalIncome ?? 0;
$totalExpense     = $totalExpense ?? 0;
$totalDepositsKas = $totalDeposits ?? 0;
$totalAdminFees   = $totalAdminFees ?? 0;
$openingCash      = $period->opening_cash ?? 0;
$netKas           = $openingCash + $totalIncome - $totalExpense - $totalDepositsKas - $totalAdminFees;
$rasioOperasional = $totalIncome > 0 ? round($totalExpense / $totalIncome * 100, 1) : 0;

/* ── Transfer & Setoran ── */
$totalDeposited   = $totalDeposited ?? 0;
$totalTransferred = $totalTransferred ?? 0;
$totalAdmin       = $totalAdminFees ?? 0;
$openingPenampung = $period->opening_penampung ?? 0;
$penampungNow     = $openingPenampung + $totalDeposited - $totalTransferred - $totalAdmin;
$utilisasi        = $totalDeposited > 0 ? round($totalTransferred / $totalDeposited * 100) : 0;

/* ── DO Agen ── */
$grandDoValue     = $piutangDOTotal + $grandBayarDO ?? 0;    // total nilai DO baru
$grandBayarDO     = $grandBayarDO ?? 0;   // total terbayar ke agen
$piutangDOTotal   = $piutangDOTotal ?? 0; // total piutang ke agen (baru + carry-over)
$rasioLunasDO     = $grandDoValue > 0 ? round($grandBayarDO / $grandDoValue * 100, 1) : 0;

/* ── Distribusi Harian ── */
$allQtyDist       = $allQtyDist ?? $totalDistQty;
$totalTagihanDist = $totalTagihanDist ?? $totalSales;
$totalKasDiterima = $totalKasDiterima ?? $totalIncome;
$piutangDist      = $piutangDist ?? ($totalTagihanDist - $totalKasDiterima);
$rasioLunasDist   = $totalTagihanDist > 0 ? round($totalKasDiterima / $totalTagihanDist * 100, 1) : 0;

/* ── Distribusi Kontrak ── */
$contractSaldoBersih = $contractSaldoBersih ?? 0; // total saldo ke customer kontrak
$contractPiutang     = $contractPiutang ?? 0;     // piutang dari customer kontrak

/* ── Total piutang semua ── */
$totalPiutangAll   = $piutangDOTotal + $piutangDist + ($contractPiutang ?? 0);
$totalSurplus      = $totalSurplus ?? 0;
$totalSurplusAll   = $totalSurplusAll ?? $totalSurplus;

/* ── Gaji Kurir ── */
$gajiKurirTotal    = array_sum(array_column($courierWages ?? [], 'wage'));

/* ── Indikator ── */
$rasioAdmin        = $totalDeposited > 0 ? round($totalAdmin / $totalDeposited * 100, 2) : 0;
$utilisasiBar      = $totalDeposited > 0 ? min(round(($totalTransferred + $totalAdmin) / ($openingPenampung + $totalDeposited) * 100), 100) : 0;
$piutangPct        = ($piutangDOTotal + $totalTransferred) > 0 ? round($piutangDOTotal / ($piutangDOTotal + $totalTransferred) * 100) : 0;

/* ── Proyeksi ── */
$pred              = $pred ?? [];
$predKas           = $pred['predKas'] ?? null;
$remainDays        = $pred['remainDays'] ?? 0;
@endphp

<div class="sum-page">

{{-- ══ PAGE HEADER ══ --}}
<div class="page-top">
    <div class="page-title">📊 Ringkasan Lengkap</div>
    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
        <form method="GET" action="{{ route('summary.index') }}">
            <select name="period_id" onchange="this.form.submit()" class="period-select">
                @foreach ($periods as $p)
                    <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                @endforeach
            </select>
        </form>
        @if($period->status === 'open')
            <span class="badge badge-green">🟢 Buka</span>
        @else
            <span class="badge" style="background:#f0f0f0;color:#666">🔒 Tutup</span>
        @endif
        <a href="{{ route('export', ['period_id' => $period->id]) }}" class="export-btn">⬇ Export</a>
    </div>
</div>

{{-- ══ KPI UTAMA 4 KOLOM ══ --}}
<div class="metric-grid-4" style="margin-bottom:14px">
    <div class="metric-card c-orange">
        <div class="metric-label">DO Masuk</div>
        <div class="metric-value orange">{{ number_format($totalDoQty) }}</div>
        <div class="metric-sub">tabung dari agen</div>
        @if($totalCarryoverQty > 0)
            <div class="metric-note">+{{ number_format($totalCarryoverQty) }} carry-over</div>
        @endif
    </div>
    <div class="metric-card c-blue">
        <div class="metric-label">Distribusi</div>
        <div class="metric-value blue">{{ number_format($totalDistQty) }}</div>
        <div class="metric-sub">tabung ke customer</div>
    </div>
    <div class="metric-card c-green">
        <div class="metric-label">Stok Sisa</div>
        <div class="metric-value {{ $stockSisa >= 0 ? 'green' : 'red' }}">{{ number_format($stockSisa) }}</div>
        <div class="metric-sub">{{ number_format($period->opening_stock) }}+{{ number_format($totalDoQty) }}-{{ number_format($totalDistQty) }}</div>
    </div>
    <div class="metric-card c-purple">
        <div class="metric-label">Gross Margin</div>
        <div class="metric-value {{ $grossMargin >= 0 ? 'purple' : 'red' }}">Rp {{ number_format($grossMargin) }}</div>
        <div class="metric-sub">Penjualan - Modal</div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════
     SEKSI 1 — CASHFLOW HARIAN (KOMPREHENSIF)
══════════════════════════════════════════════════ --}}
<div class="sec-divider">
    <span class="sec-divider-title">💸 Cashflow Harian</span>
    <span class="sec-divider-line"></span>
    <a href="{{ route('cashflow.index', ['period_id' => $period->id]) }}" style="font-size:10px;color:var(--melon-dark);text-decoration:none;white-space:nowrap">Detail →</a>
</div>

<div class="metric-grid" style="margin-bottom:10px">
    <div class="metric-card c-green">
        <div class="metric-label">Kas Diterima</div>
        <div class="metric-value green" style="font-size:15px">Rp {{ number_format($totalIncome) }}</div>
        <div class="metric-sub">penjualan gas riil</div>
    </div>
    <div class="metric-card c-red">
        <div class="metric-label">Pengeluaran Ops</div>
        <div class="metric-value red" style="font-size:15px">Rp {{ number_format($totalExpense) }}</div>
        <div class="metric-sub">rasio {{ $rasioOperasional }}% dari kas masuk</div>
    </div>
    <div class="metric-card c-blue">
        <div class="metric-label">Saldo KAS (Fisik)</div>
        <div class="metric-value {{ $netKas >= 0 ? 'amber' : 'red' }}" style="font-size:15px">Rp {{ number_format($netKas) }}</div>
        <div class="metric-sub">uang di tangan saat ini</div>

    </div>
    <div class="metric-card c-amber">
        <div class="metric-label">Prediksi Saldo Akhir</div>
        @if($predKas !== null)
        <div class="metric-value {{ $predKas >= 0 ? 'green' : 'red' }}" style="font-size:15px">Rp {{ number_format(round($predKas)) }}</div>
        <div class="metric-sub">{{ $remainDays }} hari lagi · keyakinan {{ $pred['confidence'] ?? 0 }}%</div>
        @else
        <div class="metric-value green" style="font-size:15px">✓ Akhir Bulan</div>
        <div class="metric-sub">realisasi sudah penuh</div>
        @endif
    </div>
</div>

<div class="s-card">
    <div class="s-card-header">Rekapitulasi Cashflow Periode Ini</div>
    <div class="cf-row bg-green">
        <div class="cf-label bold">Saldo Awal KAS</div>
        <div class="cf-amount green">Rp {{ number_format($openingCash) }}</div>
    </div>
    <div class="cf-row bg-green">
        <div class="cf-label bold">Total Kas Diterima (Penjualan)</div>
        <div class="cf-amount green">+ Rp {{ number_format($totalIncome) }}</div>
    </div>
    <div class="cf-row">
        <div class="cf-label bold">Total Pengeluaran Operasional</div>
        <div class="cf-amount red">− Rp {{ number_format($totalExpense) }}</div>
    </div>
    @foreach(($expensesByCategory ?? []) as $cat => $val)
    @if($val > 0)
    <div class="cf-row" style="background:#fafafa">
        <div class="cf-label indent">{{ \App\Models\DailyExpense::$categoryLabels[$cat] ?? $cat }}</div>
        <div class="cf-amount gray">Rp {{ number_format($val) }}</div>
    </div>
    @endif
    @endforeach
    <div class="cf-row bg-blue">
        <div class="cf-label bold">TF ke Rekening Penampung</div>
        <div class="cf-amount blue">− Rp {{ number_format($totalDepositsKas) }}</div>
    </div>
    <div class="cf-row" style="background:#fafafa">
        <div class="cf-label indent">└ Termasuk Admin TF</div>
        <div class="cf-amount gray">Rp {{ number_format($totalAdminFees) }}</div>
    </div>
    <div class="cf-row net">
        <div class="cf-label bold" style="font-size:13px">Saldo KAS Saat Ini</div>
        <div class="cf-net-amount {{ $netKas >= 0 ? 'green' : 'red' }}" style="color:{{ $netKas >= 0 ? 'var(--melon-dark)' : '#b91c1c' }}">
            Rp {{ number_format($netKas) }}
        </div>
    </div>
    @if($gajiKurirTotal > 0)
    <div class="cf-row bg-yellow">
        <div>
            <div class="cf-label bold" style="color:#92400e">Kewajiban Gaji Kurir (belum dibayar)</div>
            <div style="font-size:10px;color:#b45309;margin-top:2px">Dibayar tgl 1 bulan depan</div>
        </div>
        <div class="cf-amount" style="color:#92400e">Rp {{ number_format($gajiKurirTotal) }}</div>
    </div>
    @endif
</div>

{{-- Gross Margin & Biaya --}}
<div class="s-card">
    <div class="s-card-header">Rekapitulasi Margin Bulan Ini</div>
    <div class="cf-row bg-green">
        <div class="cf-label bold">Total Penjualan Bruto</div>
        <div class="cf-amount green">Rp {{ number_format($totalSales) }}</div>
    </div>
    <div class="cf-row">
        <div class="cf-label">Modal Gas ({{ number_format($totalDistQty) }} tab × Rp 16.000)</div>
        <div class="cf-amount red">− Rp {{ number_format($totalModal) }}</div>
    </div>
    <div class="cf-row bg-purple">
        <div class="cf-label bold">Gross Margin</div>
        <div class="cf-amount purple">Rp {{ number_format($grossMargin) }}</div>
    </div>
    <div class="cf-row">
        <div class="cf-label bold">Pengeluaran Operasional</div>
        <div class="cf-amount red">− Rp {{ number_format($totalExpense) }}</div>
    </div>
    @if($gajiKurirTotal > 0)
    <div class="cf-row">
        <div class="cf-label">Gaji Kurir (estimasi)</div>
        <div class="cf-amount red">− Rp {{ number_format($gajiKurirTotal) }}</div>
    </div>
    @endif
    <div class="cf-row net">
        @php $netMargin = $grossMargin - $totalExpense - $gajiKurirTotal; @endphp
        <div class="cf-label bold" style="font-size:13px">Net Margin Operasional</div>
        <div class="cf-net-amount {{ $netMargin >= 0 ? 'green' : 'red' }}" style="color:{{ $netMargin >= 0 ? 'var(--melon-dark)' : '#b91c1c' }}">
            Rp {{ number_format($netMargin) }}
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════
     SEKSI 2 — TRANSFER & SETORAN
══════════════════════════════════════════════════ --}}
<div class="sec-divider">
    <span class="sec-divider-title">🏦 Transfer &amp; Setoran</span>
    <span class="sec-divider-line"></span>
    <a href="{{ route('transfer.index', ['period_id' => $period->id]) }}" style="font-size:10px;color:var(--melon-dark);text-decoration:none;white-space:nowrap">Detail →</a>
</div>

{{-- Flow Strip --}}
<div class="tf-flow-strip">
    <div class="tf-flow-node" style="background:#eff6ff">
        <div class="tf-flow-label" style="color:#1e40af">Awal</div>
        <div class="tf-flow-val" style="color:#1d4ed8">{{ number_format(round($openingPenampung/1000)) }}k</div>
    </div>
    <div class="tf-flow-sep">+</div>
    <div class="tf-flow-node" style="background:var(--melon-50)">
        <div class="tf-flow-label" style="color:var(--melon-deep)">Setoran</div>
        <div class="tf-flow-val" style="color:var(--melon-dark)">{{ number_format(round($totalDeposited/1000)) }}k</div>
    </div>
    <div class="tf-flow-sep">−</div>
    <div class="tf-flow-node" style="background:#fef2f2">
        <div class="tf-flow-label" style="color:#991b1b">Transfer</div>
        <div class="tf-flow-val" style="color:#dc2626">{{ number_format(round($totalTransferred/1000)) }}k</div>
    </div>
    <div class="tf-flow-sep">−</div>
    <div class="tf-flow-node" style="background:#fffbeb">
        <div class="tf-flow-label" style="color:#92400e">Admin</div>
        <div class="tf-flow-val" style="color:#b45309">{{ number_format(round($totalAdmin/1000)) }}k</div>
    </div>
    <div class="tf-flow-sep">=</div>
    <div class="tf-flow-node" style="background:#eff6ff;border-left:2px solid #1d4ed8">
        <div class="tf-flow-label" style="color:#1e40af">Saldo</div>
        <div class="tf-flow-val" style="color:{{ $penampungNow >= 0 ? '#1d4ed8' : '#dc2626' }}">{{ number_format(round($penampungNow/1000)) }}k</div>
    </div>
</div>

<div class="metric-grid" style="margin-bottom:10px">
    <div class="metric-card c-green">
        <div class="metric-label">Total Setoran Kurir</div>
        <div class="metric-value green" style="font-size:15px">Rp {{ number_format($totalDeposited) }}</div>
        <div class="metric-sub">masuk ke rek penampung</div>
    </div>
    <div class="metric-card c-red">
        <div class="metric-label">TF ke Rek Utama</div>
        <div class="metric-value red" style="font-size:15px">Rp {{ number_format($totalTransferred) }}</div>
        <div class="metric-sub">utilisasi {{ $utilisasi }}% dari setoran</div>
    </div>
    <div class="metric-card c-blue">
        <div class="metric-label">Saldo Penampung</div>
        <div class="metric-value {{ $penampungNow >= 0 ? 'orange' : 'red' }}" style="font-size:15px">Rp {{ number_format($penampungNow) }}</div>
        <div class="metric-sub">saldo rekening saat ini</div>
    </div>
    <div class="metric-card c-amber">
        <div class="metric-label">Total Admin TF</div>
        <div class="metric-value amber" style="font-size:15px">{{ $totalAdmin > 0 ? 'Rp '.number_format($totalAdmin) : '—' }}</div>
        <div class="metric-sub">rasio {{ $rasioAdmin }}% dari setoran</div>
    </div>
</div>

{{-- Indikator Transfer --}}
<div class="s-card">
    <div class="s-card-header">Indikator Kesehatan Rekening Penampung</div>
    <div style="padding:12px 14px">
        @foreach([
            ['Utilisasi penampung', $utilisasi.'%', 'tersalurkan ke rek utama · ideal >80%', $utilisasi, $utilisasi>=80?'var(--melon)':'#f59e0b'],
            ['Rasio admin TF', $rasioAdmin.'%', 'dari total setoran · ideal <0.5%', min($rasioAdmin*20,100), $rasioAdmin<0.5?'var(--melon)':'#f59e0b'],
            ['Piutang DO tersisa', $piutangPct.'%', 'belum terlunasi dari DO', $piutangPct, $piutangPct<20?'var(--melon)':($piutangPct<50?'#f59e0b':'#dc2626')],
        ] as [$lbl,$val,$note,$bar,$col])
        <div class="ind-row">
            <div class="ind-meta">
                <div class="ind-title">{{ $lbl }}</div>
                <div class="ind-track"><div class="ind-fill" style="width:{{ $bar }}%;background:{{ $col }}"></div></div>
            </div>
            <div class="ind-right">
                <div class="ind-val" style="color:{{ $col }}">{{ $val }}</div>
                <div class="ind-note">{{ $note }}</div>
            </div>
        </div>
        @endforeach
        @if($totalSurplusAll > 0)
        <div style="background:#eff6ff;border-radius:6px;padding:8px 10px;font-size:11px;color:#1e40af;margin-top:4px">
            💰 Surplus tabungan tercatat: <strong>Rp {{ number_format($totalSurplusAll) }}</strong>
        </div>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════
     SEKSI 3 — DO AGEN
══════════════════════════════════════════════════ --}}
<div class="sec-divider">
    <span class="sec-divider-title">📦 DO Agen</span>
    <span class="sec-divider-line"></span>
    <a href="{{ route('do.index', ['period_id' => $period->id]) }}" style="font-size:10px;color:var(--melon-dark);text-decoration:none;white-space:nowrap">Detail →</a>
</div>

<div class="metric-grid" style="margin-bottom:10px">
    <div class="metric-card c-orange">
        <div class="metric-label">Total DO Diterima</div>
        <div class="metric-value orange" style="font-size:15px">{{ number_format($totalDoQty) }} tab</div>
        <div class="metric-sub">Rp {{ number_format($grandDoValue) }}</div>
    </div>
    <div class="metric-card c-green">
        <div class="metric-label">Terbayar ke Agen</div>
        <div class="metric-value green" style="font-size:15px">Rp {{ number_format($grandBayarDO) }}</div>
        <div class="metric-sub">{{ $rasioLunasDO }}% dari nilai DO</div>
    </div>
    <div class="metric-card c-red">
        <div class="metric-label">Piutang ke Agen</div>
        <div class="metric-value {{ $piutangDOTotal > 0 ? 'red' : 'green' }}" style="font-size:15px">
            {{ $piutangDOTotal > 0 ? 'Rp '.number_format($piutangDOTotal) : '✓ Lunas' }}
        </div>
        <div class="metric-sub">termasuk carry-over</div>
    </div>
    <div class="metric-card c-blue">
        <div class="metric-label">Carry-Over Qty</div>
        <div class="metric-value blue" style="font-size:15px">{{ number_format($totalCarryoverQty) }} tab</div>
        <div class="metric-sub">piutang bawaan bln lalu</div>
    </div>
</div>

{{-- DO per Pangkalan --}}
<div class="s-card">
    <div class="s-card-header">DO per Pangkalan (Baru &amp; Carry-Over)</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Pangkalan</th>
                    <th class="r">DO Baru</th>
                    <th class="r">Carry</th>
                    <th class="r">Nilai</th>
                    <th class="r">Terbayar</th>
                    <th class="r" style="color:#dc2626">Piutang</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outlets as $o)
                @php
                    $qty      = $doByOutlet[$o->id] ?? 0;
                    $qtyCarry = $doCarryoverByOutlet[$o->id] ?? 0;
                    $nilai = $qty * ($doAvgPrice[$o->id] ?? 16000) + $qtyCarry * ($doAvgPrice[$o->id] ?? 16000);

                @endphp
                <tr>
                    <td class="bold">{{ $o->name }}</td>
                    <td class="r bold" style="{{ $qty > 0 ? 'color:#c2410c' : 'color:#d1d5db' }}">{{ $qty > 0 ? number_format($qty) : '-' }}</td>
                    <td class="r" style="{{ $qtyCarry > 0 ? 'color:#f97316;font-size:10px' : 'color:#e5e7eb;font-size:10px' }}">{{ $qtyCarry > 0 ? number_format($qtyCarry) : '-' }}</td>
                    <td class="r" style="color:var(--text2)">{{ $qty > 0 ? 'Rp '.number_format($nilai) : '-' }}</td>
                    <td class="r" style="color:var(--melon-dark)">
                        @php $bayarOutlet = $doBayarByOutlet[$o->id] ?? 0; @endphp
                        {{ $bayarOutlet > 0 ? 'Rp '.number_format($bayarOutlet) : '-' }}
                    </td>
                    <td class="r bold" style="color:{{ ($nilai - $bayarOutlet) > 0 ? '#dc2626' : ($qty>0?'var(--melon-dark)':'var(--text3)') }}">
                        @php $piutangOutlet = max($nilai - $bayarOutlet, 0); @endphp
                        {{ $qty > 0 ? ($piutangOutlet > 0 ? 'Rp '.number_format($piutangOutlet) : '✓') : '-' }}
                    </td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="r">{{ number_format($totalDoQty) }}</td>
                    <td class="r muted">{{ $totalCarryoverQty > 0 ? number_format($totalCarryoverQty) : '-' }}</td>
                    <td class="r">Rp {{ number_format($grandDoValue) }}</td>
                    <td class="r">Rp {{ number_format($grandBayarDO) }}</td>
                    <td class="r">{{ $piutangDOTotal > 0 ? 'Rp '.number_format($piutangDOTotal) : '✓ Lunas' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @if($totalCarryoverQty > 0)
    <div style="padding:8px 14px;font-size:10px;color:#c2410c;background:#fff7ed;border-top:0.5px solid #fed7aa">
        ↩ {{ number_format($totalCarryoverQty) }} tab carry-over = piutang bawaan bln lalu, sudah termasuk stok awal ({{ number_format($period->opening_stock) }} tab)
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════
     SEKSI 4 — DISTRIBUSI HARIAN
══════════════════════════════════════════════════ --}}
<div class="sec-divider">
    <span class="sec-divider-title">🚚 Distribusi Harian</span>
    <span class="sec-divider-line"></span>
    <a href="{{ route('distributions.index', ['period_id' => $period->id]) }}" style="font-size:10px;color:var(--melon-dark);text-decoration:none;white-space:nowrap">Detail →</a>
</div>

<div class="metric-grid" style="margin-bottom:10px">
    <div class="metric-card c-orange">
        <div class="metric-label">Total Distribusi</div>
        <div class="metric-value orange" style="font-size:15px">{{ number_format($allQtyDist) }} tab</div>
        <div class="metric-sub">ke semua customer</div>
    </div>
    <div class="metric-card c-blue">
        <div class="metric-label">Total Tagihan</div>
        <div class="metric-value blue" style="font-size:15px">Rp {{ number_format($totalTagihanDist) }}</div>
        <div class="metric-sub">dari distribusi bulan ini</div>
    </div>
    <div class="metric-card c-green">
        <div class="metric-label">Kas Diterima</div>
        <div class="metric-value green" style="font-size:15px">Rp {{ number_format($totalKasDiterima) }}</div>
        <div class="metric-sub">{{ $rasioLunasDist }}% dari tagihan</div>
    </div>
    <div class="metric-card c-red">
        <div class="metric-label">Piutang Customer</div>
        <div class="metric-value {{ $piutangDist > 0 ? 'red' : 'green' }}" style="font-size:15px">
            {{ $piutangDist > 0 ? 'Rp '.number_format($piutangDist) : '✓ Lunas' }}
        </div>
        <div class="metric-sub">belum terbayar customer</div>
    </div>
</div>

{{-- Penjualan per Customer --}}
<div class="s-card">
    <div class="s-card-header">
        <span>Penjualan per Customer</span>
        <span style="font-size:10px;font-weight:400;color:var(--text3)">★ = Kontrak</span>
    </div>
    <div class="scroll-x scroll-y">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th class="r">Tabung</th>
                    <th class="r">Nilai</th>
                    <th class="r">Terbayar</th>
                    <th class="r">Piutang</th>
                    <th>Progres</th>
                </tr>
            </thead>
            <tbody>
                @foreach($salesByCustomer->sortByDesc('total_qty') as $s)
                @php
                    $piutangC = $s->total_value - $s->total_paid;
                    $pctC = $s->total_value > 0 ? round($s->total_paid / $s->total_value * 100) : 100;
                    $barC = $pctC >= 80 ? 'var(--melon)' : ($pctC >= 50 ? '#f59e0b' : '#dc2626');
                @endphp
                <tr class="{{ $s->customer?->type === 'contract' ? 'highlight' : '' }}">
                    <td class="bold">
                        {{ $s->customer?->name ?? '-' }}
                        @if($s->customer?->type === 'contract') <span style="color:#d97706;font-size:10px"> ★</span> @endif
                    </td>
                    <td class="r bold">{{ number_format($s->total_qty) }}</td>
                    <td class="r" style="color:var(--text2)">Rp {{ number_format($s->total_value) }}</td>
                    <td class="r bold" style="{{ $s->total_paid < $s->total_value ? 'color:#b91c1c' : 'color:var(--melon-dark)' }}">
                        Rp {{ number_format($s->total_paid) }}
                    </td>
                    <td class="r" style="color:{{ $piutangC > 0 ? '#dc2626' : 'var(--melon-dark)' }};font-weight:600">
                        {{ $piutangC > 0 ? 'Rp '.number_format($piutangC) : '✓' }}
                    </td>
                    <td style="min-width:80px">
                        <div style="font-size:9px;color:var(--text3);margin-bottom:3px">{{ $pctC }}%</div>
                        <div class="prog-track"><div class="prog-fill" style="width:{{ $pctC }}%;background:{{ $barC }}"></div></div>
                    </td>
                </tr>
                @endforeach
                @php
                    $ttlQty = $salesByCustomer->sum('total_qty');
                    $ttlVal = $salesByCustomer->sum('total_value');
                    $ttlPaid= $salesByCustomer->sum('total_paid');
                @endphp
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="r">{{ number_format($ttlQty) }}</td>
                    <td class="r">Rp {{ number_format($ttlVal) }}</td>
                    <td class="r">Rp {{ number_format($ttlPaid) }}</td>
                    <td class="r">{{ ($ttlVal-$ttlPaid)>0 ? 'Rp '.number_format($ttlVal-$ttlPaid) : '✓ Lunas' }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ══════════════════════════════════════════════════
     SEKSI 5 — DISTRIBUSI KONTRAK
══════════════════════════════════════════════════ --}}
@if(isset($contractCustomers) && $contractCustomers->count() > 0)
<div class="sec-divider">
    <span class="sec-divider-title">⭐ Distribusi Kontrak</span>
    <span class="sec-divider-line"></span>
    <a href="{{ route('contract-dist.index', ['period_id' => $period->id]) }}" style="font-size:10px;color:var(--melon-dark);text-decoration:none;white-space:nowrap">Detail →</a>
</div>

<div class="s-card">
    <div class="s-card-header">Ringkasan Customer Kontrak</div>
    <div style="padding:12px 14px;display:flex;flex-direction:column;gap:8px">
        @foreach($contractCustomers as $customer)
        @php
            $cd = $contracts[$customer->id];
            $isFlat = $cd->isFlat();
            $saldoBersih = $cd->saldo_bersih ?? 0;
        @endphp
        <div style="border:0.5px solid var(--border);border-radius:8px;overflow:hidden">
            <div style="background:{{ $isFlat ? '#fffbeb' : '#eff6ff' }};padding:8px 12px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                <div>
                    <div style="font-size:12px;font-weight:600;color:{{ $isFlat ? '#92400e' : '#1e3a8a' }}">
                        ★ {{ $customer->name }}
                        <span style="font-size:10px;font-weight:400;color:{{ $isFlat ? '#b45309' : '#3b82f6' }}">
                            — {{ $isFlat ? 'Flat Rp '.number_format($customer->outlet->contract_rate ?? 600000).'/bln' : 'Per DO Rp '.number_format($customer->outlet->contract_rate ?? 1000).'/tab' }}
                        </span>
                    </div>
                    <div style="font-size:10px;color:{{ $isFlat ? '#b45309' : '#3b82f6' }};margin-top:2px">
                        {{ number_format($cd->total_qty) }} tab · tagihan distribusi Rp {{ number_format($cd->tagihan_distribusi) }} · setoran Rp {{ number_format($cd->total_setoran) }}
                    </div>
                </div>
                <div style="text-align:right">
                    @if($saldoBersih > 0)
                        <div style="font-size:12px;font-weight:600;color:#dc2626">⚠ Kita bayar Rp {{ number_format($saldoBersih) }}</div>
                        <div style="font-size:9px;color:#fca5a5">{{ $cd->sudah_diselesaikan ? '✓ Sudah diselesaikan' : 'Belum dibayarkan' }}</div>
                    @elseif($saldoBersih < 0)
                        <div style="font-size:12px;font-weight:600;color:#ea580c">Masih kurang Rp {{ number_format(abs($saldoBersih)) }}</div>
                    @else
                        <div style="font-size:12px;font-weight:600;color:var(--melon-dark)">✓ Seimbang</div>
                    @endif
                </div>
            </div>
            <div style="padding:8px 12px;display:grid;grid-template-columns:repeat(3,1fr);gap:6px">
                @if($isFlat)
                <div>
                    <div style="font-size:9px;color:var(--text3)">Tabung ditinggalkan</div>
                    <div style="font-size:11px;font-weight:600;color:#b45309">{{ number_format($cd->qty_ditinggalkan) }} tab → Rp {{ number_format($cd->nilai_ditinggalkan) }}</div>
                </div>
                <div>
                    <div style="font-size:9px;color:var(--text3)">Kontrak flat</div>
                    <div style="font-size:11px;font-weight:600;color:#92400e">Rp {{ number_format($cd->tagihan_kontrak) }}</div>
                </div>
                <div>
                    <div style="font-size:9px;color:var(--text3)">Sisa kewajiban kontrak</div>
                    @php $sisaKon = $cd->sisaKontrakAngga(); @endphp
                    <div style="font-size:11px;font-weight:600;color:{{ $sisaKon > 0 ? '#ea580c' : 'var(--melon-dark)' }}">
                        {{ $sisaKon > 0 ? 'Rp '.number_format($sisaKon) : '✓ Lunas' }}
                    </div>
                </div>
                @else
                <div>
                    <div style="font-size:9px;color:var(--text3)">Piutang distribusi</div>
                    @php $piutangDist2 = $cd->piutangDistribusiSukmedi(); @endphp
                    <div style="font-size:11px;font-weight:600;color:{{ $piutangDist2 > 0 ? '#ea580c' : 'var(--melon-dark)' }}">
                        {{ $piutangDist2 > 0 ? 'Rp '.number_format($piutangDist2) : '✓ Lunas' }}
                    </div>
                </div>
                <div>
                    <div style="font-size:9px;color:var(--text3)">Hak kontrak DO</div>
                    <div style="font-size:11px;font-weight:600;color:#1d4ed8">Rp {{ number_format($cd->tagihan_kontrak) }}</div>
                </div>
                <div>
                    <div style="font-size:9px;color:var(--text3)">Saldo bersih</div>
                    <div style="font-size:11px;font-weight:600;color:{{ $saldoBersih > 0 ? '#dc2626' : ($saldoBersih < 0 ? '#ea580c' : 'var(--melon-dark)') }}">
                        @if($saldoBersih > 0) +Rp {{ number_format($saldoBersih) }} (bayar ke dia)
                        @elseif($saldoBersih < 0) Rp {{ number_format(abs($saldoBersih)) }} (dia masih kurang)
                        @else ✓ Rp 0 @endif
                    </div>
                </div>
                @endif
            </div>
            @if($cd->is_cutoff)
            <div style="background:#f0fdf4;padding:6px 12px;font-size:10px;color:var(--melon-dark);border-top:0.5px solid var(--border)">
                ✓ Cutoff {{ $cd->cutoff_date?->format('d/m/Y') }}
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════
     SEKSI 6 — REKENING PENAMPUNG
══════════════════════════════════════════════════ --}}
<div class="sec-divider">
    <span class="sec-divider-title">🏦 Rekening Penampung</span>
    <span class="sec-divider-line"></span>
</div>

<div class="s-card">
    <div class="s-card-header">Posisi Rekening Penampung</div>
    <div class="penampung-grid">
        <div class="penampung-cell">
            <div class="penampung-label">Saldo Awal</div>
            <div class="penampung-value">Rp {{ number_format($openingPenampung) }}</div>
        </div>
        <div class="penampung-cell">
            <div class="penampung-label">Total Masuk (Setoran)</div>
            <div class="penampung-value green">+ Rp {{ number_format($totalDeposited) }}</div>
        </div>
        <div class="penampung-cell">
            <div class="penampung-label">Total Keluar (TF Utama)</div>
            <div class="penampung-value red">− Rp {{ number_format($totalTransferred) }}</div>
        </div>
        <div class="penampung-cell">
            <div class="penampung-label">Admin TF</div>
            <div class="penampung-value" style="color:#1d4ed8">− Rp {{ number_format($totalAdmin) }}</div>
        </div>
        <div class="penampung-cell" style="grid-column:span 2;background:var(--melon-50)">
            <div class="penampung-label">Saldo Penampung Sekarang</div>
            <div class="penampung-value {{ $penampungNow >= 0 ? 'orange' : 'red' }}" style="font-size:20px">
                Rp {{ number_format($penampungNow) }}
            </div>
            <div style="height:4px;background:var(--border);border-radius:2px;overflow:hidden;margin:6px 0 4px">
                <div style="height:100%;width:{{ $utilisasiBar }}%;background:var(--melon);border-radius:2px"></div>
            </div>
            <div style="font-size:10px;color:var(--text3)">Terpakai {{ $utilisasiBar }}% · Sisa {{ 100-$utilisasiBar }}%</div>
        </div>
    </div>
    @if($totalSurplusAll > 0)
    <div style="padding:10px 14px;background:#eff6ff;border-top:0.5px solid #bfdbfe">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:11px;font-weight:600;color:#1e40af">💰 Total Surplus / Tabungan</span>
            <span style="font-size:14px;font-weight:600;color:#1d4ed8">Rp {{ number_format($totalSurplusAll) }}</span>
        </div>
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════
     SEKSI 7 — PIUTANG & KEWAJIBAN LENGKAP
══════════════════════════════════════════════════ --}}
<div class="sec-divider">
    <span class="sec-divider-title">⚠️ Piutang &amp; Kewajiban</span>
    <span class="sec-divider-line"></span>
</div>

{{-- Total piutang summary --}}
<div class="s-card">
    <div class="s-card-header">Total Piutang &amp; Kewajiban Periode Ini</div>
    <div class="cf-row">
        <div>
            <div class="cf-label bold">Piutang DO ke Agen</div>
            <div style="font-size:10px;color:var(--text3)">belum terbayar dari DO baru + carry-over</div>
        </div>
        <div class="cf-amount {{ $piutangDOTotal > 0 ? 'red' : 'green' }}">
            {{ $piutangDOTotal > 0 ? 'Rp '.number_format($piutangDOTotal) : '✓ Lunas' }}
        </div>
    </div>
    <div class="cf-row">
        <div>
            <div class="cf-label bold">Piutang Customer (Distribusi)</div>
            <div style="font-size:10px;color:var(--text3)">belum dibayar customer dari distribusi</div>
        </div>
        <div class="cf-amount {{ $piutangDist > 0 ? 'red' : 'green' }}">
            {{ $piutangDist > 0 ? 'Rp '.number_format($piutangDist) : '✓ Lunas' }}
        </div>
    </div>
    @if(isset($contractCustomers))
    @foreach($contractCustomers as $customer)
    @php $cd2 = $contracts[$customer->id]; $sb2 = $cd2->saldo_bersih ?? 0; @endphp
    @if($sb2 != 0)
    <div class="cf-row">
        <div>
            <div class="cf-label bold">Saldo Kontrak {{ $customer->name }}</div>
            <div style="font-size:10px;color:var(--text3)">{{ $sb2 > 0 ? 'kita yang bayar ke dia' : 'dia masih kurang' }}</div>
        </div>
        <div class="cf-amount {{ $sb2 > 0 ? 'red' : 'orange' }}">
            {{ $sb2 > 0 ? 'Bayar Rp '.number_format($sb2) : 'Kurang Rp '.number_format(abs($sb2)) }}
        </div>
    </div>
    @endif
    @endforeach
    @endif
    @if($gajiKurirTotal > 0)
    <div class="cf-row bg-yellow">
        <div>
            <div class="cf-label bold">Gaji Kurir</div>
            <div style="font-size:10px;color:#b45309">Rp 500/tab · dibayar tgl 1 bulan depan</div>
        </div>
        <div class="cf-amount" style="color:#92400e">Rp {{ number_format($gajiKurirTotal) }}</div>
    </div>
    @endif
    <div class="cf-row net">
        @php $totalKewajiban = $piutangDOTotal + $piutangDist + max($contractSaldoBersih ?? 0, 0) + $gajiKurirTotal; @endphp
        <div class="cf-label bold" style="font-size:13px">Total Kewajiban Belum Selesai</div>
        <div class="cf-net-amount {{ $totalKewajiban <= 0 ? 'green' : 'red' }}" style="color:{{ $totalKewajiban > 0 ? '#b91c1c' : 'var(--melon-dark)' }}">
            {{ $totalKewajiban > 0 ? 'Rp '.number_format($totalKewajiban) : '✓ Semua Lunas' }}
        </div>
    </div>
</div>

<div class="s-card">
    <div class="s-card-header">Detail Piutang &amp; Kewajiban</div>

    {{-- DO Unpaid --}}
    @if(isset($unpaidDOs) && $unpaidDOs->count() > 0)
    <div class="debt-section">
        <div class="debt-section-title">DO Belum Lunas (periode ini)</div>
        @foreach($unpaidDOs as $udo)
        <div class="debt-row">
            <div class="debt-row-label">
                {{ $udo->outlet->name }}
                $udo->do_date = \Carbon\Carbon::createFromFormat('d/m/Y', $udo->do_date);
                <span style="font-size:9px;color:var(--text3)">
                    · {{ $udo->do_date->format('d/m/Y') }} · {{ $udo->qty }} tab
                </span>
            </div>
            <div class="debt-row-amount red">Rp {{ number_format($udo->remainingAmount()) }}</div>
        </div>
        @endforeach
        <div class="debt-total-row"><span>Subtotal</span><span style="color:#b91c1c">Rp {{ number_format($unpaidDoValue ?? 0) }}</span></div>
    </div>
    @endif

    {{-- Carry-Over --}}
    @if(isset($prevUnpaidDOs) && $prevUnpaidDOs->count() > 0)
    <div class="debt-section" style="background:#fffbeb">
        <div class="debt-section-title" style="color:#d97706">DO Carry-Over (bulan lalu)</div>
        @foreach($prevUnpaidDOs as $udo)
        <div class="debt-row">
            <div class="debt-row-label" style="color:#92400e">[{{ $udo->period->label }}] {{ $udo->outlet->name }}</div>
            <div class="debt-row-amount orange">Rp {{ number_format($udo->remainingAmount()) }}</div>
        </div>
        @endforeach
        <div class="debt-total-row"><span>Subtotal</span><span style="color:#c2410c">Rp {{ number_format($prevUnpaidValue ?? 0) }}</span></div>
    </div>
    @endif

    {{-- Kontrak --}}
    @if(isset($contractPayments) && $contractPayments->count() > 0)
    <div class="debt-section">
        <div class="debt-section-title">Kontrak Pangkalan</div>
        @foreach($contractPayments as $cp)
        <div class="contract-row">
            <div class="contract-row-top">
                <div>
                    <div class="contract-name">{{ $cp->outlet->name }}</div>
                    <div class="contract-type">{{ $cp->outlet->contract_type === 'per_do' ? '× DO' : 'Flat' }} · Rp {{ number_format($cp->calculated_amount) }}</div>
                </div>
                <div style="display:flex;align-items:center;gap:6px">
                    @if($cp->status === 'paid')
                        <span class="badge badge-green">✓ Lunas</span>
                    @else
                        <span class="badge badge-red">Belum</span>
                        @if($period->status === 'open')
                        <button type="button" class="link-btn" onclick="document.getElementById('cp-{{ $cp->id }}').classList.toggle('open')">Tandai</button>
                        @endif
                    @endif
                </div>
            </div>
            @if($period->status === 'open' && $cp->status !== 'paid')
            <div id="cp-{{ $cp->id }}" class="contract-form">
                <form method="POST" action="{{ route('contract-payments.update', $cp) }}">
                    @csrf @method('PUT')
                    <div class="form-row">
                        <div class="form-field"><label>Tgl Bayar</label><input type="date" name="paid_date" value="{{ date('Y-m-d') }}"></div>
                        <div class="form-field"><label>Nominal</label><input type="number" name="paid_amount" value="{{ $cp->calculated_amount }}"></div>
                        <button type="submit" class="form-submit">Simpan</button>
                    </div>
                </form>
            </div>
            @endif
        </div>
        @endforeach
        @if($period->status === 'open')
        <div style="margin-top:6px">
            <form method="POST" action="{{ route('contract-payments.recalc') }}">
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <button type="submit" class="link-btn-sm">↻ Recalculate Kontrak</button>
            </form>
        </div>
        @endif
    </div>
    @endif

    {{-- Gaji Kurir --}}
    @if(isset($courierWages) && count($courierWages) > 0)
    <div class="debt-section">
        <div class="debt-section-title">Gaji Kurir (Rp 500/tabung)</div>
        @foreach($courierWages as $cw)
        <div class="debt-row">
            <div class="debt-row-label">{{ $cw['name'] }} <span style="color:var(--text3)">({{ number_format($cw['qty']) }} tab)</span></div>
            <div class="debt-row-amount indigo">Rp {{ number_format($cw['wage']) }}</div>
        </div>
        @endforeach
        <div class="debt-total-row"><span>Total Gaji</span><span style="color:#4338ca">Rp {{ number_format($gajiKurirTotal) }}</span></div>
    </div>
    @endif

    {{-- External Debt --}}
    @if(isset($externalNet) && $externalNet > 0)
    <div class="debt-section">
        <div class="debt-section-title">Piutang External</div>
        <div style="background:#f8faf8;border-radius:8px;padding:10px 12px;">
            <div style="display:flex;justify-content:space-between;font-size:10px;padding:2px 0;color:var(--text2)">
                <span>Saldo Awal + Masuk</span>
                <span style="color:var(--melon-dark)">Rp {{ number_format($period->opening_external_debt + ($externalIn ?? 0)) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;padding:2px 0;color:var(--text2)">
                <span>Dibayar / Keluar</span>
                <span style="color:#b91c1c">− Rp {{ number_format($externalOut ?? 0) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:6px 0 0;font-size:11px;font-weight:600;border-top:0.5px solid var(--border);margin-top:3px">
                <span>Saldo Piutang</span>
                <span>Rp {{ number_format($externalNet) }}</span>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════
     SEKSI 8 — REKOMENDASI & KESIMPULAN
══════════════════════════════════════════════════ --}}
<div class="sec-divider">
    <span class="sec-divider-title">💡 Rekomendasi</span>
    <span class="sec-divider-line"></span>
</div>

<div class="s-card">
    <div class="s-card-header">Analisis &amp; Rekomendasi Periode Ini</div>
    <div style="padding:12px 14px" class="pill-box">

        {{-- Stok --}}
        @if($stockSisa >= 0)
        <div class="pill pill-green"><span class="pill-icon">✓</span><span>Stok sisa <strong>{{ number_format($stockSisa) }} tab</strong> — kondisi aman, tidak ada defisit stok.</span></div>
        @else
        <div class="pill pill-red"><span class="pill-icon">!</span><span>Stok defisit <strong>{{ number_format(abs($stockSisa)) }} tab</strong> — segera periksa catatan DO dan distribusi.</span></div>
        @endif

        {{-- Cashflow --}}
        @if($rasioOperasional < 25)
        <div class="pill pill-green"><span class="pill-icon">✓</span><span>Rasio biaya operasional <strong>{{ $rasioOperasional }}%</strong> — sangat efisien (ideal &lt;35%).</span></div>
        @elseif($rasioOperasional < 35)
        <div class="pill pill-green"><span class="pill-icon">✓</span><span>Rasio biaya operasional <strong>{{ $rasioOperasional }}%</strong> — masih dalam batas sehat.</span></div>
        @else
        <div class="pill pill-orange"><span class="pill-icon">⚠</span><span>Rasio biaya operasional <strong>{{ $rasioOperasional }}%</strong> — melebihi 35%, perlu efisiensi pengeluaran.</span></div>
        @endif

        {{-- Transfer utilisasi --}}
        @if($utilisasi >= 80)
        <div class="pill pill-green"><span class="pill-icon">✓</span><span>Utilisasi penampung <strong>{{ $utilisasi }}%</strong> — rekening penampung berputar sehat.</span></div>
        @else
        <div class="pill pill-orange"><span class="pill-icon">⚠</span><span>Utilisasi penampung <strong>{{ $utilisasi }}%</strong> — pertimbangkan mempercepat transfer ke rekening utama.</span></div>
        @endif

        {{-- Piutang DO --}}
        @if($piutangDOTotal > 0)
        <div class="pill {{ $piutangPct > 40 ? 'pill-red' : 'pill-orange' }}">
            <span class="pill-icon">{{ $piutangPct > 40 ? '!' : '⚠' }}</span>
            <span>Piutang DO ke agen <strong>Rp {{ number_format($piutangDOTotal) }}</strong> ({{ $piutangPct }}%) — prioritaskan pelunasan sebelum penutupan periode.</span>
        </div>
        @else
        <div class="pill pill-green"><span class="pill-icon">✓</span><span>Semua piutang DO ke agen sudah terlunasi bulan ini.</span></div>
        @endif

        {{-- Piutang customer distribusi --}}
        @if($piutangDist > 0)
        @php $pctDistP = $totalTagihanDist > 0 ? round($piutangDist / $totalTagihanDist * 100) : 0; @endphp
        <div class="pill {{ $pctDistP > 30 ? 'pill-red' : 'pill-orange' }}">
            <span class="pill-icon">⚠</span>
            <span>Piutang customer distribusi <strong>Rp {{ number_format($piutangDist) }}</strong> ({{ $pctDistP }}%) — pastikan penagihan sebelum periode tutup.</span>
        </div>
        @else
        <div class="pill pill-green"><span class="pill-icon">✓</span><span>Semua customer distribusi sudah lunas bulan ini.</span></div>
        @endif

        {{-- Saldo penampung --}}
        @if($penampungNow < 0)
        <div class="pill pill-red"><span class="pill-icon">!</span><span>Saldo rekening penampung <strong>negatif (Rp {{ number_format($penampungNow) }})</strong> — segera cek transaksi masuk/keluar.</span></div>
        @endif

        {{-- Admin fee --}}
        @if($rasioAdmin < 0.5)
        <div class="pill pill-green"><span class="pill-icon">✓</span><span>Biaya admin TF <strong>{{ $rasioAdmin }}%</strong> — sangat wajar dari total setoran.</span></div>
        @else
        <div class="pill pill-orange"><span class="pill-icon">⚠</span><span>Biaya admin TF <strong>{{ $rasioAdmin }}%</strong> — pertimbangkan transfer batch untuk mengurangi frekuensi.</span></div>
        @endif

        {{-- Surplus --}}
        @if($totalSurplusAll > 0)
        <div class="pill pill-blue"><span class="pill-icon">i</span><span>Surplus <strong>Rp {{ number_format($totalSurplusAll) }}</strong> tercatat — diakumulasi sebagai tabungan periode.</span></div>
        @endif

        {{-- Prediksi --}}
        @if($predKas !== null && $remainDays > 0)
        @if($predKas < 0)
        <div class="pill pill-red"><span class="pill-icon">!</span><span>Prediksi saldo KAS akhir bulan <strong>defisit Rp {{ number_format(abs($predKas)) }}</strong> — pantau pengeluaran dan pastikan setoran masuk tepat waktu.</span></div>
        @else
        <div class="pill pill-green"><span class="pill-icon">✓</span><span>Prediksi saldo KAS akhir bulan <strong>Rp {{ number_format($predKas) }}</strong> — proyeksi aman.</span></div>
        @endif
        @endif

        {{-- Gaji kurir --}}
        @if($gajiKurirTotal > 0)
        <div class="pill pill-blue"><span class="pill-icon">i</span><span>Kewajiban gaji kurir (Rp 500/tab) diperkirakan <strong>Rp {{ number_format($gajiKurirTotal) }}</strong> — pastikan saldo kas cukup untuk tgl 1 bulan depan.</span></div>
        @endif
    </div>
</div>

</div>{{-- end sum-page --}}
@endsection