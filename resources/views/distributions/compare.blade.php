@extends('layouts.app')
@section('title', 'Analisis Antar Periode')

@section('content')

@php
    // Semua kalkulasi sudah dilakukan di DistributionController@compare.
    // View ini murni presentasi.
    $bestQty = $grand['bestQtyPeriod'];
@endphp

<style>
    .cmp-kpi-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 14px; }
    @media (min-width: 420px) { .cmp-kpi-grid { grid-template-columns: repeat(5, 1fr); } }
    .kpi-card  { background: var(--surface); border: 0.5px solid var(--border); border-radius: var(--radius-sm); padding: 12px; display: flex; flex-direction: column; gap: 4px; }
    .kpi-label { font-size: 10px; color: var(--text3); font-weight: 500; }
    .kpi-value { font-size: 17px; font-weight: 700; line-height: 1.1; }
    .kpi-sub   { font-size: 10px; color: var(--text3); }

    .mob-table     { border-collapse: collapse; width: 100%; font-size: 11px; }
    .mob-table th,
    .mob-table td  { border: 1px solid rgba(0,0,0,.03); padding: 8px 10px; }
    .mob-table thead th { background: var(--surface2); color: var(--text3); font-size: 9px; font-weight: 700;
                        text-transform: uppercase; letter-spacing: .4px; text-align: left; }
    .mob-table th.r, .mob-table td.r { text-align: right; }
    .mob-table .total-row td { border-top: 2px solid var(--border); font-weight: 700; background: var(--surface2); }
    .mob-table tbody tr:hover td { background: var(--melon-50); }
    .scroll-x { overflow-x: auto; -webkit-overflow-scrolling: touch; }

    .growth-up   { color: var(--melon-dark); font-weight: 600; }
    .growth-down { color: #dc2626; font-weight: 600; }
    .growth-flat { color: var(--text3); }

    .legend       { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 8px; }
    .legend-item  { display: flex; align-items: center; gap: 5px; font-size: 10px; color: var(--text3); }
    .legend-sw    { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
    .legend-line  { width: 18px; height: 2px; flex-shrink: 0; }
    .page-header  { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
    .page-title   { font-size: 15px; font-weight: 700; color: var(--text1); }

    .flow-card { padding: 20px 14px; }
    .flow-stage { display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap; }
    .flow-box { background: var(--surface); border: 0.5px solid var(--border); border-radius: var(--radius-sm);
                padding: 10px 14px; min-width: 120px; text-align: center; }
    .flow-box.result { border-width: 1.5px; background: var(--melon-50); }
    .flow-label { font-size: 9px; color: var(--text3); font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }
    .flow-value { font-size: 14px; font-weight: 700; margin-top: 3px; }
    .flow-op { font-size: 18px; font-weight: 700; color: var(--text3); }
    .flow-connector { display: flex; justify-content: center; padding: 6px 0; font-size: 18px; color: var(--text3); }
    @media (max-width: 480px) { .flow-box { min-width: 100px; padding: 8px 10px; } .flow-value { font-size: 12px; } }
</style>

<div class="page-header">
    <span class="page-title">📈 Analisis Distribusi Antar Periode</span>
    <a href="{{ route('distributions.index') }}" class="btn-secondary btn-sm">← Kembali ke Distribusi</a>
</div>

{{-- ══ RINGKASAN KESELURUHAN ══ --}}
<div class="cmp-kpi-grid">
    <div class="kpi-card">
        <span class="kpi-label">Total Tabung (semua periode)</span>
        <span class="kpi-value" style="color:var(--melon-dark);">{{ number_format($grand['allQty']) }}</span>
        <span class="kpi-sub">{{ $rows->count() }} periode</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Total Tagihan</span>
        <span class="kpi-value" style="color:#1d4ed8;">Rp {{ number_format($grand['allVal']) }}</span>
        <span class="kpi-sub">avg Rp {{ number_format($rows->count() ? $grand['allVal'] / $rows->count() : 0) }}/periode</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Total Piutang</span>
        <span class="kpi-value" style="color:{{ $grand['piutang'] > 0 ? '#dc2626' : 'var(--melon-dark)' }};">
            {{ $grand['piutang'] > 0 ? 'Rp '.number_format($grand['piutang']) : '✓ Lunas' }}
        </span>
        <span class="kpi-sub">akumulasi seluruh periode</span>
    </div>
    <div class="kpi-card" style="border-color:#d1fae5;">
        <span class="kpi-label">Total Margin</span>
        <span class="kpi-value" style="color:#059669;">Rp {{ number_format($grand['allMargin']) }}</span>
        <span class="kpi-sub">tagihan − HPP</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Periode Terbaik</span>
        <span class="kpi-value" style="color:#7c3aed;font-size:14px;">{{ $bestQty['label'] ?? '-' }}</span>
        <span class="kpi-sub">{{ number_format($bestQty['allQty'] ?? 0) }} tab</span>
    </div>
</div>

<div class="cmp-kpi-grid">
    <div class="kpi-card" style="border-color:#d1fae5;">
        <span class="kpi-label">Profit Bersih (semua periode)</span>
        <span class="kpi-value" style="color:#059669;">Rp {{ number_format($grand['profitBersih']) }}</span>
        <span class="kpi-sub">margin − ops − admin</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Uang Dipegang Saat Ini</span>
        <span class="kpi-value" style="color:#1d4ed8;">Rp {{ number_format($grand['totalUangDipegangKini']) }}</span>
        <span class="kpi-sub">kas + bank + tabungan</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Total Kekayaan Saat Ini</span>
        <span class="kpi-value" style="color:#7c3aed;">Rp {{ number_format($grand['totalKekayaanKini']) }}</span>
        <span class="kpi-sub">+ piutang + nilai stok</span>
    </div>
    <div class="kpi-card" style="border-color:#fecaca;">
        <span class="kpi-label">Profit Bebas (Net Utang DO)</span>
        <span class="kpi-value" style="color:{{ $grand['profitBebas'] >= 0 ? '#059669' : '#dc2626' }};">Rp {{ number_format($grand['profitBebas']) }}</span>
        <span class="kpi-sub">profit − utang Agen belum dibayar</span>
    </div>
    <div class="kpi-card" style="border-color:{{ $grand['adaAnomali'] ? '#fecaca' : '#d1fae5' }};">
        <span class="kpi-label">Konsistensi Data</span>
        <span class="kpi-value" style="color:{{ $grand['adaAnomali'] ? '#dc2626' : 'var(--melon-dark)' }};font-size:14px;">
            {{ $grand['adaAnomali'] ? '⚠ Ada Selisih' : '✓ Konsisten' }}
        </span>
        <span class="kpi-sub">cross-check antar modul</span>
    </div>
</div>

{{-- ══ CHART TREN ANTAR PERIODE ══ --}}
<div class="s-card">
    <div class="s-card-header">📊 Tren Distribusi, Tagihan & Piutang per Periode</div>
    <div style="padding:12px 14px;">
        <div class="legend">
            <span class="legend-item"><span class="legend-sw" style="background:#bfdbfe;"></span>Total Tabung</span>
            <span class="legend-item"><span class="legend-line" style="background:#1d4ed8;"></span>Total Tagihan (Rp)</span>
            <span class="legend-item"><span class="legend-line" style="background:#dc2626;border-top:2px dashed #dc2626;height:0;"></span>Piutang (Rp)</span>
            <span class="legend-item"><span class="legend-line" style="background:#059669;border-top:2px dashed #059669;height:0;"></span>Rasio Lunas (%)</span>
        </div>
        <div style="position:relative;width:100%;height:260px;">
            <canvas id="cmpTrendChart" role="img" aria-label="Chart tren distribusi, tagihan, piutang dan rasio lunas per periode"></canvas>
        </div>
    </div>
</div>

{{-- ══ TABEL 1: PENJUALAN & DISTRIBUSI ══ --}}
<div class="s-card">
    <div class="s-card-header">📋 Tabel Penjualan & Distribusi per Periode</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Status</th>
                    <th class="r">Total Tabung</th>
                    <th class="r">Growth Qty</th>
                    <th class="r">Total Tagihan</th>
                    <th class="r">Growth Nilai</th>
                    <th class="r">Kas Diterima</th>
                    <th class="r">Piutang</th>
                    <th class="r" style="background:#d1fae5;">Margin</th>
                    <th class="r">Rasio Lunas</th>
                    <th class="r">Avg/Hari</th>
                    <th class="r">Hari Aktif</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr>
                    <td class="bold">
                        <a href="{{ route('distributions.index', ['period_id' => $row['period_id']]) }}" style="color:var(--text1);text-decoration:none;">
                            {{ $row['label'] }}
                        </a>
                    </td>
                    <td>
                        @if($row['status'] === 'open')
                            <span class="badge badge-green">Open</span>
                        @else
                            <span class="badge" style="background:var(--surface2);color:var(--text3);">Closed</span>
                        @endif
                    </td>
                    <td class="r bold">{{ number_format($row['allQty']) }}</td>
                    <td class="r">
                        @if($row['qtyGrowth'] === null)
                            <span class="growth-flat">—</span>
                        @else
                            <span class="{{ $row['qtyGrowth'] > 0 ? 'growth-up' : ($row['qtyGrowth'] < 0 ? 'growth-down' : 'growth-flat') }}">
                                {{ $row['qtyGrowth'] > 0 ? '▲' : ($row['qtyGrowth'] < 0 ? '▼' : '–') }} {{ abs($row['qtyGrowth']) }}%
                            </span>
                        @endif
                    </td>
                    <td class="r">Rp {{ number_format($row['allVal']) }}</td>
                    <td class="r">
                        @if($row['valGrowth'] === null)
                            <span class="growth-flat">—</span>
                        @else
                            <span class="{{ $row['valGrowth'] > 0 ? 'growth-up' : ($row['valGrowth'] < 0 ? 'growth-down' : 'growth-flat') }}">
                                {{ $row['valGrowth'] > 0 ? '▲' : ($row['valGrowth'] < 0 ? '▼' : '–') }} {{ abs($row['valGrowth']) }}%
                            </span>
                        @endif
                    </td>
                    <td class="r" style="color:var(--melon-dark);">Rp {{ number_format($row['allPaid']) }}</td>
                    <td class="r" style="color:{{ $row['piutang'] > 0 ? '#dc2626' : 'var(--melon-dark)' }};font-weight:600;">
                        {{ $row['piutang'] > 0 ? 'Rp '.number_format($row['piutang']) : '✓' }}
                    </td>
                    <td class="r" style="background:#f0fdf4;color:#059669;font-weight:600;">Rp {{ number_format($row['allMargin']) }}</td>
                    <td class="r" style="color:{{ $row['rasioLunas'] >= 90 ? 'var(--melon-dark)' : ($row['rasioLunas'] >= 70 ? '#d97706' : '#dc2626') }};font-weight:600;">
                        {{ number_format($row['rasioLunas'], 1) }}%
                    </td>
                    <td class="r">{{ $row['avgTabHar'] }} tab</td>
                    <td class="r">{{ $row['activeDays'] }}/{{ $row['daysInMonth'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="12" style="text-align:center;padding:24px;color:var(--text3);">Belum ada data periode.</td>
                </tr>
                @endforelse
            </tbody>
            @if($rows->count() > 0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="2">TOTAL / RATA-RATA</td>
                    <td class="r">{{ number_format($grand['allQty']) }}</td>
                    <td class="r"></td>
                    <td class="r">Rp {{ number_format($grand['allVal']) }}</td>
                    <td class="r"></td>
                    <td class="r">Rp {{ number_format($grand['allPaid']) }}</td>
                    <td class="r" style="color:{{ $grand['piutang'] > 0 ? '#dc2626' : 'var(--melon-dark)' }};">
                        {{ $grand['piutang'] > 0 ? 'Rp '.number_format($grand['piutang']) : '✓' }}
                    </td>
                    <td class="r" style="color:#059669;">Rp {{ number_format($grand['allMargin']) }}</td>
                    <td class="r">{{ number_format($rows->avg('rasioLunas'), 1) }}%</td>
                    <td class="r">{{ number_format($rows->avg('avgTabHar'), 1) }} tab</td>
                    <td class="r"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    @if($grand['adaAnomali'])
    <div style="padding:10px 14px;font-size:11px;color:#dc2626;background:#fef2f2;border-top:1px solid #fecaca;">
        ⚠ Ada periode dengan selisih data antar modul distribusi vs cashflow vs DO. Cek nilai <code>selisihIncome</code>, <code>selisihMargin</code>, <code>selisihDoQty</code>, <code>selisihSurplus</code> pada periode terkait — kemungkinan penyebab: input manual yang tidak sinkron, atau bug perhitungan (contoh kasus: swap Saldo KAS/BANK Maret 2026 yang sebelumnya ditemukan).
        <br>
        @foreach($rows->where('isKonsisten', false) as $r)
            <span style="display:inline-block;margin-top:4px;">• <strong>{{ $r['label'] }}</strong>: Income {{ number_format($r['selisihIncome']) }} | Margin {{ number_format($r['selisihMargin']) }} | DO {{ number_format($r['selisihDoQty']) }} | Surplus {{ number_format($r['selisihSurplus']) }}</span><br>
        @endforeach
    </div>
    @endif
</div>

{{-- ══ TABEL 2: CASHFLOW & TABUNGAN (GABUNGAN) ══ --}}
<div class="s-card">
    <div class="s-card-header">💰 Tabel Cashflow & Tabungan per Periode</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th class="r">Saldo Awal KAS</th>
                    <th class="r">Pemasukan</th>
                    <th class="r">Pengeluaran</th>
                    <th class="r">TF Penampung</th>
                    <th class="r">Admin TF</th>
                    <th class="r">TF Utama</th>
                    <th class="r" style="background:#fef9c3;">Surplus (→Tabungan)</th>
                    <th class="r">Tabungan Keluar</th>
                    <th class="r" style="background:#ede9fe;">Saldo Tabungan</th>
                    <th class="r">Saldo KAS</th>
                    <th class="r">Saldo BANK</th>
                    <th class="r" style="background:#dbeafe;">Net Total</th>
                    <th class="r">Growth Net</th>
                    <th class="r">Rasio Ops</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    <td class="bold">{{ $row['label'] }}</td>
                    <td class="r" style="color:#b45309;">{{ $row['cfOpeningCash'] > 0 ? 'Rp '.number_format($row['cfOpeningCash']) : '—' }}</td>
                    <td class="r">Rp {{ number_format($row['cfIncome']) }}</td>
                    <td class="r">Rp {{ number_format($row['cfExpense']) }}</td>
                    <td class="r">Rp {{ number_format($row['cfDeposits']) }}</td>
                    <td class="r" style="color:#1d4ed8;">{{ $row['cfAdminFees'] > 0 ? 'Rp '.number_format($row['cfAdminFees']) : '—' }}</td>
                    <td class="r">{{ $row['cfTransferred'] > 0 ? 'Rp '.number_format($row['cfTransferred']) : '—' }}</td>
                    <td class="r" style="background:#fefce8;color:#b45309;font-weight:600;">{{ $row['cfSurplus'] > 0 ? 'Rp '.number_format($row['cfSurplus']) : '—' }}</td>
                    <td class="r" style="color:#dc2626;">{{ $row['svOut'] > 0 ? 'Rp '.number_format($row['svOut']) : '—' }}</td>
                    <td class="r bold" style="background:#f5f3ff;color:{{ $row['svBalance'] >= 0 ? '#6d28d9' : '#dc2626' }};">Rp {{ number_format($row['svBalance']) }}</td>
                    <td class="r" style="color:{{ $row['cfNetKas'] >= 0 ? 'var(--melon-dark)' : '#dc2626' }};">Rp {{ number_format($row['cfNetKas']) }}</td>
                    <td class="r" style="color:{{ $row['cfBankBal'] >= 0 ? '#4338ca' : '#dc2626' }};">Rp {{ number_format($row['cfBankBal']) }}</td>
                    <td class="r bold" style="background:#eff6ff;color:{{ $row['cfNetTotal'] >= 0 ? 'var(--melon-dark)' : '#dc2626' }};">Rp {{ number_format($row['cfNetTotal']) }}</td>
                    <td class="r">
                        @if($row['cfNetTotalGrowth'] === null)
                            <span class="growth-flat">—</span>
                        @else
                            <span class="{{ $row['cfNetTotalGrowth'] > 0 ? 'growth-up' : ($row['cfNetTotalGrowth'] < 0 ? 'growth-down' : 'growth-flat') }}">
                                {{ $row['cfNetTotalGrowth'] > 0 ? '▲' : ($row['cfNetTotalGrowth'] < 0 ? '▼' : '–') }} {{ abs($row['cfNetTotalGrowth']) }}%
                            </span>
                        @endif
                    </td>
                    <td class="r">{{ $row['cfRasioOps'] }}%</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="r"></td>
                    <td class="r">Rp {{ number_format($grand['cfIncome']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfExpense']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfDeposits']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfAdminFees']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfTransferred']) }}</td>
                    <td class="r">Rp {{ number_format($grand['svIn']) }}</td>
                    <td class="r">Rp {{ number_format($grand['svOut']) }}</td>
                    <td class="r bold">Rp {{ number_format($grand['svBalance']) }} <span style="font-weight:400;font-size:9px;">(kini)</span></td>
                    <td class="r">Rp {{ number_format($grand['cfNetKas']) }} <span style="font-weight:400;font-size:9px;">(kini)</span></td>
                    <td class="r">Rp {{ number_format($grand['cfBankBal']) }} <span style="font-weight:400;font-size:9px;">(kini)</span></td>
                    <td class="r bold">Rp {{ number_format($grand['cfNetTotal']) }} <span style="font-weight:400;font-size:9px;">(kini)</span></td>
                    <td class="r"></td>
                    <td class="r"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- ══ TABEL 3: DELIVERY ORDER & STOK ══ --}}
<div class="s-card">
    <div class="s-card-header">🚚 Tabel Delivery Order & Stok per Periode</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th class="r">Stok Awal</th>
                    <th class="r">DO Diterima</th>
                    <th class="r">Growth DO</th>
                    <th class="r">Total DO</th>
                    <th class="r">Total Distribusi</th>
                    <th class="r">Sisa Stok</th>
                    <th class="r">Utilisasi</th>
                    <th class="r">Nilai DO (HPP)</th>
                    <th class="r">Kas Diterima</th>
                    <th class="r" style="background:#fef9c3;">Selisih Kumulatif</th>
                    <th class="r">Utang Awal</th>
                    <th class="r">Dibayar (TF Utama)</th>
                    <th class="r" style="background:#fee2e2;">Utang Akhir</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    <td class="bold">{{ $row['label'] }}</td>
                    <td class="r" style="color:var(--text2);">{{ number_format($row['openingStock']) }}</td>
                    <td class="r bold">{{ number_format($row['doReceivedQty']) }}</td>
                    <td class="r">
                        @if($row['doReceivedGrowth'] === null)
                            <span class="growth-flat">—</span>
                        @else
                            <span class="{{ $row['doReceivedGrowth'] > 0 ? 'growth-up' : ($row['doReceivedGrowth'] < 0 ? 'growth-down' : 'growth-flat') }}">
                                {{ $row['doReceivedGrowth'] > 0 ? '▲' : ($row['doReceivedGrowth'] < 0 ? '▼' : '–') }} {{ abs($row['doReceivedGrowth']) }}%
                            </span>
                        @endif
                    </td>
                    <td class="r" style="background:#eff6ff;font-weight:600;">{{ number_format($row['doTotalQty'] + $row['openingStock']) }}</td>
                    <td class="r">{{ number_format($row['doDistributed']) }}</td>
                    <td class="r" style="color:{{ $row['doSisaStok'] < 0 ? '#dc2626' : 'var(--text2)' }};">
                        {{ number_format($row['doSisaStok']) }}
                    </td>
                    <td class="r" style="color:{{ $row['doUtilisasi'] >= 90 ? 'var(--melon-dark)' : ($row['doUtilisasi'] >= 70 ? '#d97706' : '#dc2626') }};font-weight:600;">
                        {{ $row['doUtilisasi'] }}%
                    </td>
                    <td class="r" style="color:#b91c1c;">Rp {{ number_format($row['nilaiDoHpp']) }}</td>
                    <td class="r" style="color:var(--melon-dark);">Rp {{ number_format($row['allPaid']) }}</td>
                    <td class="r bold" style="background:#fefce8;color:{{ $row['kasVsDoSelisihKumulatif'] >= 0 ? '#059669' : '#dc2626' }};">
                        Rp {{ number_format($row['kasVsDoSelisihKumulatif']) }}
                    </td>
                    <td class="r" style="color:var(--text3);">{{ $row['doPayableAwal'] != 0 ? 'Rp '.number_format($row['doPayableAwal']) : '—' }}</td>
                    <td class="r" style="color:#4338ca;">Rp {{ number_format($row['cfTransferred']) }}</td>
                    <td class="r bold" style="background:#fef2f2;color:{{ $row['doPayableAkhir'] > 0 ? '#dc2626' : 'var(--melon-dark)' }};">
                        {{ $row['doPayableAkhir'] > 0 ? 'Rp '.number_format($row['doPayableAkhir']) : '✓ Lunas' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="r">{{ number_format($rows->sum('openingStock')) }}</td>
                    <td class="r">{{ number_format($grand['doReceivedQty']) }}</td>
                    <td class="r"></td>
                    <td class="r">{{ number_format($grand['doTotalQty']) }}</td>
                    <td class="r">{{ number_format($grand['doDistributed']) }}</td>
                    <td class="r"></td>
                    <td class="r">{{ $grand['avgUtilisasi'] }}%</td>
                    <td class="r">Rp {{ number_format($grand['nilaiDoHpp']) }}</td>
                    <td class="r">Rp {{ number_format($grand['allPaid']) }}</td>
                    <td class="r bold">Rp {{ number_format($grand['kasVsDoSelisih']) }} <span style="font-weight:400;font-size:9px;">(kini)</span></td>
                    <td class="r"></td>
                    <td class="r">Rp {{ number_format($grand['cfTransferred']) }}</td>
                    <td class="r bold">{{ $grand['doPayableAkhir'] > 0 ? 'Rp '.number_format($grand['doPayableAkhir']) : '✓ Lunas' }} <span style="font-weight:400;font-size:9px;">(kini)</span></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@php
    $last = $rows->last();
@endphp

@if($last)
<div class="s-card">
    <div class="s-card-header">🔀 Alur Rekonsiliasi Keuangan — {{ $last['label'] }} (Snapshot Terkini)</div>
    <div class="flow-card">

        {{-- ═══ TAHAP 1: PROFIT ═══ --}}
        <div class="flow-stage">
            <div class="flow-box">
                <div class="flow-label">Margin Kotor</div>
                <div class="flow-value" style="color:#1d4ed8;">Rp {{ number_format($last['allMargin']) }}</div>
            </div>
            <span class="flow-op">−</span>
            <div class="flow-box">
                <div class="flow-label">Beban Ops+Admin</div>
                <div class="flow-value" style="color:#dc2626;">Rp {{ number_format($last['cfExpense'] + $last['cfAdminFees']) }}</div>
            </div>
            <span class="flow-op">=</span>
            <div class="flow-box result">
                <div class="flow-label">Profit Bersih</div>
                <div class="flow-value" style="color:#059669;">Rp {{ number_format($last['profitBersih']) }}</div>
            </div>
        </div>

        <div class="flow-connector">↓</div>

        {{-- ═══ TAHAP 2: UANG DIPEGANG ═══ --}}
        <div class="flow-stage">
            <div class="flow-box">
                <div class="flow-label">Saldo KAS</div>
                <div class="flow-value" style="color:{{ $last['cfNetKas'] >= 0 ? '#b45309' : '#dc2626' }};">Rp {{ number_format($last['cfNetKas']) }}</div>
            </div>
            <span class="flow-op">+</span>
            <div class="flow-box">
                <div class="flow-label">Saldo BANK</div>
                <div class="flow-value" style="color:{{ $last['cfBankBal'] >= 0 ? '#4338ca' : '#dc2626' }};">Rp {{ number_format($last['cfBankBal']) }}</div>
            </div>
            <span class="flow-op">+</span>
            <div class="flow-box">
                <div class="flow-label">Saldo Tabungan</div>
                <div class="flow-value" style="color:#6d28d9;">Rp {{ number_format($last['svBalance']) }}</div>
            </div>
            <span class="flow-op">=</span>
            <div class="flow-box result">
                <div class="flow-label">Total Dipegang</div>
                <div class="flow-value" style="color:#1d4ed8;">Rp {{ number_format($last['totalUangDipegang']) }}</div>
            </div>
        </div>

        <div class="flow-connector">↓</div>

        {{-- ═══ TAHAP 3: TOTAL KEKAYAAN ═══ --}}
        <div class="flow-stage">
            <div class="flow-box">
                <div class="flow-label">Total Dipegang</div>
                <div class="flow-value" style="color:#1d4ed8;">Rp {{ number_format($last['totalUangDipegang']) }}</div>
            </div>
            <span class="flow-op">+</span>
            <div class="flow-box">
                <div class="flow-label">Piutang</div>
                <div class="flow-value" style="color:{{ $last['piutang'] > 0 ? '#dc2626' : 'var(--text3)' }};">
                    {{ $last['piutang'] > 0 ? 'Rp '.number_format($last['piutang']) : 'Rp 0' }}
                </div>
            </div>
            <span class="flow-op">+</span>
            <div class="flow-box">
                <div class="flow-label">Nilai Stok</div>
                <div class="flow-value" style="color:var(--text2);">Rp {{ number_format($last['nilaiStokAtCost']) }}</div>
            </div>
            <span class="flow-op">=</span>
            <div class="flow-box result">
                <div class="flow-label">Total Kekayaan</div>
                <div class="flow-value" style="color:#6d28d9;">Rp {{ number_format($last['totalKekayaan']) }}</div>
            </div>
        </div>

    </div>
</div>
@endif

{{-- Data bridge untuk chart --}}
<div id="cmpChartData"
     data-labels='@json($chart["labels"])'
     data-qty='@json($chart["qty"])'
     data-val='@json($chart["val"])'
     data-piutang='@json($chart["piutang"])'
     data-rasio='@json($chart["rasioLunas"])'
     style="display:none;"></div>

@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
    (function () {
        const el = document.getElementById('cmpChartData');
        if (!el) return;

        const labels   = JSON.parse(el.dataset.labels);
        const qty      = JSON.parse(el.dataset.qty);
        const val      = JSON.parse(el.dataset.val);
        const piutang  = JSON.parse(el.dataset.piutang);
        const rasio    = JSON.parse(el.dataset.rasio);
        if (!labels.length) return;

        const GRID = 'rgba(0,0,0,0.05)';
        const TICK = { font: { size: 10 }, color: '#9CA3AF' };

        new Chart(document.getElementById('cmpTrendChart'), {
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'bar', label: 'Total Tabung', data: qty,
                        backgroundColor: 'rgba(191,219,254,0.7)', borderColor: '#bfdbfe',
                        borderWidth: 1, borderRadius: 4, yAxisID: 'y', order: 3,
                    },
                    {
                        type: 'line', label: 'Total Tagihan', data: val.map(v => v / 1000),
                        borderColor: '#1d4ed8', borderWidth: 2, pointRadius: 3,
                        pointBackgroundColor: '#1d4ed8', tension: 0.3, fill: false,
                        backgroundColor: 'transparent', yAxisID: 'y2', order: 1,
                    },
                    {
                        type: 'line', label: 'Piutang', data: piutang.map(v => v / 1000),
                        borderColor: '#dc2626', borderWidth: 1.5, borderDash: [4, 3],
                        pointRadius: 2, pointBackgroundColor: '#dc2626', tension: 0.3, fill: false,
                        backgroundColor: 'transparent', yAxisID: 'y2', order: 1,
                    },
                    {
                        type: 'line', label: 'Rasio Lunas (%)', data: rasio,
                        borderColor: '#059669', borderWidth: 1.5, borderDash: [4, 3],
                        pointRadius: 2, pointBackgroundColor: '#059669', tension: 0.3, fill: false,
                        backgroundColor: 'transparent', yAxisID: 'y3', order: 1,
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => {
                        if (ctx.dataset.label === 'Rasio Lunas (%)') return `${ctx.dataset.label}: ${ctx.parsed.y}%`;
                        if (ctx.dataset.label === 'Total Tabung') return `${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString('id')} tab`;
                        return `${ctx.dataset.label}: Rp ${Math.round(ctx.parsed.y * 1000).toLocaleString('id-ID')}`;
                    }}},
                },
                scales: {
                    x:  { grid: { color: GRID }, ticks: { ...TICK, autoSkip: false, maxRotation: 30 } },
                    y:  { position: 'left', grid: { color: GRID }, ticks: TICK, title: { display: true, text: 'Tabung', color: '#9CA3AF', font: { size: 10 } } },
                    y2: { position: 'right', grid: { display: false }, ticks: { ...TICK, callback: v => Math.round(v) + 'k' }, title: { display: true, text: 'Ribuan Rp', color: '#1d4ed8', font: { size: 10 } } },
                    y3: { display: false, min: 0, max: 100 },
                },
            },
        });
    })();
    </script>
@endpush
