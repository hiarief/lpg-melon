@extends('layouts.app')
@section('title', isset($do) ? 'Edit DO' : 'Input DO Baru')
@section('content')

{{-- ══════════════════════════════════════════════════════════════
     BACK LINK + TITLE
══════════════════════════════════════════════════════════════ --}}

<a href="{{ route('do.index', ['period_id' => isset($do) ? $do->period_id : $period->id]) }}" class="back-link-dox">
    ← DO Agen
</a>

<div class="title-row-dox">
    <span class="page-title-dox">{{ isset($do) ? '✏️ Edit DO' : '📦 Input DO Baru' }}</span>
</div>

{{-- ══════════════════════════════════════════════════════════════
     FORM CARD
══════════════════════════════════════════════════════════════ --}}

<form method="POST" action="{{ isset($do) ? route('do.update', $do) : route('do.store') }}" class="form-card">
    @csrf
    @if(isset($do)) @method('PUT') @endif
    @unless(isset($do))
    <input type="hidden" name="period_id" value="{{ $period->id }}">
    @endunless

    <div class="form-card-header">
        <span>{{ isset($do) ? '✏️ Edit DO' : '📦 Detail DO' }}</span>
    </div>

    <div class="form-card-body">

        <div class="field-group">
            <div class="full-width">
                <label class="field-label-dox">Pangkalan *</label>
                <select name="outlet_id" class="field-select-dox" required>
                    <option value="">— Pilih Pangkalan —</option>
                    @foreach($outlets as $o)
                    <option value="{{ $o->id }}" {{ (isset($do) && $do->outlet_id == $o->id) ? 'selected' : '' }}>
                        {{ $o->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="field-label-dox">Tanggal DO *</label>
                <input type="date" name="do_date"
                    value="{{ isset($do) ? $do->do_date->format('Y-m-d') : old('do_date', date('Y-m-d')) }}"
                    class="field-input-dox" required>
            </div>

            <div>
                <label class="field-label-dox">Qty (Tabung) *</label>
                <input type="number" name="qty"
                    value="{{ isset($do) ? $do->qty : old('qty') }}"
                    min="1" class="field-input-dox text-right" required>
            </div>

            <div>
                <label class="field-label-dox">Harga/Tabung (Rp) *</label>
                <input type="number" name="price_per_unit"
                    value="{{ isset($do) ? $do->price_per_unit : old('price_per_unit', 16000) }}"
                    min="1000" step="1000" class="field-input-dox text-right" required>
            </div>

            <div class="full-width">
                <label class="field-label-dox">Catatan (Opsional)</label>
                <input type="text" name="notes"
                    value="{{ isset($do) ? $do->notes : old('notes') }}"
                    class="field-input-dox" placeholder="Misal: carry-over, diskon, dll.">
            </div>
        </div>

    </div>

    <div class="form-card-footer">
        <a href="{{ route('do.index', ['period_id' => isset($do) ? $do->period_id : $period->id]) }}" class="btn-dox-secondary">Batal</a>
        <button type="submit" class="btn-dox-primary">
            {{ isset($do) ? '💾 Update DO' : '✅ Simpan DO' }}
        </button>
    </div>
</form>

{{-- Info period --}}
<div class="form-info-dox">
    Periode: <strong>{{ $period->label }}</strong> ·
    Stok awal: <strong>{{ number_format($period->opening_stock) }}</strong> tab ·
    Status: <strong style="color:{{ $period->status === 'open' ? '#10B981' : '#6B7280' }}">
        {{ $period->status === 'open' ? '🟢 Buka' : '🔒 Tutup' }}
    </strong>
</div>

@endsection
