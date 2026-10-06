@extends('layouts.app')
@section('title', 'Analisis Antar Periode')

@section('content')

@php
    $bestQty = $grand['bestQtyPeriod'];

    // ── 3 baris × 4 cards ────────────────────────────────────────────────
    // Baris 1: Volume & Penjualan
    $kpiRow1 = [
        ['label' => 'Total Tabung', 'value' => number_format($grand['allQty']), 'sub' => $rows->count().' periode', 'color' => 'var(--melon-dark)', 'accent' => 'green'],
        ['label' => 'Total Tagihan', 'value' => 'Rp '.number_format($grand['allVal']), 'sub' => 'avg Rp '.number_format($rows->count() ? $grand['allVal'] / $rows->count() : 0).'/bln', 'color' => '#1d4ed8', 'accent' => 'blue'],
        ['label' => 'Total Piutang', 'value' => $grand['piutang'] > 0 ? 'Rp '.number_format($grand['piutang']) : '✓ Lunas', 'sub' => 'akumulasi seluruh periode', 'color' => $grand['piutang'] > 0 ? '#dc2626' : 'var(--melon-dark)', 'accent' => $grand['piutang'] > 0 ? 'red' : 'green'],
        ['label' => 'Total Margin', 'value' => 'Rp '.number_format($grand['allMargin']), 'sub' => 'tagihan − HPP', 'color' => '#059669', 'accent' => 'green'],
    ];
    // Baris 2: Profit & Kas
    $kpiRow2 = [
        ['label' => 'Profit Bersih', 'value' => 'Rp '.number_format($grand['profitBersih']), 'sub' => 'margin − ops − admin', 'color' => '#059669', 'accent' => 'green'],
        ['label' => 'Profit Kas', 'value' => 'Rp '.number_format($grand['profitKas']), 'sub' => 'netKas akhir − opening', 'color' => '#b45309', 'accent' => 'orange'],
        ['label' => 'Rekomendasi Ambil', 'value' => 'Rp '.number_format($grand['rekomendasiAmbil']), 'sub' => '70% profit kas (konservatif)', 'color' => '#6d28d9', 'accent' => 'purple'],
        ['label' => 'Profit Bebas', 'value' => 'Rp '.number_format($grand['profitBebas']), 'sub' => 'profit − utang Agen', 'color' => $grand['profitBebas'] >= 0 ? '#059669' : '#dc2626', 'accent' => $grand['profitBebas'] >= 0 ? 'green' : 'red'],
    ];
    // Baris 3: Kekayaan & Status
    $kpiRow3 = [
        ['label' => 'Uang Dipegang', 'value' => 'Rp '.number_format($grand['totalUangDipegangKini']), 'sub' => 'kas + bank + tabungan', 'color' => '#1d4ed8', 'accent' => 'blue'],
        ['label' => 'Total Kekayaan', 'value' => 'Rp '.number_format($grand['totalKekayaanKini']), 'sub' => '+ piutang + nilai stok', 'color' => '#7c3aed', 'accent' => 'purple'],
        ['label' => 'Periode Terbaik', 'value' => $bestQty['label'] ?? '-', 'sub' => number_format($bestQty['allQty'] ?? 0).' tab', 'color' => '#7c3aed', 'accent' => 'purple'],
        ['label' => 'Konsistensi', 'value' => $grand['adaAnomali'] ? '⚠ Selisih' : '✓ OK', 'sub' => 'cross-check antar modul', 'color' => $grand['adaAnomali'] ? '#dc2626' : 'var(--melon-dark)', 'accent' => $grand['adaAnomali'] ? 'red' : 'green'],
    ];
@endphp

