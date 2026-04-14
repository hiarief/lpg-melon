@extends('layouts.app')
@section('title','Piutang External')
@section('content')

<div x-data="{ showForm: false }">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;margin-bottom:12px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span style="font-size:15px;font-weight:600;color:var(--text1)">💼 Piutang External</span>
            <form method="GET" action="{{ route('external-debt.index') }}">
                <select name="period_id" onchange="this.form.submit()" class="field-select" style="padding:6px 10px;font-size:12px;width:auto">
                    @foreach($periods as $p)
                        <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        @if($period->status === 'open')
            <button @click="showForm = !showForm" class="btn-primary btn-sm">+ Input Piutang</button>
        @endif
    </div>

    {{-- Summary --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
        <div class="card" style="padding:10px 12px;border-left:3px solid #9ca3af">
            <div style="font-size:10px;color:var(--text3)">Saldo Awal Bulan</div>
            <div style="font-size:16px;font-weight:600;color:var(--text1)">Rp {{ number_format($period->opening_external_debt) }}</div>
        </div>
        <div class="card" style="padding:10px 12px;border-left:3px solid var(--melon)">
            <div style="font-size:10px;color:var(--text3)">Modal Masuk</div>
            <div style="font-size:16px;font-weight:600;color:var(--melon-dark)">+ Rp {{ number_format($netIn) }}</div>
        </div>
        <div class="card" style="padding:10px 12px;border-left:3px solid #ef4444">
            <div style="font-size:10px;color:var(--text3)">Dibayar</div>
            <div style="font-size:16px;font-weight:600;color:#dc2626">− Rp {{ number_format($netOut) }}</div>
        </div>
        <div class="card" style="padding:10px 12px;border-left:3px solid #f97316">
            <div style="font-size:10px;color:var(--text3)">Saldo Piutang</div>
            <div style="font-size:16px;font-weight:600;color:#c2410c">Rp {{ number_format($balance) }}</div>
        </div>
    </div>

    {{-- Input form --}}
    @if($period->status === 'open')
    <div x-show="showForm" x-cloak x-transition style="margin-bottom:10px">
        <div class="s-card">
            <div class="s-card-header">+ Input Piutang / Modal External</div>
            <div style="padding:12px 14px">
                <form method="POST" action="{{ route('external-debt.store') }}"
                      style="display:flex;flex-direction:column;gap:10px">
                    @csrf
                    <input type="hidden" name="period_id" value="{{ $period->id }}">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div>
                            <label class="field-label">Tanggal</label>
                            <input type="date" name="entry_date" value="{{ date('Y-m-d') }}" class="field-input" required>
                        </div>
                        <div>
                            <label class="field-label">Jenis</label>
                            <select name="type" class="field-select" required>
                                <option value="in">Masuk (Terima Modal)</option>
                                <option value="out">Keluar (Bayar Balik)</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Nama Pemberi/Penerima</label>
                            <input type="text" name="source_name" class="field-input" required>
                        </div>
                        <div>
                            <label class="field-label">Nominal (Rp)</label>
                            <input type="number" name="amount" min="1" class="field-input" required>
                        </div>
                    </div>
                    <div>
                        <label class="field-label">Keterangan</label>
                        <input type="text" name="description" class="field-input" placeholder="Opsional">
                    </div>
                    <div>
                        <button type="submit" class="btn-primary">✅ Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Table --}}
    <div class="s-card">
        <div class="s-card-header">📋 Riwayat Piutang External</div>
        <div class="scroll-x">
            <table class="mob-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Pihak</th>
                        <th class="r">Nominal</th>
                        <th>Keterangan</th>
                        @if($period->status === 'open') <th>Aksi</th> @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($debts as $debt)
                    <tr>
                        <td>{{ $debt->entry_date->format('d/m/Y') }}</td>
                        <td>
                            @if($debt->type === 'in')
                                <span class="badge badge-green">↓ Masuk</span>
                            @else
                                <span class="badge badge-red">↑ Keluar</span>
                            @endif
                        </td>
                        <td class="bold">{{ $debt->source_name }}</td>
                        <td class="r bold" style="color:{{ $debt->type === 'in' ? 'var(--melon-dark)' : '#dc2626' }}">
                            {{ $debt->type === 'in' ? '+' : '−' }} Rp {{ number_format($debt->amount) }}
                        </td>
                        <td style="color:var(--text3)">{{ $debt->description ?: '—' }}</td>
                        @if($period->status === 'open')
                        <td>
                            <form method="POST" action="{{ route('external-debt.destroy', $debt) }}" style="display:inline" onsubmit="return confirm('Hapus?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="link-btn" style="color:#dc2626">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:20px;color:var(--text3)">Belum ada data piutang external.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection