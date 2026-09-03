<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Clearancesheet</title>
  <style>
    @page {
        margin: 20px;
    }

    body {
      font-family: Arial, sans-serif;
      font-size: 11px;
      line-height: 1.5;
    }

    h2 {
      text-align: center;
      font-weight: bold;
      font-size: 14px;
      margin-bottom: 0;
    }

    .subtitle {
      text-align: center;
      font-weight: bold;
      font-size: 13px;
      margin-top: 0;
      margin-bottom: 10px;
    }

    table.meta {
      width: 100%;
      margin-bottom: 5px;
    }

    table.meta td {
      padding: 2px 0px;
      font-size: 11px;
      vertical-align: center;
    }

    .intro {
      margin-bottom: 5px;
    }

    table.checklist {
      width: 100%;
      border-collapse: collapse;
      font-size: 11px;
    }

    table.checklist td, table.checklist th {
      border: 1px solid #000;
      padding: 6px;
      vertical-align: top;
    }

    .section-title {
      font-weight: bold;
      margin-bottom: 4px;
    }

    .check-options {
      margin-top: 20px;
    }

    .check-options span {
      display: inline-block;
      margin-right: 12px;
    }

    .signature {
      text-align: center;
      font-size: 10px;
      font-weight: bold;
    }
  </style>
</head>
<body>

@php
    $jenisTokoMap = [
        'ns' => 'NEW STORE',
        'ppj' => 'PERPANJANGAN',
        'up' => 'UPGRADE POINT COFFEE',
        're' => 'RELOKASI',
    ];

    $kode = strtolower($data['jns_toko'] ?? '');

    $labelRaw = $jenisTokoMap[$kode] ?? ucfirst($kode);
    $label = ucwords(strtolower($labelRaw));
    $tittle = strtoupper($labelRaw);
@endphp

<h2>CLEARANCE SHEET - TOKO FRANCHISE ({{ $tittle }})</h2>
<p class="subtitle">CABANG {{ strtoupper($data['cabang']) }}</p>

<table class="meta">
  <tr>
    <td width="100">Kode & Nama Toko</td>
    <td>: {{ $data['kd_toko'] }} - {{ $data['nama_toko'] }}</td>
  </tr>
  <tr>
    <td>Pemilik</td>
    <td>: {{ $data['badan'] ?? '-' }}</td>
  </tr>
  <tr>
    <td>Tanggal Waralaba</td>
    <td>: {{ \Carbon\Carbon::parse($data['tgl_wrlb'])->format('d M Y') }}</td>
  </tr>
  <tr>
    <td>No RAB</td>
    <td>: {{ $data['no_rab'] }}</td>
  </tr>
</table>

