@extends('layouts.app')
@section('title','Detail Periode — ' . $period->label)
@section('content')

{{-- Header --}}
<div style="margin-top:4px;margin-bottom:14px">
    <a href="{{ route('periods.index') }}" style="font-size:12px;color:var(--text3)">← Daftar Periode</a>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:6px;flex-wrap:wrap">
        <div>
            <div style="font-size:17px;font-weight:600;color:var(--text1)">📅 {{ $period->label }}</div>
            @if($period->status === 'open')
            <span class="badge badge-green" style="margin-top:4px">🟢 Periode Buka</span>
            @else
            <span class="badge" style="background:#f0f0f0;color:#666;margin-top:4px">🔒 Tutup</span>
            @endif
        </div>
        @if($period->status === 'open')
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="{{ route('periods.edit', $period) }}" class="btn-secondary btn-sm">✏️ Edit</a>
            <a href="{{ route('summary.index', ['period_id' => $period->id]) }}" class="btn-primary btn-sm">📊 Ringkasan</a>
            <form method="POST" action="{{ route('periods.close', $period) }}" onsubmit="return confirm('Tutup periode {{ $period->label }}? Setelah ditutup tidak bisa dibuka kembali.')">
                @csrf
                <button type="submit" class="btn-danger btn-sm">🔒 Tutup</button>
            </form>
        </div>
        @endif
    </div>
    @php $latest = \App\Models\Period::orderByDesc('year')->orderByDesc('month')->first(); @endphp
    @if($latest && $latest->id === $period->id)
    <form method="POST" action="{{ route('periods.destroy', $period) }}" style="margin-top:8px" onsubmit="return confirm('⚠️ HAPUS periode {{ $period->label }}?\n\nSEMUA data (DO, distribusi, cashflow, transfer, dll) akan terhapus permanen.\n\nYakin lanjutkan?')">
        @csrf @method('DELETE')
        <button type="submit" class="btn-danger btn-sm" style="background:none;color:#dc2626;border-color:#fca5a5">🗑 Hapus Periode</button>
    </form>
    @endif
</div>

{{-- Parameter Awal --}}
<div class="s-card">
    <div class="s-card-header">📋 Parameter Awal Periode</div>
    <div style="padding:12px 14px;display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div style="background:var(--melon-50);border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;color:var(--text3)">Stok Awal</div>
            <div style="font-size:20px;font-weight:600;color:var(--melon-dark)">{{ number_format($period->opening_stock) }}</div>
            <div style="font-size:10px;color:var(--text3)">tabung</div>
        </div>
        <div style="background:#f0fdf4;border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;color:var(--text3)">Saldo Kas Awal</div>
            <div style="font-size:14px;font-weight:600;color:#166534">Rp {{ number_format($period->opening_cash) }}</div>
        </div>
        <div style="background:#eff6ff;border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;color:var(--text3)">Saldo Penampung Awal</div>
            <div style="font-size:14px;font-weight:600;color:#1e40af">Rp {{ number_format($period->opening_penampung) }}</div>
        </div>
        <div style="background:#faf5ff;border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;color:var(--text3)">Piutang External Awal</div>
            <div style="font-size:14px;font-weight:600;color:#6b21a8">Rp {{ number_format($period->opening_external_debt) }}</div>
        </div>
        <div style="background:#fef2f2;border-radius:8px;padding:10px 12px;grid-column:1/-1">
            <div style="font-size:10px;color:var(--text3)">DO Belum Lunas (bawaan)</div>
            <div style="font-size:20px;font-weight:600;color:#991b1b">{{ number_format($period->opening_do_unpaid_qty) }} <span style="font-size:12px;font-weight:400">tabung</span></div>
        </div>
    </div>
</div>

