@extends('layouts.app')
@section('title','Master Data')

@section('content')
<style>
/* ── master data page tokens ─────────────────────────────── */
.page-title { font-size:15px; font-weight:700; color:var(--text1); margin-bottom:12px; }

/* tab bar */
.tab-bar  { display:flex; gap:0; border-bottom:0.5px solid var(--border); margin-bottom:14px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
.tab-btn  { padding:9px 16px; font-size:12px; font-weight:600; color:var(--text3); background:none; border:none;
            border-bottom:2px solid transparent; cursor:pointer; font-family:inherit; white-space:nowrap;
            margin-bottom:-1px; transition:color 0.12s; }
.tab-btn:active  { color:var(--text2); }
.tab-btn.active  { color:var(--melon-dark); border-bottom-color:var(--melon); }

/* add form */
.add-form { background:var(--melon-50); border:0.5px solid var(--border); border-radius:var(--radius-sm);
            padding:12px 14px; margin-bottom:12px; }
.add-form-title { font-size:12px; font-weight:700; color:var(--melon-dark); margin-bottom:10px; }
.add-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media (min-width:400px) { .add-grid-4 { grid-template-columns:repeat(4,1fr); } }
@media (min-width:400px) { .add-grid-3 { grid-template-columns:repeat(3,1fr); } }

/* inline edit form */
.inline-edit { margin-top:8px; display:flex; flex-wrap:wrap; gap:6px; align-items:center;
               padding:8px 10px; background:var(--surface2); border-radius:8px;
               border:0.5px solid var(--border); }
.edit-input  { border:0.5px solid var(--border2); border-radius:6px; padding:5px 8px;
               font-size:11px; font-family:inherit; color:var(--text1); background:var(--surface); }
.edit-input:focus { outline:2px solid var(--melon-mid); border-color:transparent; }
.edit-select { border:0.5px solid var(--border2); border-radius:6px; padding:5px 8px;
               font-size:11px; font-family:inherit; color:var(--text1); background:var(--surface);
               appearance:none; -webkit-appearance:none; }
.edit-check-label { display:flex; align-items:center; gap:4px; font-size:11px; color:var(--text2); white-space:nowrap; }
.edit-save  { background:var(--melon); color:#fff; border:none; border-radius:6px;
              padding:5px 12px; font-size:11px; font-weight:600; cursor:pointer; font-family:inherit; white-space:nowrap; }
.edit-save:active { background:var(--melon-dark); }

/* link-like edit button */
.edit-link { background:none; border:none; font-size:11px; color:#2563eb; text-decoration:underline;
             cursor:pointer; font-family:inherit; padding:0; }
</style>

<div x-data="{ tab: 'outlet' }">

<div class="page-title">⚙️ Master Data</div>

{{-- ══ TAB BAR ══ --}}
<div class="tab-bar">
    <button class="tab-btn" :class="tab==='outlet'   ? 'active' : ''" @click="tab='outlet'">🏪 Pangkalan</button>
    <button class="tab-btn" :class="tab==='customer' ? 'active' : ''" @click="tab='customer'">👥 Customer</button>
    <button class="tab-btn" :class="tab==='courier'  ? 'active' : ''" @click="tab='courier'">🚴 Kurir</button>
</div>

{{-- ══════════════════════════════════════════════════════════
     TAB: PANGKALAN
══════════════════════════════════════════════════════════ --}}
<div x-show="tab === 'outlet'" x-cloak>

    {{-- Add form --}}
    <div class="add-form">
        <div class="add-form-title">+ Tambah Pangkalan</div>
        <form method="POST" action="{{ route('master.outlets.store') }}">
            @csrf
            <div class="add-grid add-grid-4" style="margin-bottom:10px;">
                <div>
                    <label class="field-label">Nama Pangkalan</label>
                    <input type="text" name="name" class="field-input" required>
                </div>
                <div>
                    <label class="field-label">Tipe Kontrak</label>
                    <select name="contract_type" class="field-select" required>
                        <option value="none">Tidak Ada</option>
                        <option value="per_do">Per DO (×tarif/tabung)</option>
                        <option value="flat_monthly">Flat Bulanan</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Tarif Kontrak (Rp)</label>
                    <input type="number" name="contract_rate" value="0" min="0" class="field-input">
                    <div style="font-size:9px;color:var(--text3);margin-top:3px;">Per DO: tarif/tabung · Flat: total/bulan</div>
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="padding:9px 12px;font-size:12px;">Simpan</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="s-card">
        <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Kontrak</th>
                    <th class="r">Tarif</th>
                    <th style="text-align:center;">Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outlets as $o)
                <tr x-data="{ editing: false }">
                    <td class="bold">{{ $o->name }}</td>
                    <td>
                        @if($o->contract_type === 'per_do')
                            <span class="badge badge-blue">Per DO</span>
                        @elseif($o->contract_type === 'flat_monthly')
                            <span class="badge" style="background:#f5f3ff;color:#7c3aed;">Flat</span>
                        @else
                            <span style="color:var(--text3);font-size:10px;">-</span>
                        @endif
                    </td>
                    <td class="r">{{ $o->contract_rate > 0 ? 'Rp '.number_format($o->contract_rate) : '-' }}</td>
                    <td style="text-align:center;">
                        <span class="badge {{ $o->is_active ? 'badge-green' : '' }}"
                              style="{{ !$o->is_active ? 'background:var(--surface2);color:var(--text3);' : '' }}">
                            {{ $o->is_active ? 'Aktif' : 'Non-aktif' }}
                        </span>
                    </td>
                    <td>
                        <button @click="editing = !editing" class="edit-link">Edit</button>
                        <div x-show="editing" x-cloak x-transition>
                            <form method="POST" action="{{ route('master.outlets.update', $o) }}" class="inline-edit">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $o->name }}"
                                       class="edit-input" style="width:100px;">
                                <select name="contract_type" class="edit-select">
                                    <option value="none"         {{ $o->contract_type==='none'        ?'selected':'' }}>Tidak Ada</option>
                                    <option value="per_do"       {{ $o->contract_type==='per_do'      ?'selected':'' }}>Per DO</option>
                                    <option value="flat_monthly" {{ $o->contract_type==='flat_monthly'?'selected':'' }}>Flat</option>
                                </select>
                                <input type="number" name="contract_rate" value="{{ $o->contract_rate }}"
                                       class="edit-input" style="width:80px;">
                                <label class="edit-check-label">
                                    <input type="checkbox" name="is_active" value="1" {{ $o->is_active ? 'checked' : '' }}>
                                    Aktif
                                </label>
                                <button type="submit" class="edit-save">Simpan</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     TAB: CUSTOMER
