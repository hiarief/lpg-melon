@php $isPrint = request()->boolean('print'); @endphp

@if(!$isPrint)
@extends('layouts.app')
@section('title','Slip Gaji — '.$courier->name.' — '.$period->label)
@section('content')
<div class="mt-4 max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <a href="{{ route('payslip.index', ['period_id' => $period->id]) }}"
               class="text-gray-400 hover:text-gray-600 text-sm">← Slip Gaji</a>
        </div>
        <div class="flex gap-2">
            <form method="GET" action="{{ route('payslip.show', $courier) }}">
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <select name="period_id" onchange="this.form.submit()" class="border rounded px-2 py-1 text-sm">
                    @foreach($periods as $p)
                        <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('payslip.show', [$courier, 'period_id' => $period->id, 'print' => 1]) }}"
               target="_blank"
               class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800 text-sm">
                🖨 Cetak / Print
            </a>
        </div>
    </div>
    @include('payslip._slip')
</div>
@endsection

@else
{{-- MODE PRINT: tanpa layout, langsung auto-print --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji — {{ $courier->name }} — {{ $period->label }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .slip-wrapper { box-shadow: none !important; border: 1px solid #ccc; }
        }
        body { font-family: Arial, sans-serif; background: #f3f4f6; }
    </style>
</head>
<body class="p-6 bg-gray-100">
    <div class="max-w-2xl mx-auto">
        @include('payslip._slip')
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()"
                    class="bg-gray-800 text-white px-6 py-2 rounded hover:bg-gray-900 text-sm">
                🖨 Print Sekarang
            </button>
        </div>
    </div>
    <script>
        // Auto print setelah halaman load
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>
@endif
