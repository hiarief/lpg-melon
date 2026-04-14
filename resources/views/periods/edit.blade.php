@extends('layouts.app')
@section('title','Edit Periode — ' . $period->label)
@section('content')

<div style="margin-top:4px;margin-bottom:12px">
    <a href="{{ route('periods.show', $period) }}" style="font-size:12px;color:var(--text3)">← Kembali ke {{ $period->label }}</a>
    <div style="font-size:16px;font-weight:600;color:var(--text1);margin-top:4px">✏️ Edit Periode — {{ $period->label }}</div>
</div>

<div class="s-card" style="padding:12px 14px;margin-bottom:12px;background:#fffbeb;border-color:#fde68a">
    <span style="font-size:12px;color:#92400e;line-height:1.5">
        ⚠️ Bulan dan tahun tidak bisa diubah karena sudah ada data yang terkait.
        Periode hanya bisa diedit selama masih <strong>berstatus Buka</strong>.
    </span>
</div>

@include('periods._form')

@endsection
