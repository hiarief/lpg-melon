@extends('layouts.app')
@section('title', 'DO Agen')
@section('content')

{{-- ══════════════════════════════════════════════════════════════
     HEADER & PERIOD SELECTOR
══════════════════════════════════════════════════════════════ --}}
<div class="page-header-row">
    <div class="page-header-left">
        <span class="page-title">📦 DO Agen</span>

        <form method="GET" action="{{ route('do.index') }}">
            <select name="period_id" onchange="this.form.submit()" class="field-select field-select-inline">
                @foreach($periods as $p)
                    <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                @endforeach
            </select>
        </form>

        @if($period->status === 'open')
            <span class="badge badge-green">🟢 Buka</span>
        @else
            <span class="badge badge-muted">🔒 Tutup</span>
        @endif
    </div>

    @if($period->status === 'open')
        <a href="{{ route('do.create', ['period_id' => $period->id]) }}" class="btn-primary btn-sm">+ Input DO</a>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════
     KALKULASI ANALISIS (PHP Block Terpusat)
══════════════════════════════════════════════════════════════ --}}
@php
    // ── Ringkasan DO Bulan Ini ────────────────────────────────────────────────
    $grandTotal   = $dos->sum('qty');
    $grandValue   = $dos->sum(fn ($d) => $d->qty * $d->price_per_unit);
    $grandBayar   = $dos->sum(fn ($d) => $d->paid_amount + $d->transfers->sum('surplus'));
    $grandSurplus = $dos->sum(fn ($d) => $d->transfers->sum('surplus'));
    $grandPiutang = $grandValue - $grandBayar;

    // ── Carry-Over ───────────────────────────────────────────────────────────
    $coValue   = $carryoverDOs->sum(fn ($d) => $d->qty * $d->price_per_unit);
    $coBayar   = $carryoverDOs->sum(fn ($d) => $d->paid_amount + $d->transfers->sum('surplus'));
    $coPiutang = $carryoverDOs->sum(fn ($d) => $d->remainingAmount());

    // ── Totals Gabungan ───────────────────────────────────────────────────────
    $totalNilaiAll   = $grandValue + $coValue;
    $totalBayarAll   = $grandBayar + $coBayar;
    $totalPiutangAll = $grandPiutang + $coPiutang;
    $rasioLunas      = $totalNilaiAll > 0 ? $totalBayarAll / $totalNilaiAll * 100 : 0;
    $pctPiutang      = $totalNilaiAll > 0 ? $totalPiutangAll / $totalNilaiAll * 100 : 0;
    $pctCarryover    = $totalPiutangAll > 0 ? round($coPiutang / $totalPiutangAll * 100) : 0;

    // ── Data Harian (untuk chart) ─────────────────────────────────────────────
    $hariDOLabels  = [];
    $hariDONilai   = [];
    $hariDOBayar   = [];
    $hariDOPiutang = [];

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = sprintf('%04d-%02d-%02d', $period->year, $period->month, $d);
        $dayDOs  = $dos->filter(fn ($do) => $do->do_date->format('Y-m-d') === $dateStr);
        $dv      = $dayDOs->sum(fn ($do) => $do->qty * $do->price_per_unit);
        $dp      = $dayDOs->sum(fn ($do) => $do->paid_amount + $do->transfers->sum('surplus'));

        if ($dv > 0) {
            $hariDOLabels[]  = $d;
            $hariDONilai[]   = $dv;
            $hariDOBayar[]   = $dp;
            $hariDOPiutang[] = $dv - $dp;
        }
    }

    // ── Proyeksi ──────────────────────────────────────────────────────────────
    $activeDaysDO = max(count($hariDOLabels), 1);
    $hariIni      = now()->day;
    $sisaHari     = $daysInMonth - $hariIni;
    $rasioAktif   = $hariIni > 0 ? $activeDaysDO / $hariIni : 0;

    $avgPerHariAktif     = $activeDaysDO > 0 ? $grandTotal / $activeDaysDO : 0;
    $estHariAktifSisa    = round($sisaHari * $rasioAktif);
    $proyeksiSisa        = round($avgPerHariAktif * $estHariAktifSisa);
    $proyeksiRealistis   = $grandTotal + $proyeksiSisa;
    $proyeksiOptimis     = round($proyeksiRealistis * 1.20);
    $proyeksiKonservatif = round($proyeksiRealistis * 0.80);
    $proyeksiTotal       = $grandValue + round(($grandValue / max($activeDaysDO, 1)) * $estHariAktifSisa);

    // ── Perbandingan vs Bulan Lalu ────────────────────────────────────────────
    $prevTotal = $prevDOs->sum('qty');
    $selisih   = $proyeksiRealistis - $prevTotal;
    $vsLalu    = $prevTotal > 0 ? round($proyeksiRealistis / $prevTotal * 100) : null;

    // ── Progress ──────────────────────────────────────────────────────────────
    $pctJalan    = $daysInMonth > 0 ? round($hariIni / $daysInMonth * 100) : 0;
    $pctTercapai = $proyeksiRealistis > 0 ? min(round($grandTotal / $proyeksiRealistis * 100), 100) : 0;

    // ── Status DO ────────────────────────────────────────────────────────────
    $doLunas    = $dos->where('payment_status', 'paid')->count();
    $doSebagian = $dos->where('payment_status', 'partial')->count();
    $doBelum    = $dos->where('payment_status', 'unpaid')->count();

    // ── Volume per Pangkalan (untuk chart bar) ────────────────────────────────
    $pangkalanBar = $outlets->map(fn ($o) => [
        'name'      => $o->name,
        'qty_new'   => $dos->where('outlet_id', $o->id)->sum('qty'),
        'qty_carry' => $carryoverDOs->where('outlet_id', $o->id)->sum('qty'),
    ])->filter(fn ($o) => $o['qty_new'] + $o['qty_carry'] > 0)->values();

    // ── Ranking Piutang per Pangkalan ────────────────────────────────────────
    $rankPiutangDO = $outlets->map(function ($outlet) use ($dos, $carryoverDOs) {
        $outletDOs = $dos->where('outlet_id', $outlet->id);
        $coOutlet  = $carryoverDOs->where('outlet_id', $outlet->id);

        $nilai  = $outletDOs->sum(fn ($d) => $d->qty * $d->price_per_unit)
                + $coOutlet->sum(fn ($d) => $d->qty * $d->price_per_unit);
        $bayar  = $outletDOs->sum(fn ($d) => $d->paid_amount + $d->transfers->sum('surplus'))
                + $coOutlet->sum(fn ($d) => $d->paid_amount + $d->transfers->sum('surplus'));
        $piutang = $nilai - $bayar;

        return [
            'name'         => $outlet->name,
            'qty'          => $outletDOs->sum('qty'),
            'nilai'        => $nilai,
            'bayar'        => $bayar,
            'piutang'      => $piutang,
            'pct_lunas'    => $nilai > 0 ? round($bayar / $nilai * 100) : 100,
            'has_carryover'=> $coOutlet->count() > 0,
        ];
    })->filter(fn ($o) => $o['piutang'] > 0)->sortByDesc('piutang')->values();

    // ── Proyeksi per Pangkalan ────────────────────────────────────────────────
    $proyeksiPerPangkalan = $outlets->map(function ($outlet) use ($dos, $prevDOs, $hariIni, $daysInMonth, $rasioAktif) {
        $outletDOs = $dos->where('outlet_id', $outlet->id);
        $qtySkrg   = $outletDOs->sum('qty');
        $hariAktif = $outletDOs->groupBy(fn ($d) => $d->do_date->format('Y-m-d'))->count();
        $avg       = $hariAktif > 0 ? $qtySkrg / $hariAktif : 0;
        $estSisa   = round(($daysInMonth - $hariIni) * ($hariAktif / max($hariIni, 1)));
        $proyeksi  = round($qtySkrg + $avg * $estSisa);
        $qtyLalu   = $prevDOs->where('outlet_id', $outlet->id)->sum('qty');
        $pace      = $qtyLalu > 0 ? round($proyeksi / $qtyLalu * 100) : null;

        return compact('outlet', 'qtySkrg', 'proyeksi', 'qtyLalu', 'pace', 'avg');
    })->filter(fn ($r) => $r['qtySkrg'] > 0 || $r['qtyLalu'] > 0)->values();

    // ── Indikator Kesehatan ───────────────────────────────────────────────────
    $totalQtyBar    = $pangkalanBar->sum('qty_new');
    $topPangkalan   = $rankPiutangDO->first()['name'] ?? '-';
    $rasioAktifDO   = round($activeDaysDO / 26 * 100);
    $topQty         = $pangkalanBar->max('qty_new') ?? 0;
    $konsentrasiDO  = $totalQtyBar > 0 ? round($topQty / $totalQtyBar * 100) : 0;

    // ── Helper Warna Pace ─────────────────────────────────────────────────────
    $warna = fn ($pct) => match (true) {
        $pct === null => ['bg' => '#f0f0f0', 'text' => '#666'],
        $pct >= 90    => ['bg' => '#E1F5EE', 'text' => '#0F6E56'],
        $pct >= 70    => ['bg' => '#FAEEDA', 'text' => '#854F0B'],
        default       => ['bg' => '#FCEBEB', 'text' => '#A32D2D'],
    };
