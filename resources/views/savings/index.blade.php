@extends('layouts.app')
@section('title','Tabungan / Surplus Rek Utama')
@section('content')

<div x-data="{ showForm: false }">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;margin-bottom:12px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span style="font-size:15px;font-weight:600;color:var(--text1)">💰 Tabungan / Surplus</span>
            <form method="GET" action="{{ route('savings.index') }}">
                <select name="period_id" onchange="this.form.submit()" class="field-select" style="padding:6px 10px;font-size:12px;width:auto">
                    @foreach($periods as $p)
                        <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        @if($period->status === 'open')
            <button @click="showForm = !showForm" class="btn-primary btn-sm">+ Input Manual</button>
        @endif
    </div>

    {{-- Summary --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
        <div class="card" style="padding:10px 12px;border-left:3px solid #9ca3af">
            <div style="font-size:10px;color:var(--text3)">Saldo Awal (Cutoff Lalu)</div>
            <div style="font-size:16px;font-weight:600;color:var(--text1)">Rp {{ number_format($period->opening_surplus) }}</div>
            <div style="font-size:10px;color:var(--text3)">surplus dari bulan lalu</div>
        </div>
        <div class="card" style="padding:10px 12px;border-left:3px solid var(--melon)">
            <div style="font-size:10px;color:var(--text3)">Masuk Bulan Ini</div>
            <div style="font-size:16px;font-weight:600;color:var(--melon-dark)">+ Rp {{ number_format($totalIn) }}</div>
            <div style="font-size:10px;color:var(--text3)">surplus transfer + manual</div>
        </div>
        <div class="card" style="padding:10px 12px;border-left:3px solid #ef4444">
            <div style="font-size:10px;color:var(--text3)">Keluar Bulan Ini</div>
            <div style="font-size:16px;font-weight:600;color:#dc2626">− Rp {{ number_format($totalOut) }}</div>
            <div style="font-size:10px;color:var(--text3)">diambil / dipakai</div>
        </div>
        <div class="card" style="padding:10px 12px;border-left:3px solid #eab308">
            <div style="font-size:10px;color:var(--text3)">Saldo Tabungan Sekarang</div>
            <div style="font-size:18px;font-weight:600;color:{{ $balance >= 0 ? '#92400e' : '#dc2626' }}">Rp {{ number_format($balance) }}</div>
            <div style="font-size:10px;color:var(--text3)">{{ number_format($period->opening_surplus) }} + {{ number_format($totalIn) }} − {{ number_format($totalOut) }}</div>
        </div>
    </div>

    {{-- Info --}}
    <div style="background:#fffbeb;border:0.5px solid #fde68a;border-radius:8px;padding:10px 12px;margin-bottom:10px;font-size:11px;color:#92400e;line-height:1.5">
        💡 <strong>Tabungan</strong> = surplus dari transfer penampung ke rek utama yang melebihi nilai DO.
        Otomatis tercatat saat input transfer. Bisa juga input manual untuk pengambilan atau penyesuaian.
        Surplus bulan lalu diisi di field <strong>Tabungan / Surplus Dibawa</strong> saat buka periode baru.
    </div>

    {{-- Input manual --}}
    @if($period->status === 'open')
    <div x-show="showForm" x-cloak x-transition style="margin-bottom:10px">
        <div class="s-card">
            <div class="s-card-header">+ Input Manual Tabungan</div>
            <div style="padding:12px 14px">
                <form method="POST" action="{{ route('savings.store') }}"
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
                                <option value="in">Masuk (Surplus / Tambah)</option>
                                <option value="out">Keluar (Diambil / Dipakai)</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Nominal (Rp)</label>
                            <input type="number" name="amount" min="1" class="field-input" required>
                        </div>
                        <div>
                            <label class="field-label">Keterangan</label>
                            <input type="text" name="description" class="field-input" placeholder="Opsional">
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary">✅ Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Tabel riwayat --}}
    <div class="s-card">
        <div class="s-card-header">📋 Riwayat Tabungan — {{ $period->label }}</div>
        <div class="scroll-x">
            <table class="mob-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                        <th class="r">Masuk</th>
                        <th class="r">Keluar</th>
                        <th class="r">Saldo</th>
                        <th>Sumber</th>
                        @if($period->status === 'open') <th>Aksi</th> @endif
                    </tr>
                </thead>
                <tbody>
                    {{-- Baris saldo awal --}}
                    @if($period->opening_surplus > 0)
                    <tr style="background:var(--surface2)">
                        <td style="color:var(--text3);font-style:italic">Awal {{ $period->label }}</td>
                        <td style="color:var(--text3)">—</td>
                        <td style="color:var(--text3);font-style:italic">Saldo awal (cutoff periode lalu)</td>
                        <td class="r bold">Rp {{ number_format($period->opening_surplus) }}</td>
                        <td class="r" style="color:var(--text3)">—</td>
                        <td class="r bold" style="color:#92400e">Rp {{ number_format($period->opening_surplus) }}</td>
                        <td><span class="badge" style="background:#f0f0f0;color:#666">Cutoff</span></td>
                        @if($period->status === 'open') <td></td> @endif
                    </tr>
                    @endif

                    @forelse($rows as $r)
                    @php $s = $r['saving']; @endphp
                    <tr style="{{ $s->type === 'out' ? 'background:#fef2f2' : '' }}">
                        <td>{{ $s->entry_date->format('d/m/Y') }}</td>
                        <td>
                            @if($s->type === 'in')
                                <span class="badge badge-green">↓ Masuk</span>
                            @else
                                <span class="badge badge-red">↑ Keluar</span>
                            @endif
                        </td>
                        <td style="color:var(--text3);font-size:11px">{{ $s->description ?: '—' }}</td>
                        <td class="r" style="color:{{ $s->type === 'in' ? 'var(--melon-dark)' : 'var(--text3)' }};font-weight:{{ $s->type === 'in' ? '600' : '400' }}">
                            {{ $s->type === 'in' ? 'Rp '.number_format($s->amount) : '—' }}
                        </td>
                        <td class="r" style="color:{{ $s->type === 'out' ? '#dc2626' : 'var(--text3)' }};font-weight:{{ $s->type === 'out' ? '600' : '400' }}">
                            {{ $s->type === 'out' ? 'Rp '.number_format($s->amount) : '—' }}
                        </td>
                        <td class="r bold" style="color:{{ $r['balance'] >= 0 ? '#92400e' : '#dc2626' }}">
                            Rp {{ number_format($r['balance']) }}
                        </td>
                        <td>
                            @if($s->account_transfer_id)
                                <span class="badge badge-blue" title="Surplus dari transfer otomatis">🔗 Transfer</span>
                            @else
                                <span class="badge" style="background:#f0f0f0;color:#666">Manual</span>
                            @endif
                        </td>
                        @if($period->status === 'open')
                        <td>
                            @if(!$s->account_transfer_id)
                                <form method="POST" action="{{ route('savings.destroy', $s) }}" style="display:inline" onsubmit="return confirm('Hapus entri tabungan ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="link-btn" style="color:#dc2626">Hapus</button>
                                </form>
                            @else
                                <span style="font-size:10px;color:var(--text3)" title="Hapus via halaman Transfer">—</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:20px;color:var(--text3)">
                            Belum ada riwayat tabungan bulan ini.
                            @if($period->opening_surplus == 0)
                                Surplus otomatis tercatat saat transfer penampung ke rek utama melebihi nilai DO.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if(count($rows) > 0 || $period->opening_surplus > 0)
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" class="bold">SALDO TABUNGAN AKHIR</td>
                        <td class="r">Rp {{ number_format($period->opening_surplus + $totalIn) }}</td>
                        <td class="r">Rp {{ number_format($totalOut) }}</td>
                        <td class="r">Rp {{ number_format($balance) }}</td>
                        <td colspan="{{ $period->status === 'open' ? '2' : '1' }}"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection