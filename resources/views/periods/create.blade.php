@extends('layouts.app')
@section('title','Buat Periode Baru')
@section('content')

<div style="margin-top:4px;margin-bottom:12px">
    <a href="{{ route('periods.index') }}" style="font-size:12px;color:var(--text3)">← Daftar Periode</a>
    <div style="font-size:16px;font-weight:600;color:var(--text1);margin-top:4px">📅 Buat Periode Baru</div>
</div>

@include('periods._form')

@endsection
