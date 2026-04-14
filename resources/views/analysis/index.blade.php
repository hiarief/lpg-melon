@extends('layouts.app')
@section('title','Analisa Bisnis')
@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

    .analysis-page {
        font-family: 'DM Sans', sans-serif;
    }

    .analysis-page h1,
    h2,
    h3 {
        font-family: 'Syne', sans-serif;
    }

    .score-ring {
        transition: stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-glass {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.6);
    }

    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 6px;
    }

    @keyframes shimmer {
        0% {
            background-position: 200% 0
        }

        100% {
            background-position: -200% 0
        }
    }

    .slide-up {
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.5s ease;
    }

    .slide-up.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .urgency-tinggi {
        background: #FEE2E2;
        color: #991B1B;
        border-left: 3px solid #DC2626;
    }

    .urgency-sedang {
        background: #FEF3C7;
        color: #92400E;
        border-left: 3px solid #F59E0B;
    }

    .urgency-rendah {
        background: #ECFDF5;
        color: #065F46;
        border-left: 3px solid #10B981;
    }

    .pill-good {
        background: #D1FAE5;
        color: #065F46;
    }

    .pill-warning {
        background: #FEF3C7;
        color: #92400E;
    }

    .pill-bad {
        background: #FEE2E2;
        color: #991B1B;
    }

    .pill-info {
        background: #DBEAFE;
        color: #1E40AF;
    }

    .diff-bar {
        height: 6px;
        border-radius: 3px;
        background: #E5E7EB;
        overflow: hidden;
    }

    .diff-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 1s ease;
    }

    .strategy-card {
        background: linear-gradient(135deg, #1e3a5f 0%, #0f2240 100%);
        color: white;
        border-radius: 16px;
        padding: 24px;
    }

    .tab-btn {
        transition: all 0.2s;
    }

    .tab-btn.active {
        background: #1e3a5f;
        color: white;
        border-color: #1e3a5f;
    }

</style>
<div class="analysis-page mt-4" x-data="analysisApp({{ $period->id }})">


    {{-- ── HEADER ─────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900" style="font-family:'Syne',sans-serif;">
                🔬 Analisa Bisnis
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">Deep analysis & actionable insights dari data operasional</p>
        </div>
        <div class="flex items-center gap-2">
            <form method="GET" action="{{ route('analysis.index') }}">
                <select name="period_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm">
                    @foreach($periods as $p)
                    <option value="{{ $p->id }}" {{ $p->id == $period->id ? 'selected' : '' }}>{{ $p->label }}</option>
                    @endforeach
                </select>
            </form>
            <button @click="loadAnalysis(true)" :disabled="loading" class="bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-900 text-sm font-medium flex items-center gap-2 disabled:opacity-50">
                <span x-text="loading ? '⏳ Menganalisa...' : '🤖 Generate AI Analysis'"></span>
            </button>
        </div>
    </div>

    {{-- ── METRICS SNAPSHOT (selalu tampil dari PHP) ──────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @php
        $m = $metrics;
        $snapCards = [
        ['Gross Margin', 'Rp '.number_format($m['gross_margin']), $m['margin_pct'].'% dari bruto', $m['gross_margin']>=0?'border-green-400':'border-red-400', $m['gross_margin']>=0?'text-green-700':'text-red-700'],
        ['Kas Riil Diterima', 'Rp '.number_format($m['total_kas_riil']), 'dari bruto Rp '.number_format($m['total_bruto']), 'border-blue-400', 'text-blue-700'],
        ['Net Cashflow', 'Rp '.number_format($m['net_cashflow']), 'saldo kas akhir', $m['net_cashflow']>=0?'border-green-400':'border-red-400', $m['net_cashflow']>=0?'text-green-700':'text-red-700'],
        ['Piutang Customer', 'Rp '.number_format($m['piutang_customer']), 'belum dibayar', $m['piutang_customer']>0?'border-orange-400':'border-green-400', $m['piutang_customer']>0?'text-orange-700':'text-green-700'],
        ];
        @endphp
        @foreach($snapCards as $sc)
        <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 {{ $sc[3] }}">
            <div class="text-xs text-gray-400 mb-1">{{ $sc[0] }}</div>
            <div class="text-lg font-bold {{ $sc[4] }}">{{ $sc[1] }}</div>
            <div class="text-xs text-gray-400 mt-0.5">{{ $sc[2] }}</div>
        </div>
        @endforeach
    </div>

    {{-- ── QUICK STATS ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-3 md:grid-cols-6 gap-2 mb-6">
        @php
        $stats = [
        ['Hari Aktif', $m['hari_distribusi'].'/'.$m['hari_dalam_bulan']],
        ['Tabung/Hari', $m['tabung_per_hari']],
        ['Harga Jual Rata', 'Rp '.$m['harga_jual_rata']],
        ['Margin/Tab', 'Rp '.$m['margin_per_tabung']],
        ['Efisiensi DO', $m['do_efficiency_pct'].'%'],
        ['Stok Sisa', $m['stok_sisa'].' tab'],
        ];
        @endphp
        @foreach($stats as $st)
        <div class="bg-slate-50 rounded-lg p-3 text-center">
            <div class="text-xs text-gray-400">{{ $st[0] }}</div>
            <div class="text-sm font-bold text-slate-700 mt-0.5">{{ $st[1] }}</div>
        </div>
        @endforeach
    </div>

    {{-- ── AI ANALYSIS AREA ───────────────────────────────────────────── --}}
    <div x-show="!loaded && !loading" class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-10 text-center text-white mb-6">
        <div class="text-5xl mb-4">🤖</div>
        <h2 class="text-xl font-bold mb-2" style="font-family:'Syne',sans-serif;">AI Business Analyst</h2>
        <p class="text-slate-400 text-sm mb-6 max-w-md mx-auto">
            Klik tombol di atas untuk generate analisa mendalam dari data {{ $period->label }}.
            AI akan menganalisa pemborosan, potensi surplus, dan strategi terbaik.
        </p>
        <button @click="loadAnalysis(false)" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-xl font-semibold text-sm transition">
            🚀 Mulai Analisa Sekarang
        </button>
    </div>

    {{-- Loading state --}}
    <div x-show="loading" x-cloak class="space-y-4 mb-6">
        <div class="bg-white rounded-2xl p-6 animate-pulse">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-20 h-20 rounded-full skeleton"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-5 skeleton w-1/3"></div>
                    <div class="h-4 skeleton w-2/3"></div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="h-20 skeleton"></div>
                <div class="h-20 skeleton"></div>
                <div class="h-20 skeleton"></div>
            </div>
        </div>
        <div class="text-center text-sm text-gray-400 animate-pulse">
            ⏳ AI sedang menganalisa {{ $period->label }}... biasanya 5-10 detik
        </div>
    </div>

    {{-- ── HASIL ANALISA ──────────────────────────────────────────────── --}}
    <div x-show="loaded" x-cloak class="space-y-6">

        {{-- Skor Kesehatan + KPI --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Score --}}
            <div class="bg-white rounded-2xl shadow-sm p-6 flex flex-col items-center justify-center">
                <div class="relative mb-3">
                    <svg width="120" height="120" class="-rotate-90">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="#E5E7EB" stroke-width="10" />
                        <circle cx="60" cy="60" r="52" fill="none" :stroke="scoreColor(analysis.skor_kesehatan?.warna)" stroke-width="10" stroke-linecap="round" :stroke-dasharray="326.7" :stroke-dashoffset="326.7 - (326.7 * (analysis.skor_kesehatan?.nilai || 0) / 100)" class="score-ring" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center rotate-0">
                        <span class="text-3xl font-black" :style="`color: ${scoreColor(analysis.skor_kesehatan?.warna)}`" x-text="analysis.skor_kesehatan?.nilai || 0"></span>
                        <span class="text-xs text-gray-400">/ 100</span>
                    </div>
                </div>
                <div class="font-bold text-gray-800 text-center" x-text="analysis.skor_kesehatan?.label"></div>
                <div class="text-xs text-gray-400 text-center mt-1" x-text="analysis.skor_kesehatan?.ringkasan"></div>
            </div>

            {{-- KPI --}}
            <div class="md:col-span-2 bg-white rounded-2xl shadow-sm p-6">
                <h3 class="font-bold text-gray-700 mb-4 text-sm uppercase tracking-wide">📊 KPI Utama</h3>
                <div class="space-y-3">
                    <template x-for="kpi in analysis.kpi_utama" :key="kpi.nama">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-700" x-text="kpi.nama"></span>
                                    <span class="text-xs px-2 py-0.5 rounded-full" :class="'pill-'+kpi.status" x-text="kpi.catatan"></span>
                                </div>
                            </div>
                            <div class="text-right ml-4">
                                <div class="font-bold text-gray-900 text-sm" x-text="kpi.nilai"></div>
                                <div class="text-xs text-gray-400" x-text="'Target: '+kpi.target"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- TABS --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="flex gap-1 p-3 bg-gray-50 border-b overflow-x-auto">
                @php
                $tabs = [
                ['id'=>'masalah', 'label'=>'⚠️ Masalah Utama'],
                ['id'=>'pemborosan','label'=>'🔥 Pemborosan'],
                ['id'=>'surplus', 'label'=>'📈 Naikkan Surplus'],
                ['id'=>'revenue', 'label'=>'💰 Naikkan Revenue'],
                ['id'=>'cost', 'label'=>'✂️ Kurangi Cost'],
                ['id'=>'struktur', 'label'=>'🏗 Struktur'],
                ['id'=>'prediksi', 'label'=>'🔮 Prediksi'],
                ];
                @endphp
                @foreach($tabs as $tab)
                <button @click="activeTab = '{{ $tab['id'] }}'" :class="activeTab === '{{ $tab['id'] }}' ? 'active' : 'hover:bg-gray-200 text-gray-600'" class="tab-btn px-3 py-1.5 rounded-lg text-xs font-medium border border-transparent whitespace-nowrap">
                    {{ $tab['label'] }}
                </button>
                @endforeach
            </div>

            <div class="p-6">

                {{-- TAB: MASALAH UTAMA --}}
                <div x-show="activeTab === 'masalah'">
                    <h3 class="font-bold text-gray-800 mb-4" style="font-family:'Syne',sans-serif;">⚠️ Breakdown Masalah Utama</h3>
                    <template x-if="!analysis.masalah_utama?.length">
                        <p class="text-gray-400 text-sm">Tidak ada masalah kritis terdeteksi.</p>
                    </template>
                    <div class="space-y-3">
                        <template x-for="m in analysis.masalah_utama" :key="m.prioritas">
                            <div class="rounded-xl p-4" :class="'urgency-'+m.urgensi">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg font-black opacity-40" x-text="'#'+m.prioritas"></span>
                                        <span class="font-bold text-sm" x-text="m.judul"></span>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-black bg-opacity-10" x-text="m.urgensi.toUpperCase()"></span>
                                    </div>
                                </div>
                                <div class="mt-2 text-sm opacity-80" x-text="m.akar_masalah"></div>
                                <div class="mt-2 text-xs font-semibold opacity-60" x-text="'💸 Dampak: '+m.dampak"></div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- TAB: PEMBOROSAN --}}
                <div x-show="activeTab === 'pemborosan'" x-cloak>
                    <h3 class="font-bold text-gray-800 mb-4" style="font-family:'Syne',sans-serif;">🔥 Analisa Pemborosan</h3>
                    <template x-if="!analysis.pemborosan?.length">
                        <p class="text-gray-400 text-sm">Tidak ada pemborosan signifikan terdeteksi.</p>
                    </template>
                    <div class="space-y-4">
                        <template x-for="p in analysis.pemborosan" :key="p.kategori">
                            <div class="border border-gray-100 rounded-xl p-4 hover:border-orange-200 transition">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <div class="font-semibold text-gray-800" x-text="p.kategori"></div>
                                        <div class="text-xs text-gray-400 mt-0.5" x-text="p.persen_dari_revenue+' dari revenue'"></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-red-600" x-text="p.selisih+' boros'"></div>
                                    </div>
                                </div>
                                <div class="flex gap-4 text-xs mb-3">
                                    <div><span class="text-gray-400">Aktual: </span><span class="font-semibold text-red-600" x-text="p.nilai_aktual"></span></div>
                                    <div><span class="text-gray-400">Ideal: </span><span class="font-semibold text-green-600" x-text="p.nilai_ideal"></span></div>
                                </div>
                                <div class="bg-orange-50 rounded-lg p-3 text-xs text-orange-800" x-text="'💡 '+p.rekomendasi"></div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- TAB: NAIKKAN SURPLUS --}}
                <div x-show="activeTab === 'surplus'" x-cloak>
                    <h3 class="font-bold text-gray-800 mb-4" style="font-family:'Syne',sans-serif;">📈 Cara Menaikan Surplus</h3>
                    <div class="space-y-4">
                        <template x-for="s in analysis.cara_naikkan_surplus" :key="s.strategi">
                            <div class="border border-gray-100 rounded-xl p-4 hover:shadow-sm transition">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="font-semibold text-gray-800" x-text="s.strategi"></div>
                                    <div class="flex gap-1 shrink-0">
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium" x-text="s.potensi_tambahan"></span>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600 mb-3" x-text="s.cara"></div>
                                <div class="flex gap-2 text-xs">
                                    <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-600" x-text="'⏱ '+s.timeframe"></span>
                                    <span class="px-2 py-0.5 rounded text-white" :class="s.kesulitan==='mudah'?'bg-green-500':s.kesulitan==='sedang'?'bg-yellow-500':'bg-red-500'" x-text="s.kesulitan"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- TAB: NAIKKAN REVENUE --}}
                <div x-show="activeTab === 'revenue'" x-cloak>
                    <h3 class="font-bold text-gray-800 mb-4" style="font-family:'Syne',sans-serif;">💰 Cara Menaikan Revenue</h3>
                    <div class="space-y-4">
                        <template x-for="r in analysis.cara_naikkan_revenue" :key="r.aksi">
                            <div class="border-l-4 border-blue-400 pl-4 py-1">
                                <div class="flex justify-between items-start">
                                    <div class="font-semibold text-gray-800" x-text="r.aksi"></div>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium shrink-0 ml-2" x-text="r.potensi"></span>
                                </div>
                                <div class="text-sm text-gray-600 mt-1" x-text="r.detail"></div>
                                <div class="text-xs text-gray-400 mt-1.5" x-text="'📋 Syarat: '+r.syarat"></div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- TAB: KURANGI COST --}}
                <div x-show="activeTab === 'cost'" x-cloak>
                    <h3 class="font-bold text-gray-800 mb-4" style="font-family:'Syne',sans-serif;">✂️ Cost yang Bisa Dikurangi</h3>
                    <div class="space-y-3">
                        <template x-for="c in analysis.cost_yang_bisa_dikurangi" :key="c.pos">
                            <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="font-semibold text-gray-800" x-text="c.pos"></div>
                                    <span class="text-xs font-bold text-green-700 bg-green-100 px-2 py-0.5 rounded-full" x-text="'Hemat '+c.potensi_hemat"></span>
                                </div>
                                <div class="flex gap-4 text-xs mb-2">
                                    <span><span class="text-gray-400">Sekarang: </span><span class="font-semibold text-red-600" x-text="c.nilai_sekarang"></span></span>
                                    <span><span class="text-gray-400">Target: </span><span class="font-semibold text-green-700" x-text="c.target_ideal"></span></span>
                                </div>
                                <div class="text-sm text-gray-700" x-text="c.cara"></div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- TAB: STRUKTUR --}}
                <div x-show="activeTab === 'struktur'" x-cloak>
                    <h3 class="font-bold text-gray-800 mb-4" style="font-family:'Syne',sans-serif;">🏗 Struktur Keuangan Penting</h3>
                    <div x-show="analysis.struktur_penting">
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="text-center p-4 rounded-xl border" :class="healthClass(analysis.struktur_penting?.cash_flow_health)">
                                <div class="text-2xl mb-1">💵</div>
                                <div class="text-xs font-semibold uppercase tracking-wide">Cash Flow</div>
                                <div class="text-sm font-bold mt-1 capitalize" x-text="analysis.struktur_penting?.cash_flow_health"></div>
                            </div>
                            <div class="text-center p-4 rounded-xl border" :class="healthClass(analysis.struktur_penting?.piutang_risk)">
                                <div class="text-2xl mb-1">📋</div>
                                <div class="text-xs font-semibold uppercase tracking-wide">Risiko Piutang</div>
                                <div class="text-sm font-bold mt-1 capitalize" x-text="analysis.struktur_penting?.piutang_risk"></div>
                            </div>
                            <div class="text-center p-4 rounded-xl border" :class="healthClass(analysis.struktur_penting?.do_risk)">
                                <div class="text-2xl mb-1">📦</div>
                                <div class="text-xs font-semibold uppercase tracking-wide">Risiko DO</div>
                                <div class="text-sm font-bold mt-1 capitalize" x-text="analysis.struktur_penting?.do_risk"></div>
                            </div>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 text-sm text-gray-700 leading-relaxed" x-text="analysis.struktur_penting?.analisa"></div>
                    </div>

                    {{-- Strategi Paling Efektif --}}
                    <div x-show="analysis.strategi_paling_efektif?.judul !== '-'" class="mt-6">
                        <h3 class="font-bold text-gray-800 mb-3" style="font-family:'Syne',sans-serif;">🎯 Strategi Paling Efektif</h3>
                        <div class="strategy-card">
                            <div class="flex justify-between items-start mb-3">
                                <div class="text-lg font-bold" x-text="analysis.strategi_paling_efektif?.judul"></div>
                                <span class="bg-green-400 text-green-900 text-xs font-bold px-3 py-1 rounded-full" x-text="analysis.strategi_paling_efektif?.estimasi_dampak"></span>
                            </div>
                            <p class="text-slate-300 text-sm mb-4" x-text="analysis.strategi_paling_efektif?.kenapa_ini"></p>
                            <div class="space-y-2">
                                <div class="text-xs uppercase tracking-wide text-slate-400 mb-2">Langkah Eksekusi</div>
                                <template x-for="(step, i) in analysis.strategi_paling_efektif?.langkah || []" :key="i">
                                    <div class="flex gap-3 items-start">
                                        <span class="w-5 h-5 rounded-full bg-orange-400 text-white text-xs flex items-center justify-center shrink-0 mt-0.5" x-text="i+1"></span>
                                        <span class="text-sm text-slate-200" x-text="step"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- TAB: PREDIKSI --}}
                <div x-show="activeTab === 'prediksi'" x-cloak>
                    <h3 class="font-bold text-gray-800 mb-4" style="font-family:'Syne',sans-serif;">🔮 Prediksi Bulan Depan</h3>
                    <div x-show="analysis.prediksi_bulan_depan" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-blue-50 rounded-xl p-5 border border-blue-100">
                                <div class="text-xs text-blue-500 uppercase tracking-wide mb-1">Proyeksi Revenue</div>
                                <div class="text-2xl font-bold text-blue-800" x-text="analysis.prediksi_bulan_depan?.proyeksi_revenue"></div>
                            </div>
                            <div class="bg-green-50 rounded-xl p-5 border border-green-100">
                                <div class="text-xs text-green-500 uppercase tracking-wide mb-1">Proyeksi Surplus</div>
                                <div class="text-2xl font-bold text-green-800" x-text="analysis.prediksi_bulan_depan?.proyeksi_surplus"></div>
                            </div>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4">
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Asumsi</div>
                            <p class="text-sm text-gray-700" x-text="analysis.prediksi_bulan_depan?.asumsi"></p>
                        </div>
                        <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                            <div class="text-xs font-semibold text-red-500 uppercase tracking-wide mb-2">⚠️ Risiko yang Perlu Diwaspadai</div>
                            <p class="text-sm text-red-700" x-text="analysis.prediksi_bulan_depan?.risiko"></p>
                        </div>
                    </div>
                </div>

            </div>{{-- end tab content --}}
        </div>{{-- end tab card --}}

        {{-- Error state --}}
        <div x-show="error" x-cloak class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
            ❌ <span x-text="error"></span>
            <button @click="loadAnalysis(true)" class="ml-2 underline">Coba lagi</button>
        </div>

    </div>{{-- end loaded --}}