@endphp


{{-- ══════════════════════════════════════════════════════════════
     REKAP DO PER TANGGAL
══════════════════════════════════════════════════════════════ --}}
<div class="s-card">
    <div class="s-card-header">📅 Rekap DO per Tanggal</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th class="sticky-col-header">Pangkalan</th>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        <th class="day-cell-th">{{ $d }}</th>
                    @endfor
                    <th class="r">Total</th>
                    <th class="r">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outlets as $outlet)
                @php
                    $outletDOs   = $dos->where('outlet_id', $outlet->id);
                    $outletTotal = $outletDOs->sum('qty');
                    $outletValue = $outletDOs->sum(fn ($d) => $d->qty * $d->price_per_unit);
                @endphp
                <tr>
                    <td class="bold sticky-col sticky-col-body">{{ $outlet->name }}</td>

                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dateStr = sprintf('%04d-%02d-%02d', $period->year, $period->month, $day);
                        $dayQty  = $outletDOs->filter(fn ($d) => $d->do_date->format('Y-m-d') === $dateStr)->sum('qty');
                    @endphp
                    <td class="day-cell {{ $dayQty > 0 ? 'day-cell-active' : 'day-cell-empty' }}">
                        {{ $dayQty ?: '-' }}
                    </td>
                    @endfor

                    <td class="r bold {{ $outletTotal > 0 ? 'text-melon' : 'text-muted' }}">
                        {{ $outletTotal > 0 ? number_format($outletTotal) : '-' }}
                    </td>
                    <td class="r text-secondary">
                        {{ $outletTotal > 0 ? 'Rp '.number_format($outletValue) : '-' }}
                    </td>
                </tr>
                @endforeach

                {{-- Row Total --}}
                <tr class="total-row">
                    <td class="bold sticky-col sticky-col-total">TOTAL</td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dateStr  = sprintf('%04d-%02d-%02d', $period->year, $period->month, $day);
                        $dayTotal = $dos->filter(fn ($d) => $d->do_date->format('Y-m-d') === $dateStr)->sum('qty');
                    @endphp
                    <td class="day-cell">{{ $dayTotal ?: '-' }}</td>
                    @endfor
                    <td class="r">{{ number_format($grandTotal) }}</td>
                    <td class="r">Rp {{ number_format($grandValue) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     KPI CARDS
══════════════════════════════════════════════════════════════ --}}
<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="kpi-label">Total DO Diterima</div>
        <div class="kpi-value text-orange">{{ number_format($grandTotal) }} tab</div>
        <div class="kpi-sub">Rp {{ number_format($grandValue) }}</div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-label">Total Terbayar ke Agen</div>
        <div class="kpi-value text-melon">Rp {{ number_format($totalBayarAll) }}</div>
        <div class="kpi-sub">{{ number_format($rasioLunas, 1) }}% dari nilai DO</div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-label">Piutang ke Agen</div>
        <div class="kpi-value {{ $totalPiutangAll > 0 ? 'text-red' : 'text-melon' }}">
            {{ $totalPiutangAll > 0 ? 'Rp '.number_format($totalPiutangAll) : '✓ Lunas' }}
        </div>
        <div class="kpi-sub">{{ number_format($pctPiutang, 1) }}% · termasuk carry-over</div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-label">Surplus Transfer</div>
        <div class="kpi-value text-blue">Rp {{ number_format($grandSurplus) }}</div>
        <div class="kpi-sub">dari transfer ke rek utama</div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     PROYEKSI DO AKHIR BULAN
