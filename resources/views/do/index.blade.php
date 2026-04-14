@extends('layouts.app')
@section('title', 'DO Agen')
@section('content')

{{-- Header & Period selector --}}
<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;margin-bottom:12px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:15px;font-weight:600;color:var(--text1)">📦 DO Agen</span>
        <form method="GET" action="{{ route('do.index') }}">
            <select name="period_id" onchange="this.form.submit()" class="field-select" style="padding:6px 10px;font-size:12px;width:auto">
                @foreach($periods as $p)
                    <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                @endforeach
            </select>
        </form>
        @if($period->status === 'open')
            <span class="badge badge-green">🟢 Buka</span>
        @else
            <span class="badge" style="background:#f0f0f0;color:#666">🔒 Tutup</span>
        @endif
    </div>
    @if($period->status === 'open')
        <a href="{{ route('do.create', ['period_id' => $period->id]) }}" class="btn-primary btn-sm">+ Input DO</a>
    @endif
</div>

{{-- ══ ANALISIS DO ══ --}}
@php
    $grandTotal   = $dos->sum('qty');
    $grandValue   = $dos->sum(fn($d) => $d->qty * $d->price_per_unit);
    $grandBayar   = $dos->sum(fn($d) => $d->paid_amount + $d->transfers->sum('surplus'));
    $grandSurplus = $dos->sum(fn($d) => $d->transfers->sum('surplus'));
    $grandPiutang = $grandValue - $grandBayar;

    $coValue   = $carryoverDOs->sum(fn($d) => $d->qty * $d->price_per_unit);
    $coBayar   = $carryoverDOs->sum(fn($d) => $d->paid_amount + $d->transfers->sum('surplus'));
    $coPiutang = $carryoverDOs->sum(fn($d) => $d->remainingAmount());

    $totalNilaiAll   = $grandValue + $coValue;
    $totalBayarAll   = $grandBayar + $coBayar;
    $totalPiutangAll = $grandPiutang + $coPiutang;
    $rasioLunas      = $totalNilaiAll > 0 ? ($totalBayarAll / $totalNilaiAll * 100) : 0;
    $pctPiutang      = $totalNilaiAll > 0 ? ($totalPiutangAll / $totalNilaiAll * 100) : 0;
    $pctCarryover    = $totalPiutangAll > 0 ? round($coPiutang / $totalPiutangAll * 100) : 0;

    $hariDOLabels = []; $hariDONilai = []; $hariDOBayar = []; $hariDOPiutang = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = sprintf('%04d-%02d-%02d', $period->year, $period->month, $d);
        $dayDOs  = $dos->filter(fn($do) => $do->do_date->format('Y-m-d') === $dateStr);
        $dv = $dayDOs->sum(fn($do) => $do->qty * $do->price_per_unit);
        $dp = $dayDOs->sum(fn($do) => $do->paid_amount + $do->transfers->sum('surplus'));
        if ($dv > 0) {
            $hariDOLabels[]  = $d; $hariDONilai[] = $dv;
            $hariDOBayar[]   = $dp; $hariDOPiutang[] = $dv - $dp;
        }
    }
    $activeDaysDO = max(count($hariDOLabels), 1);
    $avgNilaiDO   = round($grandValue / $activeDaysDO);
    $avgBayarDO   = round($grandBayar / $activeDaysDO);
    $hariIni      = max(now()->day, $activeDaysDO);
    $sisaHari     = max($daysInMonth - $hariIni, 0);
    $proyeksiSisa = $sisaHari > 0 ? round($avgNilaiDO * $sisaHari) : 0;
    $proyeksiTotal= $grandValue + $proyeksiSisa;

    $rankPiutangDO = $outlets->map(function($outlet) use ($dos, $carryoverDOs) {
        $outletDOs = $dos->where('outlet_id', $outlet->id);
        $coOutlet  = $carryoverDOs->where('outlet_id', $outlet->id);
        $nilai  = $outletDOs->sum(fn($d) => $d->qty * $d->price_per_unit) + $coOutlet->sum(fn($d) => $d->qty * $d->price_per_unit);
        $bayar  = $outletDOs->sum(fn($d) => $d->paid_amount + $d->transfers->sum('surplus')) + $coOutlet->sum(fn($d) => $d->paid_amount + $d->transfers->sum('surplus'));
        $piutang = $nilai - $bayar;
        return ['name' => $outlet->name, 'qty' => $outletDOs->sum('qty'), 'nilai' => $nilai, 'bayar' => $bayar, 'piutang' => $piutang, 'pct_lunas' => $nilai > 0 ? round($bayar / $nilai * 100) : 100, 'has_carryover' => $coOutlet->count() > 0];
    })->filter(fn($o) => $o['piutang'] > 0)->sortByDesc('piutang')->values();

    $doLunas    = $dos->where('payment_status', 'paid')->count();
    $doSebagian = $dos->where('payment_status', 'partial')->count();
    $doBelum    = $dos->where('payment_status', 'unpaid')->count();

    $pangkalanBar = $outlets->map(fn($o) => [
        'name' => $o->name,
        'qty_new'   => $dos->where('outlet_id', $o->id)->sum('qty'),
        'qty_carry' => $carryoverDOs->where('outlet_id', $o->id)->sum('qty'),
    ])->filter(fn($o) => $o['qty_new'] + $o['qty_carry'] > 0)->values();

    $totalQtyBar   = $pangkalanBar->sum('qty_new');
    $topPangkalan  = $rankPiutangDO->first()['name'] ?? '-';
    $rasioAktifDO  = round($activeDaysDO / 26 * 100);
    $topQty        = $pangkalanBar->max('qty_new') ?? 0;
    $konsentrasiDO = $totalQtyBar > 0 ? round($topQty / $totalQtyBar * 100) : 0;