</div>

@push('scripts')
<script>
    function analysisApp(periodId) {
        return {
            loading: false
            , loaded: false
            , error: null
            , analysis: {}
            , activeTab: 'masalah'
            , periodId: periodId,

            init() {
                this.loadAnalysis(false, true);
            },

            async loadAnalysis(forceRefresh = false, silent = false) {
                if (this.loading) return;
                this.loading = true;
                this.error = null;
                if (!silent) this.loaded = false;

                try {
                    const url = `{{ route('analysis.analyze') }}?period_id=${this.periodId}${forceRefresh ? '&refresh=1' : ''}`;
                    const res = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!res.ok) throw new Error('Server error: ' + res.status);

                    const data = await res.json();
                    this.analysis = data.analysis;
                    this.loaded = true;

                } catch (e) {
                    if (!silent) {
                        this.error = 'Gagal memuat analisa: ' + e.message;
                    }
                } finally {
                    this.loading = false;
                }
            },

            scoreColor(warna) {
                return {
                    green: '#10B981'
                    , yellow: '#F59E0B'
                    , red: '#EF4444'
                    , blue: '#3B82F6'
                } [warna] || '#6B7280';
            },

            healthClass(status) {
                return {
                    sehat: 'bg-green-50 border-green-200 text-green-800'
                    , baik: 'bg-green-50 border-green-200 text-green-800'
                    , rendah: 'bg-green-50 border-green-200 text-green-800'
                    , perhatian: 'bg-yellow-50 border-yellow-200 text-yellow-800'
                    , sedang: 'bg-yellow-50 border-yellow-200 text-yellow-800'
                    , kritis: 'bg-red-50 border-red-200 text-red-800'
                    , tinggi: 'bg-red-50 border-red-200 text-red-800'
                , } [status] || 'bg-gray-50 border-gray-200 text-gray-800';
            }
        }
    }

</script>
@endpush


@endsection
