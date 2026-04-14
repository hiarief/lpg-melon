@extends('layouts.app')
@section('title','Distribusi Kontrak')

@section('content')
<style>
/* ── contract-dist page tokens ───────────────────────────── */
.page-header { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
.page-title  { font-size:15px; font-weight:700; color:var(--text1); }
.page-sub    { font-size:10px; color:var(--text3); margin-top:2px; }

/* skema info cards */
.scheme-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; padding:2px; margin-bottom:14px; }
.scheme-grid { display:grid; grid-template-columns:repeat(2, minmax(260px,1fr)); gap:10px; min-width:540px; }
.scheme-card { border-radius:var(--radius-sm); padding:12px; font-size:11px; border:0.5px solid; }
.scheme-title{ font-size:12px; font-weight:700; margin-bottom:8px; }
.scheme-row  { margin-bottom:4px; }
.scheme-box  { border-radius:8px; padding:8px 10px; margin-top:8px; font-size:10px; line-height:1.6; }

/* customer card */
.cust-card { background:var(--surface); border:0.5px solid var(--border); border-radius:var(--radius-sm); margin-bottom:14px; overflow:hidden; }
.cust-header { padding:12px 14px; border-bottom:0.5px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.cust-avatar { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:17px; font-weight:700; color:#fff; flex-shrink:0; }
.cust-name   { font-size:14px; font-weight:700; color:var(--text1); }
.cust-scheme { font-size:10px; font-weight:500; margin-top:2px; }
.cust-body   { padding:12px 14px; display:flex; flex-direction:column; gap:14px; }

/* saldo badge */
.saldo-badge { border-radius:var(--radius-sm); padding:8px 12px; text-align:center; border:0.5px solid; flex-shrink:0; }
.saldo-label { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.3px; margin-bottom:3px; }
.saldo-value { font-size:18px; font-weight:700; line-height:1.1; }
.saldo-note  { font-size:9px; margin-top:2px; }

/* section sub-header */
.sub-hdr { font-size:10px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }

/* calculation table */
.calc-table { width:100%; border-collapse:collapse; font-size:11px; }
.calc-table td { padding:9px 10px; border-bottom:0.5px solid var(--border); vertical-align:top; }
.calc-table tbody tr:last-child td { border-bottom:none; }
.calc-num  { width:28px; font-size:13px; color:var(--text3); }
.calc-val  { text-align:right; font-weight:700; white-space:nowrap; }
.calc-note { font-size:9px; font-weight:400; color:var(--text3); margin-top:2px; }

/* info mini cards */
.info-grid-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; padding:2px; }
.info-grid { display:grid; grid-template-columns:repeat(4, minmax(130px,1fr)); gap:8px; min-width:540px; }
.info-card { background:var(--surface2); border:0.5px solid var(--border); border-radius:8px; padding:10px; }
.info-label{ font-size:9px; color:var(--text3); margin-bottom:3px; }
.info-val  { font-size:13px; font-weight:700; }
.info-sub  { font-size:9px; color:var(--text3); margin-top:2px; }

/* notes grid */
.notes-grid-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; padding:2px; }
.notes-grid { display:grid; grid-template-columns:repeat(3, minmax(180px,1fr)); gap:8px; min-width:560px; }
.note-card  { border-radius:8px; padding:10px; border:0.5px solid; }
.note-label { font-size:9px; font-weight:700; margin-bottom:5px; }
.note-body  { font-size:10px; line-height:1.6; white-space:pre-line; }

/* collapse toggle button */
.toggle-btn { width:100%; display:flex; align-items:center; justify-content:space-between;
              padding:10px 12px; border-radius:8px; border:0.5px solid var(--border);
              background:var(--surface2); font-size:11px; font-weight:600; color:var(--text2);
              cursor:pointer; font-family:inherit; text-align:left; }
.toggle-btn:active { background:var(--melon-light); }
.toggle-arrow { font-size:10px; color:var(--text3); flex-shrink:0; }

/* selesaikan form */
.selesai-form { display:flex; flex-wrap:wrap; gap:8px; padding:12px; background:var(--surface); align-items:flex-end; }
.selesai-input-wrap { flex:1; min-width:160px; }
.selesai-done { padding:10px 14px; background:var(--melon-50); border-top:0.5px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:8px; }

/* edit form grid */
.edit-grid { display:grid; grid-template-columns:1fr; gap:10px; }
@media (min-width:380px) { .edit-grid { grid-template-columns:1fr 1fr; } }
.edit-area { border:0.5px solid var(--border2); border-radius:8px; padding:8px 10px; font-size:12px;
             font-family:inherit; color:var(--text1); width:100%; resize:vertical; }