══════════════════════════════════════════════════════════════ --}}
<div class="s-card">
    <div class="s-card-header s-card-header-purple">
        <span>🔮 Proyeksi DO Akhir Bulan</span>
        <span class="sub">
            hari ke-{{ $hariIni }} dari {{ $daysInMonth }} · {{ $activeDaysDO }} hari aktif DO
        </span>
    </div>

    <div class="proj-body">

        {{-- 3 Skenario --}}
        <div class="scenario-grid">
            <div class="scenario-box scenario-optimis">
                <div class="scenario-label">Optimis <span class="opacity-muted">(+20%)</span></div>
                <div class="scenario-value">{{ number_format($proyeksiOptimis) }}</div>
                <div class="scenario-unit">tabung</div>
            </div>

            <div class="scenario-box scenario-realistis">
                <div class="scenario-label">Realistis ★</div>
                <div class="scenario-value">{{ number_format($proyeksiRealistis) }}</div>
                <div class="scenario-unit">tabung</div>
            </div>

            <div class="scenario-box scenario-konservatif">
                <div class="scenario-label">Konservatif <span class="opacity-muted">(-20%)</span></div>
                <div class="scenario-value">{{ number_format($proyeksiKonservatif) }}</div>
                <div class="scenario-unit">tabung</div>
            </div>
        </div>

        {{-- Progress Bar --}}
        <div class="progress-block">
            <div class="progress-label-row">
                <span>Progress bulan ini</span>
                <span class="accent">{{ $pctTercapai }}% dari proyeksi realistis</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width:{{ $pctTercapai }}%"></div>
                <div class="progress-marker" style="left:{{ $pctJalan }}%"></div>
            </div>
            <div class="progress-footer-row">
                <span>0</span>
                <span class="accent">← hari ke-{{ $hariIni }} ({{ $pctJalan }}%)</span>
                <span>{{ number_format($proyeksiRealistis) }} tab</span>
            </div>
        </div>

        {{-- Vs Bulan Lalu --}}
        @if($prevTotal > 0)
        <div class="vs-box {{ $selisih >= 0 ? 'positive' : 'negative' }}">
            <div>
                <div class="vs-title">
                    {{ $selisih >= 0 ? '↑' : '↓' }} vs bulan lalu ({{ number_format($prevTotal) }} tab)
                </div>
                <div class="vs-sub">
                    {{ $prevPeriod?->label ?? 'bulan lalu' }}
                </div>
            </div>
            <div>
                <div class="vs-value">
                    {{ $selisih >= 0 ? '+' : '' }}{{ number_format($selisih) }}
                </div>
                <div class="vs-pct">{{ $vsLalu }}% dari bln lalu</div>
            </div>
        </div>
        @endif

        {{-- Tabel Proyeksi per Pangkalan --}}
        <div class="subsection-label">Per pangkalan</div>
        <div class="scroll-x">
            <table class="mob-table table-sm">
                <thead>
                    <tr>
                        <th>Pangkalan</th>
                        <th class="r">Sekarang</th>
                        <th class="r">Proyeksi</th>
                        @if($prevTotal > 0) <th class="r">Bln lalu</th> @endif
                        <th style="min-width:90px">Pace</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($proyeksiPerPangkalan as $row)
                    @php
                        $w    = $warna($row['pace']);
                        $barW = $row['pace'] !== null ? min($row['pace'], 100) : 0;
                    @endphp
                    <tr>
                        <td class="bold">{{ $row['outlet']->name }}</td>
                        <td class="r text-sky">{{ number_format($row['qtySkrg']) }}</td>
                        <td class="r bold text-indigo">{{ number_format($row['proyeksi']) }}</td>
                        @if($prevTotal > 0)
                        <td class="r text-muted">{{ $row['qtyLalu'] > 0 ? number_format($row['qtyLalu']) : '-' }}</td>
                        @endif
                        <td>
                            @if($row['pace'] !== null)
                                <span class="pace-badge" style="--pace-bg:{{ $w['bg'] }};--pace-text:{{ $w['text'] }}">{{ $row['pace'] }}%</span>
                            @else
                                <span class="text-muted" style="font-size:10px">—</span>
                            @endif
                            <div class="pace-track">
                                <div class="pace-fill" style="width:{{ $barW }}%;--pace-text:{{ $w['text'] }}"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach

                    {{-- Baris Total --}}
                    <tr class="total-row">
                        <td class="bold">Total</td>
                        <td class="r bold">{{ number_format($grandTotal) }}</td>
                        <td class="r bold">{{ number_format($proyeksiRealistis) }}</td>
                        @if($prevTotal > 0)
                        <td class="r">{{ number_format($prevTotal) }}</td>
                        @endif
                        <td>
                            @if($vsLalu !== null)
                            @php $wt = $warna($vsLalu); @endphp
                            <span class="pace-badge" style="--pace-bg:{{ $wt['bg'] }};--pace-text:{{ $wt['text'] }}">{{ $vsLalu }}%</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Rekomendasi Otomatis --}}
        <div class="notice-stack">
            @if($selisih < 0 && $prevTotal > 0)
            @php $targetHarian = round(($prevTotal - $grandTotal) / max($sisaHari, 1)); @endphp
            <div class="notice notice-danger">
                ⚠ Butuh <strong>+{{ number_format($targetHarian) }} tab/hari</strong> di sisa {{ $sisaHari }} hari untuk kejar bulan lalu
            </div>
            @endif

            @if($activeDaysDO / max($hariIni, 1) < 0.5)
            <div class="notice notice-warning">
                ⚠ Hanya {{ $activeDaysDO }} dari {{ $hariIni }} hari ada DO — frekuensi pengiriman perlu ditingkatkan
            </div>
            @endif

            @if($selisih >= 0 || ($vsLalu !== null && $vsLalu >= 90))
            <div class="notice notice-success">
                ✓ Laju DO berjalan baik — pertahankan konsistensi pengiriman
            </div>
            @endif
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     CHART: NILAI DO HARIAN
══════════════════════════════════════════════════════════════ --}}
@if(count($hariDOLabels) > 0)
<div class="s-card">
    <div class="s-card-header">📈 Nilai DO Harian (Rp)</div>
    <div class="proj-body">
        <div class="chart-legend-row">
            <span class="legend-item"><span class="legend-dot" style="--legend-color:#FAC775"></span>Nilai DO</span>
            <span class="legend-item"><span class="legend-line" style="--legend-color:#1D9E75"></span>Terbayar</span>
            <span class="legend-item"><span class="legend-line legend-line-dashed" style="--legend-color:#E24B4A"></span>Piutang</span>
        </div>
        <div class="chart-wrap chart-wrap-lg">
            <canvas id="cDOBlade"></canvas>
        </div>
    </div>
