{{-- cashflow/_section-header.blade.php --}}
{{-- @param $color string   warna dot --}}
{{-- @param $label string   teks label --}}
<div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
    <span style="width:7px;height:7px;border-radius:50%;background:{{ $color }};flex-shrink:0"></span>
    <span style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px">{{ $label }}</span>
</div>
