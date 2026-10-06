@extends('layouts.app')
@section('title','Edit DO')
@section('content')

    <a href="{{ route('do.index', ['period_id' => $do->period_id]) }}" class="back-link-dox">
        ← DO Agen
    </a>
    <div class="title-row-dox">
        <span class="page-title-dox">✏️ Edit DO — {{ $do->outlet->name }} {{ $do->do_date->format('d/m/Y') }}</span>
    </div>

<form method="POST" action="{{ route('do.update', $do) }}" class="form-card">
    @csrf @method('PUT')

    <div class="s-card" style="margin-bottom:10px">
        <div class="s-card-header">📦 Detail DO</div>
        <div style="padding:12px 14px;display:flex;flex-direction:column;gap:10px">

            <div>
                <label class="field-label-dox">Pangkalan</label>
                <select name="outlet_id" class="field-select-dox" required>
                    @foreach($outlets as $o)
                    <option value="{{ $o->id }}" {{ $do->outlet_id == $o->id ? 'selected' : '' }}>{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="field-label-dox">Tanggal DO</label>
                <input type="date" name="do_date" value="{{ $do->do_date->format('Y-m-d') }}" class="field-input-dox" required>
            </div>

            <div>
                <label class="field-label-dox">Jumlah (tabung)</label>
                <input type="number" name="qty" value="{{ $do->qty }}" min="1" class="field-input-dox" required>
            </div>
            <div>
                <label class="field-label-dox">Harga/Tabung (Rp)</label>
                <input type="number" name="price_per_unit" value="{{ $do->price_per_unit }}" class="field-input-dox" required>
            </div>

            <div>
                <label class="field-label-dox">Catatan</label>
                <input type="text" name="notes" value="{{ $do->notes }}" class="field-input-dox" placeholder="Opsional">
            </div>

        </div>
    </div>

    <div style="background:#fffbeb;border:0.5px solid #fde68a;border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:11px;color:#92400e" class="pill-box">
        ⚠️ Status pembayaran: <strong>{{ ucfirst($do->payment_status) }}</strong> —
        Terbayar Rp {{ number_format($do->paid_amount) }} dari Rp {{ number_format($do->qty * $do->price_per_unit) }}
    </div>

    <div style="display:flex;gap:10px">
        <button type="submit" class="btn-dox-primary">💾 Update DO</button>
        <a href="{{ route('do.index', ['period_id' => $do->period_id]) }}" class="btn-dox-secondary">Batal</a>
    </div>
</form>

@endsection