</div>
@endif


{{-- ══════════════════════════════════════════════════════════════
     RANKING PIUTANG PER PANGKALAN
══════════════════════════════════════════════════════════════ --}}
@if($rankPiutangDO->count() > 0)
<div class="s-card">
    <div class="s-card-header">🏆 Ranking Piutang per Pangkalan</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th style="width:28px">#</th>
                    <th>Pangkalan</th>
                    <th class="r">Nilai DO</th>
                    <th class="r">Terbayar</th>
                    <th class="r">Piutang</th>
                    <th style="min-width:120px">Progres</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @php $rankColors = ['#991b1b','#c2410c','#b45309','#854F0B','#5F5E5A']; @endphp

                @foreach($rankPiutangDO as $i => $rp)
                @php
                    $rColor    = $rankColors[$i] ?? '#888780';
                    $barColor  = $rp['pct_lunas'] >= 70 ? '#1D9E75' : ($rp['pct_lunas'] >= 40 ? '#EF9F27' : '#E24B4A');
                    $pctShare  = $totalPiutangAll > 0 ? round($rp['piutang'] / $totalPiutangAll * 100) : 0;

                    [$sLbl, $sBadge] = match(true) {
                        $rp['pct_lunas'] === 0    => ['Belum',          'badge-red'],
                        $rp['pct_lunas'] < 50     => ['Sebagian kecil', 'badge-orange'],
                        $rp['pct_lunas'] < 80     => ['Sebagian',       'badge-blue'],
                        default                   => ['Hampir lunas',   'badge-green'],
                    };
                @endphp
                <tr>
                    <td>
                        <span class="rank-badge" style="--rank-color:{{ $rColor }}">{{ $i + 1 }}</span>
                    </td>
                    <td class="bold">
                        {{ $rp['name'] }}
                        @if($rp['has_carryover'])
                            <span class="co-tag">↩ c/o</span>
                        @endif
                    </td>
                    <td class="r">Rp {{ number_format($rp['nilai']) }}</td>
                    <td class="r text-melon">Rp {{ number_format($rp['bayar']) }}</td>
                    <td class="r bold" style="color:{{ $rColor }}">Rp {{ number_format($rp['piutang']) }}</td>
                    <td>
                        <div class="mini-progress-label">{{ $rp['pct_lunas'] }}% · {{ $pctShare }}% total</div>
                        <div class="mini-progress-track">
                            <div class="mini-progress-fill" style="width:{{ $rp['pct_lunas'] }}%;--mini-color:{{ $barColor }}"></div>
                        </div>
                    </td>
                    <td><span class="badge {{ $sBadge }}">{{ $sLbl }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="notice-success empty-state pad-sm">
    ✅ Semua pangkalan sudah lunas — tidak ada piutang DO
</div>
@endif


{{-- ══════════════════════════════════════════════════════════════
     PREDIKSI AKHIR BULAN
══════════════════════════════════════════════════════════════ --}}
<div class="s-card mb-10">
    <div class="s-card-header">🔮 Prediksi Akhir Bulan</div>
    <div class="pad-sm">
        <table class="mob-table">
            <tr>
                <td class="text-muted">Hari aktif</td>
                <td class="r bold text-blue-deep">{{ $activeDaysDO }} / {{ $daysInMonth }} hari</td>
            </tr>
            <tr>
                <td class="text-muted">Nilai berjalan</td>
                <td class="r bold text-orange">Rp {{ number_format($grandValue) }}</td>
            </tr>
            <tr>
                <td class="text-muted">Proyeksi akhir bulan</td>
                <td class="r bold text-indigo">Rp {{ number_format($proyeksiTotal) }}</td>
            </tr>
            <tr>
                <td class="text-muted">Piutang cair (estimasi)</td>
                <td class="r bold text-melon">Rp {{ number_format($totalPiutangAll) }}</td>
            </tr>
        </table>
        <div class="scenario-note-box">
            <div class="scenario-note-title">Skenario semua lunas</div>
            <div class="scenario-note-row">
                <span class="text-muted">Kas masuk tambahan</span>
                <span class="scenario-note-value">+ Rp {{ number_format($totalPiutangAll) }}</span>
            </div>
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     STATUS DO (DONUT)
══════════════════════════════════════════════════════════════ --}}
<div class="s-card mb-10">
    <div class="s-card-header">🍩 Status DO</div>
    <div class="pad-sm">
        <div class="chart-wrap chart-wrap-sm">
            <canvas id="cStatusBlade"></canvas>
        </div>
        <div class="donut-legend">
            @foreach([['Lunas', $doLunas, 'badge-green'], ['Sebagian', $doSebagian, 'badge-orange'], ['Belum', $doBelum, 'badge-red']] as [$lbl, $jml, $cls])
                @if($jml > 0)
                <div class="donut-legend-row">
                    <span class="badge {{ $cls }}">{{ $lbl }}</span>
                    <span class="bold text-secondary" style="font-size:11px">{{ $jml }} DO</span>
                </div>
                @endif
            @endforeach
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     VOLUME PER PANGKALAN (BAR CHART)
══════════════════════════════════════════════════════════════ --}}
<div class="s-card mb-10">
    <div class="s-card-header">📊 Volume per Pangkalan</div>
    <div class="pad-sm chart-wrap chart-wrap-md">
        <canvas id="cPangkBlade"></canvas>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     INDIKATOR KESEHATAN