══════════════════════════════════════════════════════════ --}}
<div x-show="tab === 'customer'" x-cloak>

    {{-- Add form --}}
    <div class="add-form">
        <div class="add-form-title">+ Tambah Customer</div>
        <form method="POST" action="{{ route('master.customers.store') }}">
            @csrf
            <div class="add-grid add-grid-4" style="margin-bottom:10px;">
                <div>
                    <label class="field-label">Nama Customer</label>
                    <input type="text" name="name" class="field-input" required>
                </div>
                <div>
                    <label class="field-label">Tipe</label>
                    <select name="type" class="field-select">
                        <option value="regular">Regular</option>
                        <option value="contract">Kontrak ★</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Link Pangkalan (jika kontrak)</label>
                    <select name="outlet_id" class="field-select">
                        <option value="">-- Tidak Ada --</option>
                        @foreach($outlets as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="padding:9px 12px;font-size:12px;">Simpan</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="s-card">
        <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th style="text-align:center;">Tipe</th>
                    <th>Pangkalan</th>
                    <th style="text-align:center;">Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($customers as $c)
                <tr x-data="{ editing: false }"
                    style="{{ $c->type === 'contract' ? 'background:#fffbeb;' : '' }}">
                    <td class="bold">{{ $c->name }}</td>
                    <td style="text-align:center;">
                        @if($c->type === 'contract')
                            <span class="badge badge-orange">★ Kontrak</span>
                        @else
                            <span style="color:var(--text3);font-size:10px;">Regular</span>
                        @endif
                    </td>
                    <td style="color:var(--text3);">{{ $c->outlet?->name ?? '-' }}</td>
                    <td style="text-align:center;">
                        <span class="badge {{ $c->is_active ? 'badge-green' : '' }}"
                              style="{{ !$c->is_active ? 'background:var(--surface2);color:var(--text3);' : '' }}">
                            {{ $c->is_active ? 'Aktif' : 'Non-aktif' }}
                        </span>
                    </td>
                    <td>
                        <button @click="editing = !editing" class="edit-link">Edit</button>
                        <div x-show="editing" x-cloak x-transition>
                            <form method="POST" action="{{ route('master.customers.update', $c) }}" class="inline-edit">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $c->name }}"
                                       class="edit-input" style="width:100px;">
                                <select name="type" class="edit-select">
                                    <option value="regular"  {{ $c->type==='regular' ?'selected':'' }}>Regular</option>
                                    <option value="contract" {{ $c->type==='contract'?'selected':'' }}>Kontrak</option>
                                </select>
                                <select name="outlet_id" class="edit-select">
                                    <option value="">-</option>
                                    @foreach($outlets as $o)
                                    <option value="{{ $o->id }}" {{ $c->outlet_id==$o->id?'selected':'' }}>{{ $o->name }}</option>
                                    @endforeach
                                </select>
                                <label class="edit-check-label">
                                    <input type="checkbox" name="is_active" value="1" {{ $c->is_active ? 'checked' : '' }}>
                                    Aktif
                                </label>
                                <button type="submit" class="edit-save">Simpan</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     TAB: KURIR
