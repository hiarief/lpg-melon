{{-- cashflow/_realisasi-card.blade.php --}}
{{-- Ditampilkan saat period sudah tertutup (remainDays === 0) --}}
@php
    $gajiRealisasi  = $totalTabungAktual * 500;
    $kasSetelahGaji = $netKas - $gajiRealisasi;
    $netBersih      = $netKas + $finalBankBal - $gajiRealisasi;
    $rasioRealOps   = $totalMargin > 0 ? round($totalExpense / $totalMargin * 100, 1) : 0;
    $rasioGrosReal  = $summary['totalAvailable'] > 0
        ? round(($totalExpense + $totalDeposits + $totalAdminFees + $gajiRealisasi) / $summary['totalAvailable'] * 100, 1)
        : 0;
@endphp
<div class="s-card">
    <div class="s-card-header" style="background:#f0fdf4;border-color:#86efac;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="color:#166534">✅ Realisasi Akhir Bulan</span>
        <span class="badge badge-green">Data 100% aktual</span>
        <span style="margin-left:auto;font-size:10px;color:var(--text3)">{{ $daysInMonth }} hari · {{ $summary['cfActiveDays'] }} hari aktif</span>
    </div>
    <div style="padding:12px 14px;display:flex;flex-direction:column;gap:10px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
            <div style="background:#fffbeb;border:0.5px solid #fde68a;border-radius:8px;padding:10px 12px">
                <div style="font-size:10px;color:#b45309">Saldo KAS (sebelum gaji)</div>
                <div style="font-size:16px;font-weight:600;color:{{ $netKas >= 0 ? '#92400e' : '#dc2626' }};margin-top:2px">Rp {{ number_format($netKas) }}</div>
            </div>
            <div style="background:#f0fdf4;border:2px solid var(--melon);border-radius:8px;padding:10px 12px;position:relative">
                <div style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:var(--melon);color:#fff;font-size:8px;font-weight:700;padding:2px 8px;border-radius:10px;white-space:nowrap">BERSIH</div>
                <div style="font-size:10px;color:var(--melon-dark);margin-top:4px">KAS setelah gaji kurir</div>
                <div style="font-size:16px;font-weight:600;color:{{ $kasSetelahGaji >= 0 ? 'var(--melon-dark)' : '#dc2626' }};margin-top:2px">Rp {{ number_format($kasSetelahGaji) }}</div>
                <div style="font-size:9px;color:var(--melon-dark);margin-top:3px">Gaji: Rp {{ number_format($gajiRealisasi) }} ({{ number_format($totalTabungAktual) }} tab × 500)</div>
            </div>
            <div style="background:#eff6ff;border:0.5px solid #bfdbfe;border-radius:8px;padding:10px 12px">
                <div style="font-size:10px;color:#3b82f6">Saldo BANK Penampung</div>
                <div style="font-size:16px;font-weight:600;color:{{ $finalBankBal >= 0 ? '#1e40af' : '#dc2626' }};margin-top:2px">Rp {{ number_format($finalBankBal) }}</div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
            @foreach([
                ['Rasio Ops / Margin Bersih', $rasioRealOps,  35, 'ideal <35%'],
                ['Rasio Gross Kas Keluar',    $rasioGrosReal, 80, 'ideal <80%'],
            ] as [$lbl, $val, $thresh, $ideal])
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">{{ $lbl }}</div>
                <div style="font-size:15px;font-weight:600;color:{{ $val > $thresh ? ($thresh === 35 ? '#b45309' : '#dc2626') : 'var(--melon-dark)' }};margin-top:2px">{{ number_format($val, 1) }}%</div>
                <div style="font-size:9px;color:var(--text3)">{{ $ideal }}</div>
            </div>
            @endforeach
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Total Aset (KAS + BANK − Gaji)</div>
                <div style="font-size:15px;font-weight:600;color:{{ $netBersih >= 0 ? 'var(--text1)' : '#dc2626' }};margin-top:2px">Rp {{ number_format($netBersih) }}</div>
            </div>
        </div>
    </div>
</div>