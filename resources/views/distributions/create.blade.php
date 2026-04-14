@extends('layouts.app')
@section('title', isset($distribution) ? 'Edit Distribusi' : 'Input Distribusi')
@section('content')

<div style="margin-top:4px;margin-bottom:12px">
    <a href="{{ route('distributions.index', ['period_id' => isset($distribution) ? $distribution->period_id : $period->id]) }}" style="font-size:12px;color:var(--text3)">← Distribusi</a>
    <div style="font-size:16px;font-weight:600;color:var(--text1);margin-top:4px">
        {{ isset($distribution) ? '✏️ Edit Distribusi' : '🚚 Input Distribusi' }}
    </div>
</div>

<form method="POST"
    action="{{ isset($distribution) ? route('distributions.update', $distribution) : route('distributions.store') }}"
    x-data="{
        status: '{{ isset($distribution) ? $distribution->payment_status : 'paid' }}',
        qty: {{ isset($distribution) ? $distribution->qty : (old('qty') ?: 0) }},
        price: {{ isset($distribution) ? $distribution->price_per_unit : (old('price_per_unit') ?: 18000) }},
        get total() { return this.qty > 0 && this.price > 0 ? this.qty * this.price : 0; },
        fmt(n) { return n > 0 ? 'Rp ' + new Intl.NumberFormat('id-ID').format(n) : '-'; }
    }">
    @csrf
    @if(isset($distribution)) @method('PUT') @endif
    @unless(isset($distribution))
        <input type="hidden" name="period_id" value="{{ $period->id }}">
    @endunless

    {{-- Kurir & Tanggal --}}
    <div class="s-card">
        <div class="s-card-header">🚚 Detail Distribusi</div>
        <div style="padding:12px 14px;display:flex;flex-direction:column;gap:10px">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                    <label class="field-label">Kurir</label>
                    <select name="courier_id" class="field-select" required>
                        @foreach($couriers as $c)
                            <option value="{{ $c->id }}"
                                {{ isset($distribution) && $distribution->courier_id == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Tanggal</label>
                    <input type="date" name="dist_date"
                        value="{{ isset($distribution) ? $distribution->dist_date->format('Y-m-d') : old('dist_date', date('Y-m-d')) }}"
                        class="field-input" required>
                </div>
            </div>

            <div>
                <label class="field-label">Customer</label>
                <select name="customer_id" class="field-select" required>
                    <option value="">-- Pilih Customer --</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}"
                            {{ isset($distribution) && $distribution->customer_id == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}{{ $c->type === 'contract' ? ' ★ Kontrak' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                    <label class="field-label">Qty (tabung)</label>
                    <input type="number" name="qty" x-model.number="qty"
                        value="{{ isset($distribution) ? $distribution->qty : old('qty') }}"
                        min="1" class="field-input" required>
                </div>
                <div>
                    <label class="field-label">Harga/Tabung (Rp)</label>
                    <input type="number" name="price_per_unit" x-model.number="price"
                        value="{{ isset($distribution) ? $distribution->price_per_unit : old('price_per_unit', 18000) }}"
                        min="0" max="25000" class="field-input" required>
                </div>
            </div>

            {{-- Live total --}}
            <div style="display:flex;align-items:center;justify-content:space-between;background:var(--melon-50);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                <span style="font-size:12px;color:var(--text3)">
                    <span x-text="qty || 0" style="font-weight:600;color:var(--text1)"></span> tabung
                    &times;
                    <span x-text="fmt(price)" style="font-weight:600;color:var(--text1)"></span>
                </span>
                <span style="font-size:16px;font-weight:700;color:var(--melon-dark)" x-text="fmt(total)">-</span>
            </div>

        </div>
    </div>

    {{-- Pembayaran --}}
    <div class="s-card">
        <div class="s-card-header">💳 Pembayaran</div>
        <div style="padding:12px 14px;display:flex;flex-direction:column;gap:10px">

            <div>
                <label class="field-label">Status Pembayaran</label>
                <select name="payment_status" x-model="status" class="field-select" required>
                    <option value="paid">Lunas (langsung bayar)</option>
                    <option value="deferred">Ditunda (kontrak)</option>
                    <option value="partial">Sebagian</option>
                </select>
            </div>

            <div x-show="status === 'partial'" x-cloak>
                <label class="field-label">Nominal Dibayar (Rp)</label>
                <input type="number" name="paid_amount"
                    value="{{ isset($distribution) ? $distribution->paid_amount : old('paid_amount', 0) }}"
                    min="0" class="field-input">
            </div>

            <div>
                <label class="field-label">Catatan</label>
                <input type="text" name="notes"
                    value="{{ isset($distribution) ? $distribution->notes : old('notes') }}"
                    class="field-input" placeholder="Opsional">
            </div>

        </div>
    </div>

    <div style="display:flex;gap:10px">
        <button type="submit" class="btn-primary">
            {{ isset($distribution) ? '💾 Update' : '✅ Simpan' }}
        </button>
        <a href="{{ route('distributions.index', ['period_id' => isset($distribution) ? $distribution->period_id : $period->id]) }}" class="btn-secondary">Batal</a>
    </div>

</form>

@endsection