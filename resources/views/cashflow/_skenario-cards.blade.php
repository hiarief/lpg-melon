{{-- cashflow/_skenario-cards.blade.php --}}
{{-- @param $predKas      int --}}
{{-- @param $predKasPesim int --}}
{{-- @param $predKasOptim int --}}
{{-- @param $gajiPesim    int|null --}}
{{-- @param $gajiOptim    int|null --}}
{{-- @param $piutang      int --}}
{{-- @param $labelPesim   string (default: Pesimis) --}}
{{-- @param $labelOptim   string (default: Optimis) --}}
{{-- @param $labelDasar   string (default: Konservatif) --}}
{{-- @param $notePesim    string|null --}}
{{-- @param $noteOptim    string|null --}}
{{-- @param $accentColor  string (default: #c2410c) --}}
@php
    $labelPesim  ??= 'Pesimis';
    $labelOptim  ??= 'Optimis';
    $labelDasar  ??= 'Konservatif';
    $notePesim   ??= null;
    $noteOptim   ??= null;
    $accentColor ??= '#c2410c';
    $mainBg      = $accentColor === '#16a34a' ? '#f0fdf4' : '#fff7ed';
    $mainBorder  = $accentColor === '#16a34a' ? '#22c55e' : '#f97316';
    $mainLabel   = $accentColor === '#16a34a' ? '#166534' : '#c2410c';
    $mainBadgeBg = $accentColor === '#16a34a' ? '#16a34a' : 'var(--melon)';
@endphp
<div>
    <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
        Proyeksi Saldo KAS Akhir Bulan
        <span style="font-size:10px;font-weight:400;color:var(--text3)">(sudah dipotong gaji kurir)</span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
        {{-- Pesimis --}}
        <div style="background:#fef2f2;border:0.5px solid #fca5a5;border-radius:8px;padding:10px 12px">
            <div style="font-size:9px;font-weight:700;color:#dc2626;text-transform:uppercase;margin-bottom:4px">{{ $labelPesim }}</div>
            <div style="font-size:14px;font-weight:700;color:#991b1b">{{ number_format(round($predKasPesim)) }}</div>
            @if($notePesim)
                <div style="font-size:9px;color:#dc2626;margin-top:4px">{{ $notePesim }}</div>
            @elseif(!is_null($gajiPesim))
                <div style="font-size:9px;color:#dc2626;margin-top:4px">Gaji est.: {{ number_format($gajiPesim) }}</div>
            @endif
        </div>
        {{-- Utama/Dasar --}}
        <div style="background:{{ $mainBg }};border:2px solid {{ $mainBorder }};border-radius:8px;padding:10px 12px;position:relative">
            <div style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:{{ $mainBadgeBg }};color:#fff;font-size:8px;font-weight:700;padding:2px 8px;border-radius:10px;white-space:nowrap">UTAMA</div>
            <div style="font-size:9px;font-weight:700;color:{{ $mainLabel }};text-transform:uppercase;margin-bottom:4px;margin-top:4px">{{ $labelDasar }}</div>
            <div style="font-size:16px;font-weight:700;color:{{ $predKas >= 0 ? $mainLabel : '#dc2626' }}">{{ number_format(round($predKas)) }}</div>
            @if($piutang > 0)
                <div style="font-size:9px;color:{{ $mainLabel }};margin-top:3px">+Est. piutang: {{ number_format($piutang) }}</div>
            @endif
            <div style="font-size:9px;font-weight:600;color:{{ $predKas >= 0 ? $mainLabel : '#dc2626' }};margin-top:4px">
                {{ $predKas >= 0 ? '✓ Aman' : '⚠ Defisit' }}
            </div>
        </div>
        {{-- Optimis --}}
        <div style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px">
            <div style="font-size:9px;font-weight:700;color:var(--melon-dark);text-transform:uppercase;margin-bottom:4px">{{ $labelOptim }}</div>
            <div style="font-size:14px;font-weight:700;color:#166534">{{ number_format(round($predKasOptim)) }}</div>
            @if($noteOptim)
                <div style="font-size:9px;color:var(--melon-dark);margin-top:4px">{{ $noteOptim }}</div>
            @elseif(!is_null($gajiOptim))
                <div style="font-size:9px;color:var(--melon-dark);margin-top:4px">Gaji est.: {{ number_format($gajiOptim) }}</div>
            @endif
        </div>
    </div>
</div>