{{-- Metrik Utama --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
    <div class="card" style="padding:12px 14px;border-top:3px solid #f97316">
        <div style="font-size:10px;color:var(--text3)">Total DO Masuk</div>
        <div style="font-size:22px;font-weight:600;color:#c2410c">{{ number_format($stockIn) }}</div>
        <div style="font-size:10px;color:var(--text3)">tabung</div>
        <a href="{{ route('do.index', ['period_id' => $period->id]) }}" style="font-size:10px;color:#c2410c;display:block;margin-top:4px">Lihat DO →</a>
    </div>
    <div class="card" style="padding:12px 14px;border-top:3px solid #3b82f6">
        <div style="font-size:10px;color:var(--text3)">Total Distribusi</div>
        <div style="font-size:22px;font-weight:600;color:#1d4ed8">{{ number_format($stockOut) }}</div>
        <div style="font-size:10px;color:var(--text3)">tabung terjual</div>
        <a href="{{ route('distributions.index', ['period_id' => $period->id]) }}" style="font-size:10px;color:#1d4ed8;display:block;margin-top:4px">Lihat →</a>
    </div>
    <div class="card" style="padding:12px 14px;border-top:3px solid var(--melon)">
        <div style="font-size:10px;color:var(--text3)">Stok Sisa</div>
        @php $stockSisaCalc = $period->opening_stock + $stockIn - $stockOut; @endphp
        <div style="font-size:22px;font-weight:600;color:{{ $stockSisaCalc >= 0 ? 'var(--melon-dark)' : '#dc2626' }}">{{ number_format($stockSisa) }}</div>
        <div style="font-size:10px;color:var(--text3)">= {{ $period->opening_stock }} + {{ $stockIn }} − {{ $stockOut }}</div>
    </div>
    <div class="card" style="padding:12px 14px;border-top:3px solid #8b5cf6">
        <div style="font-size:10px;color:var(--text3)">Total Penjualan</div>
        <div style="font-size:16px;font-weight:600;color:#6d28d9">Rp {{ number_format($totalSales) }}</div>
        <div style="font-size:10px;color:var(--text3)">Modal: Rp {{ number_format($totalModal) }}</div>
    </div>
</div>

{{-- Cashflow --}}
<div class="s-card">
    <div class="s-card-header" style="display:flex;justify-content:space-between">
        <span>💸 Cashflow Ringkas</span>
        <a href="{{ route('cashflow.index', ['period_id' => $period->id]) }}" class="section-link">Detail →</a>
    </div>
    <div style="padding:12px 14px">
        <table class="mob-table">
            <tr>
                <td>Total Penjualan Bruto</td>
                <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($totalSales) }}</td>
            </tr>
            <tr>
                <td>Total Pengeluaran</td>
                <td class="r bold" style="color:#dc2626">− Rp {{ number_format($totalExpense) }}</td>
            </tr>
            @php $net = $totalSales - $totalExpense; @endphp
            <tr style="background:var(--melon-50)">
                <td class="bold">Net Cashflow</td>
                <td class="r bold" style="color:{{ $net >= 0 ? '#1d4ed8' : '#dc2626' }}">Rp {{ number_format($net) }}</td>
            </tr>
            <tr>
                <td>Gross Margin</td>
                <td class="r bold" style="color:#6d28d9">Rp {{ number_format($grossMargin) }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- Rekening Penampung --}}
<div class="s-card">
    <div class="s-card-header" style="display:flex;justify-content:space-between">
        <span>🏦 Rekening Penampung</span>
        <a href="{{ route('transfer.index', ['period_id' => $period->id]) }}" class="section-link">Detail →</a>
    </div>
    <div style="padding:12px 14px">
        <table class="mob-table">
            <tr>
                <td>Saldo Awal</td>
                <td class="r bold">Rp {{ number_format($period->opening_penampung) }}</td>
            </tr>
            <tr>
                <td>Total Setoran Kurir</td>
                <td class="r bold" style="color:var(--melon-dark)">+ Rp {{ number_format($totalDeposited) }}</td>
            </tr>
            <tr>
                <td>Total Transfer ke Rek Utama</td>
                <td class="r bold" style="color:#dc2626">− Rp {{ number_format($totalTransferred) }}</td>
            </tr>
            <tr style="background:var(--melon-50)">
                <td class="bold">Saldo Penampung Sekarang</td>
                <td class="r bold" style="color:{{ $penampungBalance >= 0 ? '#c2410c' : '#dc2626' }}">Rp {{ number_format($penampungBalance) }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- Status DO --}}
