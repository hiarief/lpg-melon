@php
$monthNames = [
1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
9=>'September',10=>'Oktober',11=>'November',12=>'Desember',
];
$isEdit = isset($period);
$action = $isEdit ? route('periods.update', $period) : route('periods.store');
@endphp

<form method="POST" action="{{ $action }}" x-data="{
    hasCarryover: {{ $existingCarryoverDOs->count() > 0 || (!$isEdit && ($prevUnpaidDOs->count() > 0 || $prevUnpaidQty > 0)) ? 'true' : 'false' }},
    rows: {{ json_encode($carryoverRows) }},
    addRow() { this.rows.push({ outlet_id: '', qty: 0, price_per_unit: 16000 }) },
    removeRow(i) { if (this.rows.length > 1) this.rows.splice(i, 1) },
    totalCarryover() { return this.rows.reduce((s, r) => s + (parseInt(r.qty) || 0), 0) }
}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    {{-- Bulan & Tahun --}}
    <div class="s-card">
        <div class="s-card-header">🗓 Bulan & Tahun</div>
        <div style="padding:12px 14px;display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div>
                <label class="field-label">Bulan</label>
                @if($isEdit)
                <input type="text" value="{{ $monthNames[$period->month] }}" class="field-input" style="background:var(--surface2);color:var(--text3);cursor:not-allowed" readonly>
                <span style="font-size:10px;color:var(--text3);display:block;margin-top:3px">Tidak bisa diubah</span>
                @else
                <select name="month" class="field-select" required>
                    @foreach($monthNames as $num => $name)
                    <option value="{{ $num }}" {{ $suggested->month == $num ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                @endif
            </div>
            <div>
                <label class="field-label">Tahun</label>
                @if($isEdit)
                <input type="text" value="{{ $period->year }}" class="field-input" style="background:var(--surface2);color:var(--text3);cursor:not-allowed" readonly>
                <span style="font-size:10px;color:var(--text3);display:block;margin-top:3px">Tidak bisa diubah</span>
                @else
                <input type="number" name="year" value="{{ $suggested->year }}" min="2020" max="2100" class="field-input" required>
                @endif
            </div>
        </div>
    </div>

    {{-- Saldo Awal --}}
    <div class="s-card">
        <div class="s-card-header">💰 Saldo Awal</div>
        <div style="padding:12px 14px;display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div>
                <label class="field-label">Stok Tabung Sisa (tabung)</label>
                <input type="number" name="opening_stock" value="{{ old('opening_stock', $isEdit ? $period->opening_stock : $prevStockSisa) }}" min="0" class="field-input" required>
                @if(!$isEdit && $latest)
                <span style="font-size:10px;color:var(--text3);display:block;margin-top:3px">Sisa stok dari {{ $latest->label }}</span>
                @endif
            </div>
            <div>
                <label class="field-label">Saldo Kas Fisik Awal (Rp)</label>
                <input type="number" name="opening_cash" value="{{ old('opening_cash', $isEdit ? $period->opening_cash : 0) }}" min="0" class="field-input" required>
            </div>
            <div>
                <label class="field-label">Saldo Rek. Penampung Awal (Rp)</label>
                <input type="number" name="opening_penampung" value="{{ old('opening_penampung', $isEdit ? $period->opening_penampung : 0) }}" min="0" class="field-input" required>
            </div>
            <div>
                <label class="field-label">Piutang External Dibawa (Rp)</label>
                <input type="number" name="opening_external_debt" value="{{ old('opening_external_debt', $isEdit ? $period->opening_external_debt : 0) }}" min="0" class="field-input" required>
            </div>
            <div style="grid-column:1/-1">
                <label class="field-label">Tabungan / Surplus Dibawa (Rp)</label>
                <input type="number" name="opening_surplus" value="{{ old('opening_surplus', $isEdit ? ($period->opening_surplus ?? 0) : 0) }}" min="0" class="field-input">
                <span style="font-size:10px;color:var(--text3);display:block;margin-top:3px">Surplus transfer bulan lalu yang tersimpan di rek utama</span>
            </div>
        </div>
    </div>

    {{-- DO Carry-Over --}}
    <div class="s-card">
        <div class="s-card-header" style="display:flex;align-items:center;justify-content:space-between">
            <span>⚠️ DO Belum Lunas dari Bulan Lalu</span>
            <label style="display:flex;align-items:center;gap:6px;font-size:11px;font-weight:500;color:var(--text2);cursor:pointer">
                <input type="checkbox" x-model="hasCarryover" style="accent-color:var(--melon)">
                Ada piutang bawaan
            </label>
        </div>
        <div style="padding:12px 14px">

            {{-- Existing carry-over (edit mode) --}}
            @if($isEdit && $existingCarryoverDOs->count() > 0)
            <div style="background:#fffbeb;border:0.5px solid #fde68a;border-radius:8px;padding:10px 12px;margin-bottom:10px;font-size:11px">
                <div style="font-weight:600;color:#92400e;margin-bottom:6px">📦 DO Carry-Over yang sudah tercatat:</div>
                @foreach($existingCarryoverDOs as $udo)
                <div style="display:flex;justify-content:space-between;padding:3px 0;color:var(--text2)">
                    <span>{{ $udo->outlet->name }} — {{ $udo->do_date->format('d/m/Y') }} ({{ $udo->qty }} tab × Rp {{ number_format($udo->price_per_unit) }})</span>
                    <span style="font-weight:600;color:{{ $udo->payment_status === 'paid' ? 'var(--melon-dark)' : '#b45309' }}">
                        {{ $udo->payment_status === 'paid' ? '✓ Lunas' : 'Sisa: Rp ' . number_format($udo->remainingAmount()) }}
                    </span>
                </div>
                @endforeach
                <div style="margin-top:6px;font-size:10px;color:#b45309">
                    Untuk mengubah, hapus di <a href="{{ route('do.index', ['period_id' => $period->id]) }}" style="color:#92400e;text-decoration:underline">DO Agen</a> lalu tambah ulang di sini.
                </div>
            </div>
            @endif

            {{-- Prev unpaid DO (create mode) --}}
            @if(!$isEdit && $prevUnpaidDOs->count() > 0)
            <div style="background:#fef2f2;border:0.5px solid #fca5a5;border-radius:8px;padding:10px 12px;margin-bottom:10px;font-size:11px">
                <div style="font-weight:600;color:#991b1b;margin-bottom:6px">📋 DO belum lunas dari {{ $latest->label }}:</div>
                @foreach($prevUnpaidDOs as $udo)
                <div style="display:flex;justify-content:space-between;padding:3px 0;color:var(--text2)">
                    <span>{{ $udo->outlet->name }} — {{ $udo->do_date->format('d/m/Y') }} ({{ $udo->qty }} tab)</span>
                    <span style="font-weight:600;color:#991b1b">Sisa: Rp {{ number_format($udo->remainingAmount()) }}</span>
                </div>
                @endforeach
                <div style="margin-top:6px;font-size:10px;color:#b91c1c">
                    ⚡ DO ini sudah tercatat dan otomatis muncul di Transfer. Tidak perlu input ulang.
                </div>
            </div>
            @endif

            {{-- Carry-over rows input --}}
            <div x-show="hasCarryover" x-cloak x-transition>
                <div style="font-size:11px;color:var(--text3);margin-bottom:8px">
                    Isi hanya jika ada DO lama yang <strong>belum pernah diinput</strong> ke sistem sama sekali.
                    @if($isEdit) Baris baru di sini akan ditambahkan ke carry-over yang ada. @endif
                </div>
                <div class="s-card" style="margin-bottom:0">
                    <div class="scroll-x">
                        <table class="mob-table">
                            <thead>
                                <tr>
                                    <th>Pangkalan</th>
                                    <th class="r" style="width:80px">Qty</th>
                                    <th class="r" style="width:110px">Harga/tab</th>
                                    <th style="width:32px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, i) in rows" :key="i">
                                    <tr>
                                        <td>
                                            <select :name="'carryover[' + i + '][outlet_id]'" x-model="row.outlet_id" class="field-select" style="padding:6px 8px;font-size:11px">
                                                <option value="">-- Pangkalan --</option>
                                                @foreach($outlets as $o)
                                                <option value="{{ $o->id }}">{{ $o->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="r">
                                            <input type="number" :name="'carryover[' + i + '][qty]'" x-model.number="row.qty" min="1" class="field-input" style="padding:6px 8px;font-size:11px;text-align:right">
                                        </td>
                                        <td class="r">
                                            <input type="number" :name="'carryover[' + i + '][price_per_unit]'" x-model.number="row.price_per_unit" min="1000" class="field-input" style="padding:6px 8px;font-size:11px;text-align:right">
                                        </td>
                                        <td style="text-align:center">
                                            <button type="button" @click="removeRow(i)" style="background:none;border:none;font-size:18px;color:#ef4444;line-height:1;cursor:pointer;padding:0">×</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" style="padding:8px 10px">
                                        <div style="display:flex;align-items:center;justify-content:space-between">
                                            <button type="button" @click="addRow()" class="link-btn" style="color:var(--melon-dark)">+ Tambah Baris</button>
                                            <span style="font-size:10px;color:var(--text3)">Total baru: <strong x-text="totalCarryover()"></strong> tabung</span>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Actions --}}
    <div style="display:flex;gap:10px;padding-top:4px">
        <button type="submit" class="btn-primary">
            {{ $isEdit ? '💾 Simpan Perubahan' : '✅ Buat Periode' }}
        </button>
        <a href="{{ $isEdit ? route('periods.show', $period) : route('periods.index') }}" class="btn-secondary">Batal</a>
    </div>

</form>
