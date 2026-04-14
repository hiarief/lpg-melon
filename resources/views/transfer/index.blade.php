@extends('layouts.app')
@section('title','Transfer & Setoran')

@section('content')

@php
$totalNominal = $deposits->sum(fn($d) => $d->amount + $d->admin_fee);
$totalBersih  = $deposits->sum('amount');

// Tren harian
$depositsByDay  = [];
$transfersByDay = [];
foreach ($deposits as $d) {
    $day = $d->deposit_date->day;
    $depositsByDay[$day] = ($depositsByDay[$day] ?? 0) + $d->amount;
}
foreach ($transfers as $tf) {
    $day = $tf->transfer_date->day;
    $transfersByDay[$day] = ($transfersByDay[$day] ?? 0) + $tf->amount;
}

$trendLabels = []; $trendDep = []; $trendTf = []; $trendSaldo = [];
$saldoRun = $period->opening_penampung;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dep = $depositsByDay[$d] ?? 0;
    $tf  = $transfersByDay[$d] ?? 0;
    if ($dep > 0 || $tf > 0) {
        $saldoRun += $dep - $tf;
        $trendLabels[] = $d;
        $trendDep[]    = $dep;
        $trendTf[]     = $tf;
        $trendSaldo[]  = $saldoRun;
    }
}

// Setoran per kurir
$kurirMap = [];
foreach ($deposits as $d) {
    $name = $d->courier->name;
    $kurirMap[$name] = ($kurirMap[$name] ?? 0) + $d->amount;
}
arsort($kurirMap);
$kurirNames  = array_keys($kurirMap);
$kurirTotals = array_values($kurirMap);

// Indikator
$utilisasi  = $totalDeposited > 0 ? round($totalTransferred / $totalDeposited * 100) : 0;
$rasioAdmin = $totalDeposited > 0 ? round($totalAdmin / $totalDeposited * 100, 2) : 0;
$piutangPct = isset($totalPiutangDO) && ($totalPiutangDO + $totalTransferred) > 0
    ? round($totalPiutangDO / ($totalPiutangDO + $totalTransferred) * 100) : 0;
@endphp

<style>
.tf-tab-strip { display:flex;border-bottom:0.5px solid var(--border);margin-bottom:14px;gap:0; }
.tf-tab-btn {
    display:flex;align-items:center;gap:6px;padding:10px 16px;
    font-size:12px;font-weight:600;background:none;border:none;
    border-bottom:2px solid transparent;cursor:pointer;
    color:var(--text3);transition:color .15s,border-color .15s;white-space:nowrap;
}
.tf-tab-btn.active { color:var(--melon-dark);border-bottom-color:var(--melon-dark); }
.tf-tab-btn:hover:not(.active) { color:var(--text1);background:var(--surface2); }
.tf-tab-btn svg { width:14px;height:14px;flex-shrink:0; }

.tf-flow-strip {
    display:flex;align-items:center;border:0.5px solid var(--border);
    border-radius:var(--radius-sm);overflow:hidden;margin-bottom:14px;
}
.tf-flow-node { flex:1;padding:10px 8px;text-align:center; }
.tf-flow-sep { font-size:15px;color:var(--text3);flex-shrink:0;padding:0 2px; }
.tf-flow-label { font-size:10px;font-weight:600;margin-bottom:3px; }
.tf-flow-val { font-size:12px;font-weight:600; }

.ind-row { display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px; }
.ind-meta { flex:1; }
.ind-title { font-size:11px;color:var(--text2); }
.ind-track { width:100%;height:5px;background:var(--melon-light);border-radius:3px;margin-top:5px;overflow:hidden; }
.ind-fill  { height:100%;border-radius:3px; }
.ind-right { text-align:right;flex-shrink:0; }
.ind-val   { font-size:13px;font-weight:700; }
.ind-note  { font-size:10px;color:var(--text3); }

.pill-box { display:flex;flex-direction:column;gap:6px; }
.pill { display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border-radius:8px;font-size:11px; }
.pill-icon { font-size:13px;flex-shrink:0;margin-top:1px; }
.pill-green { background:var(--melon-50);color:var(--melon-deep);border:0.5px solid var(--border); }
.pill-red   { background:#fef2f2;color:#991b1b;border:0.5px solid #fca5a5; }
.pill-orange{ background:#fff7ed;color:#9a3412;border:0.5px solid #fed7aa; }
.pill-blue  { background:#eff6ff;color:#1e40af;border:0.5px solid #bfdbfe; }

.chart-legend { display:flex;flex-wrap:wrap;gap:12px;margin-bottom:10px; }
.leg-item { display:flex;align-items:center;gap:5px;font-size:10px;color:var(--text3); }
.leg-sq   { width:10px;height:10px;border-radius:2px;flex-shrink:0; }
.leg-dash { width:18px;border-top:2.5px dashed;flex-shrink:0; }

.tf-tab-strip {
    display: flex;
    border-bottom: 0.5px solid var(--border);
    margin-bottom: 14px;
    gap: 0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;       /* Firefox */
    flex-wrap: nowrap;
}
.tf-tab-strip::-webkit-scrollbar { display: none; } /* Chrome/Safari */

.tf-tab-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 9px 12px;           /* sedikit lebih kecil dari 10px 16px */
    font-size: 11px;             /* turun dari 12px */
    font-weight: 600;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    color: var(--text3);
    transition: color .15s, border-color .15s;
    white-space: nowrap;         /* penting: cegah teks wrap */
    flex-shrink: 0;              /* penting: cegah tombol menyusut */
}
</style>

<div x-data="{ tabActive: 'setoran' }">

{{-- ══ HEADER ══ --}}
<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;margin-bottom:12px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="font-size:15px;font-weight:600;color:var(--text1)">🏦 Transfer & Setoran</span>
        <form method="GET" action="{{ route('transfer.index') }}">
            <select name="period_id" onchange="this.form.submit()" class="field-select" style="padding:6px 10px;font-size:12px;width:auto">
                @foreach($periods as $p)
                    <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

 {{-- Summary --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
        <div class="card" style="padding:10px 12px;border-left:3px">
            <div style="font-size:10px;color:var(--text3)">Saldo Awal Penampung</div>
            <div style="font-size:16px;font-weight:600;color:#1d4ed8">Rp {{ number_format($period->opening_penampung) }}</div>
        </div>
        <div class="card" style="padding:10px 12px;border-left:3px">
            <div style="font-size:10px;color:var(--text3)">Total Masuk (Setoran Kurir)</div>
            <div style="font-size:16px;font-weight:600;color:var(--melon-dark)">Rp {{ number_format($totalDeposited) }}</div>
        </div>
        <div class="card" style="padding:10px 12px;border-left:3px">
            <div style="font-size:10px;color:var(--text3)">Total Keluar (ke Rek Utama)</div>
            <div style="font-size:16px;font-weight:600;color:#dc2626">Rp {{ number_format($totalTransferred) }}</div>
        </div>
        <div class="card" style="padding:10px 12px;border-left:3px">
            <div style="font-size:10px;color:var(--text3)">Total Biaya Admin</div>
            <div style="font-size:16px;font-weight:600;color:#dc2626">Rp {{ number_format($totalAdmin) }}</div>
        </div>
        <div class="card" style="grid-column: span 2; padding:10px 12px;border-left:3px">
            <div style="font-size:10px;color:var(--text3)">Saldo Penampung Saat Ini</div>
            <div style="font-size:18px;font-weight:700;color:{{ $penampungNow >= 0 ? '#c2410c' : '#dc2626' }}">
                Rp {{ number_format($penampungNow) }}
            </div>
            <div style="font-size:10px;color:var(--text3)">
                = {{ number_format($period->opening_penampung) }} + {{ number_format($totalDeposited) }} − {{ number_format($totalTransferred) }}
            </div>
        </div>
    </div>

{{-- ══ SALDO BANNER ══ --}}
<div style="background:var(--melon-50);border:0.5px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:14px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <div>
            <div style="font-size:12px;font-weight:600;color:var(--text1)">Saldo Penampung Saat Ini</div>
            <div style="font-size:10px;color:var(--text3);margin-top:2px">
                {{ number_format($period->opening_penampung) }} + {{ number_format($totalDeposited) }} − {{ number_format($totalTransferred) }} − {{ number_format($totalAdmin) }}
            </div>
        </div>
        <div style="font-size:22px;font-weight:700;color:{{ $penampungNow >= 0 ? '#c2410c' : '#dc2626' }};white-space:nowrap">
            Rp {{ number_format($penampungNow) }}
        </div>
    </div>
    <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:10px;">
        @php $utilisasiBar = $totalDeposited > 0 ? min(round(($totalTransferred + $totalAdmin) / ($period->opening_penampung + $totalDeposited) * 100), 100) : 0; @endphp
        <div style="height:100%;width:{{ $utilisasiBar }}%;background:var(--melon);border-radius:3px;transition:width .4s;"></div>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-top:4px;">
        <span>Terpakai {{ $utilisasiBar }}%</span>
        <span>Sisa {{ 100 - $utilisasiBar }}%</span>
    </div>
</div>

{{-- ══ FLOW STRIP ══ --}}
<div class="tf-flow-strip">
    <div class="tf-flow-node" style="background:#eff6ff;">
        <div class="tf-flow-label" style="color:#1e40af;">Awal</div>
        <div class="tf-flow-val" style="color:#1d4ed8;">{{ number_format(round($period->opening_penampung/1000)) }}k</div>
    </div>
    <div class="tf-flow-sep">+</div>
    <div class="tf-flow-node" style="background:var(--melon-50);">
        <div class="tf-flow-label" style="color:var(--melon-deep);">Setoran</div>
        <div class="tf-flow-val" style="color:var(--melon-dark);">{{ number_format(round($totalDeposited/1000)) }}k</div>
    </div>
    <div class="tf-flow-sep">−</div>
    <div class="tf-flow-node" style="background:#fef2f2;">
        <div class="tf-flow-label" style="color:#991b1b;">Transfer</div>
        <div class="tf-flow-val" style="color:#dc2626;">{{ number_format(round($totalTransferred/1000)) }}k</div>
    </div>
    <div class="tf-flow-sep">−</div>
    <div class="tf-flow-node" style="background:#fffbeb;">
        <div class="tf-flow-label" style="color:#92400e;">Admin</div>
        <div class="tf-flow-val" style="color:#b45309;">{{ number_format(round($totalAdmin/1000)) }}k</div>
    </div>
    <div class="tf-flow-sep">=</div>
    <div class="tf-flow-node" style="background:#eff6ff;border-left:2px solid #1d4ed8;">
        <div class="tf-flow-label" style="color:#1e40af;">Saldo</div>
        <div class="tf-flow-val" style="color:{{ $penampungNow >= 0 ? '#1d4ed8' : '#dc2626' }};">{{ number_format(round($penampungNow/1000)) }}k</div>
    </div>
</div>

{{-- ══ TAB STRIP ══ --}}
<div class="tf-tab-strip" style="overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;flex-wrap:nowrap;">
    <button class="tf-tab-btn" :class="tabActive==='setoran'?'active':''" @click="tabActive='setoran'">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 11V4m0 7-3-3m3 3 3-3"/><rect x="2" y="13" width="12" height="1.5" rx="0.75"/></svg>
        Setoran
    </button>
    <button class="tf-tab-btn" :class="tabActive==='transfer'?'active':''" @click="tabActive='transfer'">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 5v7M8 5l-3 3M8 5l3 3"/><rect x="2" y="2" width="12" height="1.5" rx="0.75"/></svg>
        Transfer
    </button>
    <button class="tf-tab-btn" :class="tabActive==='analisis'?'active':''" @click="tabActive='analisis'">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 13l3-4 3 2 3-5 3 4"/></svg>
        Analisis
    </button>
    <button class="tf-tab-btn" :class="tabActive==='riwayat'?'active':''" @click="tabActive='riwayat'">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5l2 2"/></svg>
        Riwayat
    </button>
</div>

{{-- ══════════════ TAB: SETORAN ══════════════ --}}
<div x-show="tabActive==='setoran'" x-cloak>
    @if($period->status === 'open')
    <div class="s-card">
        <div class="s-card-header">+ Input Setoran Kurir ke Rekening Penampung</div>
        <div style="padding:12px 14px">
            <form method="POST" action="{{ route('transfer.deposit.store') }}" style="display:flex;flex-direction:column;gap:10px">
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <label class="field-label">Kurir</label>
                        <select name="courier_id" class="field-select" required>
                            @foreach($couriers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Tanggal Transfer</label>
                        <input type="date" name="deposit_date" value="{{ date('Y-m-d') }}" class="field-input" required>
                    </div>
                    <div>
                        <label class="field-label">Nominal Transfer (Rp)</label>
                        <input type="number" name="amount" min="1" class="field-input" required>
                    </div>
                    <div>
                        <label class="field-label">Biaya Admin (Rp)</label>
                        <input type="number" name="admin_fee" value="0" min="0" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">No. Referensi</label>
                        <input type="text" name="reference_no" class="field-input" placeholder="Opsional">
                    </div>
                    <div>
                        <label class="field-label">Catatan</label>
                        <input type="text" name="notes" class="field-input" placeholder="Opsional">
                    </div>
                </div>
                <div><button type="submit" class="btn-primary">✅ Simpan Setoran</button></div>
            </form>
        </div>
    </div>
    @endif

    <div class="s-card">
        <div class="s-card-header">📋 Daftar Setoran Kurir</div>
        <div class="scroll-x">
            <table class="mob-table">
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Kurir</th>
                        <th class="r">Nominal</th><th class="r">Admin</th><th class="r">Bersih</th>
                        <th>No. Ref</th>
                        @if($period->status==='open') <th>Aksi</th> @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($deposits as $dep)
                    <tr>
                        <td>{{ $dep->deposit_date->format('d/m/Y') }}</td>
                        <td class="bold">{{ $dep->courier->name }}</td>
                        <td class="r">Rp {{ number_format($dep->amount + $dep->admin_fee) }}</td>
                        <td class="r" style="color:{{ $dep->admin_fee>0?'#dc2626':'var(--text3)' }}">{{ $dep->admin_fee>0?'Rp '.number_format($dep->admin_fee):'—' }}</td>
                        <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($dep->amount) }}</td>
                        <td style="color:var(--text3)">{{ $dep->reference_no ?: '—' }}</td>
                        @if($period->status==='open')
                        <td>
                            <form method="POST" action="{{ route('transfer.deposit.destroy', $dep) }}" style="display:inline" onsubmit="return confirm('Hapus?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-btn" style="color:#dc2626">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text3)">Belum ada setoran.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2" class="bold">TOTAL</td>
                        <td class="r bold">Rp {{ number_format($totalNominal) }}</td>
                        <td class="r bold" style="color:#dc2626">{{ $totalAdmin>0?'Rp '.number_format($totalAdmin):'—' }}</td>
                        <td class="r bold">Rp {{ number_format($totalBersih) }}</td>
                        <td colspan="{{ $period->status==='open'?'2':'1' }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- ══════════════ TAB: TRANSFER ══════════════ --}}
<div x-show="tabActive==='transfer'" x-cloak
     x-data="{ mode:'auto', allocations:[], addAlloc(){ this.allocations.push({do_id:'',amount:''}) }, removeAlloc(i){ this.allocations.splice(i,1) } }">

    @if($period->status==='open')
    <div class="s-card">
        <div class="s-card-header">+ Transfer Rekening Penampung → Rekening Utama</div>
        <div style="padding:12px 14px">
            <form method="POST" action="{{ route('transfer.account.store') }}">
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">

                @if($allUnpaidDOs->count() > 0)
                <div style="margin-bottom:12px;border-radius:8px;overflow:hidden;border:0.5px solid #fca5a5">
                    <div style="background:#dc2626;padding:8px 12px;display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:11px;font-weight:600;color:#fff">⚠ DO Belum Lunas</span>
                        <span style="font-size:11px;font-weight:600;color:#fff">Total: Rp {{ number_format($totalPiutangDO) }}</span>
                    </div>
                    <div class="scroll-x">
                        <table class="mob-table">
                            <thead><tr><th>Pangkalan</th><th>Tgl DO</th><th class="r">Qty</th><th class="r">Total DO</th><th class="r">Terbayar</th><th class="r" style="color:#dc2626">Sisa</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($allUnpaidDOs as $udo)
                                @php $isCarryover=str_contains($udo->notes??'','Carry-over'); $isPrev=isset($udo->period)&&$udo->period_id!==$period->id; @endphp
                                <tr style="{{ $isCarryover?'background:#fff7ed':'' }}">
                                    <td class="bold">{{ $udo->outlet->name }} @if($isCarryover)<span style="font-size:9px;color:#c2410c">↩carry</span>@endif</td>
                                    <td style="color:var(--text3)">{{ $udo->do_date->format('d/m/Y') }}</td>
                                    <td class="r">{{ number_format($udo->qty) }}</td>
                                    <td class="r">Rp {{ number_format($udo->qty*$udo->price_per_unit) }}</td>
                                    <td class="r" style="color:var(--melon-dark)">Rp {{ number_format($udo->paid_amount) }}</td>
                                    <td class="r bold" style="color:#dc2626">Rp {{ number_format($udo->remainingAmount()) }}</td>
                                    <td>@if($udo->payment_status==='partial')<span class="badge badge-orange">Sebagian</span>@else<span class="badge badge-red">Belum</span>@endif</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @else
                <div style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:11px;color:#166534">
                    ✅ Tidak ada piutang DO. Transfer ini akan dicatat sebagai surplus/tabungan.
                </div>
                @endif

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                    <div>
                        <label class="field-label">Tanggal Transfer</label>
                        <input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" class="field-input" required>
                    </div>
                    <div>
                        <label class="field-label">Nominal Transfer (Rp)</label>
                        <input type="number" name="amount" min="1" class="field-input" required>
                    </div>
                    <div style="grid-column:1/-1">
                        <label class="field-label">Catatan</label>
                        <input type="text" name="notes" class="field-input" placeholder="Opsional">
                    </div>
                </div>

                <div style="margin-bottom:12px">
                    <label class="field-label">Cara Alokasi ke DO</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <label style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;border-radius:8px;cursor:pointer"
                               :style="mode==='auto'?'border:1px solid var(--melon-mid);background:var(--melon-50)':'border:0.5px solid var(--border);background:var(--surface)'">
                            <input type="radio" name="alloc_mode" value="auto" x-model="mode" style="accent-color:var(--melon);margin-top:2px">
                            <div>
                                <div style="font-size:12px;font-weight:600;color:var(--text1)">⚡ Otomatis</div>
                                <div style="font-size:10px;color:var(--text3)">Lunasi DO terlama dulu, sisa jadi surplus</div>
                            </div>
                        </label>
                        <label style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;border-radius:8px;cursor:pointer"
                               :style="mode==='manual'?'border:1px solid var(--melon-mid);background:var(--melon-50)':'border:0.5px solid var(--border);background:var(--surface)'">
                            <input type="radio" name="alloc_mode" value="manual" x-model="mode" style="accent-color:var(--melon);margin-top:2px">
                            <div>
                                <div style="font-size:12px;font-weight:600;color:var(--text1)">🔧 Manual</div>
                                <div style="font-size:10px;color:var(--text3)">Pilih sendiri DO mana yang dilunasi</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div x-show="mode==='manual'" x-cloak style="margin-bottom:12px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <span style="font-size:11px;font-weight:600;color:var(--text2)">Pilih DO yang dilunasi:</span>
                        <button type="button" @click="addAlloc()" class="btn-secondary btn-sm">+ Tambah DO</button>
                    </div>
                    <template x-for="(alloc,i) in allocations" :key="i">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                            <select :name="'do_allocations['+i+'][do_id]'" x-model="alloc.do_id" class="field-select" style="flex:1" required>
                                <option value="">-- Pilih DO --</option>
                                @foreach($allUnpaidDOs as $udo)
                                <option value="{{ $udo->id }}">{{ $udo->outlet->name }} | {{ $udo->do_date->format('d/m') }} | Sisa Rp {{ number_format($udo->remainingAmount()) }}</option>
                                @endforeach
                            </select>
                            <input type="number" :name="'do_allocations['+i+'][amount]'" x-model="alloc.amount" placeholder="Nominal" min="1" class="field-input" style="width:130px" required>
                            <button type="button" @click="removeAlloc(i)" style="background:none;border:none;font-size:18px;color:#ef4444;cursor:pointer;flex-shrink:0">×</button>
                        </div>
                    </template>
                    <p x-show="allocations.length===0" style="font-size:11px;color:var(--text3);font-style:italic">Klik "+ Tambah DO" untuk alokasi manual.</p>
                </div>

                <button type="submit" class="btn-primary">💾 Simpan Transfer & Perbarui Status DO</button>
            </form>
        </div>
    </div>
    @endif

    <div class="s-card">
        <div class="s-card-header">📋 Riwayat Transfer ke Rek Utama</div>
        <div class="scroll-x">
            <table class="mob-table">
                <thead>
                    <tr><th>Tanggal</th><th class="r">Nominal</th><th class="r">Surplus</th><th>DO Dilunasi</th><th>Catatan</th>@if($period->status==='open')<th>Aksi</th>@endif</tr>
                </thead>
                <tbody>
                    @forelse($transfers as $tf)
                    <tr>
                        <td>{{ $tf->transfer_date->format('d/m/Y') }}</td>
                        <td class="r bold" style="color:#1d4ed8">Rp {{ number_format($tf->amount) }}</td>
                        <td class="r" style="color:{{ $tf->surplus>0?'var(--melon-dark)':'var(--text3)' }}">{{ $tf->surplus>0?'Rp '.number_format($tf->surplus):'—' }}</td>
                        <td>@forelse($tf->deliveryOrders as $do)<span class="badge badge-blue" style="margin-right:3px;margin-bottom:2px">{{ $do->outlet->name }} Rp{{ number_format($do->pivot->amount_allocated/1000) }}k</span>@empty<span style="color:var(--text3)">—</span>@endforelse</td>
                        <td style="color:var(--text3)">{{ $tf->notes ?: '—' }}</td>
                        @if($period->status==='open')
                        <td>
                            <form method="POST" action="{{ route('transfer.account.destroy', $tf) }}" style="display:inline" onsubmit="return confirm('Hapus? Status DO akan di-reset.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-btn" style="color:#dc2626">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text3)">Belum ada transfer.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td class="bold">TOTAL</td>
                        <td class="r bold">Rp {{ number_format($totalTransferAmount) }}</td>
                        <td class="r bold">{{ $totalSurplus>0?'Rp '.number_format($totalSurplus):'—' }}</td>
                        <td colspan="{{ $period->status==='open'?'3':'2' }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- ══════════════ TAB: ANALISIS ══════════════ --}}
<div x-show="tabActive==='analisis'" x-cloak>

    {{-- Chart tren harian --}}
    <div class="s-card">
        <div class="s-card-header">📈 Tren Setoran & Transfer Harian</div>
        <div style="padding:12px 14px;">
            <div class="chart-legend">
                <span class="leg-item"><span class="leg-sq" style="background:var(--melon);opacity:.7;"></span>Setoran masuk</span>
                <span class="leg-item"><span class="leg-sq" style="background:#3b82f6;opacity:.7;"></span>Transfer keluar</span>
                <span class="leg-item"><span class="leg-dash" style="border-color:#b45309;"></span>Saldo penampung</span>
            </div>
            <div style="position:relative;width:100%;height:220px;">
                <canvas id="tfTrendChart" role="img" aria-label="Chart tren setoran masuk dan transfer keluar penampung per hari">Tren setoran dan transfer harian.</canvas>
            </div>
        </div>
    </div>

    {{-- Dua kolom: donut + indikator --}}
    <div class="two-col">
        <div class="s-card" style="margin-bottom:0;">
            <div class="s-card-header">Komposisi Setoran per Kurir</div>
            <div style="padding:12px 14px;">
                <div style="position:relative;height:140px;"><canvas id="tfDonutChart" role="img" aria-label="Donut chart setoran per kurir">Setoran per kurir.</canvas></div>
                <div id="tfDonutLegend" style="margin-top:10px;display:flex;flex-direction:column;gap:4px;"></div>
            </div>
        </div>
        <div class="s-card" style="margin-bottom:0;">
            <div class="s-card-header">Indikator Kesehatan</div>
            <div style="padding:12px 14px;">
                @foreach([
                    ['Utilisasi penampung', $utilisasi.'%', 'tersalurkan ke rek utama', $utilisasi, $utilisasi>=80?'var(--melon)':'#f59e0b'],
                    ['Rasio admin TF', $rasioAdmin.'%', 'dari total setoran · ideal <0.5%', min($rasioAdmin*20,100), $rasioAdmin<0.5?'var(--melon)':'#f59e0b'],
                    ['Piutang DO tersisa', $piutangPct.'%', 'belum terlunasi', $piutangPct, $piutangPct<20?'var(--melon)':($piutangPct<50?'#f59e0b':'#dc2626')],
                ] as [$lbl,$val,$note,$bar,$col])
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
        </div>
    </div>

    {{-- Rekomendasi --}}
    <div class="s-card" style="margin-top:10px;">
        <div class="s-card-header">💡 Rekomendasi</div>
        <div style="padding:12px 14px;" class="pill-box">
            @if($utilisasi >= 80)
            <div class="pill pill-green"><span class="pill-icon">✓</span><span>Utilisasi {{ $utilisasi }}% — penampung berputar sehat, tidak menumpuk kas idle.</span></div>
            @else
            <div class="pill pill-orange"><span class="pill-icon">⚠</span><span>Utilisasi {{ $utilisasi }}% — pertimbangkan mempercepat jadwal transfer ke rek utama.</span></div>
            @endif

            @if($rasioAdmin < 0.5)
            <div class="pill pill-green"><span class="pill-icon">✓</span><span>Biaya admin {{ $rasioAdmin }}% — sangat wajar dari total setoran.</span></div>
            @else
            <div class="pill pill-orange"><span class="pill-icon">⚠</span><span>Biaya admin {{ $rasioAdmin }}% — pertimbangkan transfer batch untuk mengurangi frekuensi admin.</span></div>
            @endif

            @if($piutangPct > 0)
            <div class="pill {{ $piutangPct > 40 ? 'pill-red' : 'pill-orange' }}">
                <span class="pill-icon">{{ $piutangPct > 40 ? '!' : '⚠' }}</span>
                <span>Masih ada {{ $piutangPct }}% piutang DO belum terlunasi — prioritaskan sebelum penutupan periode.</span>
            </div>
            @else
            <div class="pill pill-green"><span class="pill-icon">✓</span><span>Semua piutang DO sudah terlunasi bulan ini.</span></div>
            @endif

            @if($totalSurplus > 0)
            <div class="pill pill-blue"><span class="pill-icon">i</span><span>Surplus Rp {{ number_format($totalSurplus) }} tercatat — akan diakumulasi sebagai tabungan periode.</span></div>
            @endif
        </div>
    </div>
</div>

{{-- ══════════════ TAB: RIWAYAT ══════════════ --}}
<div x-show="tabActive==='riwayat'" x-cloak>
    <div class="s-card">
        <div class="s-card-header">📜 Mutasi Rekening Penampung</div>
        <div class="scroll-x">
            <table class="mob-table">
                <thead>
                    <tr><th>Tanggal</th><th>Keterangan</th><th class="r">Masuk</th><th class="r">Keluar</th><th class="r">Saldo</th></tr>
                </thead>
                <tbody>
                    <tr style="background:var(--melon-50)">
                        <td class="bold">Saldo Awal</td>
                        <td style="color:var(--text3)">Dibawa dari bulan lalu</td>
                        <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($period->opening_penampung) }}</td>
                        <td class="r" style="color:var(--text3)">—</td>
                        <td class="r bold" style="color:#1d4ed8">Rp {{ number_format($period->opening_penampung) }}</td>
                    </tr>
                    @foreach($balanceRows as $row)
                    <tr>
                        <td>{{ $row['date']->format('d/m/Y') }}</td>
                        <td>{{ $row['desc'] }}</td>
                        <td class="r" style="color:{{ $row['type']==='in'?'var(--melon-dark)':'var(--text3)' }};font-weight:{{ $row['type']==='in'?'600':'400' }}">{{ $row['type']==='in'?'Rp '.number_format($row['amount']):'—' }}</td>
                        <td class="r" style="color:{{ $row['type']==='out'?'#dc2626':'var(--text3)' }};font-weight:{{ $row['type']==='out'?'600':'400' }}">{{ $row['type']==='out'?'Rp '.number_format($row['amount']):'—' }}</td>
                        <td class="r bold" style="color:{{ $row['balance']>=0?'#1d4ed8':'#dc2626' }}">Rp {{ number_format($row['balance']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>{{-- end x-data --}}

{{-- Data JSON untuk chart --}}
<div id="tfChartData"
     data-labels='@json($trendLabels)'
     data-dep='@json($trendDep)'
     data-tf='@json($trendTf)'
     data-saldo='@json($trendSaldo)'
     data-kurir-names='@json($kurirNames)'
     data-kurir-totals='@json($kurirTotals)'
     style="display:none;"></div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
(function(){
    const el = document.getElementById('tfChartData');
    if (!el) return;

    const labels      = JSON.parse(el.dataset.labels);
    const depData     = JSON.parse(el.dataset.dep);
    const tfData      = JSON.parse(el.dataset.tf);
    const saldoData   = JSON.parse(el.dataset.saldo);
    const kurirNames  = JSON.parse(el.dataset.kurirNames);
    const kurirTotals = JSON.parse(el.dataset.kurirTotals);

    const GRID = 'rgba(0,0,0,0.05)';
    const TICK = { font:{size:10}, color:'#9CA3AF' };
    const fmtK = v => { const a=Math.abs(v); return (v<0?'-':'')+(a>=1000000?(Math.round(a/100000)/10).toFixed(1)+'jt':Math.round(a/1000)+'k'); };

    /* ── Tren harian ── */
    const trendEl = document.getElementById('tfTrendChart');
    if (trendEl && labels.length) {
        new Chart(trendEl, {
            data: {
                labels: labels.map(d => 'Tgl '+d),
                datasets: [
                    { type:'bar',  label:'Setoran masuk',   data: depData.map(v=>v/1000),   backgroundColor:'rgba(67,160,71,0.4)', borderColor:'#43A047', borderWidth:1, borderRadius:3, stack:'a', order:2 },
                    { type:'bar',  label:'Transfer keluar',  data: tfData.map(v=>v/1000),    backgroundColor:'rgba(59,130,246,0.4)', borderColor:'#3b82f6', borderWidth:1, borderRadius:3, stack:'b', order:2 },
                    { type:'line', label:'Saldo penampung',  data: saldoData.map(v=>v/1000), borderColor:'#b45309', borderWidth:2, borderDash:[5,3], pointRadius:3, pointBackgroundColor:'#b45309', tension:0.35, fill:false, yAxisID:'y2', order:1, backgroundColor:'transparent' },
                ]
            },
            options: {
                responsive:true, maintainAspectRatio:false,
                interaction:{ mode:'index', intersect:false },
                plugins:{ legend:{display:false}, tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${fmtK(ctx.parsed.y*1000)}`}} },
                scales:{
                    x:{ grid:{color:GRID}, ticks:{...TICK,autoSkip:true,maxTicksLimit:16,maxRotation:0} },
                    y:{ grid:{color:GRID}, ticks:{...TICK,callback:v=>fmtK(v*1000)}, title:{display:true,text:'Ribuan Rp',color:'#9CA3AF',font:{size:10}} },
                    y2:{ position:'right', grid:{display:false}, ticks:{...TICK,callback:v=>fmtK(v*1000)}, title:{display:true,text:'Saldo',color:'#b45309',font:{size:10}} }
                }
            }
        });
    }

    /* ── Donut kurir ── */
    const donutEl = document.getElementById('tfDonutChart');
    if (donutEl && kurirNames.length) {
        const palette = ['#43A047','#3b82f6','#f59e0b','#7c3aed','#dc2626','#0891b2'];
        const colors  = kurirNames.map((_,i) => palette[i % palette.length]);
        const tot     = kurirTotals.reduce((a,b)=>a+b,0);

        new Chart(donutEl, {
            type:'doughnut',
            data:{ labels:kurirNames, datasets:[{ data:kurirTotals, backgroundColor:colors, borderWidth:1, borderColor:'#fff' }] },
            options:{ responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{display:false}, tooltip:{callbacks:{label:ctx=>`${ctx.label}: Rp ${Math.round(ctx.raw).toLocaleString('id-ID')}`}} } }
        });

        const leg = document.getElementById('tfDonutLegend');
        kurirNames.forEach((name,i) => {
            const pct = tot > 0 ? (kurirTotals[i]/tot*100).toFixed(1) : 0;
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;gap:6px;font-size:10px;';
            row.innerHTML = `<span style="width:9px;height:9px;border-radius:2px;background:${colors[i]};flex-shrink:0;"></span><span style="flex:1;color:var(--text2);">${name}</span><span style="font-weight:600;color:var(--text1);">${pct}%</span>`;
            leg.appendChild(row);
        });
    }
})();
</script>
@endpush