<p class="intro">
  Sebelum perhitungan LPD toko {{ $label }} Franchise disampaikan kepada Franchisee, harap dicek apakah semua transaksi
  yang berhubungan dengan toko tersebut sudah clear atau belum dengan memberikan tanda centang
  (<span style="font-family: DejaVu Sans, sans-serif;">&#10003;</span>) pada catatan di bawah ini :
</p>

<table class="checklist">
  <!-- Row 1 -->
  <tr>
    <td width="50%" style="border-right: none;">
      <div class="section-title">1. Marketing Franchise</div>
      <div>* Berkas Toko NS/PPJ</div>
      <div>* Investasi {{ 'Rp ' . number_format(($data['setor'] + $data['cad_dana']), 0, ',', '.') }}</div>
      <div>* Selisih RAB VS Investasi</div>
      <div>* Pekerjaan Oleh Frcsee</div>
      <div>* Tambahan BA lain-lain (jika ada)</div>
      <div>* Outstanding biaya terkait ({{ $data['jns_toko'] }} - {{ $data['nama_toko'] }})</div>
      <div>* Lain-lain :</div>
      <div>-</div>
    </td>
    <td width="30%" style="vertical-align: top; border-left: none;">
      <div class="check-options" style="margin-top: 20px;">
        @for($i = 0; $i < 6; $i++)
          <span>O Clear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Belum&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Tidak Ada</span><br>
        @endfor
      </div>
    </td>
    <td width="20%" style="vertical-align: top;">
      <div class="signature" style="margin-top: 40px;">
        Disetujui<br><br><br>
        Mkt - Frc Mgr
      </div>
    </td>
  </tr>

  <!-- Row 2 -->
  <tr>
    <td width="50%" style="border-right: none;">
      <div class="section-title">2. Project dan Maintenance Dept</div>
      <div>* Selisih RAB Final VS Est LPD</div>
      <div>* Tagihan BAPJ</div>
      <div>* LPD Project</div>
      <div>* Pekerjaan Oleh Frcsee</div>
      <div>* Outstanding biaya terkait ({{ $data['jns_toko'] }} - {{ $data['nama_toko'] }})</div>
      <div>* Lain-lain :</div>
      <div>-</div>
    </td>
    <td width="30%" style="vertical-align: top; border-left: none;">
      <div class="check-options" style="margin-top: 20px;">
        @for($i = 0; $i < 5; $i++)
          <span>O Clear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Belum&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Tidak Ada</span><br>
        @endfor
      </div>
    </td>
    <td width="20%" style="vertical-align: top;">
      <div class="signature" style="margin-top: 30px;">
        Disetujui<br><br><br>
        Proj Mgr
      </div>
    </td>
  </tr>
  
  <!-- Row 3 -->
  <tr>
    <td width="50%" style="border-right: none;">
      <div class="section-title">3. Finance dan Tax Dept</div>
      <div>* Pemastian invoice AP (BAPJ, SJ, Perakitan, BTB, dsb)</div>
      <div>* SWO Modal dan Cadangan Dana</div>
      <div>* Pph atas sewa</div>
      <div>* Outstanding biaya terkait ({{ $data['jns_toko'] }} - {{ $data['nama_toko'] }})</div>
      <div>* Lain-lain :</div>
      <div>-</div>
    </td>
    <td width="30%" style="vertical-align: top; border-left: none;">
      <div class="check-options" style="margin-top: 20px;">
        @for($i = 0; $i < 4; $i++)
          <span>O Clear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Belum&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Tidak Ada</span><br>
        @endfor
      </div>
    </td>
    <td width="20%" style="vertical-align: top;">
      <div class="signature" style="margin-top: 30px;">
        Disetujui<br><br><br>
        Office Mgr
      </div>
    </td>
  </tr>

  <!-- Row 4 -->
  <tr>
    <td width="50%" style="border-right: none;">
      <div class="section-title">4. GA Dept.</div>
      <div>* PP AT Lengkap</div>
      <div>* BTB AT Lengkap</div>
      <div>* Alokasi Sarana Toko (SJ)</div>
      <div>* Proses P3AT</div>
      <div>* Outstanding biaya terkait ({{ $data['jns_toko'] }} - {{ $data['nama_toko'] }})</div>
      <div>* Lain-lain :</div>
      <div>-</div>
    </td>
    <td width="30%" style="vertical-align: top; border-left: none;">
      <div class="check-options" style="margin-top: 20px;">
        @for($i = 0; $i < 5; $i++)
          <span>O Clear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Belum&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Tidak Ada</span><br>
        @endfor
      </div>
    </td>
    <td width="20%" style="vertical-align: top;">
      <div class="signature" style="margin-top: 30px;">
        Disetujui<br><br><br>
        GA Mgr
      </div>
    </td>
  </tr>

  <!-- Row 5 -->
  <tr>
    <td width="50%" style="border-right: none;">
      <div class="section-title">5. EDP Dept.</div>
      <div>* Alokasi Assembly / Perakitan</div>
      <div>* Outstanding biaya terkait ({{ $data['jns_toko'] }} - {{ $data['nama_toko'] }})</div>
      <div>* Lain-lain :</div>
      <div>-</div>
    </td>
    <td width="30%" style="vertical-align: top; border-left: none;">
      <div class="check-options" style="margin-top: 20px;">
        @for($i = 0; $i < 2; $i++)
          <span>O Clear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Belum&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Tidak Ada</span><br>
        @endfor
      </div>
    </td>
    <td width="20%" style="vertical-align: top;">
      <div class="signature" style="margin-top: 10px;">
        Disetujui<br><br><br>
        EDP Mgr
      </div>
    </td>
  </tr>

  <!-- Row 6 -->
  <tr>
    <td width="50%" style="border-right: none;">
      <div class="section-title">6. License Dept.</div>
      <div>* Sewa Lahan / Gedung</div>
      <div>* Akte Sewa atau Perjanjian Pengalihan Sewa terakhir</div>
      <div>* Outstanding biaya terkait ({{ $data['jns_toko'] }} - {{ $data['nama_toko'] }})</div>
      <div>* Lain-lain :</div>
      <div>-</div>
    </td>
    <td width="30%" style="vertical-align: top; border-left: none;">
      <div class="check-options" style="margin-top: 20px;">
        @for($i = 0; $i < 3; $i++)
          <span>O Clear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Belum&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Tidak Ada</span><br>
        @endfor
      </div>
    </td>
    <td width="20%" style="vertical-align: top;">
      <div class="signature" style="margin-top: 30px;">
        Disetujui<br><br><br>
        License Mgr
      </div>
    </td>
  </tr>

  <!-- Row 7 -->
  <tr>
    <td width="50%" style="border-right: none;">
      <div class="section-title">7. Development Dept.</div>
      <div>* Pertanggungjawaban PUM Grand Opening Toko</div>
      <div>* Sarana Promosi</div>
      <div>* Outstanding biaya terkait ({{ $data['jns_toko'] }} - {{ $data['nama_toko'] }})</div>
      <div>* Lain-lain :</div>
      <div>-</div>
    </td>
    <td width="30%" style="vertical-align: top; border-left: none;">
      <div class="check-options" style="margin-top: 20px;">
        @for($i = 0; $i < 3; $i++)
          <span>O Clear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Belum&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Tidak Ada</span><br>
        @endfor
      </div>
    </td>
    <td width="20%" style="vertical-align: top;">
      <div class="signature" style="margin-top: 20px;">
        Disetujui<br><br><br>
        Dev Mgr
      </div>
    </td>
  </tr>

  <!-- Row 8 -->
  <tr>
    <td width="50%" style="border-right: none;">
      <div class="section-title">8. Accounting Dept.</div>
      <div>* Alokasi Sarana Toko (SJ) dan Assembly/Perakitan</div>
      <div>* P3AT</div>
      <div>* Pengalihan Sewa</div>
      <div>* Penagihan Frc Fee</div>
      <div>* Penagihan Jasa Rekrut dan Training</div>
      <div>* Outstanding Aktiva / Prepaid di RAB </div>
      <div>* BA Pot Surkas  (Bila ada )</div>
      <div>* Outstanding biaya terkait ({{ $data['jns_toko'] }} - {{ $data['nama_toko'] }})</div>
      <div>* Lain-lain :</div>
      <div>-</div>
    </td>
    <td width="30%" style="vertical-align: top; border-left: none;">
      <div class="check-options" style="margin-top: 20px;">
        @for($i = 0; $i < 8; $i++)
          <span>O Clear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Belum&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Tidak Ada</span><br>
        @endfor
      </div>
    </td>
    <td width="20%" style="vertical-align: top;">
      <div class="signature" style="margin-top: 60px;">
        Disetujui<br><br><br>
        Region Adm Mgr
      </div>
    </td>
  </tr>

  <!-- Row 9 -->
  <tr>
    <td width="50%" style="border-right: none;">
      <div class="section-title">9. Finance Dept. (AP Region)</div>
      <div>* Perhitungan Detail LPD</div>
      <div>* Rekonsiliasi RAB Detail VS Realisasi LPD (Sarana Toko)</div>
      <div>* Rekonsiliasi DAT/PR VS Realisasi LPD</div>
      <div>* Outstanding biaya terkait ({{ $data['jns_toko'] }} - {{ $data['nama_toko'] }})</div>
      <div>* Lain-lain :</div>
      <div>-</div>
    </td>
    <td width="30%" style="vertical-align: top; border-left: none;">
      <div class="check-options" style="margin-top: 20px;">
        @for($i = 0; $i < 4; $i++)
          <span>O Clear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Belum&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Tidak Ada</span><br>
        @endfor
      </div>
    </td>
    <td width="20%" style="vertical-align: top;">
      <div class="signature" style="margin-top: 40px;">
        Disetujui<br><br><br>
        Region Fin Mgr (AP)
      </div>
    </td>
  </tr>

  <!-- Row 10 -->
  <tr>
    <td width="50%" style="border-right: none;">
      <div class="section-title">10. Finance Dept. (AR Region)</div>
      <div>* Modal dan Cadangan Dana</div>
      <div>* Outstanding biaya terkait ({{ $data['jns_toko'] }} - {{ $data['nama_toko'] }})</div>
      <div>* Lain-lain :</div>
      <div>-</div>
    </td>
    <td width="30%" style="vertical-align: top; border-left: none;">
      <div class="check-options" style="margin-top: 20px;">
        @for($i = 0; $i < 2; $i++)
          <span>O Clear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Belum&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;O Tidak Ada</span><br>
        @endfor
      </div>
    </td>
    <td width="20%" style="vertical-align: top;">
      <div class="signature" style="margin-top: 10px;">
        Disetujui<br><br><br>
        Region Fin Mgr (AR)
      </div>
    </td>
  </tr>
</table>

</body>
</html>
