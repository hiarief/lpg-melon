@extends('layouts.app')
@section('title','Slip Gaji Kurir')
@section('content')
<div class="mt-4">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h1 class="text-xl font-bold">🧾 Slip Gaji Kurir</h1>
        <form method="GET" action="{{ route('payslip.index') }}">
            <select name="period_id" onchange="this.form.submit()" class="border rounded px-2 py-1 text-sm">
                @foreach($periods as $p)
                    <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($couriers as $c)
        <div class="bg-white rounded-lg shadow p-5 flex flex-col gap-3">
            <div class="flex items-center gap-3">
                <div class="bg-orange-100 text-orange-600 rounded-full w-12 h-12 flex items-center justify-center text-xl font-bold">
                    {{ strtoupper(substr($c->name, 0, 1)) }}
                </div>
                <div>
                    <div class="font-bold text-gray-800">{{ $c->name }}</div>
                    <div class="text-xs text-gray-400">Kurir — Rp {{ number_format($c->wage_per_unit) }}/tabung</div>
                </div>
            </div>
            <div class="flex gap-2 mt-1">
                <a href="{{ route('payslip.show', [$c, 'period_id' => $period->id]) }}"
                   class="flex-1 bg-orange-600 text-white text-center py-2 rounded hover:bg-orange-700 text-sm font-medium">
                    📄 Lihat Slip
                </a>
                <a href="{{ route('payslip.show', [$c, 'period_id' => $period->id, 'print' => 1]) }}"
                   target="_blank"
                   class="flex-1 bg-gray-100 text-gray-700 text-center py-2 rounded hover:bg-gray-200 text-sm font-medium">
                    🖨 Cetak
                </a>
            </div>
        </div>
        @empty
        <div class="col-span-3 text-center py-12 text-gray-400">Belum ada kurir aktif.</div>
        @endforelse
    </div>
</div>
@endsection
