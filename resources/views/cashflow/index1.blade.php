@extends('layouts.app')
@section('title','Cashflow Harian')
@section('content')
<div class="mt-4" x-data="{ showForm: false }">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <h1 class="text-xl font-bold">💸 Cashflow Harian</h1>
            <form method="GET" action="{{ route('cashflow.index') }}">
                <select name="period_id" onchange="this.form.submit()" class="border rounded px-2 py-1 text-sm">
                    @foreach($periods as $p)
                    <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        @if($period->status === 'open')
        <button @click="showForm = !showForm" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 text-sm">
            + Input Pengeluaran
        </button>
        @endif
    </div>

    {{-- Input form --}}
    @if($period->status === 'open')
    <div x-show="showForm" x-cloak x-transition class="bg-orange-50 border border-orange-200 rounded-lg p-5 mb-5">
        <h2 class="font-semibold text-orange-800 mb-3">+ Input Pengeluaran</h2>
        <form method="POST" action="{{ route('cashflow.store') }}" class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period->id }}">
            <div>
                <label class="text-xs font-semibold text-gray-600">Tanggal</label>
                <input type="date" name="expense_date" value="{{ date('Y-m-d') }}" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Kategori</label>
                <select name="category" class="w-full border rounded px-3 py-2 text-sm" required>
                    @foreach(\App\Models\DailyExpense::$categoryLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Nominal (Rp)</label>
                <input type="number" name="amount" min="1" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Keterangan</label>
                <input type="text" name="description" class="w-full border rounded px-3 py-2 text-sm" placeholder="Opsional">
            </div>
            <div class="col-span-2 md:col-span-4 flex justify-end">
                <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700 text-sm">Simpan</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Summary cards --}}
    @php
    $totalAllIncome = $openingCash + $totalIncome;
    $totalAllExpense = $totalExpense + $totalDeposits + $totalAdminFees;
    $netKas = $totalAllIncome - $totalAllExpense;
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="text-xs text-gray-500">Total Kas Diterima</div>
            <div class="text-lg font-bold text-green-700">Rp {{ number_format($totalAllIncome) }}</div>
            <div class="text-xs text-gray-400">Saldo awal + kas riil penjualan</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
            <div class="text-xs text-gray-500">Total Pengeluaran</div>
            <div class="text-lg font-bold text-red-700">Rp {{ number_format($totalExpense) }}</div>
            <div class="text-xs text-gray-400">Operasional harian</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="text-xs text-gray-500">TF ke Penampung + Admin</div>
            <div class="text-lg font-bold text-blue-700">Rp {{ number_format($totalDeposits + $totalAdminFees) }}</div>
            <div class="text-xs text-gray-400">
                TF: Rp {{ number_format($totalDeposits) }}
                @if($totalAdminFees > 0) | Admin: Rp {{ number_format($totalAdminFees) }} @endif
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
            <div class="text-xs text-gray-500">Saldo Kas Bersih</div>
            <div class="text-lg font-bold {{ $netKas >= 0 ? 'text-orange-700' : 'text-red-700' }}">
                Rp {{ number_format($netKas) }}
            </div>
            <div class="text-xs text-gray-400">Masuk - Keluar - TF - Admin</div>
        </div>
    </div>

    {{-- ============================================================
         Hitung variabel rata-rata di sini agar tersedia di semua row
         ============================================================ --}}
    @php
    $cfActiveDays = collect(range(1, $daysInMonth))->filter(fn($d) =>
    ($salesByDay[$d] ?? 0) > 0 || ($dayTotals[$d] ?? 0) > 0 ||
    ($depositsByDay[$d]['total'] ?? 0) > 0
    )->count();
    $cfActiveDays = max($cfActiveDays, 1);

    $totalNet = $openingCash + $totalIncome - $totalExpense - $totalDeposits - $totalAdminFees;
    $avgSales = round($totalIncome / $cfActiveDays);
    $avgExpense = round($totalExpense / $cfActiveDays);
    $avgDeposit = round($totalDeposits / $cfActiveDays);
    $avgAdminFee = round($totalAdminFees / $cfActiveDays);
    $avgNet = round($totalNet / $cfActiveDays);

    $avgCatByDay = [];
    foreach ($categories as $cat) {
    $avgCatByDay[$cat] = ($categoryTotals[$cat] ?? 0) > 0
    ? round($categoryTotals[$cat] / $cfActiveDays) : 0;
    }
    @endphp

    {{-- ============================================================
     ANALISA CASHFLOW: Chart Tren + Anomaly Detection
     ============================================================ --}}
    @php
    $salesJson = json_encode($salesByDay);
    $dayTotalsJson = json_encode($dayTotals);
    $depositsJson = json_encode(collect($depositsByDay)->map(fn($d) => ['total'=>$d['total'],'admin'=>$d['admin']]));
    $openingJson = json_encode($openingCash);
    $daysJson = json_encode($daysInMonth);
    $catLabelsJson = json_encode(array_values(\App\Models\DailyExpense::$categoryLabels));
    $catTotalsJson = json_encode(array_values($categoryTotals));
    @endphp

    <div class="mb-5 space-y-4" x-data>
        {{-- Trend Chart --}}
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b font-semibold bg-orange-50 text-sm flex items-center gap-2">
                📈 <span>Tren Harian — Kas Masuk vs Pengeluaran vs TF ke Penampung</span>
            </div>
            <div class="p-4">
                <div class="flex flex-wrap gap-4 mb-3 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm" style="background:#1D9E75"></span>Kas Diterima</span>
                    <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm" style="background:#E24B4A"></span>Pengeluaran Ops</span>
                    <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm" style="background:#378ADD"></span>TF ke Penampung</span>
                    <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full border-2" style="border-color:#BA7517"></span>Saldo Harian</span>
                </div>
                <div class="relative w-full" style="height:260px">
                    <canvas id="cfTrendChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Anomaly Detection Panel --}}
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b font-semibold bg-orange-50 text-sm flex items-center gap-2">
                🔍 <span>Indikator Anomali & Kesehatan Cashflow</span>
                <span id="cf-score-badge" class="ml-auto text-xs px-2 py-0.5 rounded font-medium"></span>
            </div>
            <div class="p-4">
                <div id="cf-anomaly-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3"></div>
            </div>
        </div>

        {{-- Donut Category Chart --}}
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b font-semibold bg-orange-50 text-sm">
                📊 Komposisi Pengeluaran per Kategori
            </div>
            <div class="p-4 flex flex-wrap gap-6 items-center">
                <div class="relative" style="width:160px;height:160px;overflow:visible">
                    <canvas id="cfDonutChart"></canvas>
                </div>
                <div id="cf-donut-legend" class="flex-1 min-w-32 flex flex-col gap-1.5 text-xs"></div>
            </div>
        </div>
    </div>


    {{-- Grid pengeluaran per kategori per hari --}}
    <div class="bg-white rounded-lg shadow mb-5">
        <div class="px-4 py-3 border-b font-semibold bg-orange-50">📊 Rekap Pengeluaran (UANG KELUAR)</div>
        <div class="table-scroll">
            <table class="text-xs w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-3 py-2 text-left sticky left-0 bg-gray-100 z-10 w-36">Kategori</th>
                        @for($d = 1; $d <= $daysInMonth; $d++) <th class="px-1 py-2 text-center w-9">{{ $d }}</th>
                            @endfor
                            <th class="px-3 py-2 text-right bg-orange-50">Total</th>
                            <th class="px-3 py-2 text-right bg-yellow-50 sticky right-0 z-10 whitespace-nowrap">Avg/Hari</th>
                    </tr>
                </thead>
                <tbody>

                    {{-- ── Saldo Awal ── --}}
                    @if($openingCash > 0)
                    <tr class="border-b bg-emerald-50">
                        <td class="px-3 py-2 font-bold sticky left-0 bg-emerald-50 z-10 text-emerald-800">
                            Saldo Awal
                            <div class="text-xs font-normal text-emerald-600">cutoff periode lalu</div>
                        </td>
                        @for($day = 1; $day <= $daysInMonth; $day++) <td class="px-0.5 py-2 text-center {{ $day === 1 ? 'text-emerald-700 font-semibold' : 'text-gray-200' }}" title="{{ $day === 1 ? 'Saldo awal: Rp '.number_format($openingCash) : '' }}">
                            {{ $day === 1 ? number_format($openingCash/1000).'k' : '-' }}
                            </td>
                            @endfor
                            <td class="px-3 py-2 text-right font-bold text-emerald-700">Rp {{ number_format($openingCash) }}</td>
                            <td class="px-3 py-2 text-right text-emerald-500 font-semibold sticky right-0 bg-emerald-50">-</td>
                    </tr>
                    @endif

                    {{-- ── Kategori pengeluaran ── --}}
                    @foreach($categories as $cat)
                    @php $catLabel = \App\Models\DailyExpense::$categoryLabels[$cat] ?? $cat; @endphp
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium sticky left-0 bg-white z-10 text-gray-700">{{ $catLabel }}</td>
                        @for($day = 1; $day <= $daysInMonth; $day++) @php $val=$expenseGrid[$cat][$day] ?? 0; @endphp <td class="px-0.5 py-2 text-center {{ $val > 0 ? 'text-red-700 font-semibold' : 'text-gray-200' }}">
                            {{ $val > 0 ? number_format($val/1000).'k' : '-' }}
                            </td>
                            @endfor
                            <td class="px-3 py-2 text-right font-bold {{ ($categoryTotals[$cat] ?? 0) > 0 ? 'text-red-700' : 'text-gray-300' }}">
                                {{ ($categoryTotals[$cat] ?? 0) > 0 ? 'Rp '.number_format($categoryTotals[$cat]) : '-' }}
                            </td>
                            <td class="px-3 py-2 text-right font-semibold sticky right-0 bg-white {{ $avgCatByDay[$cat] > 0 ? 'text-red-500' : 'text-gray-300' }}">
                                {{ $avgCatByDay[$cat] > 0 ? 'Rp '.number_format($avgCatByDay[$cat]) : '-' }}
                            </td>
                    </tr>
                    @endforeach

                    {{-- ── Total Pengeluaran Operasional ── --}}
                    <tr class="border-b bg-red-600 text-white font-bold">
                        <td class="px-3 py-2 sticky left-0 bg-red-600 z-10">Total Pengeluaran</td>
                        @for($day = 1; $day <= $daysInMonth; $day++) @php $val=$dayTotals[$day] ?? 0; @endphp <td class="px-0.5 py-2 text-center text-xs {{ $val > 0 ? 'text-red-100 font-semibold' : 'text-red-400' }}">
                            {{ $val > 0 ? number_format($val/1000).'k' : '-' }}
                            </td>
                            @endfor
                            <td class="px-3 py-2 text-right">Rp {{ number_format($totalExpense) }}</td>
                            <td class="px-3 py-2 text-right sticky right-0 bg-red-600 text-red-100">Rp {{ number_format($avgExpense) }}</td>
                    </tr>

                    {{-- ── TF ke Penampung ── --}}
                    <tr class="border-b bg-blue-50">
                        <td class="px-3 py-2 font-bold sticky left-0 bg-blue-50 z-10 text-blue-800">TF ke Penampung</td>
                        @for($day = 1; $day <= $daysInMonth; $day++) @php $dep=$depositsByDay[$day] ?? null; $val=$dep ? $dep['total'] : 0; @endphp <td class="px-0.5 py-2 text-center {{ $val > 0 ? 'text-blue-700 font-semibold' : 'text-gray-200' }}" title="{{ $val > 0 ? 'Nominal TF: Rp '.number_format($val) : '' }}">
                            {{ $val > 0 ? number_format($val/1000).'k' : '-' }}
                            </td>
                            @endfor
                            <td class="px-3 py-2 text-right font-bold text-blue-700">Rp {{ number_format($totalDeposits) }}</td>
                            <td class="px-3 py-2 text-right font-bold text-blue-700 sticky right-0 bg-blue-50">Rp {{ number_format($avgDeposit) }}</td>
                    </tr>

                    {{-- ── Biaya Admin TF ── --}}
                    <tr class="border-b bg-blue-50">
                        <td class="px-3 py-2 sticky left-0 bg-blue-50 z-10 text-blue-500 text-xs pl-6">
                            └ Admin TF Penampung
                        </td>
                        @for($day = 1; $day <= $daysInMonth; $day++) @php $dep=$depositsByDay[$day] ?? null; $val=$dep ? $dep['admin'] : 0; @endphp <td class="px-0.5 py-2 text-center {{ $val > 0 ? 'text-blue-500 font-semibold' : 'text-gray-200' }}" title="{{ $val > 0 ? 'Admin: Rp '.number_format($val) : '' }}">
                            {{ $val > 0 ? number_format($val/1000).'k' : '-' }}
                            </td>
                            @endfor
                            <td class="px-3 py-2 text-right font-semibold {{ $totalAdminFees > 0 ? 'text-blue-500' : 'text-gray-300' }}">
                                {{ $totalAdminFees > 0 ? 'Rp '.number_format($totalAdminFees) : '-' }}
                            </td>
                            <td class="px-3 py-2 text-right font-semibold sticky right-0 bg-blue-50 {{ $avgAdminFee > 0 ? 'text-blue-500' : 'text-gray-300' }}">
                                {{ $avgAdminFee > 0 ? 'Rp '.number_format($avgAdminFee) : '-' }}
                            </td>
                    </tr>

                    {{-- ── Kas Riil Diterima dari Penjualan ── --}}
                    <tr class="border-b bg-green-50">
                        <td class="px-3 py-2 font-bold sticky left-0 bg-green-50 z-10 text-green-800">
                            Kas Diterima
                            <div class="text-xs font-normal text-green-600">penjualan gas riil</div>
                        </td>
                        @for($day = 1; $day <= $daysInMonth; $day++) @php $val=$salesByDay[$day] ?? 0; @endphp <td class="px-0.5 py-2 text-center {{ $val > 0 ? 'text-green-700 font-semibold' : 'text-gray-200' }}">
                            {{ $val > 0 ? number_format($val/1000).'k' : '-' }}
                            </td>
                            @endfor
                            <td class="px-3 py-2 text-right font-bold text-green-700">Rp {{ number_format($totalIncome) }}</td>
                            <td class="px-3 py-2 text-right font-bold text-green-700 sticky right-0 bg-green-50">Rp {{ number_format($avgSales) }}</td>
                    </tr>

                    {{-- ── Saldo Harian (running balance) ── --}}
                    <tr class="border-b bg-indigo-50">
                        <td class="px-3 py-2 font-bold sticky left-0 bg-indigo-50 z-10 text-indigo-800">
                            Saldo Harian
                            <div class="text-xs font-normal text-indigo-500">akumulasi s/d tgl ini</div>
                        </td>
                        @for($day = 1; $day <= $daysInMonth; $day++) @php $bal=$dailyBalance[$day]; $prevBal=$day> 1 ? $dailyBalance[$day - 1] : $openingCash;
                            $hasAny = ($salesByDay[$day] ?? 0) > 0
                            || ($dayTotals[$day] ?? 0) > 0
                            || ($depositsByDay[$day]['total'] ?? 0) > 0
                            || ($day === 1 && $openingCash > 0);
                            $showBal = $hasAny || $bal !== $prevBal;
                            @endphp
                            <td class="px-0.5 py-2 text-center text-xs
                                {{ !$showBal ? 'text-gray-200' : ($bal > 0 ? 'text-indigo-700 font-semibold' : ($bal < 0 ? 'text-red-600 font-bold' : 'text-gray-400')) }}" title="{{ $showBal ? 'Saldo s/d tgl '.$day.': Rp '.number_format($bal) : '' }}">
                                {{ $showBal ? number_format($bal/1000).'k' : '-' }}
                            </td>
                            @endfor
                            @php $finalBalance = $dailyBalance[$daysInMonth]; @endphp
                            <td class="px-3 py-2 text-right font-bold {{ $finalBalance >= 0 ? 'text-indigo-700' : 'text-red-700' }}">
                                Rp {{ number_format($finalBalance) }}
                            </td>
                            {{-- Saldo harian = akumulasi, avg tidak relevan --}}
                            <td class="px-3 py-2 text-right text-indigo-300 sticky right-0 bg-indigo-50">-</td>
                    </tr>

                    {{-- ── Net Harian ── --}}
                    <tr class="bg-gray-700 text-white font-bold">
                        <td class="px-3 py-2 sticky left-0 bg-gray-700 z-10">
                            Net Harian
                            <div class="text-xs font-normal text-gray-400">selisih hari ini saja</div>
                        </td>
                        @for($day = 1; $day <= $daysInMonth; $day++) @php $income=($salesByDay[$day] ?? 0) + ($day===1 ? $openingCash : 0); $expense=$dayTotals[$day] ?? 0; $deposit=($depositsByDay[$day]['total'] ?? 0) + ($depositsByDay[$day]['admin'] ?? 0); $net=$income - $expense - $deposit; $hasData=($salesByDay[$day] ?? 0)> 0 || ($dayTotals[$day] ?? 0) > 0
                            || ($depositsByDay[$day]['total'] ?? 0) > 0
                            || ($day === 1 && $openingCash > 0);
                            @endphp
                            <td class="px-0.5 py-2 text-center text-xs {{ $net > 0 ? 'text-green-300' : ($net < 0 ? 'text-red-300' : 'text-gray-400') }}">
                                {{ $hasData ? number_format($net/1000).'k' : '-' }}
                            </td>
                            @endfor
                            <td class="px-3 py-2 text-right {{ $totalNet >= 0 ? 'text-green-300' : 'text-red-300' }}">
                                Rp {{ number_format($totalNet) }}
                            </td>
                            <td class="px-3 py-2 text-right sticky right-0 bg-gray-700 {{ $avgNet >= 0 ? 'text-green-300' : 'text-red-300' }}">
                                Rp {{ number_format($avgNet) }}
                            </td>
                    </tr>

                    {{-- ── Rata-rata/Hari (header row) ── --}}
                    <tr class="border-t-2 border-gray-400 bg-slate-100 text-xs">
                        <td class="px-3 py-2 sticky left-0 bg-slate-100 z-10 font-semibold text-slate-600">
                            Rata-rata/Hari
                            <div class="font-normal text-slate-400">{{ $cfActiveDays }} hari aktif</div>
                        </td>
                        @for($day = 1; $day <= $daysInMonth; $day++) <td class="px-0.5 py-2 text-center text-slate-300">-</td>
                            @endfor
                            <td class="px-3 py-2 text-right font-semibold text-slate-500">
                                Rp {{ number_format($avgNet) }}
                            </td>
                            <td class="px-3 py-2 text-right font-semibold text-slate-500 sticky right-0 bg-slate-100">
                                Rp {{ number_format($avgNet) }}
                            </td>
                    </tr>

                    {{-- ── Avg Pengeluaran ── --}}
                    <tr class="bg-slate-50 text-xs border-b">
                        <td class="px-3 py-2 sticky left-0 bg-slate-50 z-10 text-slate-500 pl-5">
                            └ Avg Pengeluaran
                        </td>
                        @for($day = 1; $day <= $daysInMonth; $day++) <td class="px-0.5 py-2 text-center text-slate-200">-</td>
                            @endfor
                            <td class="px-3 py-2 text-right text-red-500 font-semibold">
                                Rp {{ number_format($avgExpense) }}
                            </td>
                            <td class="px-3 py-2 text-right text-red-500 font-semibold sticky right-0 bg-slate-50">
                                Rp {{ number_format($avgExpense) }}
                            </td>
                    </tr>

                    {{-- ── Avg Kas Diterima ── --}}
                    <tr class="bg-slate-50 text-xs border-b">
                        <td class="px-3 py-2 sticky left-0 bg-slate-50 z-10 text-slate-500 pl-5">
                            └ Avg Kas Diterima
                        </td>
                        @for($day = 1; $day <= $daysInMonth; $day++) <td class="px-0.5 py-2 text-center text-slate-200">-</td>
                            @endfor
                            <td class="px-3 py-2 text-right text-green-600 font-semibold">
                                Rp {{ number_format($avgSales) }}
                            </td>
                            <td class="px-3 py-2 text-right text-green-600 font-semibold sticky right-0 bg-slate-50">
                                Rp {{ number_format($avgSales) }}
                            </td>
                    </tr>

                    {{-- ── Avg TF ke Penampung ── --}}
                    <tr class="bg-slate-50 text-xs border-b">
                        <td class="px-3 py-2 sticky left-0 bg-slate-50 z-10 text-slate-500 pl-5">
                            └ Avg TF ke Penampung
                        </td>
                        @for($day = 1; $day <= $daysInMonth; $day++) <td class="px-0.5 py-2 text-center text-slate-200">-</td>
                            @endfor
                            <td class="px-3 py-2 text-right text-blue-600 font-semibold">
                                Rp {{ number_format($avgDeposit) }}
                            </td>
                            <td class="px-3 py-2 text-right text-blue-600 font-semibold sticky right-0 bg-slate-50">
                                Rp {{ number_format($avgDeposit) }}
                            </td>
                    </tr>

                </tbody>
            </table>
        </div>
        <p class="px-4 py-2 text-xs text-gray-400">
            Nilai dalam ribuan (k). <span class="text-green-600 font-semibold">Kas Diterima</span> = uang riil masuk ke tangan (paid_amount),
            bukan nilai tagihan — distribusi yang ditunda/sebagian tidak dihitung sampai dibayar.
            <span class="text-yellow-600 font-semibold">Avg/Hari</span> = rata-rata per hari aktif ({{ $cfActiveDays }} hari).
        </p>
    </div>

    {{-- Detail list --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b font-semibold bg-orange-50">📋 Detail Pengeluaran</div>
        <div class="table-scroll">
            <table class="w-full text-xs">
                <thead class="bg-gray-100 text-gray-500 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left">Tanggal</th>
                        <th class="px-3 py-2 text-left">Kategori</th>
                        <th class="px-3 py-2 text-left">Keterangan</th>
                        <th class="px-3 py-2 text-right">Nominal (Rp)</th>
                        <th class="px-3 py-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $exp)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2">{{ $exp->expense_date->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            <span class="bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded text-xs">
                                {{ $exp->categoryLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-gray-500">{{ $exp->description ?: '-' }}</td>
                        <td class="px-3 py-2 text-right font-semibold text-red-700">Rp {{ number_format($exp->amount) }}</td>
                        <td class="px-3 py-2">
                            @if($period->status === 'open')
                            <form method="POST" action="{{ route('cashflow.destroy', $exp) }}" class="inline" onsubmit="return confirm('Hapus?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:underline text-xs">Hapus</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada pengeluaran.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php
$catLabelsSafe = array_values(\App\Models\DailyExpense::$categoryLabels);
$catTotalsSafe = array_values($categoryTotals);
@endphp

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function() {

        const salesByDay = @json($salesByDay);
        const dayTotals = @json($dayTotals);
        const depositsByDay = @json($depositsByDay);
        const openingCash = @json($openingCash);
        const daysInMonth = @json($daysInMonth);
        const catLabels = @json($catLabelsSafe);
        const catTotals = @json($catTotalsSafe);
        console.log(catLabels, catTotals);


        const labels = Array.from({
            length: daysInMonth
        }, (_, i) => i + 1);

        const fmt = v => 'Rp ' + Math.round(v / 1000).toLocaleString('id-ID') + 'k';

        const isDark = matchMedia('(prefers-color-scheme: dark)').matches;
        const gridColor = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
        const tickColor = isDark ? '#9c9a92' : '#5F5E5A';

        // ===============================
        // DATA ARRAYS
        // ===============================
        const incomeArr = labels.map(d => (salesByDay[d] ?? 0) / 1000);
        const expArr = labels.map(d => (dayTotals[d] ?? 0) / 1000);
        const depArr = labels.map(d => (depositsByDay[d]?.total ?? 0) / 1000);

        let bal = openingCash;
        const balArr = labels.map(d => {
            bal += (salesByDay[d] ?? 0) -
                (dayTotals[d] ?? 0) -
                (depositsByDay[d]?.total ?? 0) -
                (depositsByDay[d]?.admin ?? 0);
            return Math.round(bal / 1000);
        });

        // ===============================
        // TREND CHART
        // ===============================
        new Chart(document.getElementById('cfTrendChart'), {
            type: 'bar'
            , data: {
                labels
                , datasets: [{
                        label: 'Kas Diterima'
                        , data: incomeArr
                        , backgroundColor: '#1D9E75CC'
                        , order: 2
                    }
                    , {
                        label: 'Pengeluaran'
                        , data: expArr
                        , backgroundColor: '#E24B4ACC'
                        , order: 2
                    }
                    , {
                        label: 'TF Penampung'
                        , data: depArr
                        , backgroundColor: '#378ADDCC'
                        , order: 2
                    }
                    , {
                        label: 'Saldo Harian'
                        , data: balArr
                        , type: 'line'
                        , order: 1
                        , borderColor: '#BA7517'
                        , borderWidth: 2
                        , pointRadius: 2
                        , tension: 0.3
                        , yAxisID: 'y2'
                    }
                ]
            }
            , options: {
                responsive: true
                , maintainAspectRatio: false
                , plugins: {
                    legend: {
                        display: false
                    }
                    , tooltip: {
                        callbacks: {
                            label: ctx => `${ctx.dataset.label}: ${fmt(ctx.parsed.y * 1000)}`
                        }
                    }
                }
                , scales: {
                    x: {
                        grid: {
                            color: gridColor
                        }
                        , ticks: {
                            color: tickColor
                            , font: {
                                size: 10
                            }
                            , autoSkip: true
                            , maxTicksLimit: 15
                        }
                    }
                    , y: {
                        grid: {
                            color: gridColor
                        }
                        , ticks: {
                            color: tickColor
                            , callback: v => v + 'k'
                        }
                    }
                    , y2: {
                        position: 'right'
                        , grid: {
                            display: false
                        }
                        , ticks: {
                            color: '#BA7517'
                            , callback: v => v + 'k'
                        }
                    }
                }
            }
        });

        // ===============================
        // ANOMALY ENGINE
        // ===============================
        const activeDays = labels.filter(d =>
            (salesByDay[d] ?? 0) > 0 ||
            (dayTotals[d] ?? 0) > 0 ||
            (depositsByDay[d]?.total ?? 0) > 0
        ).length || 1;

        const totalIncome = Object.values(salesByDay).reduce((a, b) => a + b, 0);
        const totalExp = Object.values(dayTotals).reduce((a, b) => a + b, 0);
        const totalDep = Object.values(depositsByDay).reduce((a, b) => a + (b.total || 0), 0);

        const avgExp = totalExp / activeDays;
        const avgIncome = totalIncome / activeDays;

        const spikeDays = labels.filter(d => (dayTotals[d] ?? 0) > avgExp * 2);

        const anomalies = [];

        if (spikeDays.length) {
            anomalies.push({
                sev: 'danger'
                , badge: 'ANOMALI'
                , title: `Lonjakan pengeluaran`
                , body: `Tanggal: ${spikeDays.join(', ')}`
            });
        }

        if (!anomalies.length) {
            anomalies.push({
                sev: 'ok'
                , badge: 'SEHAT'
                , title: 'Cashflow normal'
                , body: 'Tidak ada anomali'
            });
        }

        const grid = document.getElementById('cf-anomaly-grid');

        anomalies.forEach(a => {
            grid.insertAdjacentHTML('beforeend', `
            <div class="p-3 rounded ${a.sev === 'danger' ? 'bg-red-50' : 'bg-green-50'}">
                <div class="font-semibold">${a.title}</div>
                <div class="text-xs">${a.body}</div>
            </div>
        `);
        });

        // ===============================
        // DONUT CHART
        // ===============================
        new Chart(document.getElementById('cfDonutChart'), {
            type: 'doughnut'
            , data: {
                labels: catLabels
                , datasets: [{
                    data: catTotals
                }]
            }
            , options: {
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

    })();

</script>

@endpush