<div class="s-card">
    <div class="s-card-header" style="display:flex;justify-content:space-between">
        <span>📦 Status Pembayaran DO</span>
        <a href="{{ route('do.index', ['period_id' => $period->id]) }}" class="section-link">Kelola →</a>
    </div>
    <div style="padding:12px 14px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:10px">
            <div style="background:#f0fdf4;border-radius:8px;padding:10px;text-align:center">
                <div style="font-size:22px;font-weight:600;color:#166534">{{ $doPaid }}</div>
                <div style="font-size:10px;color:var(--text3)">DO Lunas</div>
            </div>
            <div style="background:#fffbeb;border-radius:8px;padding:10px;text-align:center">
                <div style="font-size:22px;font-weight:600;color:#b45309">{{ $doPartial }}</div>
                <div style="font-size:10px;color:var(--text3)">DO Sebagian</div>
            </div>
            <div style="background:#fef2f2;border-radius:8px;padding:10px;text-align:center">
                <div style="font-size:22px;font-weight:600;color:#991b1b">{{ $doUnpaid }}</div>
                <div style="font-size:10px;color:var(--text3)">Blm Lunas (tab)</div>
            </div>
        </div>
        @if($doUnpaid > 0)
        <div style="background:#fef2f2;border:0.5px solid #fca5a5;border-radius:8px;padding:10px 12px;font-size:11px;color:#991b1b">
            ⚠️ Masih ada <strong>{{ number_format($doUnpaid) }} tabung</strong> DO yang belum dilunasi.
            Lunasi via <a href="{{ route('transfer.index', ['period_id' => $period->id]) }}" style="color:#991b1b;text-decoration:underline">Transfer</a>.
        </div>
        @else
        <div style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px;font-size:11px;color:#166534">
            ✅ Semua DO periode ini sudah lunas.
        </div>
        @endif
    </div>
</div>

{{-- Aksi Cepat --}}
<div class="s-card">
    <div class="s-card-header">⚡ Aksi Cepat</div>
    <div style="padding:12px 14px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
        <a href="{{ route('do.create', ['period_id' => $period->id]) }}" class="drawer-item">
            <span class="drawer-item-icon">📦</span>
            <span class="drawer-item-label">Input DO</span>
        </a>
        <a href="{{ route('distributions.index', ['period_id' => $period->id]) }}" class="drawer-item">
            <span class="drawer-item-icon">🚚</span>
            <span class="drawer-item-label">Distribusi</span>
        </a>
        <a href="{{ route('cashflow.index', ['period_id' => $period->id]) }}" class="drawer-item">
            <span class="drawer-item-icon">💸</span>
            <span class="drawer-item-label">Pengeluaran</span>
        </a>
        <a href="{{ route('transfer.index', ['period_id' => $period->id]) }}" class="drawer-item">
            <span class="drawer-item-icon">🏦</span>
            <span class="drawer-item-label">Transfer</span>
        </a>
        <a href="{{ route('external-debt.index', ['period_id' => $period->id]) }}" class="drawer-item">
            <span class="drawer-item-icon">💼</span>
            <span class="drawer-item-label">Piutang</span>
        </a>
        <a href="{{ route('summary.index', ['period_id' => $period->id]) }}" class="drawer-item">
            <span class="drawer-item-icon">📊</span>
            <span class="drawer-item-label">Ringkasan</span>
        </a>
    </div>
</div>

@endsection