@push('styles')
<style>
    /* KPI Compare — 4 kolom di desktop, responsif untuk mobile */
    .kpi-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    @media (max-width: 768px) {
        .kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 480px) {
        .kpi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

<div class="cmp-section">
    <div class="page-header">
        <span class="page-title">📈 Analisis Distribusi Antar Periode</span>
        <a href="{{ route('distributions.index') }}" class="btn-secondary btn-sm">← Kembali</a>
    </div>
</div>

{{-- KPI ROW 1: Volume & Penjualan (4 cards) --}}
<div class="kpi-grid cmp-section">
    @foreach($kpiRow1 as $kpi)
    <div class="kpi-card" data-accent="{{ $kpi['accent'] }}">
        <span class="kpi-label">{{ $kpi['label'] }}</span>
        <span class="kpi-value" style="color:{{ $kpi['color'] }};">{{ $kpi['value'] }}</span>
        <span class="kpi-sub">{{ $kpi['sub'] }}</span>
    </div>
    @endforeach
</div>

{{-- KPI ROW 2: Profit & Kas (4 cards) --}}
<div class="kpi-grid cmp-section">
    @foreach($kpiRow2 as $kpi)
    <div class="kpi-card" data-accent="{{ $kpi['accent'] }}">
        <span class="kpi-label">{{ $kpi['label'] }}</span>
        <span class="kpi-value" style="color:{{ $kpi['color'] }};">{{ $kpi['value'] }}</span>
        <span class="kpi-sub">{{ $kpi['sub'] }}</span>
    </div>
    @endforeach
</div>

{{-- KPI ROW 3: Kekayaan & Status (4 cards) --}}
<div class="kpi-grid cmp-section">
    @foreach($kpiRow3 as $kpi)
    <div class="kpi-card" data-accent="{{ $kpi['accent'] }}">
        <span class="kpi-label">{{ $kpi['label'] }}</span>
        <span class="kpi-value" style="color:{{ $kpi['color'] }};">{{ $kpi['value'] }}</span>
        <span class="kpi-sub">{{ $kpi['sub'] }}</span>
    </div>
    @endforeach
</div>

{{-- CHART --}}
<div class="s-card cmp-section">
    <div class="s-card-header">📊 Tren Distribusi, Tagihan & Piutang per Periode</div>
    <div style="padding:12px 14px;">
        <div class="legend" style="margin-bottom:8px;">
            <span class="legend-item"><span class="legend-sw" style="background:#bfdbfe;"></span>Tabung</span>
            <span class="legend-item"><span class="legend-line" style="background:#1d4ed8;"></span>Tagihan</span>
            <span class="legend-item"><span class="legend-line" style="background:#dc2626;border-top:2px dashed #dc2626;height:0;"></span>Piutang</span>
            <span class="legend-item"><span class="legend-line" style="background:#059669;border-top:2px dashed #059669;height:0;"></span>Rasio Lunas</span>
        </div>
        <div style="position:relative;width:100%;height:240px;">
            <canvas id="cmpTrendChart" role="img" aria-label="Chart tren per periode"></canvas>
        </div>
    </div>
</div>

{{-- TABEL 1 --}}
<div class="s-card cmp-section">
    <div class="s-card-header">📋 Penjualan & Distribusi per Periode</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Periode</th><th>Status</th>
                    <th class="r">Tabung</th><th class="r">Growth</th>
                    <th class="r">Tagihan</th><th class="r">Growth Nilai</th>
                    <th class="r">Kas Diterima</th><th class="r">Piutang</th>
                    <th class="r" style="background:#d1fae5;">Margin</th><th class="r">Lunas%</th>
                    <th class="r">Avg/Hari</th><th class="r">Hari</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr>
                    <td class="bold"><a href="{{ route('distributions.index', ['period_id' => $row['period_id']]) }}" style="color:var(--text1);text-decoration:none;">{{ $row['label'] }}</a></td>
                    <td>@if($row['status'] === 'open')<span class="badge badge-green">Open</span>@else<span class="badge" style="background:var(--surface2);color:var(--text3);">Closed</span>@endif</td>
                    <td class="r bold">{{ number_format($row['allQty']) }}</td>
                    <td class="r">@if($row['qtyGrowth']===null)<span class="growth-flat">—</span>@else<span class="{{ $row['qtyGrowth']>0?'growth-up':'growth-down' }}">{{ $row['qtyGrowth']>0?'▲':'▼' }} {{ abs($row['qtyGrowth']) }}%</span>@endif</td>
                    <td class="r">Rp {{ number_format($row['allVal']) }}</td>
                    <td class="r">@if($row['valGrowth']===null)<span class="growth-flat">—</span>@else<span class="{{ $row['valGrowth']>0?'growth-up':'growth-down' }}">{{ $row['valGrowth']>0?'▲':'▼' }} {{ abs($row['valGrowth']) }}%</span>@endif</td>
                    <td class="r" style="color:var(--melon-dark);">Rp {{ number_format($row['allPaid']) }}</td>
                    <td class="r" style="color:{{ $row['piutang']>0?'#dc2626':'var(--melon-dark)' }};font-weight:600;">{{ $row['piutang']>0?'Rp '.number_format($row['piutang']):'✓' }}</td>
                    <td class="r" style="background:#f0fdf4;color:#059669;font-weight:600;">Rp {{ number_format($row['allMargin']) }}</td>
                    <td class="r" style="color:{{ $row['rasioLunas']>=90?'var(--melon-dark)':($row['rasioLunas']>=70?'#d97706':'#dc2626') }};font-weight:600;">{{ number_format($row['rasioLunas'],1) }}%</td>
                    <td class="r">{{ $row['avgTabHar'] }} tab</td>
                    <td class="r">{{ $row['activeDays'] }}/{{ $row['daysInMonth'] }}</td>
                </tr>
                @empty
                <tr><td colspan="12" style="text-align:center;padding:24px;color:var(--text3);">Belum ada data.</td></tr>
                @endforelse
            </tbody>
            @if($rows->count()>0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="2">TOTAL / RATA-RATA</td>
                    <td class="r">{{ number_format($grand['allQty']) }}</td><td class="r"></td>
                    <td class="r">Rp {{ number_format($grand['allVal']) }}</td><td class="r"></td>
                    <td class="r">Rp {{ number_format($grand['allPaid']) }}</td>
                    <td class="r" style="color:{{ $grand['piutang']>0?'#dc2626':'var(--melon-dark)' }};">{{ $grand['piutang']>0?'Rp '.number_format($grand['piutang']):'✓' }}</td>
                    <td class="r" style="color:#059669;">Rp {{ number_format($grand['allMargin']) }}</td>
                    <td class="r">{{ number_format($rows->avg('rasioLunas'),1) }}%</td>
                    <td class="r">{{ number_format($rows->avg('avgTabHar'),1) }} tab</td>
                    <td class="r"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    @if($grand['adaAnomali'])
    <div style="padding:10px 14px;font-size:11px;color:#dc2626;background:#fef2f2;border-top:1px solid #fecaca;">
        ⚠ Selisih data antar modul: Income {{ number_format($r['selisihIncome']??0) }} | Margin {{ number_format($r['selisihMargin']??0) }} | DO {{ number_format($r['selisihDoQty']??0) }} | Surplus {{ number_format($r['selisihSurplus']??0) }}
        @foreach($rows->where('isKonsisten',false) as $r)
            <span style="display:inline-block;margin-top:4px;">• <strong>{{ $r['label'] }}</strong>: Income {{ number_format($r['selisihIncome']) }} | Margin {{ number_format($r['selisihMargin']) }} | DO {{ number_format($r['selisihDoQty']) }} | Surplus {{ number_format($r['selisihSurplus']) }}</span><br>
        @endforeach
    </div>
    @endif
</div>

{{-- TABEL 2: CASHFLOW --}}
<div class="s-card cmp-section">
    <div class="s-card-header">💰 Cashflow & Tabungan per Periode</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Periode</th><th class="r">Saldo Awal</th><th class="r">Pemasukan</th><th class="r">Pengeluaran</th>
                    <th class="r">TF Penampung</th><th class="r">Admin TF</th><th class="r">TF Utama</th>
                    <th class="r" style="background:#fef9c3;">Surplus</th><th class="r">Tab Keluar</th>
                    <th class="r" style="background:#ede9fe;">Saldo Tab</th><th class="r">Saldo KAS</th><th class="r">Saldo BANK</th>
                    <th class="r" style="background:#dbeafe;">Net Total</th><th class="r">Growth</th><th class="r">Ops%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    <td class="bold">{{ $row['label'] }}</td>
                    <td class="r" style="color:#b45309;">{{ $row['cfOpeningCash']>0?'Rp '.number_format($row['cfOpeningCash']):'—' }}</td>
                    <td class="r">Rp {{ number_format($row['cfIncome']) }}</td>
                    <td class="r">Rp {{ number_format($row['cfExpense']) }}</td>
                    <td class="r">Rp {{ number_format($row['cfDeposits']) }}</td>
                    <td class="r" style="color:#1d4ed8;">{{ $row['cfAdminFees']>0?'Rp '.number_format($row['cfAdminFees']):'—' }}</td>
                    <td class="r">{{ $row['cfTransferred']>0?'Rp '.number_format($row['cfTransferred']):'—' }}</td>
                    <td class="r" style="background:#fefce8;color:#b45309;font-weight:600;">{{ $row['cfSurplus']>0?'Rp '.number_format($row['cfSurplus']):'—' }}</td>
                    <td class="r" style="color:#dc2626;">{{ $row['svOut']>0?'Rp '.number_format($row['svOut']):'—' }}</td>
                    <td class="r bold" style="background:#f5f3ff;color:{{ $row['svBalance']>=0?'#6d28d9':'#dc2626' }};">Rp {{ number_format($row['svBalance']) }}</td>
                    <td class="r" style="color:{{ $row['cfNetKas']>=0?'var(--melon-dark)':'#dc2626' }};">Rp {{ number_format($row['cfNetKas']) }}</td>
                    <td class="r" style="color:{{ $row['cfBankBal']>=0?'#4338ca':'#dc2626' }};">Rp {{ number_format($row['cfBankBal']) }}</td>
                    <td class="r bold" style="background:#eff6ff;color:{{ $row['cfNetTotal']>=0?'var(--melon-dark)':'#dc2626' }};">Rp {{ number_format($row['cfNetTotal']) }}</td>
                    <td class="r">@if($row['cfNetTotalGrowth']===null)<span class="growth-flat">—</span>@else<span class="{{ $row['cfNetTotalGrowth']>0?'growth-up':'growth-down' }}">{{ $row['cfNetTotalGrowth']>0?'▲':'▼' }} {{ abs($row['cfNetTotalGrowth']) }}%</span>@endif</td>
                    <td class="r">{{ $row['cfRasioOps'] }}%</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td>TOTAL</td><td class="r"></td>
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
                    <td class="r bold">Rp {{ number_format($grand['cfNetTotalTerakhir']) }} <span style="font-weight:400;font-size:9px;">(kini)</span></td>
                    <td class="r"></td><td class="r"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- TABEL 3: REKONSILIASI --}}