.edit-area:focus { outline:2px solid var(--melon-mid); border-color:transparent; }

/* progress bar */
.prog-track { width:100%; height:5px; background:var(--melon-light); border-radius:3px; overflow:hidden; margin-top:4px; }
.prog-fill  { height:100%; border-radius:3px; }
</style>

<div x-data="{}">

{{-- ══ PAGE HEADER ══ --}}
<div class="page-header">
    <div>
        <div class="page-title">⭐ Distribusi Customer Kontrak</div>
        <div class="page-sub">Rekap terpisah Angga &amp; Sukmedi — kalkulasi otomatis per skema</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <form method="GET" action="{{ route('contract-dist.index') }}">
            <select name="period_id" onchange="this.form.submit()" class="field-select" style="width:auto;padding:6px 10px;font-size:12px;">
                @foreach($periods as $p)
                <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                @endforeach
            </select>
        </form>
        <form method="POST" action="{{ route('contract-dist.sync') }}">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period->id }}">
            <button class="btn-secondary btn-sm">🔄 Sync</button>
        </form>
    </div>
</div>

{{-- ══ PENJELASAN SKEMA ══ --}}
<div class="scheme-wrap">
<div class="scheme-grid">
    {{-- Angga --}}
    <div class="scheme-card" style="background:#fffbeb;border-color:#fcd34d;">
        <div class="scheme-title" style="color:#92400e;">★ Skema Angga — Flat Bulanan</div>
        <div class="scheme-row" style="color:#b45309;">• Harga distribusi gas: <strong>Rp 18.000/tabung</strong></div>
        <div class="scheme-row" style="color:#b45309;">• Kontrak pangkalan: <strong>Flat Rp 600.000/bulan</strong></div>
        <div class="scheme-row" style="color:#b45309;">• Tabung "ditinggalkan" = <strong>tidak ditagih</strong>, nilainya langsung offset ke kontrak</div>
        <div class="scheme-box" style="background:#fef3c7;color:#92400e;">
            Contoh: Angga tinggal 33 tab → 33×18.000 = <strong>Rp 594.000</strong><br>
            → Offset kontrak 600rb → Sisa kontrak: <strong>Rp 6.000</strong>
        </div>
    </div>
    {{-- Sukmedi --}}
    <div class="scheme-card" style="background:#eff6ff;border-color:#bfdbfe;">
        <div class="scheme-title" style="color:#1e3a8a;">★ Skema Sukmedi — Per DO (Rp 1.000/tabung)</div>
        <div class="scheme-row" style="color:#1d4ed8;">• Harga distribusi gas: <strong>Rp 18.000/tabung</strong></div>
        <div class="scheme-row" style="color:#1d4ed8;">• Hak kontrak: <strong>Rp 1.000 × total DO qty Sukmedi</strong> (dibayar kita ke dia)</div>
        <div class="scheme-row" style="color:#1d4ed8;">• Sukmedi setor seadanya → akhir bulan dihitung bersih</div>
        <div class="scheme-box" style="background:#dbeafe;color:#1e3a8a;">
            <strong>Piutang distribusi</strong> = tagihan distribusi − setoran masuk<br>
            <strong>Saldo bersih</strong> = hak kontrak − piutang distribusi<br>
            <div style="margin-top:6px;padding-top:6px;border-top:0.5px solid #93c5fd;">
                Contoh: 50 tab, tagihan Rp 900rb, bayar Rp 828rb → piutang <strong>Rp 72rb</strong><br>
                DO 170 tab → hak kontrak <strong>Rp 170rb</strong><br>
                Saldo = 170.000 − 72.000 = <strong>+Rp 98.000 (kita bayar ke Sukmedi)</strong>
            </div>
        </div>
    </div>
</div>
</div>

{{-- ══ KARTU PER CUSTOMER ══ --}}
@foreach($contractCustomers as $customer)
@php
    $cd          = $contracts[$customer->id];
    $outlet      = $customer->outlet;
    $dists       = $cd->dailyDistributions;
    $isFlat      = $cd->isFlat();
    $isPerDo     = $cd->isPerDo();
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $period->month, $period->year);
    $accentBg    = $isFlat ? '#fffbeb' : '#eff6ff';
    $accentBdr   = $isFlat ? '#fcd34d' : '#bfdbfe';
    $accentCol   = $isFlat ? '#92400e' : '#1e3a8a';
    $avatarBg    = $isFlat ? '#d97706' : '#2563eb';
