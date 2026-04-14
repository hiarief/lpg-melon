<div class="slip-wrapper bg-white rounded-lg shadow-lg overflow-hidden text-sm" style="font-family: Arial, sans-serif;">

    {{-- ── HEADER ──────────────────────────────────────────────────── --}}
    <div style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; padding: 20px 24px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 18px; font-weight: bold; letter-spacing: 1px;">⛽ REKAP GAS LPG 3KG</div>
                <div style="font-size: 11px; color: #aab; margin-top: 2px;">Slip Gaji Kurir — {{ $period->label }}</div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 11px; color: #aab;">Diterbitkan</div>
                <div style="font-size: 12px;">{{ now()->format('d/m/Y') }}</div>
            </div>
        </div>
        <div style="margin-top: 16px; padding: 12px; background: rgba(255,255,255,0.08); border-radius: 8px; display: flex; gap: 32px;">
            <div>
                <div style="font-size: 10px; color: #99a; text-transform: uppercase; letter-spacing: 1px;">Nama</div>
                <div style="font-size: 16px; font-weight: bold; margin-top: 2px;">{{ $courier->name }}</div>
            </div>
            <div>
                <div style="font-size: 10px; color: #99a; text-transform: uppercase; letter-spacing: 1px;">Jabatan</div>
                <div style="font-size: 14px; margin-top: 2px;">Kurir Distribusi Gas</div>
            </div>
            <div>
                <div style="font-size: 10px; color: #99a; text-transform: uppercase; letter-spacing: 1px;">Periode</div>
                <div style="font-size: 14px; font-weight: bold; margin-top: 2px;">{{ $period->label }}</div>
            </div>
        </div>
    </div>

    {{-- ── SECTION: PENDAPATAN KOTOR ───────────────────────────────── --}}
    <div style="background: #1e3a5f; color: white; padding: 8px 20px; font-size: 12px; font-weight: bold; letter-spacing: 0.5px;">
        PENDAPATAN KOTOR (Sebelum Potongan)
    </div>

    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #e8f4f8; color: #1a5276;">
                <th style="padding: 8px 16px; text-align: left; font-size: 11px; font-weight: bold; border-bottom: 2px solid #aed6f1;">Komponen</th>
                <th style="padding: 8px 16px; text-align: center; font-size: 11px; font-weight: bold; border-bottom: 2px solid #aed6f1;">Satuan / Volume</th>
                <th style="padding: 8px 16px; text-align: center; font-size: 11px; font-weight: bold; border-bottom: 2px solid #aed6f1;">Rate / Unit</th>
                <th style="padding: 8px 16px; text-align: right; font-size: 11px; font-weight: bold; border-bottom: 2px solid #aed6f1;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            {{-- Upah distribusi tabung --}}
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 9px 16px; font-size: 12px;">Upah Distribusi Tabung</td>
                <td style="padding: 9px 16px; text-align: center; font-size: 12px; color: #555;">
                    {{ number_format($totalTabung) }} Tabung
                </td>
                <td style="padding: 9px 16px; text-align: center; font-size: 12px; color: #555;">
                    Rp {{ number_format($courier->wage_per_unit) }}/tabung
                </td>
                <td style="padding: 9px 16px; text-align: right; font-size: 12px; font-weight: bold;">
                    {{ number_format($upahTabung) }}
                </td>
            </tr>
            {{-- Tunjangan transportasi --}}
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 9px 16px; font-size: 12px;">Tunjangan Transportasi</td>
                <td style="padding: 9px 16px; text-align: center; font-size: 12px; color: #555;">
                    {{ $hariDistribusi }} Hari
                </td>
                <td style="padding: 9px 16px; text-align: center; font-size: 12px; color: #555;">
                    Rp 10.000/hari
                </td>
                <td style="padding: 9px 16px; text-align: right; font-size: 12px; font-weight: bold;">
                    {{ number_format($tunjanganTransportasi) }}
                </td>
            </tr>
            {{-- Tunjangan perawatan kendaraan (hanya jika ada) --}}
            @if($tunjanganKendaraan > 0)
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 9px 16px; font-size: 12px;">Tunjangan Perawatan Kendaraan</td>
                <td style="padding: 9px 16px; text-align: center; font-size: 12px; color: #555;">1</td>
                <td style="padding: 9px 16px; text-align: center; font-size: 12px; color: #555;">
                    {{ number_format($tunjanganKendaraan) }}
                </td>
                <td style="padding: 9px 16px; text-align: right; font-size: 12px; font-weight: bold;">
                    {{ number_format($tunjanganKendaraan) }}
                </td>
            </tr>
            @endif
        </tbody>
        <tfoot>
            <tr style="background: #1e3a5f; color: white;">
                <td colspan="3" style="padding: 10px 16px; font-size: 12px; font-weight: bold; letter-spacing: 0.5px;">
                    TOTAL PENDAPATAN KOTOR
                </td>
                <td style="padding: 10px 16px; text-align: right; font-size: 14px; font-weight: bold;">
                    {{ number_format($totalPendapatanKotor) }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- ── SECTION: POTONGAN ───────────────────────────────────────── --}}
    <div style="background: #7b241c; color: white; padding: 8px 20px; font-size: 12px; font-weight: bold; letter-spacing: 0.5px; margin-top: 2px;">
        POTONGAN &nbsp; (Pengurang Gaji Bersih)
    </div>

    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #fdedec; color: #922b21;">
                <th style="padding: 8px 16px; text-align: left; font-size: 11px; font-weight: bold; border-bottom: 2px solid #f1948a;">Komponen Potongan</th>
                <th style="padding: 8px 16px; text-align: left; font-size: 11px; font-weight: bold; border-bottom: 2px solid #f1948a;">Keterangan</th>
                <th style="padding: 8px 16px; text-align: left; font-size: 11px; font-weight: bold; border-bottom: 2px solid #f1948a;">Catatan</th>
                <th style="padding: 8px 16px; text-align: right; font-size: 11px; font-weight: bold; border-bottom: 2px solid #f1948a;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            {{-- Potongan bensin --}}
            <tr style="border-bottom: 1px solid #fde8e7;">
                <td style="padding: 9px 16px; font-size: 12px;">Reimbursement Bensin (Terpakai)</td>
                <td style="padding: 9px 16px; font-size: 11px; color: #666;">
                    Jatah Rp{{ number_format($jatahBensin) }} | Terpakai Rp{{ number_format($bensinTerpakai) }}
                </td>
                <td style="padding: 9px 16px; font-size: 11px; color: #888;">
                    @if($potonganBensin > 0)
                        Selisih dipotong
                    @else
                        <span style="color: #27ae60;">Dalam batas jatah ✓</span>
                    @endif
                </td>
                <td style="padding: 9px 16px; text-align: right; font-size: 12px; font-weight: bold;
                    color: {{ $potonganBensin > 0 ? '#c0392b' : '#27ae60' }};">
                    {{ $potonganBensin > 0 ? number_format($potonganBensin) : '0' }}
                </td>
            </tr>
            {{-- Potongan makan & rokok --}}
            <tr style="border-bottom: 1px solid #fde8e7;">
                <td style="padding: 9px 16px; font-size: 12px;">Makan &amp; Rokok (Terpakai)</td>
                <td style="padding: 9px 16px; font-size: 11px; color: #666;">
                    Reimbursement digunakan
                    @if($rokokTotal > 0 || $makanTotal > 0)
                        (Rokok: Rp{{ number_format($rokokTotal) }} | Makan: Rp{{ number_format($makanTotal) }})
                    @endif
                </td>
                <td style="padding: 9px 16px; font-size: 11px; color: #888;">Dipotong dari gaji</td>
                <td style="padding: 9px 16px; text-align: right; font-size: 12px; font-weight: bold;
                    color: {{ $potonganMakanRokok > 0 ? '#c0392b' : '#888' }};">
                    {{ number_format($potonganMakanRokok) }}
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr style="background: #7b241c; color: white;">
                <td colspan="3" style="padding: 10px 16px; font-size: 12px; font-weight: bold; letter-spacing: 0.5px;">
                    TOTAL POTONGAN
                </td>
                <td style="padding: 10px 16px; text-align: right; font-size: 14px; font-weight: bold;">
                    {{ number_format($totalPotongan) }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- ── GAJI BERSIH ─────────────────────────────────────────────── --}}
    <div style="background: {{ $gajiBersih >= 0 ? '#1e8449' : '#922b21' }}; color: white; padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; margin-top: 2px;">
        <div>
            <div style="font-size: 16px; font-weight: bold; letter-spacing: 0.5px;">
                {{ $gajiBersih >= 0 ? '✓' : '✗' }} &nbsp;GAJI BERSIH DITERIMA
            </div>
            <div style="font-size: 10px; color: rgba(255,255,255,0.7); margin-top: 4px;">
                ↑ Sudah dikurangi semua potongan dari pendapatan kotor
            </div>
        </div>
        <div style="font-size: 28px; font-weight: bold; letter-spacing: 1px;">
            {{ number_format($gajiBersih) }}
        </div>
    </div>

    {{-- ── RINGKASAN ───────────────────────────────────────────────── --}}
    <div style="background: #f8f9fa; border-top: 3px solid #e9ecef; padding: 16px 20px;">
        <div style="font-size: 12px; font-weight: bold; color: #555; letter-spacing: 0.5px; margin-bottom: 10px;">
            RINGKASAN
        </div>
        <table style="width: 100%; font-size: 12px;">
            <tr>
                <td style="padding: 5px 0; color: #555;">🚛 Total Tabung Terkirim</td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($totalTabung) }} tabung</td>
            </tr>
            <tr>
                <td style="padding: 5px 0; color: #555;">📅 Hari Distribusi Aktif</td>
                <td style="text-align: right; font-weight: bold;">{{ $hariDistribusi }} hari</td>
            </tr>
            <tr style="border-top: 1px solid #dee2e6;">
                <td style="padding: 5px 0; color: #1a5276;">💰 Pendapatan Kotor</td>
                <td style="text-align: right; font-weight: bold; color: #1a5276;">{{ number_format($totalPendapatanKotor) }}</td>
            </tr>
            <tr>
                <td style="padding: 5px 0; color: #922b21;">✂️ Total Potongan</td>
                <td style="text-align: right; font-weight: bold; color: #922b21;">({{ number_format($totalPotongan) }})</td>
            </tr>
            <tr style="border-top: 2px solid #dee2e6;">
                <td style="padding: 7px 0; font-size: 13px; font-weight: bold; color: {{ $gajiBersih >= 0 ? '#1e8449' : '#922b21' }};">
                    💵 Gaji Bersih Diterima
                </td>
                <td style="text-align: right; font-size: 15px; font-weight: bold; color: {{ $gajiBersih >= 0 ? '#1e8449' : '#922b21' }};">
                    {{ number_format($gajiBersih) }}
                </td>
            </tr>
        </table>
    </div>

    {{-- ── DETAIL DISTRIBUSI PER HARI (collapsible) ───────────────── --}}
    @if($distByDay->count() > 0)
    <div style="background: #f0f4f8; border-top: 1px solid #dce3ea; padding: 12px 20px;">
        <div style="font-size: 11px; font-weight: bold; color: #666; letter-spacing: 0.5px; margin-bottom: 8px;">
            RINCIAN DISTRIBUSI HARIAN
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
            @foreach($distByDay as $tgl => $qty)
            <div style="background: white; border: 1px solid #c8d6e5; border-radius: 6px; padding: 5px 10px; font-size: 11px; text-align: center;">
                <div style="color: #888;">{{ \Carbon\Carbon::parse($tgl)->format('d/m') }}</div>
                <div style="font-weight: bold; color: #1a5276;">{{ number_format($qty) }} tab</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── FOOTER ──────────────────────────────────────────────────── --}}
    <div style="background: #f8f9fa; border-top: 1px solid #dee2e6; padding: 12px 20px; text-align: center; font-size: 10px; color: #888;">
        Dokumen ini diterbitkan secara otomatis oleh Sistem Rekap Gas LPG 3KG.
        Pertanyaan hubungi Admin. &nbsp;|&nbsp;
        Dicetak: {{ now()->format('d/m/Y H:i') }}
    </div>

</div>