<div class="s-card cmp-section">
    <div class="s-card-header">🧮 Rekonsiliasi Penjualan vs Transfer</div>
    <div style="padding:6px 14px 0;font-size:10px;color:var(--text3);">Penjualan − TF Penampung − Admin TF − Ops − TF Rek Utama = Selisih</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Periode</th><th class="r">Penjualan</th><th class="r">TF Penampung</th><th class="r">Admin TF</th>
                    <th class="r">Ops</th><th class="r">TF Utama</th><th class="r" style="background:#d1fae5;">Margin</th>
                    <th class="r" style="background:#fef9c3;">Selisih</th><th class="r" style="background:#fef9c3;">Sel TF</th>
                    <th class="r" style="background:#ede9fe;">Margin−Ops−Admin−Sel TF</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr>
                    <td class="bold">{{ $row['label'] }}</td>
                    <td class="r">Rp {{ number_format($row['cfIncome']) }}</td>
                    <td class="r">{{ $row['cfDeposits']>0?'Rp '.number_format($row['cfDeposits']):'—' }}</td>
                    <td class="r" style="color:#1d4ed8;">{{ $row['cfAdminFees']>0?'Rp '.number_format($row['cfAdminFees']):'—' }}</td>
                    <td class="r" style="color:#dc2626;">{{ $row['cfExpense']>0?'Rp '.number_format($row['cfExpense']):'—' }}</td>
                    <td class="r">{{ $row['cfTransferred']>0?'Rp '.number_format($row['cfTransferred']):'—' }}</td>
                    <td class="r bold" style="background:#f0fdf4;color:#059669;">Rp {{ number_format($row['cfMargin']) }}</td>
                    <td class="r bold" style="background:#fefce8;color:{{ $row['cfSelisihRekon']>=0?'#059669':'#dc2626' }};">{{ $row['cfSelisihRekon']>=0?'Rp '.number_format($row['cfSelisihRekon']):'− Rp '.number_format(abs($row['cfSelisihRekon'])) }}</td>
                    <td class="r bold" style="background:#fefce8;color:{{ $row['cfSelisihPenampungVsRekeningUtama']>=0?'#059669':'#dc2626' }};">{{ $row['cfSelisihPenampungVsRekeningUtama']>=0?'Rp '.number_format($row['cfSelisihPenampungVsRekeningUtama']):'− Rp '.number_format(abs($row['cfSelisihPenampungVsRekeningUtama'])) }}</td>
                    <td class="r bold" style="background:#f5f3ff;color:{{ $row['cfSelisihMarginOpsAdminTf']>=0?'#6d28d9':'#dc2626' }};">{{ $row['cfSelisihMarginOpsAdminTf']>=0?'Rp '.number_format($row['cfSelisihMarginOpsAdminTf']):'− Rp '.number_format(abs($row['cfSelisihMarginOpsAdminTf'])) }}</td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;padding:24px;color:var(--text3);">Belum ada data.</td></tr>
                @endforelse
            </tbody>
            @if($rows->count()>0)
            <tfoot>
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="r">Rp {{ number_format($grand['cfIncome']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfDeposits']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfAdminFees']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfExpense']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfTransferred']) }}</td>
                    <td class="r bold">Rp {{ number_format($grand['cfMargin']) }}</td>
                    <td class="r bold">{{ $grand['cfSelisihRekon']>=0?'Rp '.number_format($grand['cfSelisihRekon']):'− Rp '.number_format(abs($grand['cfSelisihRekon'])) }}</td>
                    <td class="r bold">{{ $grand['cfSelisihPenampungVsRekeningUtama']>=0?'Rp '.number_format($grand['cfSelisihPenampungVsRekeningUtama']):'− Rp '.number_format(abs($grand['cfSelisihPenampungVsRekeningUtama'])) }}</td>
                    <td class="r bold">{{ $grand['cfSelisihMarginOpsAdminTf']>=0?'Rp '.number_format($grand['cfSelisihMarginOpsAdminTf']):'− Rp '.number_format(abs($grand['cfSelisihMarginOpsAdminTf'])) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- TABEL 4: STOK DO vs TRANSFER --}}
<div class="s-card cmp-section">
    <div class="s-card-header">📦 Stok DO vs Pendanaan Transfer</div>
    <div style="padding:6px 14px 0;font-size:10px;color:var(--text3);">(DO Diterima × Rp16.000) − TF Rek Utama = Selisih</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Periode</th><th class="r">Stok Awal</th><th class="r">DO Diterima</th><th class="r">Total DO</th>
                    <th class="r">Distribusi</th><th class="r">Sisa Stok</th><th class="r" style="background:#fee2e2;">DO×16k</th>
                    <th class="r">Kas Diterima</th><th class="r">TF Penampung</th><th class="r">Admin TF</th><th class="r">TF Utama</th>
                    <th class="r" style="background:#fef9c3;">Selisih</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr>
                    <td class="bold">{{ $row['label'] }}</td>
                    <td class="r" style="color:var(--text2);">{{ number_format($row['openingStock']) }}</td>
                    <td class="r bold">{{ number_format($row['doReceivedQty']) }}</td>
                    <td class="r">{{ number_format($row['doTotalQty']) }}</td>
                    <td class="r">{{ number_format($row['doDistributed']) }}</td>
                    <td class="r" style="color:{{ $row['doSisaStok']<0?'#dc2626':'var(--text2)' }};">{{ number_format($row['doSisaStok']) }}</td>
                    <td class="r bold" style="background:#fef2f2;color:#b91c1b;">Rp {{ number_format($row['doReceivedHpp']) }}</td>
                    <td class="r" style="color:var(--melon-dark);">Rp {{ number_format($row['allPaid']) }}</td>
                    <td class="r">{{ $row['cfDeposits']>0?'Rp '.number_format($row['cfDeposits']):'—' }}</td>
                    <td class="r" style="color:#1d4ed8;">{{ $row['cfAdminFees']>0?'Rp '.number_format($row['cfAdminFees']):'—' }}</td>
                    <td class="r">{{ $row['cfTransferred']>0?'Rp '.number_format($row['cfTransferred']):'—' }}</td>
                    <td class="r bold" style="background:#fefce8;color:{{ $row['selisihDoReceivedVsTransfer']>=0?'#059669':'#dc2626' }};">{{ $row['selisihDoReceivedVsTransfer']>=0?'Rp '.number_format($row['selisihDoReceivedVsTransfer']):'− Rp '.number_format(abs($row['selisihDoReceivedVsTransfer'])) }}</td>
                </tr>
                @empty
                <tr><td colspan="12" style="text-align:center;padding:24px;color:var(--text3);">Belum ada data.</td></tr>
                @endforelse
            </tbody>
            @if($rows->count()>0)
            <tfoot>
                <tr class="total-row">
                    <td>TOTAL</td><td class="r">{{ number_format($rows->sum('openingStock')) }}</td>
                    <td class="r">{{ number_format($grand['doReceivedQty']) }}</td><td class="r">{{ number_format($grand['doTotalQty']) }}</td>
                    <td class="r">{{ number_format($grand['doDistributed']) }}</td><td class="r"></td>
                    <td class="r bold">Rp {{ number_format($grand['doReceivedHpp']) }}</td>
                    <td class="r">Rp {{ number_format($grand['allPaid']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfDeposits']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfAdminFees']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfTransferred']) }}</td>
                    <td class="r bold">{{ $grand['selisihDoReceivedVsTransfer']>=0?'Rp '.number_format($grand['selisihDoReceivedVsTransfer']):'− Rp '.number_format(abs($grand['selisihDoReceivedVsTransfer'])) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- TABEL 5: PROFIT BEBAS --}}
<div class="s-card cmp-section">
    <div class="s-card-header">💵 Profit Bebas per Periode</div>
    <div style="padding:6px 14px 0;font-size:10px;color:var(--text3);">Profit Bersih − Kenaikan Utang DO = Profit Bebas</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Periode</th><th class="r" style="background:#d1fae5;">Margin</th><th class="r">Ops</th><th class="r">Admin TF</th>
                    <th class="r" style="background:#eff6ff;">Profit Bersih</th><th class="r" style="background:#fef3c7;">Profit Kas</th>
                    <th class="r">Utang DO Naik</th><th class="r" style="background:#ede9fe;">Profit Bebas</th>
                    <th class="r" style="background:#d1fae5;">Ambil 70%</th><th class="r">Utang Akhir</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr>
                    <td class="bold">{{ $row['label'] }}</td>
                    <td class="r" style="background:#f0fdf4;color:#059669;">Rp {{ number_format($row['allMargin']) }}</td>
                    <td class="r" style="color:#dc2626;">{{ $row['cfExpense']>0?'Rp '.number_format($row['cfExpense']):'—' }}</td>
                    <td class="r" style="color:#1d4ed8;">{{ $row['cfAdminFees']>0?'Rp '.number_format($row['cfAdminFees']):'—' }}</td>
                    <td class="r bold" style="background:#eff6ff;color:{{ $row['profitBersih']>=0?'#1d4ed8':'#dc2626' }};">Rp {{ number_format($row['profitBersih']) }}</td>
                    <td class="r" style="color:{{ $row['profitKas']>=0?'#b45309':'#dc2626' }};">{{ $row['profitKas']>=0?'Rp '.number_format($row['profitKas']):'− Rp '.number_format(abs($row['profitKas'])) }}</td>
                    <td class="r" style="color:{{ $row['doPayable']>0?'#dc2626':'#059669' }};">{{ $row['doPayable']>0?'− Rp '.number_format($row['doPayable']):($row['doPayable']<0?'+ Rp '.number_format(abs($row['doPayable'])):'—') }}</td>
                    <td class="r bold" style="background:#f5f3ff;color:{{ $row['profitBebasPeriode']>=0?'#6d28d9':'#dc2626' }};">Rp {{ number_format($row['profitBebasPeriode']) }}</td>
                    <td class="r bold" style="color:#059669;">Rp {{ number_format($row['rekomendasiAmbil']) }}</td>
                    <td class="r" style="color:{{ $row['doPayableAkhir']>0?'#dc2626':'var(--melon-dark)' }};">{{ $row['doPayableAkhir']>0?'Rp '.number_format($row['doPayableAkhir']):'✓ Lunas' }}</td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;padding:24px;color:var(--text3);">Belum ada data.</td></tr>
                @endforelse
            </tbody>
            @if($rows->count()>0)
            <tfoot>
                <tr class="total-row">
                    <td>TOTAL</td><td class="r">Rp {{ number_format($grand['allMargin']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfExpense']) }}</td>
                    <td class="r">Rp {{ number_format($grand['cfAdminFees']) }}</td>
                    <td class="r bold">Rp {{ number_format($grand['profitBersih']) }}</td>
                    <td class="r"></td><td class="r"></td>
                    <td class="r bold" style="color:{{ $grand['profitBebas']>=0?'#6d28d9':'#dc2626' }};">Rp {{ number_format($grand['profitBebas']) }}</td>
                    <td class="r bold" style="color:#059669;">Rp {{ number_format($grand['rekomendasiAmbil']) }}</td>
                    <td class="r">{{ $grand['doPayableAkhir']>0?'Rp '.number_format($grand['doPayableAkhir']):'✓ Lunas' }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- ALUR DANA CARD — Mapping Struktur Aliran Dana → Profit --}}
@if($rows->count()>0)
<div class="s-card cmp-section">
    <div class="s-card-header">🔄 Alur Dana: Dari Penjualan Hingga Profit Bebas</div>
    <div style="padding:16px 14px;">
        <div style="display:flex;flex-direction:column;gap:12px;">

            <!-- Tahap 1: Penjualan → Kas + Piutang -->
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;min-width:140px;">
                    <div style="font-size:10px;color:#1d4ed8;font-weight:600;">PENJUALAN (TAGIHAN)</div>
                    <div style="font-size:16px;font-weight:700;color:#1d4ed8;">Rp {{ number_format($grand['allVal']) }}</div>
                    <div style="font-size:9px;color:#64748b;">{{ number_format($grand['allQty']) }} tabung</div>
                </div>
                <div style="font-size:18px;color:#94a3b8;">→</div>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;min-width:140px;">
                    <div style="font-size:10px;color:#059669;font-weight:600;">KAS DITERIMA</div>
                    <div style="font-size:16px;font-weight:700;color:#059669;">Rp {{ number_format($grand['allPaid']) }}</div>
                    <div style="font-size:9px;color:#64748b;">{{ $grand['allVal'] > 0 ? number_format($grand['allPaid'] / $grand['allVal'] * 100, 1) : 0 }}% lunas</div>
                </div>
                <div style="font-size:18px;color:#94a3b8;">+</div>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;min-width:140px;">
                    <div style="font-size:10px;color:#dc2626;font-weight:600;">PIUTANG</div>
                    <div style="font-size:16px;font-weight:700;color:#dc2626;">Rp {{ number_format($grand['piutang']) }}</div>
                    <div style="font-size:9px;color:#64748b;">belum terbayar</div>
                </div>
            </div>

            <!-- Tahap 2: Margin → Profit Bersih -->
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;min-width:140px;">
                    <div style="font-size:10px;color:#059669;font-weight:600;">MARGIN (KOTOR)</div>
                    <div style="font-size:16px;font-weight:700;color:#059669;">Rp {{ number_format($grand['allMargin']) }}</div>
                    <div style="font-size:9px;color:#64748b;">penjualan − HPP 16rb/tab</div>
                </div>
                <div style="font-size:18px;color:#94a3b8;">−</div>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;min-width:140px;">
                    <div style="font-size:10px;color:#dc2626;font-weight:600;">OPS + ADMIN</div>
                    <div style="font-size:16px;font-weight:700;color:#dc2626;">Rp {{ number_format($grand['cfExpense'] + $grand['cfAdminFees']) }}</div>
                    <div style="font-size:9px;color:#64748b;">ops {{ number_format($grand['cfExpense']) }} + admin {{ number_format($grand['cfAdminFees']) }}</div>
                </div>
                <div style="font-size:18px;color:#94a3b8;">=</div>
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;min-width:140px;">
                    <div style="font-size:10px;color:#1d4ed8;font-weight:600;">PROFIT BERSIH</div>
                    <div style="font-size:16px;font-weight:700;color:#1d4ed8;">Rp {{ number_format($grand['profitBersih']) }}</div>
                    <div style="font-size:9px;color:#64748b;">margin − ops − admin</div>
                </div>
            </div>

            <!-- Tahap 3: Profit Bersih → Profit Kas → Profit Bebas -->
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;min-width:140px;">
                    <div style="font-size:10px;color:#1d4ed8;font-weight:600;">PROFIT BERSIH</div>
                    <div style="font-size:16px;font-weight:700;color:#1d4ed8;">Rp {{ number_format($grand['profitBersih']) }}</div>
                    <div style="font-size:9px;color:#64748b;">dari margin</div>
                </div>
                <div style="font-size:18px;color:#94a3b8;">→</div>
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;min-width:140px;">
                    <div style="font-size:10px;color:#b45309;font-weight:600;">PROFIT KAS</div>
                    <div style="font-size:16px;font-weight:700;color:#b45309;">Rp {{ number_format($grand['profitKas']) }}</div>
                    <div style="font-size:9px;color:#64748b;">netKas − opening</div>
                </div>
                <div style="font-size:18px;color:#94a3b8;">−</div>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;min-width:140px;">
                    <div style="font-size:10px;color:#dc2626;font-weight:600;">UTANG DO</div>
                    <div style="font-size:16px;font-weight:700;color:#dc2626;">Rp {{ number_format($grand['doPayableAkhir']) }}</div>
                    <div style="font-size:9px;color:#64748b;">utang ke agen</div>
                </div>
                <div style="font-size:18px;color:#94a3b8;">=</div>
                <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:10px 14px;min-width:140px;border-width:2px;">
                    <div style="font-size:10px;color:#6d28d9;font-weight:600;">PROFIT BEBAS</div>
                    <div style="font-size:16px;font-weight:700;color:#6d28d9;">Rp {{ number_format($grand['profitBebas']) }}</div>
                    <div style="font-size:9px;color:#64748b;">profit − utang Agen</div>
                </div>
            </div>

            <!-- Summary bar -->
            <div style="background:linear-gradient(135deg,#f0fdf4,#eff6ff);border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin-top:4px;">
                <div style="font-size:11px;font-weight:700;color:#059669;margin-bottom:6px;">📊 Ringkasan Aliran Dana</div>
                <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:10px;color:#475569;">
                    <span>Penjualan: <strong>Rp {{ number_format($grand['allVal']) }}</strong></span>
                    <span>→ Kas: <strong>Rp {{ number_format($grand['allPaid']) }}</strong></span>
                    <span>→ Margin: <strong>Rp {{ number_format($grand['allMargin']) }}</strong></span>
                    <span>→ Profit Bersih: <strong>Rp {{ number_format($grand['profitBersih']) }}</strong></span>
                    <span>→ Profit Bebas: <strong style="color:#6d28d9;">Rp {{ number_format($grand['profitBebas']) }}</strong></span>
                </div>
            </div>

        </div>
    </div>
</div>
@endif

{{-- FLOW CARD --}}
@if($rows->count()>0)
<div class="s-card cmp-section">
    <div class="s-card-header">🔄 Aliran Kas {{ $rows->first()['label'] }} → {{ $rows->last()['label'] }}</div>
    <div style="padding:16px 14px;">
        <div class="flow-card">
            <div class="flow-stage">
                <div class="flow-box"><div class="flow-label">Saldo Awal</div><div class="flow-value" style="color:#1d4ed8;">Rp {{ number_format($rows->first()['cfOpeningCash']??0) }}</div></div>
                <div class="flow-op">+</div>
                <div class="flow-box"><div class="flow-label">Pendapatan</div><div class="flow-value" style="color:#059669;">Rp {{ number_format($grand['cfIncome']) }}</div></div>
                <div class="flow-op">−</div>
                <div class="flow-box"><div class="flow-label">Pengeluaran</div><div class="flow-value" style="color:#dc2626;">Rp {{ number_format($grand['cfExpense']) }}</div></div>
                <div class="flow-op">=</div>
                <div class="flow-box result"><div class="flow-label">Net Kas</div><div class="flow-value" style="color:#b45309;">Rp {{ number_format($grand['cfNetKas']) }}</div></div>
            </div>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
    (function () {
        const ctx = document.getElementById('cmpTrendChart');
        if (!ctx) return;

        const labels = @json($rows->pluck('label'));
        const qty = @json($rows->pluck('allQty'));
        const val = @json($rows->pluck('allVal'));
        const paid = @json($rows->pluck('allPaid'));
        const piutang = @json($rows->pluck('piutang'));
        const rasioLunas = @json($rows->pluck('rasioLunas'));

        const cs = getComputedStyle(document.documentElement);
        const BLUE = '#2563eb';
        const RED = '#dc2626';
        const GREEN = '#059669';
        const PURPLE = '#7c3aed';
        const TICK = { font: { size: 10 }, color: '#9CA3AF' };

        new Chart(ctx, {
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Tabung',
                        data: qty,
                        backgroundColor: BLUE + 'cc',
                        borderRadius: 4,
                        order: 3,
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: 'Tagihan (Rp)',
                        data: val,
                        borderColor: GREEN,
                        backgroundColor: 'transparent',
                        pointBackgroundColor: GREEN,
                        pointRadius: 4,
                        tension: 0.3,
                        borderWidth: 2,
                        order: 1,
                        yAxisID: 'y1',
                    },
                    {
                        type: 'line',
                        label: 'Piutang (Rp)',
                        data: piutang,
                        borderColor: RED,
                        backgroundColor: 'transparent',
                        pointBackgroundColor: RED,
                        pointRadius: 4,
                        tension: 0.3,
                        borderWidth: 2,
                        borderDash: [5, 3],
                        order: 2,
                        yAxisID: 'y1',
                    },
                    {
                        type: 'line',
                        label: 'Rasio Lunas (%)',
                        data: rasioLunas,
                        borderColor: PURPLE,
                        backgroundColor: 'transparent',
                        pointBackgroundColor: PURPLE,
                        pointRadius: 4,
                        tension: 0.3,
                        borderWidth: 2,
                        order: 0,
                        yAxisID: 'y2',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const v = ctx.parsed.y;
                                if (ctx.datasetIndex === 0) return 'Tabung: ' + v.toLocaleString('id-ID') + ' tab';
                                if (ctx.datasetIndex === 3) return 'Rasio Lunas: ' + v.toFixed(1) + '%';
                                return ctx.dataset.label + ': Rp ' + v.toLocaleString('id-ID');
                            },
                        },
                    },
                },
                scales: {
                    x: { ticks: TICK, grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: TICK,
                        title: { display: true, text: 'Tabung', font: { size: 10 }, color: '#888' },
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        ticks: { ...TICK, callback: function (v) { return 'Rp ' + (v / 1000000).toFixed(0) + 'jt'; } },
                        title: { display: true, text: 'Rp', font: { size: 10 }, color: '#888' },
                        grid: { drawOnChartArea: false },
                    },
                    y2: {
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        ticks: { ...TICK, callback: function (v) { return v + '%'; } },
                        title: { display: true, text: 'Lunas', font: { size: 10 }, color: PURPLE },
                        grid: { drawOnChartArea: false },
                    },
                },
            },
        });
    })();
</script>
@endpush

@endsection