══════════════════════════════════════════════════════════════ --}}
<div class="s-card mb-10">
    <div class="s-card-header">🩺 Indikator Kesehatan</div>
    <div class="pad-sm health-list">
        @php
        $indikators = [
            ['Pelunasan DO',           number_format($rasioLunas, 1).'%', $rasioLunas,     $rasioLunas >= 85    ? '#1D9E75' : ($rasioLunas >= 70    ? '#EF9F27' : '#E24B4A')],
            ['Piutang carry-over',     $pctCarryover.'%',                 $pctCarryover,   $pctCarryover < 20   ? '#1D9E75' : ($pctCarryover < 40   ? '#EF9F27' : '#E24B4A')],
            ['Hari aktif DO',          $activeDaysDO.' hari',             $rasioAktifDO,   '#378ADD'],
            ['Konsentrasi 1 pangkalan',$konsentrasiDO.'%',                $konsentrasiDO,  $konsentrasiDO < 30  ? '#1D9E75' : ($konsentrasiDO < 50  ? '#EF9F27' : '#E24B4A')],
        ];
        @endphp

        @foreach($indikators as [$lbl, $val, $bar, $col])
        <div style="--health-color:{{ $col }}">
            <div class="health-row-label">
                <span class="lbl">{{ $lbl }}</span>
                <span class="val">{{ $val }}</span>
            </div>
            <div class="health-track">
                <div class="health-fill" style="width:{{ min($bar, 100) }}%"></div>
            </div>
        </div>
        @endforeach

        {{-- Rekomendasi --}}
        <div class="notice-stack" style="margin-top:4px">
            @if($rasioLunas < 85)
            <div class="notice notice-danger">
                ⚠ Pelunasan {{ number_format($rasioLunas, 1) }}% — tagih <strong>{{ $topPangkalan }}</strong>
            </div>
            @endif

            @if($pctCarryover > 30)
            <div class="notice notice-warning">
                ⚠ {{ $pctCarryover }}% piutang dari carry-over — tinjau batas kredit
            </div>
            @endif

            @if($konsentrasiDO > 50)
            <div class="notice notice-warning">
                ⚠ {{ $konsentrasiDO }}% DO ke 1 pangkalan — diversifikasi outlet
            </div>
            @endif

            @if($rasioLunas >= 85 && $pctCarryover <= 30 && $konsentrasiDO <= 50)
            <div class="notice notice-success">
                ✓ Semua indikator DO baik
            </div>
            @endif
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     DETAIL LIST DO BARU
══════════════════════════════════════════════════════════════ --}}
<div class="s-card">
    <div class="s-card-header">📋 DO Baru Bulan Ini</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Pangkalan</th>
                    <th class="r">Qty</th>
                    <th class="r">Harga</th>
                    <th class="r">Nilai DO</th>
                    <th class="r">Terbayar</th>
                    <th class="r">Surplus</th>
                    <th>Status</th>
                    @if($period->status === 'open') <th>Aksi</th> @endif
                </tr>
            </thead>
            <tbody>
                @forelse($dos as $do)
                <tr>
                    <td>{{ $do->do_date->format('d/m/Y') }}</td>
                    <td class="bold">{{ $do->outlet->name }}</td>
                    <td class="r bold">{{ number_format($do->qty) }}</td>
                    <td class="r">Rp {{ number_format($do->price_per_unit) }}</td>
                    <td class="r bold">Rp {{ number_format($do->qty * $do->price_per_unit) }}</td>
                    <td class="r">Rp {{ number_format($do->paid_amount + $do->transfers->sum('surplus')) }}</td>
                    <td class="r text-blue">Rp {{ number_format($do->transfers->sum('surplus')) }}</td>
                    <td>
                        @if($do->payment_status === 'paid')
                            <span class="badge badge-green">✓ Lunas</span>
                        @elseif($do->payment_status === 'partial')
                            <span class="badge badge-orange">⚡ Sebagian</span>
                        @else
                            <span class="badge badge-red">✗ Belum</span>
                        @endif
                    </td>
                    @if($period->status === 'open')
                    <td>
                        <div class="action-links">
                            <a href="{{ route('do.edit', $do) }}" class="link-edit">Edit</a>
                            <form method="POST" action="{{ route('do.destroy', $do) }}" class="inline-form" onsubmit="return confirm('Hapus DO ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-btn danger">Hapus</button>
                            </form>
                        </div>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="empty-row-cell">Belum ada DO baru untuk periode ini.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" class="bold">Total</td>
                    <td class="r bold">{{ number_format($dos->sum('qty')) }}</td>
                    <td class="r"></td>
                    <td class="r bold">Rp {{ number_format($dos->sum(fn($d) => $d->qty * $d->price_per_unit)) }}</td>
                    <td class="r bold">Rp {{ number_format($dos->sum(fn($d) => $d->paid_amount + $d->transfers->sum('surplus'))) }}</td>
                    <td class="r bold">Rp {{ number_format($dos->sum(fn($d) => $d->transfers->sum('surplus'))) }}</td>
                    <td></td>
                    @if($period->status === 'open') <td></td> @endif
                </tr>
            </tfoot>
        </table>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     CARRY-OVER DO
══════════════════════════════════════════════════════════════ --}}
@if($carryoverDOs->count() > 0)
<div class="s-card card-accent-orange">
    <div class="s-card-header s-card-header-warning">
        <span>↩️ Piutang DO Carry-Over (dari bulan lalu)</span>
        <span class="badge badge-orange">
            {{ number_format($carryoverDOs->sum('qty')) }} tab · Rp {{ number_format($carryoverDOs->sum(fn ($d) => $d->qty * $d->price_per_unit)) }}
        </span>
    </div>

    <div class="warning-note">
        ⚠️ Ini hanya <strong>piutang pembayaran</strong> ke agen dari bulan lalu. Stoknya sudah terhitung di Stok Awal
        ({{ number_format($period->opening_stock) }} tabung) — tidak dihitung ulang.
    </div>

    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Tgl DO (asli)</th>
                    <th>Pangkalan</th>
                    <th class="r">Qty</th>
                    <th class="r">Nilai</th>
                    <th class="r">Terbayar</th>
                    <th class="r">Surplus</th>
                    <th class="r">Sisa</th>
                    <th>Status</th>
                    @if($period->status === 'open') <th>Aksi</th> @endif
                </tr>
            </thead>
            <tbody>
                @foreach($carryoverDOs as $do)
                <tr>
                    <td class="text-muted">{{ $do->do_date->format('d/m/Y') }}</td>
                    <td class="bold">{{ $do->outlet->name }}</td>
                    <td class="r bold">{{ number_format($do->qty) }}</td>
                    <td class="r">Rp {{ number_format($do->qty * $do->price_per_unit) }}</td>
                    <td class="r text-melon">Rp {{ number_format($do->paid_amount + $do->transfers->sum('surplus')) }}</td>
                    <td class="r text-blue">Rp {{ number_format($do->transfers->sum('surplus')) }}</td>
                    <td class="r bold {{ $do->remainingAmount() > 0 ? 'text-red' : 'text-melon' }}">
                        Rp {{ number_format($do->remainingAmount()) }}
                    </td>
                    <td>
                        @if($do->payment_status === 'paid')
                            <span class="badge badge-green">✓ Lunas</span>
                        @elseif($do->payment_status === 'partial')
                            <span class="badge badge-orange">⚡ Sebagian</span>
                        @else
                            <span class="badge badge-red">✗ Belum</span>
                        @endif
                    </td>
                    @if($period->status === 'open')
                    <td>
                        <div class="action-links">
                            <a href="{{ route('do.edit', $do) }}" class="link-edit">Edit</a>
                            <form method="POST" action="{{ route('do.destroy', $do) }}" class="inline-form" onsubmit="return confirm('Hapus DO carry-over ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-btn danger">Hapus</button>
                            </form>
                        </div>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" class="bold">Total</td>
                    <td class="r bold">{{ number_format($carryoverDOs->sum('qty')) }}</td>
                    <td class="r bold">Rp {{ number_format($carryoverDOs->sum(fn($d) => $d->qty * $d->price_per_unit)) }}</td>
                    <td class="r bold">Rp {{ number_format($carryoverDOs->sum(fn($d) => $d->paid_amount + $d->transfers->sum('surplus'))) }}</td>
                    <td class="r bold">Rp {{ number_format($carryoverDOs->sum(fn($d) => $d->transfers->sum('surplus'))) }}</td>
                    <td class="r bold">Rp {{ number_format($carryoverDOs->sum(fn($d) => $d->remainingAmount())) }}</td>
                    <td></td>
                    @if($period->status === 'open') <td></td> @endif
                </tr>
            </tfoot>

        </table>
    </div>
