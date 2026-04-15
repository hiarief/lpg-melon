{{-- cashflow/_prediction-card.blade.php --}}
{{-- Kartu prediksi lengkap: WMA, OLS, Holt DES, Monte Carlo, Konsensus --}}
{{-- Variabel yang dibutuhkan: $pred, $ols, $netKas, $avgTabungPerHari, $daysInMonth --}}

<div class="s-card">
    {{-- Header --}}
    <div class="s-card-header" style="background:#fff7ed;border-color:#fed7aa;flex-direction:column;gap:10px;padding-bottom:0">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span style="color:#c2410c">🔮 Prediksi Saldo KAS Akhir Bulan</span>
            @php $confColor = $pred['confidence'] >= 75 ? 'badge-green' : ($pred['confidence'] >= 50 ? 'badge-orange' : 'badge-red'); @endphp
            <span class="badge {{ $confColor }}">Keyakinan {{ $pred['confidence'] }}%</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3)">
                Hari ke-{{ $pred['today'] }} · sisa {{ $pred['remainDays'] }} hari
            </span>
        </div>

        <div style="background:#fef3c7;border:0.5px solid #fde68a;border-radius:6px;padding:7px 10px;font-size:10px;color:#92400e;margin-top:4px">
            ℹ️ <strong>Semua prediksi berbasis margin bersih</strong> (penjualan − HPP Rp 16.000/tabung).
            Rasio operasional ideal &lt;35% dari margin.
        </div>

        {{-- Tab switcher --}}
        <div id="predMethodTabs" style="display:flex;gap:0;margin:0 -14px;padding:0 14px;overflow-x:auto;margin-top:4px">
            @foreach([
                ['wma',       'WMA Adaptif',    true],
                ['ols',       'Regresi Linear', false],
                ['holt',      'Holt DES',       false],
                ['mc',        'Monte Carlo',    false],
                ['konsensus', '🎯 Konsensus',   false],
            ] as [$id, $label, $active])
            <button onclick="switchPredTab('{{ $id }}', this)" id="pred-tab-{{ $id }}"
                style="padding:4px 10px;font-size:10px;font-weight:600;border:none;
                       border-bottom:2px solid {{ $active ? '#f97316' : 'transparent' }};
                       background:transparent;color:{{ $active ? '#c2410c' : 'var(--text3)' }};
                       cursor:pointer;white-space:nowrap;border-radius:0">
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- ══ TAB 1: WMA Adaptif ══ --}}
    <div id="pred-panel-wma" style="padding:12px 14px;display:flex;flex-direction:column;gap:14px">

        {{-- Progress --}}
        <div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-bottom:4px">
                <span>Progress bulan berjalan</span>
                <span>{{ $pred['progressPct'] }}% ({{ $pred['today'] }}/{{ $daysInMonth }} hari)</span>
            </div>
            <div style="height:6px;background:#f0f0f0;border-radius:3px;overflow:hidden">
                <div style="height:100%;width:{{ $pred['progressPct'] }}%;background:var(--melon);border-radius:3px"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;margin-top:4px">
                <span style="color:var(--melon-dark);font-weight:600">Aktual: {{ $pred['today'] }} hari</span>
                <span style="color:var(--text3)">Estimasi: {{ $pred['remainDays'] }} hari lagi</span>
            </div>
        </div>

        {{-- Engine Prediksi --}}
        <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">⚙ Engine Prediksi — Transparansi Metode</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                @foreach([
                    ['Rate Margin WMA',    'Rp '.number_format(round($pred['marginRateWma'])),    '5 hari terakhir ×2 bobot',      'var(--melon-dark)'],
                    ['Rate Margin Simpel', 'Rp '.number_format(round($pred['marginRateSimple'])), 'total margin ÷ hari aktif',     'var(--text2)'],
                    ['Rate Final (60/40)', 'Rp '.number_format(round($pred['marginRate'])),       'WMA×0.6 + simpel×0.4',         '#1d4ed8'],
                    ['Faktor Konservatif', '×'.number_format($pred['conserv'], 2),                'basis 0.88 + tren adj',         '#b45309'],
                ] as [$lbl, $val, $note, $col])
                <div>
                    <div style="font-size:10px;color:var(--text3)">{{ $lbl }}</div>
                    <div style="font-size:13px;font-weight:600;color:{{ $col }}">{{ $val }}</div>
                    <div style="font-size:10px;color:var(--text3)">{{ $note }}</div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Momentum 7 hari --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            @foreach([
                ['Tren Margin Bersih (7h)', $pred['salesMomentum'], true],
                ['Tren Pengeluaran (7h)',   $pred['expMomentum'],   false],
            ] as [$lbl, $mom, $isIncome])
            @php $isGood = $isIncome ? $mom >= 0 : $mom <= 0; @endphp
            <div class="card" style="padding:10px 12px;display:flex;align-items:center;gap:8px">
                <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;background:{{ $isGood ? '#f0fdf4' : '#fef2f2' }};color:{{ $isGood ? 'var(--melon-dark)' : '#dc2626' }}">
                    {{ $isGood ? '↑' : '↓' }}
                </div>
                <div>
                    <div style="font-size:10px;color:var(--text3)">{{ $lbl }}</div>
                    <div style="font-size:13px;font-weight:600;color:{{ $isGood ? 'var(--melon-dark)' : '#dc2626' }}">
                        {{ $mom >= 0 ? '+' : '' }}{{ number_format($mom, 1) }}%
                    </div>
                    <div style="font-size:10px;color:var(--text3)">vs 7 hari sebelumnya</div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- 3 Skenario --}}
        @include('cashflow._skenario-cards', [
            'predKas'       => $pred['predKas'],
            'predKasPesim'  => $pred['predKasPesim'],
            'predKasOptim'  => $pred['predKasOptim'],
            'gajiPesim'     => $pred['gajiPesim'],
            'gajiOptim'     => $pred['gajiOptim'],
            'piutang'       => $pred['piutangCairEstimasi'],
        ])

        {{-- Tabel rincian WMA --}}
        <div class="s-card" style="margin-bottom:0">
            <div class="s-card-header">Rincian Asumsi ({{ $pred['remainDays'] }} hari ke depan — konservatif adaptif)</div>
            <div class="scroll-x">
                <table class="mob-table">
                    <thead>
                        <tr>
                            <th>Komponen</th>
                            <th class="r">Rate/Hari (WMA)</th>
                            <th class="r">Rate/Hari (Simpel)</th>
                            <th class="r">Asumsi/Hari</th>
                            <th class="r">Est. {{ $pred['remainDays'] }} Hari</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="color:var(--melon-dark);font-weight:600">
                                + Margin Bersih
                                <div style="font-size:9px;font-weight:400;color:var(--text3)">penjualan − HPP × konservatif</div>
                            </td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format(round($pred['marginRateWma'])) }}</td>
                            <td class="r" style="color:var(--text3)">Rp {{ number_format(round($pred['marginRateSimple'])) }}</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format(round($pred['marginRate'] * $pred['conserv'])) }}</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($pred['projMargin']) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#dc2626;font-weight:600">
                                − Pengeluaran Ops
                                <div style="font-size:9px;font-weight:400;color:var(--text3)">biaya tetap — tidak dikali conserv</div>
                            </td>
                            <td class="r" style="color:#dc2626">Rp {{ number_format(round($pred['expRateWma'])) }}</td>
                            <td class="r" style="color:var(--text3)">Rp {{ number_format(round($pred['expRateSimple'])) }}</td>
                            <td class="r bold" style="color:#dc2626">Rp {{ number_format(round($pred['expRate'])) }}</td>
                            <td class="r bold" style="color:#dc2626">Rp {{ number_format($pred['projExp']) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#3b82f6;font-weight:600">
                                − Admin TF penampung
                                <div style="font-size:9px;font-weight:400;color:var(--text3)">rata-rata per hari aktif</div>
                            </td>
                            <td class="r" style="color:var(--text3)" colspan="2">Rp {{ number_format(round($pred['adminRateSimple'])) }}/hari</td>
                            <td class="r bold" style="color:#3b82f6">Rp {{ number_format(round($pred['adminRateSimple'])) }}</td>
                            <td class="r bold" style="color:#3b82f6">Rp {{ number_format($pred['projAdmin']) }}</td>
                        </tr>
                        @if($pred['piutangCairEstimasi'] > 0)
                        <tr style="background:#f0fdf4">
                            <td style="color:var(--melon-dark);font-weight:600">
                                + Est. Piutang Cair
                                <div style="font-size:10px;font-weight:400;color:var(--text3)">piutang margin Rp {{ number_format($pred['piutangMarginBelumBayar']) }} · asumsi 30%</div>
                            </td>
                            <td class="r" colspan="2" style="color:var(--text3)">× 30%</td>
                            <td class="r" style="color:var(--melon-dark)">—</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($pred['piutangCairEstimasi']) }}</td>
                        </tr>
                        @endif
                        <tr style="background:#fffbeb">
                            <td style="color:#92400e;font-weight:600">
                                − Gaji Kurir (kewajiban)
                                <div style="font-size:10px;font-weight:400;color:var(--text3)">Rp 500/tab · dibayar tgl 1 bulan depan</div>
                            </td>
                            <td class="r" style="color:var(--text3)">{{ number_format($avgTabungPerHari, 1) }} tab/hari × Rp 500</td>
                            <td class="r" style="color:#b45309">{{ number_format($pred['predTabungSisa']) }} tab est.</td>
                            <td class="r" style="color:var(--text3)"></td>
                            <td class="r bold" style="color:#92400e">
                                Rp {{ number_format($pred['predGajiTotal']) }}
                                <div style="font-size:9px;font-weight:400;color:#b45309">
                                    Terhutang: Rp {{ number_format($pred['gajiAktualSdIni']) }}<br>
                                    + Est. sisa: Rp {{ number_format($pred['predGajiSisa']) }}
                                </div>
                            </td>
                        </tr>
                        <tr style="background:var(--melon-50)">
                            <td class="bold">Saldo KAS saat ini</td>
                            <td class="r" colspan="3" style="color:var(--text3)">posisi aktual hari ini</td>
                            <td class="r bold" style="color:#c2410c">Rp {{ number_format($netKas) }}</td>
                        </tr>
                        <tr class="total-row">
                            <td class="bold">
                                = Prediksi KAS Akhir Bulan
                                <div style="font-size:9px;font-weight:400;color:rgba(255,255,255,0.7)">setelah semua kewajiban</div>
                            </td>
                            <td colspan="3"></td>
                            <td class="r bold" style="font-size:14px;color:{{ $pred['predKas'] < 0 ? '#fca5a5' : '#fff' }}">
                                Rp {{ number_format(round($pred['predKas'])) }}
                                <div style="font-size:10px;color:{{ $pred['predKas'] >= 0 ? 'rgba(255,255,255,0.8)' : '#fca5a5' }}">
                                    {{ $pred['predKas'] >= 0 ? '✓ Aman' : '⚠ Defisit' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Prediksi Bank & Rasio --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div class="card" style="padding:10px 12px;background:#eff6ff;border-color:#bfdbfe">
                <div style="font-size:10px;color:#3b82f6">Prediksi Saldo BANK Penampung</div>
                <div style="font-size:16px;font-weight:600;color:{{ $pred['predBank'] >= 0 ? '#1e40af' : '#dc2626' }};margin-top:2px">
                    Rp {{ number_format(round($pred['predBank'])) }}
                </div>
                <div style="font-size:10px;color:#3b82f6;margin-top:2px">rekening transit</div>
            </div>
            <div class="card" style="padding:10px 12px">
                <div style="font-size:10px;color:var(--text3)">Proyeksi Rasio Ops / Margin <span style="font-size:9px;font-style:italic">(akhir bulan)</span></div>
                <div style="font-size:16px;font-weight:600;color:{{ $pred['predRasio'] > 35 ? '#b45309' : 'var(--melon-dark)' }};margin-top:2px">
                    {{ number_format($pred['predRasio'], 1) }}%
                </div>
                <div style="height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden;margin:4px 0">
                    <div style="height:100%;width:{{ min($pred['predRasio'], 100) }}%;background:{{ $pred['predRasio'] > 35 ? '#f59e0b' : 'var(--melon)' }};border-radius:2px"></div>
                </div>
                <div style="font-size:10px;color:var(--text3)">biaya ops ÷ margin bersih · ideal &lt;35%</div>
                <div style="font-size:10px;color:{{ $pred['predRasio'] > 35 ? '#b45309' : 'var(--melon-dark)' }};font-weight:600;margin-top:2px">
                    {{ $pred['predRasio'] > 35 ? '⚠ perlu efisiensi' : '✓ sehat' }}
                </div>
                <div style="font-size:10px;color:var(--text3);margin-top:4px;border-top:0.5px solid var(--border);padding-top:4px">
                    Aktual: <strong style="color:{{ $pred['rasioAktual'] > 35 ? '#b45309' : 'var(--melon-dark)' }}">{{ number_format($pred['rasioAktual'], 1) }}%</strong>
                </div>
            </div>
        </div>

        {{-- Metodologi --}}
        <div style="font-size:10px;color:var(--text3);border-top:0.5px solid var(--border);padding-top:10px;line-height:1.6">
            <span style="font-weight:600;color:var(--text2)">Metodologi:</span>
            Rate = <strong>WMA 5 hari (×2) × 60% + rata simpel × 40%</strong> berbasis margin bersih.
            Faktor konservatif: {{ number_format($pred['conserv'], 2) }} (tren {{ $pred['salesMomentum'] >= 0 ? '+' : '' }}{{ number_format($pred['salesMomentum'], 1) }}%).
            Pengeluaran <strong>tidak dikali conserv</strong>.
            @if($pred['piutangCairEstimasi'] > 0)
                Piutang: <strong>30% diasumsikan cair</strong>.
            @endif
            Confidence <strong>{{ $pred['confidence'] }}%</strong>.
            <span style="color:#c2410c;font-weight:600">Bukan jaminan — panduan perencanaan.</span>
        </div>

        @if($pred['predGajiTotal'] > 0)
        <div style="background:#fffbeb;border:0.5px solid #fde68a;border-radius:8px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px">
            <div>
                <div style="font-size:11px;font-weight:600;color:#92400e">⏰ Kewajiban Gaji Kurir (tgl 1 bulan depan)</div>
                <div style="font-size:10px;color:#b45309;margin-top:2px">
                    Terhutang: Rp {{ number_format($pred['gajiAktualSdIni']) }} + Est. sisa: Rp {{ number_format($pred['predGajiSisa']) }}
                </div>
            </div>
            <div style="font-size:16px;font-weight:700;color:#92400e;white-space:nowrap">Rp {{ number_format($pred['predGajiTotal']) }}</div>
        </div>
        <div style="font-size:10px;color:var(--text3);margin-top:4px;padding:0 2px">
            Prediksi KAS setelah gaji:
            <strong style="color:{{ $pred['predKasSetelahGaji'] >= 0 ? 'var(--melon-dark)' : '#dc2626' }}">
                Rp {{ number_format(round($pred['predKasSetelahGaji'])) }} {{ $pred['predKasSetelahGaji'] >= 0 ? '✓' : '⚠' }}
            </strong>
        </div>
        @endif
    </div>{{-- end panel WMA --}}

    {{-- ══ TAB 2: Regresi Linear OLS ══ --}}
    <div id="pred-panel-ols" style="display:none;padding:12px 14px;flex-direction:column;gap:14px">

        <div style="background:#f0f9ff;border:0.5px solid #bae6fd;border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">📐 Regresi Linear OLS</div>
            <div style="font-size:10px;color:#0c4a6e;line-height:1.6">
                Formula: <code style="background:#e0f2fe;padding:1px 4px;border-radius:3px">ŷ = b₀ + b₁·hari</code>.
                Berbasis <strong>margin bersih</strong>. Unggul saat ada <strong>tren linear konsisten</strong>.
            </div>
        </div>

        {{-- Koefisien --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            @foreach([
                ['Margin Bersih', $ols['b0Margin'], $ols['b1Margin'], $ols['r2Margin'], $ols['qualMargin'], $ols['trendMargin'], $ols['dataPointsMargin'], 'var(--melon-dark)', '#dc2626'],
                ['Pengeluaran Ops', $ols['b0Exp'], $ols['b1Exp'], $ols['r2Exp'], $ols['qualExp'], $ols['trendExp'], $ols['dataPointsExp'], '#dc2626', 'var(--melon-dark)'],
            ] as [$title, $b0, $b1, $r2, $qual, $trend, $dp, $col, $slopeGoodCol])
            @php
                $qualBg  = $r2 >= 0.7 ? '#dcfce7' : ($r2 >= 0.4 ? '#fef9c3' : '#fee2e2');
                $qualCol = $r2 >= 0.7 ? '#166534' : ($r2 >= 0.4 ? '#92400e' : '#991b1b');
            @endphp
            <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                <div style="font-size:10px;color:var(--text3);margin-bottom:4px">{{ $title }}</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <div>
                        <div style="font-size:9px;color:var(--text3)">b₀</div>
                        <div style="font-size:12px;font-weight:600;color:{{ $col }}">Rp {{ number_format($b0) }}</div>
                    </div>
                    <div>
                        <div style="font-size:9px;color:var(--text3)">b₁/hari</div>
                        <div style="font-size:12px;font-weight:600;color:{{ $b1 >= 0 ? $col : '#dc2626' }}">
                            {{ $b1 >= 0 ? '+' : '' }}Rp {{ number_format($b1) }}
                        </div>
                    </div>
                </div>
                <div style="margin-top:6px;display:flex;align-items:center;gap:6px">
                    <div style="font-size:10px;color:var(--text3)">R² = <strong style="color:{{ $qualCol }}">{{ number_format($r2, 3) }}</strong></div>
                    <span style="font-size:9px;padding:1px 6px;border-radius:10px;font-weight:600;background:{{ $qualBg }};color:{{ $qualCol }}">{{ $qual }}</span>
                    <span style="font-size:10px;color:var(--text3)">{{ $trend }}</span>
                </div>
                <div style="font-size:9px;color:var(--text3);margin-top:2px">{{ $dp }} titik data</div>
            </div>
            @endforeach
        </div>

        {{-- Tabel OLS --}}
        <div class="s-card" style="margin-bottom:0">
            <div class="s-card-header">Rincian Proyeksi OLS ({{ $pred['remainDays'] }} hari)</div>
            <div class="scroll-x">
                <table class="mob-table">
                    <thead>
                        <tr><th>Komponen</th><th class="r">b₀</th><th class="r">b₁/hari</th><th class="r">R²</th><th class="r">Est. {{ $pred['remainDays'] }} Hari</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="color:var(--melon-dark);font-weight:600">+ Margin Bersih</td>
                            <td class="r">Rp {{ number_format($ols['b0Margin']) }}</td>
                            <td class="r" style="color:{{ $ols['b1Margin'] >= 0 ? 'var(--melon-dark)' : '#dc2626' }}">{{ $ols['b1Margin'] >= 0 ? '+' : '' }}{{ number_format($ols['b1Margin']) }}</td>
                            <td class="r">{{ number_format($ols['r2Margin'], 3) }}</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($ols['projMargin']) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#dc2626;font-weight:600">− Pengeluaran</td>
                            <td class="r">Rp {{ number_format($ols['b0Exp']) }}</td>
                            <td class="r" style="color:{{ $ols['b1Exp'] <= 0 ? 'var(--melon-dark)' : '#dc2626' }}">{{ $ols['b1Exp'] >= 0 ? '+' : '' }}{{ number_format($ols['b1Exp']) }}</td>
                            <td class="r">{{ number_format($ols['r2Exp'], 3) }}</td>
                            <td class="r bold" style="color:#dc2626">Rp {{ number_format($ols['projExp']) }}</td>
                        </tr>
                        <tr>
                            <td style="color:#3b82f6;font-weight:600">− Admin TF</td>
                            <td class="r" colspan="3" style="color:var(--text3)">rate simpel ({{ number_format(round($pred['adminRateSimple'])) }}/hari)</td>
                            <td class="r bold" style="color:#3b82f6">Rp {{ number_format($ols['projAdmin']) }}</td>
                        </tr>
                        @if($pred['piutangCairEstimasi'] > 0)
                        <tr style="background:#f0fdf4">
                            <td style="color:var(--melon-dark);font-weight:600">+ Est. Piutang Cair</td>
                            <td class="r" colspan="3" style="color:var(--text3)">sama dengan WMA (30%)</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($pred['piutangCairEstimasi']) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td style="color:#92400e;font-weight:600">− Gaji Kurir</td>
                            <td class="r" colspan="3" style="color:var(--text3)">sama dengan WMA</td>
                            <td class="r bold" style="color:#92400e">Rp {{ number_format($pred['predGajiTotal']) }}</td>
                        </tr>
                        <tr style="background:var(--melon-50)">
                            <td class="bold">Saldo KAS saat ini</td>
                            <td class="r" colspan="3" style="color:var(--text3)">posisi aktual</td>
                            <td class="r bold" style="color:#c2410c">Rp {{ number_format($netKas) }}</td>
                        </tr>
                        <tr class="total-row">
                            <td class="bold">= Prediksi KAS (OLS)<div style="font-size:9px;font-weight:400;color:rgba(255,255,255,0.7)">setelah semua kewajiban</div></td>
                            <td colspan="3"></td>
                            <td class="r bold" style="font-size:14px;color:{{ $ols['predKas'] < 0 ? '#fca5a5' : '#fff' }}">
                                Rp {{ number_format($ols['predKas']) }}
                                <div style="font-size:10px;color:{{ $ols['predKas'] >= 0 ? 'rgba(255,255,255,0.8)' : '#fca5a5' }}">{{ $ols['predKas'] >= 0 ? '✓ Aman' : '⚠ Defisit' }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="font-size:10px;color:var(--text3);border-top:0.5px solid var(--border);padding-top:10px;line-height:1.6">
            <span style="font-weight:600;color:var(--text2)">Metodologi OLS:</span>
            <code style="background:var(--surface2);padding:1px 4px;border-radius:3px">ŷ = b₀ + b₁·d</code>
            via Ordinary Least Squares. R² ≥0.7 = baik, 0.4–0.7 = cukup, &lt;0.4 = lemah.
            Confidence <strong>{{ $ols['confidence'] }}%</strong>.
            <span style="color:#0369a1;font-weight:600">Terbaik saat ada tren linear jelas.</span>
        </div>
    </div>{{-- end panel OLS --}}

    {{-- ══ TAB 3: Holt DES ══ --}}
    <div id="pred-panel-holt" style="display:none;padding:12px 14px;flex-direction:column;gap:14px">

        <div style="background:#f0fdf4;border:0.5px solid #86efac;border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">📉 Holt's Double Exponential Smoothing (DES)</div>
            <div style="font-size:10px;color:#14532d;line-height:1.6">
                Smoothing ganda: <strong>Level L(t)</strong> (α) + <strong>Trend T(t)</strong> (β).
                Parameter dioptimasi via <strong>grid-search SSE minimum</strong> (25 kombinasi).
                Unggul saat tren <strong>berubah secara gradual</strong>.
            </div>
        </div>

        {{-- Parameter optimal --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            @foreach([
                ['Margin Bersih', $pred['holtsAlphaMargin'], $pred['holtsBetaMargin'], $pred['holtsLevelMargin'], $pred['holtsTrendMargin'], $pred['holtsMarginTrend'], $pred['holtsMaeMargin'], 'var(--melon-dark)'],
                ['Pengeluaran Ops', $pred['holtsAlphaExp'], $pred['holtsBetaExp'], $pred['holtsLevelExp'], $pred['holtsTrendExp'], $pred['holtsExpTrend'], $pred['holtsMaeExp'], '#dc2626'],
            ] as [$title, $alpha, $beta, $level, $trend, $trendLabel, $mae, $col])
            <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                <div style="font-size:10px;color:var(--text3);margin-bottom:6px;font-weight:600">{{ $title }}</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px">
                    <div><div style="font-size:9px;color:var(--text3)">α (level)</div><div style="font-size:13px;font-weight:600;color:{{ $col }}">{{ number_format($alpha, 2) }}</div></div>
                    <div><div style="font-size:9px;color:var(--text3)">β (trend)</div><div style="font-size:13px;font-weight:600;color:{{ $col }}">{{ number_format($beta, 2) }}</div></div>
                    <div><div style="font-size:9px;color:var(--text3)">Level L(t)</div><div style="font-size:12px;font-weight:600;color:{{ $col }}">Rp {{ number_format(round($level)) }}</div></div>
                    <div><div style="font-size:9px;color:var(--text3)">Trend T/hari</div>
                        <div style="font-size:12px;font-weight:600;color:{{ $trend >= 0 ? $col : '#dc2626' }}">{{ $trend >= 0 ? '+' : '' }}Rp {{ number_format(round($trend)) }}</div>
                    </div>
                </div>
                <span style="font-size:10px;color:var(--text3)">{{ $trendLabel }}</span>
                <span style="font-size:9px;color:var(--text3);margin-left:6px">MAE: Rp {{ number_format($mae) }}/hari</span>
            </div>
            @endforeach
        </div>

        {{-- 3 Skenario Holt --}}
        @include('cashflow._skenario-cards', [
            'predKas'       => $pred['holtsPredKas'],
            'predKasPesim'  => $pred['holtsPredKasPesim'],
            'predKasOptim'  => $pred['holtsPredKasOptim'],
            'gajiPesim'     => null,
            'gajiOptim'     => null,
            'piutang'       => $pred['piutangCairEstimasi'],
            'labelPesim'    => 'Pesimis (−1.5σ)',
            'labelOptim'    => 'Optimis (+1.5σ)',
            'notePesim'     => 'margin −1.5σ, ops +1σ',
            'noteOptim'     => 'margin +1.5σ, ops −1σ',
            'accentColor'   => '#16a34a',
            'labelDasar'    => 'Dasar',
        ])

        {{-- Tabel Holt --}}
        <div class="s-card" style="margin-bottom:0">
            <div class="s-card-header">Rincian Proyeksi Holt DES ({{ $pred['remainDays'] }} hari)</div>
            <div class="scroll-x">
                <table class="mob-table">
                    <thead>
                        <tr><th>Komponen</th><th class="r">Level L(t)</th><th class="r">Trend T/hari</th><th class="r">α · β</th><th class="r">Est. {{ $pred['remainDays'] }} Hari</th></tr>
                    </thead>
                    <tbody>
                        @foreach([
                            ['+ Margin Bersih', $pred['holtsLevelMargin'], $pred['holtsTrendMargin'], $pred['holtsAlphaMargin'], $pred['holtsBetaMargin'], $pred['holtsProjMargin'], 'var(--melon-dark)', true],
                            ['− Pengeluaran Ops', $pred['holtsLevelExp'], $pred['holtsTrendExp'], $pred['holtsAlphaExp'], $pred['holtsBetaExp'], $pred['holtsProjExp'], '#dc2626', false],
                        ] as [$lbl, $lvl, $tnd, $a, $b, $proj, $col, $isIncome])
                        <tr>
                            <td style="color:{{ $col }};font-weight:600">{{ $lbl }}</td>
                            <td class="r bold" style="color:{{ $col }}">Rp {{ number_format(round($lvl)) }}</td>
                            <td class="r" style="color:{{ $isIncome ? ($tnd >= 0 ? $col : '#dc2626') : ($tnd <= 0 ? 'var(--melon-dark)' : '#dc2626') }}">
                                {{ $tnd >= 0 ? '+' : '' }}Rp {{ number_format(round($tnd)) }}
                            </td>
                            <td class="r" style="color:var(--text3)">{{ number_format($a, 2) }} · {{ number_format($b, 2) }}</td>
                            <td class="r bold" style="color:{{ $col }}">Rp {{ number_format($proj) }}</td>
                        </tr>
                        @endforeach
                        <tr>
                            <td style="color:#3b82f6;font-weight:600">− Admin TF</td>
                            <td class="r" style="color:#3b82f6">Rp {{ number_format(round($pred['holtsLevelAdmin'])) }}</td>
                            <td class="r" style="color:{{ $pred['holtsTrendAdmin'] <= 0 ? 'var(--melon-dark)' : '#dc2626' }}">{{ $pred['holtsTrendAdmin'] >= 0 ? '+' : '' }}Rp {{ number_format(round($pred['holtsTrendAdmin'])) }}</td>
                            <td class="r" style="color:var(--text3)">0.40 · 0.20</td>
                            <td class="r bold" style="color:#3b82f6">Rp {{ number_format($pred['holtsProjAdmin']) }}</td>
                        </tr>
                        @if($pred['piutangCairEstimasi'] > 0)
                        <tr style="background:#f0fdf4">
                            <td style="color:var(--melon-dark);font-weight:600">+ Est. Piutang Cair</td>
                            <td class="r" colspan="3" style="color:var(--text3)">× 30%</td>
                            <td class="r bold" style="color:var(--melon-dark)">Rp {{ number_format($pred['piutangCairEstimasi']) }}</td>
                        </tr>
                        @endif
                        <tr style="background:#fffbeb">
                            <td style="color:#92400e;font-weight:600">− Gaji Kurir</td>
                            <td class="r" colspan="3" style="color:var(--text3)">deterministik</td>
                            <td class="r bold" style="color:#92400e">Rp {{ number_format($pred['predGajiTotal']) }}</td>
                        </tr>
                        <tr style="background:var(--melon-50)">
                            <td class="bold">Saldo KAS saat ini</td>
                            <td class="r" colspan="3" style="color:var(--text3)">posisi aktual</td>
                            <td class="r bold" style="color:#c2410c">Rp {{ number_format($netKas) }}</td>
                        </tr>
                        <tr class="total-row">
                            <td class="bold">= Prediksi KAS (Holt DES)</td>
                            <td colspan="3"></td>
                            <td class="r bold" style="font-size:14px;color:{{ $pred['holtsPredKas'] < 0 ? '#fca5a5' : '#fff' }}">
                                Rp {{ number_format(round($pred['holtsPredKas'])) }}
                                <div style="font-size:10px;color:{{ $pred['holtsPredKas'] >= 0 ? 'rgba(255,255,255,0.8)' : '#fca5a5' }}">{{ $pred['holtsPredKas'] >= 0 ? '✓ Aman' : '⚠ Defisit' }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="font-size:10px;color:var(--text3);border-top:0.5px solid var(--border);padding-top:10px;line-height:1.6">
            <span style="font-weight:600;color:var(--text2)">Metodologi Holt DES:</span>
            α={{ number_format($pred['holtsAlphaMargin'], 2) }}, β={{ number_format($pred['holtsBetaMargin'], 2) }},
            {{ $pred['holtsDataPoints'] }} hari aktif.
            Proyeksi: <code style="background:var(--surface2);padding:1px 4px;border-radius:3px">L + h·T</code>.
            MAE margin: Rp {{ number_format($pred['holtsMaeMargin']) }}/hari.
            Confidence <strong>{{ $pred['holtsConfidence'] }}%</strong>.
            <span style="color:#166534;font-weight:600">Terbaik saat tren berubah gradual.</span>
        </div>
    </div>{{-- end panel Holt --}}

    {{-- ══ TAB 4: Monte Carlo ══ --}}
    <div id="pred-panel-mc" style="display:none;padding:12px 14px;flex-direction:column;gap:14px">

        <div style="background:#faf5ff;border:0.5px solid #d8b4fe;border-radius:8px;padding:10px 12px">
            <div style="font-size:10px;font-weight:700;color:#6d28d9;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">🎲 Monte Carlo — 10.000 Iterasi</div>
            <div style="font-size:10px;color:#4c1d95;line-height:1.6">
                Sampling dari <strong>distribusi normal</strong> via Box-Muller.
                Margin & expense berkorelasi (Cholesky). Basis <strong>margin bersih</strong>.
            </div>
        </div>

        {{-- Parameter MC --}}
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
            @foreach([
                ['Margin Bersih/Hari', $mc['marginMean'], $mc['marginStd'], 'var(--melon-dark)', null],
                ['Pengeluaran Ops/Hari', $mc['expMean'], $mc['expStd'], '#dc2626', null],
                ['Admin TF/Hari', $mc['adminMean'], $mc['adminStd'], '#1d4ed8', 'Korelasi M-E: '.number_format($mc['corrCoef'], 2)],
            ] as [$title, $mean, $std, $col, $extra])
            <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                <div style="font-size:9px;color:var(--text3);margin-bottom:3px">{{ $title }}</div>
                <div style="font-size:11px;font-weight:600;color:{{ $col }}">μ = Rp {{ number_format(round($mean)) }}</div>
                <div style="font-size:11px;color:var(--text3)">σ = Rp {{ number_format(round($std)) }}</div>
                @if($extra)
                <div style="font-size:9px;color:var(--text3);margin-top:3px">{{ $extra }}</div>
                @elseif($mean > 0)
                <div style="font-size:9px;color:var(--text3);margin-top:3px">CV = {{ number_format($std / $mean * 100, 1) }}%</div>
                @endif
            </div>
            @endforeach
        </div>

        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <button id="mc-run-btn" onclick="runMonteCarlo()"
                style="padding:8px 18px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px">
                <span id="mc-btn-icon">▶</span> Jalankan Simulasi
            </button>
            <div id="mc-status" style="font-size:10px;color:var(--text3)">Belum dijalankan.</div>
        </div>

        <div id="mc-results" style="display:none;flex-direction:column;gap:14px">
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Distribusi Saldo KAS (10.000 skenario)</div>
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px" id="mc-percentiles"></div>
            </div>
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Histogram Distribusi</div>
                <div style="position:relative;width:100%;height:160px"><canvas id="mcHistChart"></canvas></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <div style="background:#faf5ff;border:0.5px solid #d8b4fe;border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:#6d28d9;margin-bottom:4px">Probabilitas Defisit</div>
                    <div id="mc-prob-deficit" style="font-size:22px;font-weight:700"></div>
                    <div style="font-size:9px;color:#6d28d9;margin-top:3px">saldo akhir &lt; 0</div>
                </div>
                <div style="background:#faf5ff;border:0.5px solid #d8b4fe;border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:#6d28d9;margin-bottom:4px">Median (P50)</div>
                    <div id="mc-median" style="font-size:18px;font-weight:700"></div>
                    <div style="font-size:9px;color:#6d28d9;margin-top:3px">ekspektasi terbaik</div>
                </div>
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Range 90% CI (P5–P95)</div>
                    <div id="mc-ci90" style="font-size:11px;font-weight:600;color:var(--text1)"></div>
                </div>
                <div style="background:var(--surface2);border:0.5px solid var(--border);border-radius:8px;padding:10px 12px">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:4px">Range 50% CI (P25–P75)</div>
                    <div id="mc-ci50" style="font-size:11px;font-weight:600;color:var(--text1)"></div>
                </div>
            </div>
            <div id="mc-interpretation" style="border:0.5px solid;border-radius:8px;padding:10px 12px;font-size:10px;line-height:1.6"></div>
        </div>
    </div>{{-- end panel MC --}}

    {{-- ══ TAB 5: Konsensus ══ --}}
    <div id="pred-panel-konsensus" style="display:none;padding:12px 14px;flex-direction:column;gap:14px">

        <div class="s-card" style="margin-bottom:0">
            <div class="s-card-header">Perbandingan Prediksi Saldo KAS Akhir Bulan</div>
            <div class="scroll-x">
                <table class="mob-table">
                    <thead>
                        <tr><th>Metode</th><th class="r">Prediksi KAS</th><th class="r">Confidence</th><th class="r">Bobot</th><th>Kelebihan</th></tr>
                    </thead>
                    <tbody>
                        @foreach([
                            ['WMA Adaptif',     $pred['predKas'],        $pred['confidence'],        'cons-w-wma',  '#c2410c', 'Responsif tren terkini, faktor konservatif'],
                            ['Regresi Linear',  $ols['predKas'],         $ols['confidence'],         'cons-w-ols',  '#0369a1', 'Menangkap tren linear jangka panjang'],
                            ['Holt DES',        $pred['holtsPredKas'],   $pred['holtsConfidence'],   'cons-w-holt', '#166534', 'Tren berubah gradual, smoothing α+β'],
                        ] as [$method, $kas, $conf, $wId, $col, $desc])
                        <tr>
                            <td style="font-weight:600;color:{{ $col }}">{{ $method }}</td>
                            <td class="r bold" style="color:{{ $kas >= 0 ? $col : '#dc2626' }}">Rp {{ number_format(round($kas)) }}</td>
                            <td class="r">{{ $conf }}%</td>
                            <td class="r" id="{{ $wId }}">—</td>
                            <td style="font-size:10px;color:var(--text3)">{{ $desc }}</td>
                        </tr>
                        @endforeach
                        <tr>
                            <td style="font-weight:600;color:#6d28d9">Monte Carlo P50</td>
                            <td class="r bold" id="cons-mc-val" style="color:#6d28d9">— (belum simulasi)</td>
                            <td class="r" id="cons-mc-conf">—</td>
                            <td class="r" id="cons-w-mc">—</td>
                            <td style="font-size:10px;color:var(--text3)">Distribusi probabilistik, tail-risk realistis</td>
                        </tr>
                        <tr style="background:var(--melon-50)">
                            <td class="bold">Saldo KAS Saat Ini</td>
                            <td class="r bold" style="color:#c2410c">Rp {{ number_format($netKas) }}</td>
                            <td class="r" style="color:var(--text3)">aktual</td>
                            <td class="r">—</td>
                            <td style="font-size:10px;color:var(--text3)">Posisi nyata hari ini</td>
                        </tr>
                        <tr class="total-row">
                            <td class="bold">= Konsensus Tertimbang<div style="font-size:9px;font-weight:400;color:rgba(255,255,255,0.7)" id="cons-note">—</div></td>
                            <td colspan="2"></td>
                            <td class="r" style="color:rgba(255,255,255,0.7)" id="cons-total-w">—</td>
                            <td class="r bold" style="font-size:14px" id="cons-result"><span style="color:rgba(255,255,255,0.5)">Menunggu...</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="position:relative;width:100%;height:180px">
            <canvas id="consCompChart"></canvas>
        </div>

        <div id="cons-insight" style="background:#f0f9ff;border:0.5px solid #bae6fd;border-radius:8px;padding:10px 12px;font-size:10px;color:#0c4a6e;line-height:1.6">
            Jalankan Monte Carlo untuk konsensus lengkap 4 metode.
        </div>
    </div>{{-- end panel konsensus --}}

</div>{{-- end card prediksi --}}