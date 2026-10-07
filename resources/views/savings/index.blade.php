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
        <form method="GET" action="{{ route('savings.export-year') }}" style="display:inline-flex;align-items:center;gap:6px">
            <select name="year" class="field-select" style="padding:6px 10px;font-size:12px;width:auto">
                @for($y = date('Y'); $y >= 2020; $y--)
                    <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="btn-sm" style="background:#059669;color:#fff;border:none;padding:6px 12px;border-radius:4px;font-size:12px;cursor:pointer;">📥 Export Excel</button>
        </form>
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

    <!-- Tab toggle sort mode -->
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap">
        <span style="font-size:11px;color:var(--text3);font-weight:600;">Urutkan berdasarkan:</span>
        <div style="display:flex;gap:4px;background:var(--surface2);border-radius:8px;padding:3px">
            <a href="{{ route('savings.index', ['period_id' => $period->id, 'sort' => 'transfer']) }}"
               style="padding:5px 12px;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;
                      {{ $sortMode === 'transfer' ? 'background:var(--surface);color:var(--text1);box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:var(--text3);' }}">
                📅 Tanggal Transfer
            </a>
            <a href="{{ route('savings.index', ['period_id' => $period->id, 'sort' => 'do']) }}"
               style="padding:5px 12px;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;
                      {{ $sortMode === 'do' ? 'background:var(--surface);color:var(--text1);box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:var(--text3);' }}">
                📦 Tanggal DO
            </a>
            <a href="{{ route('savings.index', ['period_id' => $period->id, 'sort' => 'per_do']) }}"
               style="padding:5px 12px;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;
                      {{ $sortMode === 'per_do' ? 'background:var(--surface);color:var(--text1);box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:var(--text3);' }}">
                📊 Mutasi per DO
            </a>
        </div>
        @if($sortMode === 'per_do')
            <span style="font-size:10px;color:#059669;background:#f0fdf4;padding:3px 8px;border-radius:4px;">
                💡 Ringkasan tabungan per tanggal DO
            </span>
        @elseif($sortMode === 'do')
            <span style="font-size:10px;color:#1d4ed8;background:#eff6ff;padding:3px 8px;border-radius:4px;">
                💡 Diurutkan by tanggal DO terlama
            </span>
        @else
            <span style="font-size:10px;color:#7c3aed;background:#f5f3ff;padding:3px 8px;border-radius:4px;">
                💡 Diurutkan by tanggal transfer
            </span>
        @endif
    </div>

    <!-- Tabel per-DO (hanya tampil di mode per_do) -->
    @if($sortMode === 'per_do')
    <div class="s-card" style="margin-bottom:10px">
        <div class="s-card-header">📊 Mutasi Tabungan per Tanggal DO — {{ $period->label }}</div>
        <div style="padding:10px 14px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;font-size:11px;color:#059669;">
            💡 Menampilkan berapa tabungan (surplus) yang dihasilkan dari tiap DO berdasarkan <strong>tanggal DO</strong>.
            Satu transfer bisa melunasi beberapa DO, jadi surplus dibagi rata ke setiap DO yang dilunasi.
        </div>
        <div class="scroll-x">
            <table class="mob-table">
                <thead>
                    <tr>
                        <th>Tanggal DO</th>
                        <th>Pangkalan</th>
                        <th class="r">Qty DO</th>
                        <th class="r">Nilai DO</th>
                        <th class="r">Surplus (Tabungan)</th>
                        <th class="r">Surplus/DO</th>
                        <th>Transfer</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($perDoData as $d)
                    <tr>
                        <td style="white-space:nowrap;font-weight:600;color:#1d4ed8;">
                            {{ \Carbon\Carbon::parse($d['do_date'])->format('d/m/Y') }}
                        </td>
                        <td style="font-size:11px;">
                            <span class="badge badge-blue" style="font-size:9px;">{{ $d['outlet_name'] }}</span>
                        </td>
                        <td class="r">{{ number_format($d['do_qty']) }}</td>
                        <td class="r">Rp {{ number_format($d['do_value']) }}</td>
                        <td class="r bold" style="color:#059669;">Rp {{ number_format($d['surplus']) }}</td>
                        <td class="r" style="color:#059669;">Rp {{ number_format($d['do_value'] > 0 ? $d['surplus'] / $d['do_qty'] : 0) }}</td>
                        <td style="font-size:10px;color:var(--text3);">
                            @foreach($d['transfer_ids'] as $tid)
                                <span class="badge" style="background:#f5f3ff;color:#6d28d9;font-size:9px;">TF #{{ $tid }}</span>
                            @endforeach
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:20px;color:var(--text3)">
                            Belum ada data mutasi per DO.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if(count($perDoData) > 0)
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2" class="bold">TOTAL</td>
                        <td class="r">{{ number_format(collect($perDoData)->sum('do_qty')) }}</td>
                        <td class="r">Rp {{ number_format(collect($perDoData)->sum('do_value')) }}</td>
                        <td class="r bold" style="color:#059669;">Rp {{ number_format(collect($perDoData)->sum('surplus')) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    @endif

    <!-- Tabel riwayat -->
    <div class="s-card">
        <div class="s-card-header">📋 Riwayat Tabungan — {{ $period->label }}</div>
        <div class="scroll-x">
            <table class="mob-table">
                <thead>
                    <tr>
                        <th>Tgl DO</th>
                        <th>Tgl Transfer</th>
                        <th>Selisih</th>
                        <th>Tgl Entry</th>
                        <th>Jenis</th>
                        <th>DO / Keterangan</th>
                        <th class="r">Masuk</th>
                        <th class="r">Keluar</th>
                        <th class="r">Saldo</th>
                        <th>Sumber</th>
                        @if($period->status === 'open') <th>Aksi</th> @endif
                    </tr>
                </thead>
                <tbody>
                    <!-- Baris saldo awal -->
                    @if($period->opening_surplus > 0)
                    <tr style="background:var(--surface2)">
                        <td style="color:var(--text3)">—</td>
                        <td style="color:var(--text3)">—</td>
                        <td style="color:var(--text3)">—</td>
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
                    @php
                        $s      = $r['saving'];
                        $doList = $r['do_list'];
                        $earDo  = $r['earliest_do'];
                        $tfDate = $r['transfer_date'];
                    @endphp
                    <tr style="{{ $s->type === 'out' ? 'background:#fef2f2' : '' }}">

                        {{-- Tgl DO --}}
                        <td style="white-space:nowrap;">
                            @if($earDo)
                                <span style="font-weight:600;color:#1d4ed8;">
                                    {{ \Carbon\Carbon::parse($earDo->do_date)->format('d/m/Y') }}
                                </span>
                                @if($doList->count() > 1)
                                    <span style="font-size:9px;color:var(--text3);display:block;">
                                        +{{ $doList->count() - 1 }} DO lain
                                    </span>
                                @endif
                            @else
                                <span style="color:var(--text3);">—</span>
                            @endif
                        </td>

                        <!-- Tgl Transfer -->
                        <td style="white-space:nowrap;">
                            @if($tfDate)
                                <span style="color:#7c3aed;font-weight:600;">
                                    {{ \Carbon\Carbon::parse($tfDate)->format('d/m/Y') }}
                                </span>
                            @else
                                <span style="color:var(--text3);">—</span>
                            @endif
                        </td>

                        <!-- Selisih hari -->
                        <td style="white-space:nowrap;">
                            @if($r['selisih_hari'] !== null)
                                <span style="font-size:10px;font-weight:600;
                                             color:{{ $r['selisih_hari'] == 0 ? '#059669' : ($r['selisih_hari'] <= 3 ? '#d97706' : '#dc2626') }};
                                             background:{{ $r['selisih_hari'] == 0 ? '#f0fdf4' : ($r['selisih_hari'] <= 3 ? '#fffbeb' : '#fef2f2') }};
                                             padding:2px 6px;border-radius:4px;">
                                    {{ $r['selisih_hari'] == 0 ? 'Hari yang same' : $r['selisih_hari'] . ' hari' }}
                                </span>
                            @else
                                <span style="color:var(--text3);">—</span>
                            @endif
                        </td>

                        {{-- Tgl Entry --}}
                        <td style="color:var(--text3);font-size:11px;white-space:nowrap;">
                            {{ $s->entry_date->format('d/m/Y') }}
                        </td>

                        {{-- Jenis --}}
                        <td>
                            @if($s->type === 'in')
                                <span class="badge badge-green">↓ Masuk</span>
                            @else
                                <span class="badge badge-red">↑ Keluar</span>
                            @endif
                        </td>

                        {{-- DO / Keterangan --}}
                        <td style="font-size:11px;min-width:140px;">
                            @if($doList->isNotEmpty())
                                @foreach($doList as $do)
                                    <div style="display:flex;align-items:center;gap:4px;margin-bottom:2px;">
                                        <span class="badge badge-blue" style="font-size:9px;">
                                            {{ $do->outlet->name }}
                                        </span>
                                        <span style="color:var(--text3);font-size:10px;">
                                            Rp {{ number_format($do->pivot->amount_allocated ?? 0) }}
                                        </span>
                                    </div>
                                @endforeach
                                @if($s->description)
                                    <div style="color:var(--text3);font-size:10px;margin-top:2px;">
                                        {{ $s->description }}
                                    </div>
                                @endif
                            @else
                                <span style="color:var(--text3);">{{ $s->description ?: '—' }}</span>
                            @endif
                        </td>

                        {{-- Masuk --}}
                        <td class="r" style="color:{{ $s->type === 'in' ? 'var(--melon-dark)' : 'var(--text3)' }};font-weight:{{ $s->type === 'in' ? '600' : '400' }}">
                            {{ $s->type === 'in' ? 'Rp '.number_format($s->amount) : '—' }}
                        </td>

                        {{-- Keluar --}}
                        <td class="r" style="color:{{ $s->type === 'out' ? '#dc2626' : 'var(--text3)' }};font-weight:{{ $s->type === 'out' ? '600' : '400' }}">
                            {{ $s->type === 'out' ? 'Rp '.number_format($s->amount) : '—' }}
                        </td>

                        {{-- Saldo --}}
                        <td class="r bold" style="color:{{ $r['balance'] >= 0 ? '#92400e' : '#dc2626' }}">
                            Rp {{ number_format($r['balance']) }}
                        </td>

                        {{-- Sumber --}}
                        <td>
                            @if($s->account_transfer_id)
                                <span class="badge badge-blue" title="Surplus dari transfer otomatis">🔗 Transfer</span>
                            @else
                                <span class="badge" style="background:#f0f0f0;color:#666">Manual</span>
                            @endif
                        </td>

                        {{-- Aksi --}}
                        @if($period->status === 'open')
                        <td>
                            @if(!$s->account_transfer_id)
                                <form method="POST" action="{{ route('savings.destroy', $s) }}"
                                    style="display:inline" onsubmit="return confirm('Hapus entri tabungan ini?')">
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
                        <td colspan="{{ $period->status === 'open' ? '11' : '10' }}"
                            style="text-align:center;padding:20px;color:var(--text3)">
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
                        <td colspan="6" class="bold">SALDO TABUNGAN AKHIR</td>
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