</div>
@endif

@endsection


@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
        (function () {
            const GRAY = 'rgba(0,0,0,0.06)';
            const TICK  = { font: { size: 10 }, color: '#9CA3AF' };
            const fmtK  = v => 'Rp ' + Math.round(Math.abs(v) / 1000).toLocaleString('id') + 'k';

            // Data dari PHP
            const hariLabels  = @json($hariDOLabels);
            const hariNilai   = @json($hariDONilai);
            const hariKas     = @json($hariDOBayar);
            const hariPiutang = @json($hariDOPiutang);
            const pangkNames  = @json($pangkalanBar->pluck('name'));
            const pangkNew    = @json($pangkalanBar->pluck('qty_new'));
            const pangkCarry  = @json($pangkalanBar->pluck('qty_carry'));

            // ── Chart Nilai DO Harian ─────────────────────────────────────────────────
            const elDO = document.getElementById('cDOBlade');
            if (elDO && hariLabels.length) {
                new Chart(elDO, {
                    data: {
                        labels: hariLabels.map(d => '' + d),
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Nilai DO',
                                data: hariNilai.map(v => v / 1000),
                                backgroundColor: '#FAC77580',
                                borderRadius: 3,
                                order: 2,
                            },
                            {
                                type: 'line',
                                label: 'Terbayar',
                                data: hariKas.map(v => v / 1000),
                                borderColor: '#1D9E75',
                                borderWidth: 2,
                                pointRadius: 2.5,
                                tension: 0.3,
                                fill: false,
                                backgroundColor: 'transparent',
                                order: 1,
                            },
                            {
                                type: 'line',
                                label: 'Piutang',
                                data: hariPiutang.map(v => v / 1000),
                                borderColor: '#E24B4A',
                                borderWidth: 1.5,
                                borderDash: [4, 3],
                                pointRadius: 2,
                                tension: 0.3,
                                fill: false,
                                backgroundColor: 'transparent',
                                order: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: { label: ctx => `${ctx.dataset.label}: ${fmtK(ctx.parsed.y * 1000)}` },
                            },
                        },
                        scales: {
                            x: { grid: { color: GRAY }, ticks: TICK },
                            y: {
                                grid: { color: GRAY },
                                ticks: { ...TICK, callback: v => v + 'k' },
                                title: { display: true, text: 'Ribuan Rp', color: '#9CA3AF', font: { size: 10 } },
                            },
                        },
                    },
                });
            }

            // ── Chart Status Donut ────────────────────────────────────────────────────
            const elStatus = document.getElementById('cStatusBlade');
            if (elStatus) {
                new Chart(elStatus, {
                    type: 'doughnut',
                    data: {
                        labels: ['Lunas', 'Sebagian', 'Belum'],
                        datasets: [{
                            data: [{{ $doLunas }}, {{ $doSebagian }}, {{ $doBelum }}],
                            backgroundColor: ['#1D9E75', '#EF9F27', '#E24B4A'],
                            borderWidth: 1,
                            borderColor: '#fff',
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '65%',
                        plugins: { legend: { display: false } },
                    },
                });
            }

            // ── Chart Volume per Pangkalan ────────────────────────────────────────────
            const elPangk = document.getElementById('cPangkBlade');
            if (elPangk && pangkNames.length) {
                new Chart(elPangk, {
                    type: 'bar',
                    data: {
                        labels: pangkNames,
                        datasets: [
                            { label: 'DO baru',    data: pangkNew,   backgroundColor: '#FAC77599', borderRadius: 3 },
                            { label: 'Carry-over', data: pangkCarry, backgroundColor: '#F0997B99', borderRadius: 3 },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true, labels: { font: { size: 10 }, boxWidth: 10, padding: 8 } },
                            tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y} tab` } },
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: TICK, stacked: false },
                            y: { grid: { color: GRAY }, ticks: TICK },
                        },
                    },
                });
            }
        })();
    </script>
@endpush
