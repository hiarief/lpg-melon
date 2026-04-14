@extends('layouts.app')
@section('title','Daftar Periode')
@section('content')

<div class="section-header" style="margin-top:4px">
    <span class="section-title">📅 Daftar Periode</span>
    <a href="{{ route('periods.create') }}" class="btn-primary btn-sm">+ Buat Baru</a>
</div>

<div class="s-card">
    <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Status</th>
                    <th class="r">Stok Awal</th>
                    <th class="r">Saldo Kas</th>
                    <th class="r">Saldo Penampung</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($periods as $p)
                <tr>
                    <td class="bold">{{ $p->label }}</td>
                    <td>
                        @if($p->status === 'open')
                        <span class="badge badge-green">BUKA</span>
                        @else
                        <span class="badge" style="background:#f0f0f0;color:#666">Tutup</span>
                        @endif
                    </td>
                    <td class="r">{{ number_format($p->opening_stock) }} tab</td>
                    <td class="r">Rp {{ number_format($p->opening_cash) }}</td>
                    <td class="r">Rp {{ number_format($p->opening_penampung) }}</td>
                    <td>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                            <a href="{{ route('periods.show', $p) }}" style="font-size:11px;color:var(--melon-dark);font-weight:600">Detail</a>
                            <a href="{{ route('summary.index', ['period_id' => $p->id]) }}" style="font-size:11px;color:#2563eb">Rekap</a>
                            @if($p->status === 'open')
                            <a href="{{ route('periods.edit', $p) }}" style="font-size:11px;color:var(--text2)">Edit</a>
                            <form method="POST" action="{{ route('periods.close', $p) }}" style="display:inline" onsubmit="return confirm('Tutup periode {{ $p->label }}? Tidak bisa dibuka kembali.')">
                                @csrf
                                <button type="submit" class="link-btn" style="color:#dc2626">Tutup</button>
                            </form>
                            @endif
                            @php $latest = \App\Models\Period::orderByDesc('year')->orderByDesc('month')->first(); @endphp
                            @if($latest && $latest->id === $p->id)
                            <form method="POST" action="{{ route('periods.destroy', $p) }}" style="display:inline" onsubmit="return confirm('⚠️ HAPUS periode {{ $p->label }}?\n\nSEMUA data DO, distribusi, cashflow, transfer, dll akan ikut terhapus permanen.\n\nYakin lanjutkan?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-btn" style="color:#9ca3af">Hapus</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:24px;color:var(--text3)">
                        Belum ada periode. Buat periode pertama.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