══════════════════════════════════════════════════════════ --}}
<div x-show="tab === 'courier'" x-cloak>

    {{-- Add form --}}
    <div class="add-form">
        <div class="add-form-title">+ Tambah Kurir</div>
        <form method="POST" action="{{ route('master.couriers.store') }}">
            @csrf
            <div class="add-grid add-grid-3" style="margin-bottom:10px;max-width:480px;">
                <div>
                    <label class="field-label">Nama Kurir</label>
                    <input type="text" name="name" class="field-input" required>
                </div>
                <div>
                    <label class="field-label">Upah per Tabung (Rp)</label>
                    <input type="number" name="wage_per_unit" value="500" min="0" class="field-input">
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="padding:9px 12px;font-size:12px;">Simpan</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="s-card" style="max-width:480px;">
        <div class="scroll-x">
        <table class="mob-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th class="r">Upah/Tabung</th>
                    <th style="text-align:center;">Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($couriers as $c)
                <tr x-data="{ editing: false }">
                    <td class="bold">{{ $c->name }}</td>
                    <td class="r">Rp {{ number_format($c->wage_per_unit) }}</td>
                    <td style="text-align:center;">
                        <span class="badge {{ $c->is_active ? 'badge-green' : '' }}"
                              style="{{ !$c->is_active ? 'background:var(--surface2);color:var(--text3);' : '' }}">
                            {{ $c->is_active ? 'Aktif' : 'Non-aktif' }}
                        </span>
                    </td>
                    <td>
                        <button @click="editing = !editing" class="edit-link">Edit</button>
                        <div x-show="editing" x-cloak x-transition>
                            <form method="POST" action="{{ route('master.couriers.update', $c) }}" class="inline-edit">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $c->name }}"
                                       class="edit-input" style="width:100px;">
                                <input type="number" name="wage_per_unit" value="{{ $c->wage_per_unit }}"
                                       class="edit-input" style="width:70px;">
                                <label class="edit-check-label">
                                    <input type="checkbox" name="is_active" value="1" {{ $c->is_active ? 'checked' : '' }}>
                                    Aktif
                                </label>
                                <button type="submit" class="edit-save">Simpan</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>

</div>{{-- end x-data --}}
@endsection