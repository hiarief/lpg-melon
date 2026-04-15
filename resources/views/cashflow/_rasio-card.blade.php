{{-- cashflow/_rasio-card.blade.php --}}
{{-- @param $label     string   label utama --}}
{{-- @param $note      string   keterangan kecil (opsional) --}}
{{-- @param $value     float    nilai rasio (%) --}}
{{-- @param $threshold int      ambang batas merah --}}
{{-- @param $ideal     string   teks ideal --}}
@php
    $isWarn  = $value > $threshold;
    $barBg   = $isWarn ? ($threshold === 35 ? '#f59e0b' : '#ef4444') : 'var(--melon)';
    $valCol  = $isWarn ? ($threshold === 35 ? '#b45309' : '#dc2626') : 'var(--melon-dark)';
    $infoTxt = $isWarn ? ($threshold === 35 ? '⚠ perlu efisiensi' : '⚠ perhatian') : '✓ sehat';
@endphp
<div class="card" style="padding:10px 12px;{{ $isWarn && $threshold === 80 ? 'border-color:#fca5a5' : '' }}">
    <div style="font-size:10px;color:var(--text3)">
        {{ $label }}
        @if(!empty($note))
            <span style="font-size:9px;font-style:italic"> {{ $note }}</span>
        @endif
    </div>
    <div style="font-size:16px;font-weight:600;color:{{ $valCol }};margin-top:2px">{{ number_format($value, 1) }}%</div>
    <div style="height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden;margin:4px 0">
        <div style="height:100%;width:{{ min($value, 100) }}%;background:{{ $barBg }};border-radius:2px"></div>
    </div>
    <div style="font-size:10px;color:var(--text3)">{{ $ideal }}</div>
</div>