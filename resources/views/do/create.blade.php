@extends('layouts.app')
@section('title', isset($do) ? 'Edit DO' : 'Input DO Baru')
@section('content')

<div style="margin-top:4px;margin-bottom:12px">
    <a href="{{ route('do.index', ['period_id' => isset($do) ? $do->period_id : $period->id]) }}" style="font-size:12px;color:var(--text3)">← DO Agen</a>
    <div style="font-size:16px;font-weight:600;color:var(--text1);margin-top:4px">
        {{ isset($do) ? '✏️ Edit DO' : '📦 Input DO Baru' }}
    </div>
</div>

<form method="POST" action="{{ isset($do) ? route('do.update', $do) : route('do.store') }}">
    @csrf
    @if(isset($do)) @method('PUT') @endif
    @unless(isset($do))
    <input type="hidden" name="period_id" value="{{ $period->id }}">
    @endunless

    <div class="s-card" style="margin-bottom:10px">
        <div class="s-card-header">📦 Detail DO</div>
        <div style="padding:12px 14px;display:flex;flex-direction:column;gap:10px">

            <div>
                <label class="field-label">Pangkalan</label>
                <select name="outlet_id" class="field-select" required>
                    <option value="">-- Pilih Pangkalan --</option>
                    @foreach($outlets as $o)
                    <option value="{{ $o->id }}" {{ (isset($do) && $do->outlet_id == $o->id) ? 'selected' : '' }}>
                        {{ $o->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="field-label">Tanggal DO</label>
                <input type="date" name="do_date" value="{{ isset($do) ? $do->do_date->format('Y-m-d') : old('do_date', date('Y-m-d')) }}" class="field-input" required>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                    <label class="field-label">Jumlah (tabung)</label>
                    <input type="number" name="qty" value="{{ isset($do) ? $do->qty : old('qty') }}" min="1" class="field-input" required>
                </div>
                <div>
                    <label class="field-label">Harga/Tabung (Rp)</label>
                    <input type="number" name="price_per_unit" value="{{ isset($do) ? $do->price_per_unit : old('price_per_unit', 16000) }}" min="1000" class="field-input" required>
                </div>
            </div>

            <div>
                <label class="field-label">Catatan</label>
                <input type="text" name="notes" value="{{ isset($do) ? $do->notes : old('notes') }}" class="field-input" placeholder="Opsional">
            </div>

        </div>
    </div>

    <div style="display:flex;gap:10px">
        <button type="submit" class="btn-primary">
            {{ isset($do) ? '💾 Update' : '✅ Simpan DO' }}
        </button>
        <a href="{{ route('do.index', ['period_id' => isset($do) ? $do->period_id : $period->id]) }}" class="btn-secondary">Batal</a>
    </div>
</form>

@endsection