@endphp

<div class="cust-card" x-data="{ showTimeline: false, showForm: false }">

    {{-- Header --}}
    <div class="cust-header" style="background:{{ $accentBg }};">
        <div style="display:flex;align-items:center;gap:10px;">
            <div class="cust-avatar" style="background:{{ $avatarBg }};">{{ strtoupper(substr($customer->name,0,1)) }}</div>
            <div>
                <div class="cust-name">{{ $customer->name }}</div>
                <div class="cust-scheme" style="color:{{ $accentCol }};">
                    @if($isFlat) Flat Rp {{ number_format($outlet->contract_rate) }}/bulan
                    @elseif($isPerDo) Per DO Rp {{ number_format($outlet->contract_rate) }}/tab
                    @endif
                    @if($cd->is_cutoff)
                        &nbsp;·&nbsp;<span style="color:var(--melon-dark);font-weight:700;">✓ Cutoff {{ $cd->cutoff_date?->format('d/m/Y') }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Saldo badge --}}
        @if($cd->saldo_bersih > 0)
        <div class="saldo-badge" style="background:#fef2f2;border-color:#fca5a5;">
            <div class="saldo-label" style="color:#dc2626;">⚠ Kita Bayar ke {{ $customer->name }}</div>
            <div class="saldo-value" style="color:#dc2626;">Rp {{ number_format($cd->saldo_bersih) }}</div>
            <div class="saldo-note" style="color:#fca5a5;">{{ $cd->sudah_diselesaikan ? '✓ Sudah diselesaikan' : 'Belum dibayarkan' }}</div>
        </div>
        @elseif($cd->saldo_bersih < 0)
        <div class="saldo-badge" style="background:#fff7ed;border-color:#fed7aa;">
            <div class="saldo-label" style="color:#ea580c;">{{ $customer->name }} Masih Kurang</div>
            <div class="saldo-value" style="color:#ea580c;">Rp {{ number_format(abs($cd->saldo_bersih)) }}</div>
            <div class="saldo-note" style="color:#fdba74;">piutang ke kita</div>
        </div>
        @elseif($cd->total_qty > 0)
        <div class="saldo-badge" style="background:var(--melon-50);border-color:var(--melon-mid);">
            <div class="saldo-label" style="color:var(--melon-dark);">✓ Saldo Seimbang</div>
            <div class="saldo-value" style="color:var(--melon-dark);">Rp 0</div>
        </div>
        @endif
    </div>

    <div class="cust-body">

    {{-- ════════════════════════
         SKEMA ANGGA (FLAT)
    ════════════════════════ --}}
    @if($isFlat)
    <div>
        <div class="sub-hdr">Kalkulasi Skema Angga (Flat)</div>
        <div style="overflow:hidden;border:0.5px solid var(--border);border-radius:var(--radius-sm);">
        <table class="calc-table">
            <tbody>
                <tr>
                    <td class="calc-num">①</td>
                    <td style="color:#1d4ed8;font-weight:600;">
                        Total distribusi gas bulan ini
                        <div class="calc-note">{{ number_format($cd->total_qty) }} tab × Rp {{ number_format($cd->price_per_unit) }}</div>
                    </td>
                    <td class="calc-val" style="color:#1d4ed8;">Rp {{ number_format($cd->tagihan_distribusi) }}</td>
                </tr>
                <tr style="background:#fefce8;">
                    <td class="calc-num" style="color:#ca8a04;">②</td>
                    <td style="color:#92400e;font-weight:600;">
                        Tabung "ditinggalkan" (tidak ditagih)
                        <div class="calc-note">nilainya langsung offset kontrak flat · {{ number_format($cd->qty_ditinggalkan) }} tab × Rp {{ number_format($cd->price_per_unit) }}</div>
                    </td>
                    <td class="calc-val" style="color:#b45309;">(Rp {{ number_format($cd->nilai_ditinggalkan) }})</td>
                </tr>
                <tr>
                    <td class="calc-num">③</td>
                    <td style="color:var(--text2);font-weight:600;">
                        Tagihan distribusi bersih (yang ditagih)
                        <div class="calc-note">① − ②</div>
                    </td>
                    <td class="calc-val" style="color:var(--text1);">Rp {{ number_format($cd->tagihanDistribusiBersihAngga()) }}</td>
                </tr>
                <tr>
                    <td class="calc-num">④</td>
                    <td style="color:var(--melon-dark);font-weight:600;">
                        Setoran distribusi diterima
                        <div class="calc-note">dari paid_amount distributions</div>
                    </td>
                    <td class="calc-val" style="color:var(--melon-dark);">Rp {{ number_format($cd->total_setoran) }}</td>
                </tr>
                @php $piutangAngga = $cd->piutangDistribusiAngga(); @endphp
                <tr style="background:{{ $piutangAngga > 0 ? '#fef2f2' : 'var(--melon-50)' }};">
                    <td class="calc-num">=</td>
                    <td style="font-weight:600;color:{{ $piutangAngga > 0 ? '#dc2626' : 'var(--melon-dark)' }};">
                        Piutang distribusi (③ − ④)
                    </td>
                    <td class="calc-val" style="font-size:14px;color:{{ $piutangAngga > 0 ? '#dc2626' : 'var(--melon-dark)' }};">
                        {{ $piutangAngga > 0 ? 'Rp '.number_format($piutangAngga) : '✓ Lunas' }}
                    </td>
                </tr>
                <tr style="background:#fefce8;border-top:2px solid #fcd34d;">
                    <td class="calc-num" style="color:#ca8a04;font-weight:700;">⑤</td>
                    <td style="color:#92400e;font-weight:700;">
                        Kontrak flat bulanan
                        <div class="calc-note">tagihan Rp {{ number_format($cd->tagihan_kontrak) }}</div>
                    </td>
                    <td class="calc-val" style="color:#b45309;">Rp {{ number_format($cd->tagihan_kontrak) }}</td>
                </tr>
                <tr style="background:#fffbeb;">
                    <td class="calc-num" style="color:#fbbf24;">↳</td>
                    <td style="color:#b45309;">
                        Offset dari tabung ditinggalkan
                        <div class="calc-note">min(nilai_ditinggalkan, tagihan_kontrak) · Rp {{ number_format($cd->nilai_ditinggalkan) }} → {{ number_format($cd->offsetDariDistribusiAngga()) }}</div>
                    </td>
                    <td class="calc-val" style="color:#d97706;">(Rp {{ number_format($cd->offsetDariDistribusiAngga()) }})</td>
                </tr>
                @if($cd->bayar_kontrak_tunai > 0)
                <tr style="background:#fffbeb;">
                    <td class="calc-num" style="color:#fbbf24;">↳</td>
                    <td style="color:#b45309;">Bayar kontrak tunai</td>
                    <td class="calc-val" style="color:#d97706;">(Rp {{ number_format($cd->bayar_kontrak_tunai) }})</td>
                </tr>
                @endif
                @php $sisaKontrak = $cd->sisaKontrakAngga(); @endphp
                <tr style="background:{{ $sisaKontrak > 0 ? '#fff7ed' : 'var(--melon-50)' }};border-top:0.5px solid #fcd34d;">
                    <td class="calc-num">=</td>
                    <td style="font-weight:600;color:{{ $sisaKontrak > 0 ? '#ea580c' : 'var(--melon-dark)' }};">Sisa kewajiban kontrak</td>
                    <td class="calc-val" style="color:{{ $sisaKontrak > 0 ? '#ea580c' : 'var(--melon-dark)' }};">
                        {{ $sisaKontrak > 0 ? 'Rp '.number_format($sisaKontrak) : '✓ Lunas' }}
                    </td>
                </tr>
                {{-- Saldo bersih --}}
                <tr style="background:{{ $cd->saldo_bersih > 0 ? '#fef2f2' : ($cd->saldo_bersih < 0 ? '#fff7ed' : 'var(--melon-50)') }};border-top:2px solid var(--border2);">
                    <td class="calc-num" style="font-size:16px;">⚖</td>
                    <td style="font-weight:700;font-size:12px;color:{{ $cd->saldo_bersih > 0 ? '#dc2626' : ($cd->saldo_bersih < 0 ? '#ea580c' : 'var(--melon-dark)') }};">
                        Saldo Bersih Akhir
                        <div class="calc-note">setoran − (distribusi bersih + sisa kontrak)</div>
                    </td>
                    <td class="calc-val" style="font-size:16px;color:{{ $cd->saldo_bersih > 0 ? '#dc2626' : ($cd->saldo_bersih < 0 ? '#ea580c' : 'var(--melon-dark)') }};">
                        @if($cd->saldo_bersih > 0)
                            +Rp {{ number_format($cd->saldo_bersih) }}
                            <div style="font-size:9px;font-weight:400;color:#fca5a5;">kita bayar ke Angga</div>
                        @elseif($cd->saldo_bersih < 0)
                            Rp {{ number_format(abs($cd->saldo_bersih)) }}
                            <div style="font-size:9px;font-weight:400;color:#fdba74;">Angga masih kurang</div>
                        @else
                            Rp 0 ✓
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>

    {{-- ════════════════════════
         SKEMA SUKMEDI (PER DO)
    ════════════════════════ --}}
    @elseif($isPerDo)
    <div>
        <div class="sub-hdr">Kalkulasi Skema Sukmedi (Per DO)</div>
        <div style="overflow:hidden;border:0.5px solid var(--border);border-radius:var(--radius-sm);">
        <table class="calc-table">
            <tbody>
                <tr>
                    <td class="calc-num">①</td>
                    <td style="color:#1d4ed8;font-weight:600;">
                        Tagihan distribusi gas
                        <div class="calc-note">berapa yang seharusnya Sukmedi bayar · {{ number_format($cd->total_qty) }} tab × Rp {{ number_format($cd->price_per_unit) }}</div>
                    </td>
                    <td class="calc-val" style="color:#1d4ed8;">Rp {{ number_format($cd->tagihan_distribusi) }}</td>
                </tr>
                <tr>
                    <td class="calc-num">②</td>
                    <td style="color:var(--melon-dark);font-weight:600;">
                        Total setoran diterima dari Sukmedi
                        <div class="calc-note">dari paid_amount distribusi</div>
                    </td>
                    <td class="calc-val" style="color:var(--melon-dark);">Rp {{ number_format($cd->total_setoran) }}</td>
                </tr>
                @php $piutangDist = $cd->piutangDistribusiSukmedi(); @endphp
                <tr style="background:{{ $piutangDist > 0 ? '#fff7ed' : 'var(--melon-50)' }};">
                    <td class="calc-num">=</td>
                    <td style="font-weight:600;color:{{ $piutangDist > 0 ? '#ea580c' : 'var(--melon-dark)' }};">
                        Piutang distribusi Sukmedi (① − ②)
                        <div class="calc-note">yang belum dilunasi dari distribusi gas</div>
                    </td>
                    <td class="calc-val" style="color:{{ $piutangDist > 0 ? '#ea580c' : 'var(--melon-dark)' }};">
                        {{ $piutangDist > 0 ? 'Rp '.number_format($piutangDist) : '✓ Lunas' }}
                    </td>
                </tr>
                @php
                    $doQty = \App\Models\DeliveryOrder::where('period_id',$period->id)
                        ->where('outlet_id',$outlet->id)
                        ->where(fn($q) => $q->whereNull('notes')->orWhere('notes','not like','%Carry-over%'))
                        ->sum('qty');
                @endphp
                <tr style="background:#eff6ff;border-top:2px solid #bfdbfe;">
                    <td class="calc-num" style="color:#2563eb;font-weight:700;">③</td>
                    <td style="color:#1e3a8a;font-weight:700;">
                        Hak kontrak Sukmedi (per DO)
                        <div class="calc-note">= DO qty Sukmedi × Rp {{ number_format($outlet->contract_rate) }} — yang berhak diterima Sukmedi dari kita · {{ number_format($doQty) }} tab × Rp {{ number_format($outlet->contract_rate) }}</div>
                    </td>
                    <td class="calc-val" style="color:#1d4ed8;">Rp {{ number_format($cd->tagihan_kontrak) }}</td>
                </tr>
                @php $saldo = $cd->saldoSetoran(); @endphp
                <tr style="background:{{ $saldo > 0 ? '#fef2f2' : ($saldo < 0 ? '#fff7ed' : 'var(--melon-50)') }};border-top:2px solid var(--border2);">
                    <td class="calc-num" style="font-size:16px;">⚖</td>
                    <td style="font-weight:700;font-size:12px;color:{{ $saldo > 0 ? '#dc2626' : ($saldo < 0 ? '#ea580c' : 'var(--melon-dark)') }};">
                        Saldo Bersih (③ − Piutang)
                        <div class="calc-note">hak kontrak Sukmedi − piutang distribusinya · Rp {{ number_format($cd->tagihan_kontrak) }} − Rp {{ number_format($piutangDist) }}</div>
                    </td>
                    <td class="calc-val" style="font-size:16px;color:{{ $saldo > 0 ? '#dc2626' : ($saldo < 0 ? '#ea580c' : 'var(--melon-dark)') }};">
                        @if($saldo > 0)
                            +Rp {{ number_format($saldo) }}
                            <div style="font-size:9px;font-weight:400;color:#fca5a5;">kita bayar ke Sukmedi</div>
                        @elseif($saldo < 0)
                            Rp {{ number_format(abs($saldo)) }}
                            <div style="font-size:9px;font-weight:400;color:#fdba74;">Sukmedi masih kurang</div>
                        @else
                            Rp 0 ✓
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
        </div>

        {{-- Info mini cards --}}
        <div class="info-grid-wrap" style="margin-top:10px;">
        <div class="info-grid">
            <div class="info-card">
                <div class="info-label">Tagihan distribusi</div>
                <div class="info-val" style="color:#1d4ed8;">Rp {{ number_format($cd->tagihan_distribusi) }}</div>
                <div class="info-sub">{{ number_format($cd->total_qty) }} tab × Rp {{ number_format($cd->price_per_unit) }}</div>
            </div>
            <div class="info-card">
                <div class="info-label">Total setoran</div>
                <div class="info-val" style="color:var(--melon-dark);">Rp {{ number_format($cd->total_setoran) }}</div>
                <div class="info-sub">diterima dari Sukmedi</div>
            </div>
            <div class="info-card" style="{{ $cd->piutangDistribusiSukmedi() > 0 ? 'background:#fff7ed;border-color:#fed7aa;' : 'background:var(--melon-50);' }}">
                <div class="info-label">Piutang distribusi</div>
                <div class="info-val" style="color:{{ $cd->piutangDistribusiSukmedi() > 0 ? '#ea580c' : 'var(--melon-dark)' }};">
                    {{ $cd->piutangDistribusiSukmedi() > 0 ? 'Rp '.number_format($cd->piutangDistribusiSukmedi()) : '✓ Lunas' }}
                </div>
                <div class="info-sub">tagihan − setoran</div>
            </div>
            <div class="info-card" style="background:#eff6ff;border-color:#bfdbfe;">
                <div class="info-label">Hak kontrak DO</div>
                <div class="info-val" style="color:#1d4ed8;">Rp {{ number_format($cd->hakKontrakSukmedi()) }}</div>
                <div class="info-sub">{{ number_format($doQty) }} tab DO × Rp 1.000</div>
            </div>
        </div>
        </div>
    </div>
    @endif

    {{-- ══ PENYELESAIAN SALDO ══ --}}
    @if($cd->saldo_bersih != 0 && $period->status === 'open')
    <div style="overflow:hidden;border:0.5px solid var(--border);border-radius:var(--radius-sm);">
        <div class="s-card-header" style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <span>{{ $cd->hutang_ke_customer ? '💸 Catat Pembayaran ke '.strtoupper($customer->name) : '📥 Catat Pelunasan dari '.strtoupper($customer->name) }}</span>
            @if($cd->sudah_diselesaikan)
            <span class="badge badge-green">✓ Selesai</span>
            @else
            <span class="badge badge-red">Sisa Rp {{ number_format($cd->sisaBelumDiselesaikan()) }}</span>
            @endif
        </div>
        @if(!$cd->sudah_diselesaikan)
        <form method="POST" action="{{ route('contract-dist.selesaikan', $cd) }}" class="selesai-form">
            @csrf
            <div class="selesai-input-wrap">
                <label class="field-label">Nominal (Rp) — sisa Rp {{ number_format($cd->sisaBelumDiselesaikan()) }}</label>
                <input type="number" name="nominal" min="1" max="{{ $cd->sisaBelumDiselesaikan() }}"
                       value="{{ $cd->sisaBelumDiselesaikan() }}" class="field-input">
            </div>
            <div class="selesai-input-wrap">
                <label class="field-label">Catatan</label>
                <input type="text" name="catatan" class="field-input"
                       placeholder="{{ $cd->hutang_ke_customer ? 'misal: bayar tunai tgl 30' : 'misal: terima lunasan tgl 30' }}">
            </div>
            <button type="submit"
                    class="{{ $cd->hutang_ke_customer ? 'btn-danger' : 'btn-primary' }} btn-sm" style="margin-bottom:1px;">
                {{ $cd->hutang_ke_customer ? 'Catat Bayar ke '.$customer->name : 'Catat Terima dari '.$customer->name }}
            </button>
        </form>
        @else
        <div class="selesai-done">
            <span style="font-size:11px;color:var(--melon-dark);">✓ Sudah diselesaikan — Rp {{ number_format($cd->nominal_diselesaikan) }}</span>
            <form method="POST" action="{{ route('contract-dist.reset', $cd) }}">
                @csrf
                <button type="submit" style="background:none;border:none;font-size:10px;color:var(--text3);text-decoration:underline;cursor:pointer;font-family:inherit;">Reset</button>
            </form>
        </div>
        @endif
    </div>
    @endif

    {{-- ══ TIMELINE DISTRIBUSI HARIAN ══ --}}
    <div>
        <button class="toggle-btn" @click="showTimeline = !showTimeline">
            <span>📅 Timeline Distribusi Harian ({{ $dists->count() }} entri · {{ number_format($cd->total_qty) }} tabung · setoran Rp {{ number_format($cd->total_setoran) }})</span>
            <span class="toggle-arrow" x-text="showTimeline ? '▲' : '▼'"></span>
        </button>
        <div x-show="showTimeline" x-transition style="margin-top:8px;">
            @if($dists->count() > 0)
            <div style="overflow:hidden;border:0.5px solid var(--border);border-radius:var(--radius-sm);">
            <div class="scroll-x">
            <table class="mob-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="r">Qty</th>
                        <th class="r">Harga/Tab</th>
                        <th class="r">Tagihan</th>
                        <th class="r">Setoran</th>
                        <th class="r">Sisa</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dists as $dist)
                    @php $nilai = $dist->qty * $dist->price_per_unit; $sisa = $nilai - $dist->paid_amount; @endphp
                    <tr style="{{ $dist->payment_status === 'deferred' ? 'background:#fefce8;' : '' }}">
                        <td>{{ $dist->dist_date->format('d/m/Y') }}</td>
                        <td class="r bold" style="color:var(--melon-dark);">{{ number_format($dist->qty) }}</td>
                        <td class="r" style="color:var(--text3);">{{ number_format($dist->price_per_unit) }}</td>
                        <td class="r" style="color:#1d4ed8;">Rp {{ number_format($nilai) }}</td>
                        <td class="r" style="color:var(--melon-dark);">Rp {{ number_format($dist->paid_amount) }}</td>
                        <td class="r" style="color:{{ $sisa > 0 ? '#dc2626' : 'var(--melon-dark)' }};font-weight:{{ $sisa > 0 ? 600 : 400 }};">
                            {{ $sisa > 0 ? 'Rp '.number_format($sisa) : '✓' }}
                        </td>
                        <td>
                            @if($dist->payment_status === 'paid')
                            <span class="badge badge-green">Lunas</span>
                            @elseif($dist->payment_status === 'deferred')
                            <span class="badge badge-orange">Tunda</span>
                            @else
                            <span class="badge badge-blue">Sebagian</span>
                            @endif
                        </td>
                        <td style="color:var(--text3);">{{ $dist->contract_note ?? $dist->notes ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @php $sisaTotal = $cd->tagihan_distribusi - $cd->total_setoran; @endphp
                    <tr style="background:var(--melon-50);font-weight:700;">
                        <td style="color:var(--melon-dark);">TOTAL</td>
                        <td class="r" style="color:var(--melon-dark);">{{ number_format($cd->total_qty) }}</td>
                        <td class="r" style="color:var(--text3);font-weight:400;font-size:9px;">avg Rp {{ number_format($cd->price_per_unit) }}</td>
                        <td class="r" style="color:#1d4ed8;">Rp {{ number_format($cd->tagihan_distribusi) }}</td>
                        <td class="r" style="color:var(--melon-dark);">Rp {{ number_format($cd->total_setoran) }}</td>
                        <td class="r" style="color:{{ $sisaTotal > 0 ? '#dc2626' : 'var(--melon-dark)' }};">
                            {{ $sisaTotal > 0 ? 'Rp '.number_format($sisaTotal) : '✓' }}
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
            </div>
            </div>
            @else
            <div style="text-align:center;padding:20px;color:var(--text3);font-size:12px;background:var(--surface2);border-radius:8px;">
                Belum ada distribusi untuk {{ $customer->name }} bulan ini.
            </div>
            @endif
        </div>
    </div>

    {{-- ══ FORM EDIT MANUAL ══ --}}
    <div>
        <button class="toggle-btn" @click="showForm = !showForm">
            <span>✏️ Edit Data Manual &amp; Catatan</span>
            <span class="toggle-arrow" x-text="showForm ? '▲' : '▼'"></span>
        </button>
        <div x-show="showForm" x-transition style="margin-top:8px;">
            <form method="POST" action="{{ route('contract-dist.update', $cd) }}"
                  style="background:var(--surface2);border:0.5px solid var(--border);border-radius:var(--radius-sm);padding:12px;">
                @csrf @method('PUT')
                <div class="edit-grid">

                    @if($isFlat)
                    <div>
                        <label class="field-label">
                            Qty Tabung "Ditinggalkan"
                            <span style="color:#d97706;font-weight:400;">→ offset ke kontrak flat</span>
                        </label>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="number" name="qty_ditinggalkan" value="{{ $cd->qty_ditinggalkan }}"
                                   min="0" max="{{ $cd->total_qty }}" class="field-input" style="flex:1;">
                            <span style="font-size:11px;color:var(--text3);">tab</span>
                        </div>
                        @if($cd->qty_ditinggalkan > 0)
                        <div style="font-size:10px;color:#d97706;margin-top:4px;">
                            = Rp {{ number_format($cd->qty_ditinggalkan * $cd->price_per_unit) }}
                            → offset ke kontrak Rp {{ number_format($cd->offsetDariDistribusiAngga()) }}
                        </div>
                        @endif
                    </div>
                    <div>
                        <label class="field-label">
                            Bayar Kontrak Tunai (Rp)
                            <span style="color:var(--text3);font-weight:400;">jika ada sisa kontrak yang dibayar cash</span>
                        </label>
                        <input type="number" name="bayar_kontrak_tunai" value="{{ $cd->bayar_kontrak_tunai }}"
                               min="0" max="{{ $cd->tagihan_kontrak }}" class="field-input">
                    </div>
                    @endif

                    <div>
                        <label class="field-label">📦 Catatan Distribusi</label>
                        <textarea name="catatan_distribusi" rows="3" class="edit-area"
                                  placeholder="Distribusi tgl 1-28, ada retur tgl 15...">{{ $cd->catatan_distribusi }}</textarea>
                    </div>
                    <div>
                        <label class="field-label">📋 Catatan Kontrak</label>
                        <textarea name="catatan_kontrak" rows="3" class="edit-area"
                                  placeholder="{{ $isFlat ? 'Kontrak flat 600rb, offset 33 tab...' : 'Sukmedi setor tgl 5, 10, 25...' }}">{{ $cd->catatan_kontrak }}</textarea>
                    </div>
                    <div style="{{ $isFlat ? '' : 'grid-column:1/-1;' }}">
                        <label class="field-label">📝 Catatan Khusus</label>
                        <textarea name="catatan_khusus" rows="2" class="edit-area"
                                  placeholder="Perjanjian, masalah, dll...">{{ $cd->catatan_khusus }}</textarea>
                    </div>

                    <div style="grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                        @if(!$cd->is_cutoff && $period->status === 'open')
                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                            <label class="field-label" style="margin:0;">Cutoff tanggal:</label>
                            <input type="date" name="cutoff_date" value="{{ date('Y-m-d') }}"
                                   class="field-input" style="width:auto;padding:5px 8px;font-size:12px;">
                            <span style="font-size:10px;color:var(--text3);">(kosongkan jika belum cutoff)</span>
                        </div>
                        @else
                        <div></div>
                        @endif
                        <button type="submit" class="btn-primary btn-sm">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ══ CATATAN RINGKASAN ══ --}}
    @if($cd->catatan_distribusi || $cd->catatan_kontrak || $cd->catatan_khusus)
    <div class="notes-grid-wrap">
    <div class="notes-grid">
        @if($cd->catatan_distribusi)
        <div class="note-card" style="background:#eff6ff;border-color:#bfdbfe;">
            <div class="note-label" style="color:#1d4ed8;">📦 Catatan Distribusi</div>
            <div class="note-body" style="color:#1e3a8a;">{{ $cd->catatan_distribusi }}</div>
        </div>
        @endif
        @if($cd->catatan_kontrak)
        <div class="note-card" style="background:{{ $isFlat ? '#fffbeb' : '#eff6ff' }};border-color:{{ $isFlat ? '#fcd34d' : '#bfdbfe' }};">
            <div class="note-label" style="color:{{ $isFlat ? '#b45309' : '#1d4ed8' }};">📋 Catatan Kontrak</div>
            <div class="note-body" style="color:{{ $isFlat ? '#92400e' : '#1e3a8a' }};">{{ $cd->catatan_kontrak }}</div>
        </div>
        @endif
        @if($cd->catatan_khusus)
        <div class="note-card" style="background:#faf5ff;border-color:#e9d5ff;">
            <div class="note-label" style="color:#7c3aed;">📝 Catatan Khusus</div>
            <div class="note-body" style="color:#581c87;">{{ $cd->catatan_khusus }}</div>
        </div>
        @endif
    </div>
    </div>
    @endif

    </div>{{-- end cust-body --}}
</div>{{-- end cust-card --}}
@endforeach

</div>{{-- end x-data --}}
@endsection