@endphp


{{-- Rekap Grid per Tanggal --}}
<div class="s-card">
    <div class="s-card-header">📅 Rekap DO per Tanggal</div>
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th style="position:sticky;left:0;background:#f8faf8;z-index:2">Pangkalan</th>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        <th style="text-align:center;width:28px">{{ $d }}</th>
                    @endfor
                    <th class="r">Total</th>
                    <th class="r">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outlets as $outlet)
                @php
                    $outletDOs    = $dos->where('outlet_id', $outlet->id);
                    $outletTotal  = $outletDOs->sum('qty');
                    $outletValue  = $outletDOs->sum(fn($d) => $d->qty * $d->price_per_unit);
                @endphp
                <tr>
                    <td class="bold" style="position:sticky;left:0;background:#fff;z-index:1">{{ $outlet->name }}</td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dateStr = sprintf('%04d-%02d-%02d', $period->year, $period->month, $day);
                        $dayQty  = $outletDOs->filter(fn($d) => $d->do_date->format('Y-m-d') === $dateStr)->sum('qty');
                    @endphp
                    <td style="text-align:center;padding:6px 2px;{{ $dayQty > 0 ? 'background:var(--melon-light);font-weight:600;color:var(--melon-dark)' : 'color:#d1d5db' }}">
                        {{ $dayQty ?: '-' }}
                    </td>
                    @endfor
                    <td class="r bold" style="color:{{ $outletTotal > 0 ? 'var(--melon-dark)' : 'var(--text3)' }}">{{ number_format($outletTotal) ?: '-' }}</td>
                    <td class="r" style="color:var(--text2)">{{ $outletTotal > 0 ? 'Rp '.number_format($outletValue) : '-' }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td class="bold" style="position:sticky;left:0;background:var(--melon);z-index:1">TOTAL</td>
                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dateStr  = sprintf('%04d-%02d-%02d', $period->year, $period->month, $day);
                        $dayTotal = $dos->filter(fn($d) => $d->do_date->format('Y-m-d') === $dateStr)->sum('qty');
                    @endphp
                    <td style="text-align:center;padding:6px 2px">{{ $dayTotal ?: '-' }}</td>
                    @endfor
                    <td class="r">{{ number_format($grandTotal) }}</td>
                    <td class="r">Rp {{ number_format($grandValue) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- KPI Cards --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
    <div class="card" style="padding:10px 12px">
        <div style="font-size:10px;color:var(--text3)">Total DO Diterima</div>
        <div style="font-size:18px;font-weight:600;color:#c2410c">{{ number_format($grandTotal) }} tab</div>
        <div style="font-size:10px;color:var(--text3)">Rp {{ number_format($grandValue) }}</div>
    </div>
    <div class="card" style="padding:10px 12px">
        <div style="font-size:10px;color:var(--text3)">Total Terbayar ke Agen</div>
        <div style="font-size:18px;font-weight:600;color:var(--melon-dark)">Rp {{ number_format($totalBayarAll) }}</div>
        <div style="font-size:10px;color:var(--text3)">{{ number_format($rasioLunas, 1) }}% dari nilai DO</div>
    </div>
    <div class="card" style="padding:10px 12px;{{ $totalPiutangAll > 0 ? '' : '' }}">
        <div style="font-size:10px;color:var(--text3)">Piutang ke Agen</div>
        <div style="font-size:18px;font-weight:600;color:{{ $totalPiutangAll > 0 ? '#991b1b' : 'var(--melon-dark)' }}">
            {{ $totalPiutangAll > 0 ? 'Rp '.number_format($totalPiutangAll) : '✓ Lunas' }}
        </div>
        <div style="font-size:10px;color:var(--text3)">{{ number_format($pctPiutang, 1) }}% · termasuk carry-over</div>
    </div>
    <div class="card" style="padding:10px 12px">
        <div style="font-size:10px;color:var(--text3)">Surplus Transfer</div>
        <div style="font-size:18px;font-weight:600;color:#1d4ed8">Rp {{ number_format($grandSurplus) }}</div>
        <div style="font-size:10px;color:var(--text3)">dari transfer ke rek utama</div>
    </div>
</div>

{{-- ══ PROYEKSI DO BULAN INI ══ --}}
{{-- Letakkan blok ini di do/index.blade.php, setelah blok KPI Cards --}}

@php
    // ── Data bulan lalu ──────────────────────────────────────────────────────
    // Ambil total DO dari periode sebelumnya (carry-over bukan termasuk)
    $prevPeriod = \App\Models\Period::where('year', $period->year)
        ->where('month', $period->month - 1)
        ->orWhere(fn($q) => $q->where('year', $period->year - 1)->where('month', 12)->where(fn($q2) => $period->month == 1))
        ->orderByDesc('year')->orderByDesc('month')
        ->first();

    $prevDOs = $prevPeriod
        ? \App\Models\DeliveryOrder::where('period_id', $prevPeriod->id)
            ->where(fn($q) => $q->whereNull('notes')->orWhere('notes', 'not like', '%Carry-over%'))
            ->with('outlet')
            ->get()
        : collect();

    $prevTotal      = $prevDOs->sum('qty');
    $prevDaysInMonth = $prevPeriod ? cal_days_in_month(CAL_GREGORIAN, $prevPeriod->month, $prevPeriod->year) : 30;

    // ── Data bulan ini (berjalan) ─────────────────────────────────────────────
    // $dos, $daysInMonth, $grandTotal sudah tersedia dari controller

    $hariIni        = now()->day;                         // hari kalender saat ini
    $hariAktifDO    = $hariDOLabels ? count($hariDOLabels) : max(1, $activeDaysDO ?? 1);
    $rasioAktif     = $hariIni > 0 ? $hariAktifDO / $hariIni : 0;
    $sisaHari       = $daysInMonth - $hariIni;

    // Rata-rata DO per hari aktif
    $avgPerHariAktif = $hariAktifDO > 0 ? $grandTotal / $hariAktifDO : 0;

    // Estimasi hari aktif tersisa berdasarkan rasio berjalan
    $estHariAktifSisa = round($sisaHari * $rasioAktif);
    $proyeksiSisa     = round($avgPerHariAktif * $estHariAktifSisa);
    $proyeksiRealistis = $grandTotal + $proyeksiSisa;
    $proyeksiOptimis  = round($proyeksiRealistis * 1.20);
    $proyeksiKonservatif = round($proyeksiRealistis * 0.80);

    // Perbandingan vs bulan lalu
    $vsLalu     = $prevTotal > 0 ? round(($proyeksiRealistis / $prevTotal) * 100) : null;
    $selisih    = $proyeksiRealistis - $prevTotal;
    $pctJalan   = $daysInMonth > 0 ? round($hariIni / $daysInMonth * 100) : 0;
    $pctTercapai = $proyeksiRealistis > 0 ? min(round($grandTotal / $proyeksiRealistis * 100), 100) : 0;

    // ── Proyeksi per pangkalan ─────────────────────────────────────────────────
    $proyeksiPerPangkalan = $outlets->map(function ($outlet) use ($dos, $prevDOs, $hariIni, $daysInMonth, $rasioAktif) {
        $outletDOs   = $dos->where('outlet_id', $outlet->id);
        $qtySkrg     = $outletDOs->sum('qty');
        $hariAktif   = $outletDOs->groupBy(fn($d) => $d->do_date->format('Y-m-d'))->count();
        $avg         = $hariAktif > 0 ? $qtySkrg / $hariAktif : 0;
        $estSisa     = round(($daysInMonth - $hariIni) * ($hariAktif / max($hariIni, 1)));
        $proyeksi    = round($qtySkrg + $avg * $estSisa);
        $qtyLalu     = $prevDOs->where('outlet_id', $outlet->id)->sum('qty');
        $pace        = $qtyLalu > 0 ? round($proyeksi / $qtyLalu * 100) : null;
        return compact('outlet', 'qtySkrg', 'proyeksi', 'qtyLalu', 'pace', 'avg');
    })->filter(fn($r) => $r['qtySkrg'] > 0 || $r['qtyLalu'] > 0)->values();

    // ── Warna helper ─────────────────────────────────────────────────────────
    $warna = fn($pct) => match(true) {
        $pct === null       => ['bg' => '#f0f0f0',  'text' => '#666'],
        $pct >= 90          => ['bg' => '#E1F5EE',  'text' => '#0F6E56'],
        $pct >= 70          => ['bg' => '#FAEEDA',  'text' => '#854F0B'],
        default             => ['bg' => '#FCEBEB',  'text' => '#A32D2D'],
    };
@endphp

<div class="s-card" style="">
    <div class="s-card-header" style="background:#EEEDFE;border-color:#CECBF6">
        <span style="color:#3C3489">🔮 Proyeksi DO Akhir Bulan</span>
        <span style="font-size:10px;color:#534AB7;font-weight:400">
            hari ke-{{ $hariIni }} dari {{ $daysInMonth }} · {{ $hariAktifDO }} hari aktif DO
        </span>
    </div>

    {{-- ── 3 Skenario ── --}}
    <div style="padding:12px 14px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">

            {{-- Optimis --}}
            <div style="background:#E6F1FB;border-radius:8px;padding:10px;text-align:center">
                <div style="font-size:10px;color:#185FA5;margin-bottom:4px">Optimis <span style="opacity:.7">(+20%)</span></div>
                <div style="font-size:20px;font-weight:600;color:#0C447C">{{ number_format($proyeksiOptimis) }}</div>
                <div style="font-size:9px;color:#185FA5">tabung</div>
            </div>

            {{-- Realistis --}}
            <div style="background:#EEEDFE;border:2px solid #AFA9EC;border-radius:8px;padding:10px;text-align:center">
                <div style="font-size:10px;color:#534AB7;margin-bottom:4px">Realistis ★</div>
                <div style="font-size:20px;font-weight:600;color:#3C3489">{{ number_format($proyeksiRealistis) }}</div>
                <div style="font-size:9px;color:#534AB7">tabung</div>
            </div>

            {{-- Konservatif --}}
            <div style="background:#FAEEDA;border-radius:8px;padding:10px;text-align:center">
                <div style="font-size:10px;color:#854F0B;margin-bottom:4px">Konservatif <span style="opacity:.7">(-20%)</span></div>
                <div style="font-size:20px;font-weight:600;color:#633806">{{ number_format($proyeksiKonservatif) }}</div>
                <div style="font-size:9px;color:#854F0B">tabung</div>
            </div>
        </div>

        {{-- Progress bar --}}
        <div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-bottom:4px">
                <span>Progress bulan ini</span>
                <span style="font-weight:600;color:#534AB7">{{ $pctTercapai }}% dari proyeksi realistis</span>
            </div>
            <div style="height:8px;background:#f0f0f0;border-radius:4px;overflow:hidden;position:relative">
                <div style="height:100%;width:{{ $pctTercapai }}%;background:#7F77DD;border-radius:4px;transition:width .4s"></div>
                {{-- Marker hari berjalan --}}
                <div style="position:absolute;top:0;left:{{ $pctJalan }}%;width:2px;height:100%;background:#E24B4A;opacity:.7"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:9px;color:var(--text3);margin-top:3px">
                <span>0</span>
                <span style="color:#E24B4A">← hari ke-{{ $hariIni }} ({{ $pctJalan }}%)</span>
                <span>{{ number_format($proyeksiRealistis) }} tab</span>
            </div>
        </div>

        {{-- Vs bulan lalu --}}
        @if($prevTotal > 0)
        <div style="background:{{ $selisih >= 0 ? '#E1F5EE' : '#FCEBEB' }};border-radius:8px;padding:9px 12px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="font-size:10px;color:{{ $selisih >= 0 ? '#0F6E56' : '#A32D2D' }};font-weight:600">
                    {{ $selisih >= 0 ? '↑' : '↓' }} vs bulan lalu ({{ number_format($prevTotal) }} tab)
                </div>
                <div style="font-size:10px;color:var(--text3);margin-top:2px">
                    {{ $prevPeriod ? $prevPeriod->label : 'bulan lalu' }}
                </div>
            </div>
            <div style="text-align:right">
                <div style="font-size:16px;font-weight:600;color:{{ $selisih >= 0 ? '#0F6E56' : '#A32D2D' }}">
                    {{ $selisih >= 0 ? '+' : '' }}{{ number_format($selisih) }}
                </div>
                <div style="font-size:10px;color:{{ $selisih >= 0 ? '#0F6E56' : '#A32D2D' }}">{{ $vsLalu }}% dari bln lalu</div>
            </div>
        </div>
        @endif

        {{-- Tabel proyeksi per pangkalan --}}
        <div style="font-size:11px;font-weight:600;color:var(--text2);margin-bottom:6px">Per pangkalan</div>
        <div class="scroll-x">
            <table class="mob-table" style="font-size:11px">
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
                        $w = $warna($row['pace']);
                        $barW = $row['pace'] !== null ? min($row['pace'], 100) : 0;
                    @endphp
                    <tr>
                        <td class="bold">{{ $row['outlet']->name }}</td>
                        <td class="r" style="color:#378ADD">{{ number_format($row['qtySkrg']) }}</td>
                        <td class="r" style="color:#7F77DD;font-weight:600">{{ number_format($row['proyeksi']) }}</td>
                        @if($prevTotal > 0)
                        <td class="r" style="color:var(--text3)">{{ $row['qtyLalu'] > 0 ? number_format($row['qtyLalu']) : '-' }}</td>
                        @endif
                        <td>
                            @if($row['pace'] !== null)
                                <span style="font-size:10px;background:{{ $w['bg'] }};color:{{ $w['text'] }};padding:1px 6px;border-radius:4px;display:inline-block;margin-bottom:3px">
                                    {{ $row['pace'] }}%
                                </span>
                            @else
                                <span style="font-size:10px;color:var(--text3)">—</span>
                            @endif
                            <div style="height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden">
                                <div style="height:100%;width:{{ $barW }}%;background:{{ $w['text'] }};border-radius:2px"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    {{-- Total --}}
                    <tr style="border-top:1px solid var(--border)">
                        <td class="bold">Total</td>
                        <td class="r bold" style="color:#378ADD">{{ number_format($grandTotal) }}</td>
                        <td class="r bold" style="color:#7F77DD">{{ number_format($proyeksiRealistis) }}</td>
                        @if($prevTotal > 0)
                        <td class="r" style="color:var(--text3)">{{ number_format($prevTotal) }}</td>
                        @endif
                        <td>
                            @if($vsLalu !== null)
                            @php $wt = $warna($vsLalu); @endphp
                            <span style="font-size:10px;background:{{ $wt['bg'] }};color:{{ $wt['text'] }};padding:1px 6px;border-radius:4px;display:inline-block">
                                {{ $vsLalu }}%
                            </span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Rekomendasi otomatis --}}
        <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px">
            @if($selisih < 0 && $prevTotal > 0)
                @php $targetHarian = $prevTotal > 0 ? round(($prevTotal - $grandTotal) / max($sisaHari, 1)) : 0; @endphp
                <div style="background:#FCEBEB;border-radius:6px;padding:7px 9px;font-size:10px;color:#791F1F">
                    ⚠ Butuh <strong>+{{ number_format($targetHarian) }} tab/hari</strong> di sisa {{ $sisaHari }} hari untuk kejar bulan lalu
                </div>
            @endif
            @if($hariAktifDO / max($hariIni,1) < 0.5)
                <div style="background:#FAEEDA;border-radius:6px;padding:7px 9px;font-size:10px;color:#854F0B">
                    ⚠ Hanya {{ $hariAktifDO }} dari {{ $hariIni }} hari ada DO — frekuensi pengiriman perlu ditingkatkan
                </div>
            @endif
            @if($selisih >= 0 || ($vsLalu !== null && $vsLalu >= 90))
                <div style="background:#E1F5EE;border-radius:6px;padding:7px 9px;font-size:10px;color:#0F6E56">
                    ✓ Laju DO berjalan baik — pertahankan konsistensi pengiriman
                </div>
            @endif
        </div>
    </div>
</div>


{{-- Chart Nilai DO Harian --}}
@if(count($hariDOLabels) > 0)
<div class="s-card">
    <div class="s-card-header">📈 Nilai DO Harian (Rp)</div>
    <div style="padding:12px 14px">
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:8px">
            <span style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:4px">
                <span style="width:10px;height:10px;background:#FAC775;border-radius:2px;display:inline-block"></span>Nilai DO
            </span>
            <span style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:4px">
                <span style="width:14px;border-top:2px solid #1D9E75;display:inline-block"></span>Terbayar
            </span>
            <span style="font-size:10px;color:var(--text3);display:flex;align-items:center;gap:4px">
                <span style="width:14px;border-top:2px dashed #E24B4A;display:inline-block"></span>Piutang
            </span>
        </div>
        <div style="position:relative;width:100%;height:200px">
            <canvas id="cDOBlade"></canvas>
        </div>
    </div>
</div>
@endif

{{-- Ranking Piutang --}}
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
                    $rColor   = $rankColors[$i] ?? '#888780';
                    $barColor = $rp['pct_lunas'] >= 70 ? '#1D9E75' : ($rp['pct_lunas'] >= 40 ? '#EF9F27' : '#E24B4A');
                    $pctShare = $totalPiutangAll > 0 ? round($rp['piutang'] / $totalPiutangAll * 100) : 0;
                    if ($rp['pct_lunas'] == 0)        { $sLbl='Belum';        $sBadge='badge-red'; }
                    elseif ($rp['pct_lunas'] < 50)    { $sLbl='Sebagian kecil'; $sBadge='badge-orange'; }
                    elseif ($rp['pct_lunas'] < 80)    { $sLbl='Sebagian';     $sBadge='badge-blue'; }
                    else                              { $sLbl='Hampir lunas'; $sBadge='badge-green'; }
                @endphp
                <tr>
                    <td>
                        <span style="width:20px;height:20px;border-radius:50%;background:{{ $rColor }};color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:600">{{ $i+1 }}</span>
                    </td>
                    <td class="bold">
                        {{ $rp['name'] }}
                        @if($rp['has_carryover']) <span style="font-size:9px;color:#b45309">↩ c/o</span> @endif
                    </td>
                    <td class="r">Rp {{ number_format($rp['nilai']) }}</td>
                    <td class="r" style="color:var(--melon-dark)">Rp {{ number_format($rp['bayar']) }}</td>
                    <td class="r bold" style="color:{{ $rColor }}">Rp {{ number_format($rp['piutang']) }}</td>
                    <td>
                        <div style="font-size:9px;color:var(--text3);margin-bottom:3px">{{ $rp['pct_lunas'] }}% · {{ $pctShare }}% total</div>
                        <div style="height:5px;background:#f0f0f0;border-radius:3px;overflow:hidden">
                            <div style="height:100%;width:{{ $rp['pct_lunas'] }}%;background:{{ $barColor }};border-radius:3px"></div>
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
<div style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px;font-size:12px;color:#166534;margin-bottom:10px">
    ✅ Semua pangkalan sudah lunas — tidak ada piutang DO
</div>
@endif

{{-- Prediksi + Status Donut --}}
{{--  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">  --}}
    <div class="s-card" style="margin-bottom:10px">
        <div class="s-card-header">🔮 Prediksi Akhir Bulan</div>
        <div style="padding:10px 12px">
            <table class="mob-table">
                <tr>
                    <td style="color:var(--text3)">Hari aktif</td>
                    <td class="r bold" style="color:#185FA5">{{ $activeDaysDO }} / {{ $daysInMonth }} hari</td>
                </tr>
                <tr>
                    <td style="color:var(--text3)">Nilai berjalan</td>
                    <td class="r bold" style="color:#c2410c">Rp {{ number_format($grandValue) }}</td>
                </tr>
                <tr>
                    <td style="color:var(--text3)">Proyeksi akhir bulan</td>
                    <td class="r bold" style="color:#7F77DD">Rp {{ number_format($proyeksiTotal) }}</td>
                </tr>
                <tr>
                    <td style="color:var(--text3)">Piutang cair (estimasi)</td>
                    <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($totalPiutangAll) }}</td>
                </tr>
            </table>
            <div style="background:#EEEDFE;border-radius:6px;padding:8px 10px;margin-top:8px">
                <div style="font-size:10px;font-weight:600;color:#534AB7;margin-bottom:4px">Skenario semua lunas</div>
                <div style="display:flex;justify-content:space-between;font-size:11px">
                    <span style="color:var(--text3)">Kas masuk tambahan</span>
                    <span style="font-weight:600;color:#3C3489">+ Rp {{ number_format($totalPiutangAll) }}</span>
                </div>
            </div>
        </div>
    </div>
{{--  </div>  --}}



    <div class="s-card" style="margin-bottom:10px">
        <div class="s-card-header">🍩 Status DO</div>
        <div style="padding:10px 12px">
            <div style="position:relative;height:120px">
                <canvas id="cStatusBlade"></canvas>
            </div>
            <div style="margin-top:8px;display:flex;flex-direction:column;gap:4px">
                @foreach([['Lunas',$doLunas,'badge-green'],['Sebagian',$doSebagian,'badge-orange'],['Belum',$doBelum,'badge-red']] as [$lbl,$jml,$cls])
                @if($jml > 0)
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span class="badge {{ $cls }}">{{ $lbl }}</span>
                    <span style="font-size:11px;font-weight:600;color:var(--text2)">{{ $jml }} DO</span>
                </div>
                @endif
                @endforeach
            </div>
        </div>
    </div>

{{-- Indikator + Bar Pangkalan --}}
{{--  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">  --}}
    <div class="s-card" style="margin-bottom:10px">
        <div class="s-card-header">📊 Volume per Pangkalan</div>
        <div style="padding:10px 12px;position:relative;height:180px">
            <canvas id="cPangkBlade"></canvas>
        </div>
    </div>

    <div class="s-card" style="margin-bottom:10px">
        <div class="s-card-header">🩺 Indikator Kesehatan</div>
        <div style="padding:10px 12px;display:flex;flex-direction:column;gap:10px">
            @php
            $indikators = [
                ['Pelunasan DO', number_format($rasioLunas,1).'%', $rasioLunas, $rasioLunas>=85?'#1D9E75':($rasioLunas>=70?'#EF9F27':'#E24B4A')],
                ['Piutang carry-over', $pctCarryover.'%', $pctCarryover, $pctCarryover<20?'#1D9E75':($pctCarryover<40?'#EF9F27':'#E24B4A')],
                ['Hari aktif DO', $activeDaysDO.' hari', $rasioAktifDO, '#378ADD'],
                ['Konsentrasi 1 pangkalan', $konsentrasiDO.'%', $konsentrasiDO, $konsentrasiDO<30?'#1D9E75':($konsentrasiDO<50?'#EF9F27':'#E24B4A')],
            ];
            @endphp
            @foreach($indikators as [$lbl,$val,$bar,$col])
            <div>
                <div style="display:flex;justify-content:space-between;margin-bottom:3px">
                    <span style="font-size:10px;color:var(--text3)">{{ $lbl }}</span>
                    <span style="font-size:11px;font-weight:600;color:{{ $col }}">{{ $val }}</span>
                </div>
                <div style="height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden">
                    <div style="height:100%;width:{{ min($bar,100) }}%;background:{{ $col }};border-radius:2px"></div>
                </div>
            </div>
            @endforeach

            {{-- Rekomendasi --}}
            <div style="margin-top:4px;display:flex;flex-direction:column;gap:6px">
                @if($rasioLunas < 85)
                <div style="background:#fef2f2;border-radius:6px;padding:7px 9px;font-size:10px;color:#991b1b">
                    ⚠ Pelunasan {{ number_format($rasioLunas,1) }}% — tagih <strong>{{ $topPangkalan }}</strong>
                </div>
                @endif
                @if($pctCarryover > 30)
                <div style="background:#fffbeb;border-radius:6px;padding:7px 9px;font-size:10px;color:#92400e">
                    ⚠ {{ $pctCarryover }}% piutang dari carry-over — tinjau batas kredit
                </div>
                @endif
                @if($konsentrasiDO > 50)
                <div style="background:#fffbeb;border-radius:6px;padding:7px 9px;font-size:10px;color:#92400e">
                    ⚠ {{ $konsentrasiDO }}% DO ke 1 pangkalan — diversifikasi outlet
                </div>
                @endif
                @if($rasioLunas >= 85 && $pctCarryover <= 30 && $konsentrasiDO <= 50)
                <div style="background:#f0fdf4;border-radius:6px;padding:7px 9px;font-size:10px;color:#166534">
                    ✓ Semua indikator DO baik
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Detail DO List --}}
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
                    <td class="r" style="color:#1d4ed8">Rp {{ number_format($do->transfers->sum('surplus')) }}</td>
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
                        <div style="display:flex;gap:8px">
                            <a href="{{ route('do.edit', $do) }}" style="font-size:11px;color:#2563eb">Edit</a>
                            <form method="POST" action="{{ route('do.destroy', $do) }}" style="display:inline" onsubmit="return confirm('Hapus DO ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-btn" style="color:#dc2626">Hapus</button>
                            </form>
                        </div>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:20px;color:var(--text3)">Belum ada DO baru untuk periode ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Carry-over DO --}}
@if($carryoverDOs->count() > 0)
<div class="s-card" style="border-left:3px solid #f97316">
    <div class="s-card-header" style="background:#fff7ed;border-color:#fed7aa;display:flex;justify-content:space-between;align-items:center">
        <span style="color:#9a3412">↩️ Piutang DO Carry-Over (dari bulan lalu)</span>
        <span class="badge badge-orange">{{ number_format($carryoverDOs->sum('qty')) }} tab · Rp {{ number_format($carryoverDOs->sum(fn($d) => $d->qty * $d->price_per_unit)) }}</span>
    </div>
    <div style="background:#fff7ed;padding:8px 14px;font-size:11px;color:#c2410c;border-bottom:0.5px solid #fed7aa">
        ⚠️ Ini hanya <strong>piutang pembayaran</strong> ke agen dari bulan lalu. Stoknya sudah terhitung di Stok Awal ({{ number_format($period->opening_stock) }} tabung) — tidak dihitung ulang.
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
                    <td style="color:var(--text3)">{{ $do->do_date->format('d/m/Y') }}</td>
                    <td class="bold">{{ $do->outlet->name }}</td>
                    <td class="r bold">{{ number_format($do->qty) }}</td>
                    <td class="r">Rp {{ number_format($do->qty * $do->price_per_unit) }}</td>
                    <td class="r" style="color:var(--melon-dark)">Rp {{ number_format($do->paid_amount + $do->transfers->sum('surplus')) }}</td>
                    <td class="r" style="color:#1d4ed8">Rp {{ number_format($do->transfers->sum('surplus')) }}</td>
                    <td class="r bold" style="color:{{ $do->remainingAmount() > 0 ? '#991b1b' : 'var(--melon-dark)' }}">
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
                        <div style="display:flex;gap:8px">
                            <a href="{{ route('do.edit', $do) }}" style="font-size:11px;color:#2563eb">Edit</a>
                            <form method="POST" action="{{ route('do.destroy', $do) }}" style="display:inline" onsubmit="return confirm('Hapus DO carry-over ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-btn" style="color:#dc2626">Hapus</button>
                            </form>
                        </div>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
(function(){
    const GRAY='rgba(0,0,0,0.06)', TICK={font:{size:10},color:'#9CA3AF'};
    const fmtK=v=>'Rp '+Math.round(Math.abs(v)/1000).toLocaleString('id')+'k';

    const hariLabels  = @json($hariDOLabels);
    const hariNilai   = @json($hariDONilai);
    const hariKas     = @json($hariDOBayar);
    const hariPiutang = @json($hariDOPiutang);
    const pangkNames  = @json($pangkalanBar->pluck('name'));
    const pangkNew    = @json($pangkalanBar->pluck('qty_new'));
    const pangkCarry  = @json($pangkalanBar->pluck('qty_carry'));

    const elDO = document.getElementById('cDOBlade');
    if(elDO && hariLabels.length){
        new Chart(elDO,{
            data:{
                labels: hariLabels.map(d=>''+d),
                datasets:[
                    {type:'bar', label:'Nilai DO', data:hariNilai.map(v=>v/1000), backgroundColor:'#FAC77580', borderRadius:3, order:2},
                    {type:'line',label:'Terbayar', data:hariKas.map(v=>v/1000), borderColor:'#1D9E75', borderWidth:2, pointRadius:2.5, tension:0.3, fill:false, backgroundColor:'transparent', order:1},
                    {type:'line',label:'Piutang',  data:hariPiutang.map(v=>v/1000), borderColor:'#E24B4A', borderWidth:1.5, borderDash:[4,3], pointRadius:2, tension:0.3, fill:false, backgroundColor:'transparent', order:1},
                ]
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{legend:{display:false}, tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${fmtK(ctx.parsed.y*1000)}`}}},
                scales:{
                    x:{grid:{color:GRAY},ticks:TICK},
                    y:{grid:{color:GRAY},ticks:{...TICK,callback:v=>v+'k'},title:{display:true,text:'Ribuan Rp',color:'#9CA3AF',font:{size:10}}},
                }
            }
        });
    }

    const elStatus = document.getElementById('cStatusBlade');
    if(elStatus){
        new Chart(elStatus,{
            type:'doughnut',
            data:{
                labels:['Lunas','Sebagian','Belum'],
                datasets:[{data:[{{ $doLunas }},{{ $doSebagian }},{{ $doBelum }}], backgroundColor:['#1D9E75','#EF9F27','#E24B4A'], borderWidth:1, borderColor:'#fff'}]
            },
            options:{responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{legend:{display:false}}}
        });
    }

    const elPangk = document.getElementById('cPangkBlade');
    if(elPangk && pangkNames.length){
        new Chart(elPangk,{
            type:'bar',
            data:{
                labels: pangkNames,
                datasets:[
                    {label:'DO baru',    data:pangkNew,   backgroundColor:'#FAC77599', borderRadius:3},
                    {label:'Carry-over', data:pangkCarry, backgroundColor:'#F0997B99', borderRadius:3},
                ]
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{legend:{display:true, labels:{font:{size:10},boxWidth:10,padding:8}}, tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${ctx.parsed.y} tab`}}},
                scales:{x:{grid:{display:false},ticks:TICK,stacked:false}, y:{grid:{color:GRAY},ticks:TICK}}
            }
        });
    }
})();
</script>
@endpush