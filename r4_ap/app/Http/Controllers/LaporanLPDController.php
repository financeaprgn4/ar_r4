<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use App\Helpers\DateHelper;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanLPDController extends Controller
{
    public function export(Request $request)
    {
        $no_rab = $request->query('no_rab');

        $lpd = DB::table('lpd')
            ->join('lpd_realisasi_detail', 'lpd.no_rab', '=', 'lpd_realisasi_detail.rab')
            ->where('lpd.no_rab', $no_rab)
            ->select('lpd.*', 'lpd_realisasi_detail.*')
            ->first();

        if (!$lpd) {
            return response()->json(['message' => 'Data LPD tidak ditemukan'], 404);
        }

        $templatePath = 'C:\\xampp\\htdocs\\AP_R4\\File\\draft\\Form LPD.xlsx';
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getSheetByName('Lpd');

        if (!$sheet) {
            return response()->json(['message' => 'Sheet "Lpd" tidak ditemukan'], 500);
        }

        $rab_renov = $lpd->rab_fg + $lpd->rab_kanopi + $lpd->rab_ins_ac + $lpd->rab_teralis +
                     $lpd->rab_halaman + $lpd->rab_policarbonate + $lpd->rab_listrik +
                     $lpd->rab_aluminium_kaca + $lpd->rab_signage + $lpd->rab_sipil + $lpd->rab_interior;

        $total_rab = $lpd->rab_frc_fee + $lpd->rab_promo + $lpd->rab_rekrut_train +
                     $lpd->rab_sw_pph + $lpd->rab_jasa_pihak3 + $rab_renov +
                     $lpd->rab_prasarana + $lpd->rab_peralatan;

        $sheet->setCellValue('B2', 'INDOMARET ' . $lpd->kd_toko . ' - ' . $lpd->nama_toko);
        $sheet->setCellValue('B3', DateHelper::formatTanggalExcelLabel('TANGGAL WARALABA', $lpd->tgl_wrlb));
        $sheet->setCellValue('B4', 'NAMA PEMILIK   : ' . $lpd->badan);
        $sheet->setCellValue('D4', $lpd->kd_toko . ' - ' . $lpd->nama_toko);

        $sheet->setCellValue('C10', $lpd->rab_frc_fee);
        $sheet->setCellValue('C14', $lpd->rab_frc_fee);
        $sheet->setCellValue('C16', $lpd->rab_promo);
        $sheet->setCellValue('C20', $lpd->rab_promo);
        $sheet->setCellValue('C23', $lpd->rab_rekrut_train);
        $sheet->setCellValue('C26', $lpd->rab_rekrut_train);
        $sheet->setCellValue('C28', $rab_renov);
        $sheet->setCellValue('C32', $rab_renov);
        $sheet->setCellValue('C34', $lpd->rab_jasa_pihak3);
        $sheet->setCellValue('C38', $lpd->rab_jasa_pihak3);
        $sheet->setCellValue('C40', $lpd->rab_prasarana);
        $sheet->setCellValue('C44', $lpd->rab_prasarana);
        $sheet->setCellValue('C46', $lpd->rab_peralatan);
        $sheet->setCellValue('C50', $lpd->rab_peralatan);
        $sheet->setCellValue('C52', $lpd->rab_sw_pph);
        $sheet->setCellValue('C56', $lpd->rab_sw_pph);
        $sheet->setCellValue('C58', $total_rab);

        $modalDetails = DB::table('modal_detail')->where('rab', $no_rab)->get();
        $modalCount = $modalDetails->count();
        $startRowModal = 62;

        if ($modalCount > 1) {
            $sheet->insertNewRowBefore($startRowModal + 1, $modalCount - 1);
        }

        $currentRow = $startRowModal;
        foreach ($modalDetails as $modal) {
            $sheet->setCellValue("B{$currentRow}", ExcelDate::PHPToExcel(new \DateTime($modal->tgl_bbt)));
            $sheet->getStyle("B{$currentRow}")->getNumberFormat()->setFormatCode('dd-mmm-yy');
            $sheet->setCellValue("C{$currentRow}", $modal->nilai);
            $sheet->setCellValue("D{$currentRow}", $modal->bbt);
            $currentRow++;
        }

        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $lpd->kd_toko);
        $data = DB::table($tableName)->where('rab', $no_rab)->get()->groupBy('kd_group');

        $datPrMap = DB::table('dat_pr')
            ->select('flag_realisasi', DB::raw('GROUP_CONCAT(seri SEPARATOR "-") as seri_concat'))
            ->groupBy('flag_realisasi')
            ->pluck('seri_concat', 'flag_realisasi');

        $groupRowMap = [
            '030001' => 11,
            '030002' => 17,
            '030003' => 23 + ($data->get('030002')?->count() ?? 0),
            '030006' => 29 + ($data->get('030002')?->count() ?? 0) + ($data->get('030003')?->count() ?? 0),
            '030005' => 35 + ($data->get('030006')?->count() ?? 0) + ($data->get('030003')?->count() ?? 0) + ($data->get('030002')?->count() ?? 0),
            '030007' => 41 + ($data->get('030005')?->count() ?? 0) + ($data->get('030006')?->count() ?? 0) + ($data->get('030003')?->count() ?? 0) + ($data->get('030002')?->count() ?? 0),
            '030008' => 47 + ($data->get('030007')?->count() ?? 0) + ($data->get('030005')?->count() ?? 0) + ($data->get('030006')?->count() ?? 0) + ($data->get('030003')?->count() ?? 0) + ($data->get('030002')?->count() ?? 0),
            '030004' => 53 + ($data->get('030008')?->count() ?? 0) + ($data->get('030007')?->count() ?? 0) + ($data->get('030005')?->count() ?? 0) + ($data->get('030006')?->count() ?? 0) + ($data->get('030003')?->count() ?? 0) + ($data->get('030002')?->count() ?? 0),
        ];

        foreach ($groupRowMap as $kd_group => $startRow) {
            $rows = $data->get($kd_group, collect());
            $count = $rows->count();

            if ($count > 1) {
                $sheet->insertNewRowBefore($startRow, $count);
            }

            $rowNum = $startRow;
            foreach ($rows as $item) {
                $sheet->setCellValue("D{$rowNum}", $item->keterangan);
                $sheet->setCellValue("E{$rowNum}", $item->dpp);
                $sheet->setCellValue("F{$rowNum}", $item->ppn);
                $sheet->setCellValue("G{$rowNum}", $item->total);
                $sheet->setCellValue("H{$rowNum}", $item->inv_num);
                $sheet->setCellValue("I{$rowNum}", 
                    empty($item->flag_dat_pr) 
                        ? 'Belum Input' 
                        : ($datPrMap[$item->flag_dat_pr] ?? 'Belum Input')
                );
                $rowNum++;
            }

            if ($count === 0) {
                $namedRow = $startRow + 3;
            } elseif ($count === 1) {
                $namedRow = $startRow + $count + 2;
            } else {
                $namedRow = $startRow + $count + 3;
            }

            $colRAB   = '$C$' . $namedRow;
            $colDPP   = '$E$' . $namedRow;
            $colPPN   = '$F$' . $namedRow;
            $colTotal = '$G$' . $namedRow;

            $nameRAB   = strtoupper("RAB_{$kd_group}");
            $nameDPP   = strtoupper("DPP_{$kd_group}");
            $namePPN   = strtoupper("PPN_{$kd_group}");
            $nameTotal = strtoupper("TOTAL_{$kd_group}");
            
            $spreadsheet->addNamedRange(new \PhpOffice\PhpSpreadsheet\NamedRange($nameRAB, $sheet, $colRAB));
            $spreadsheet->addNamedRange(new \PhpOffice\PhpSpreadsheet\NamedRange($nameDPP, $sheet, $colDPP));
            $spreadsheet->addNamedRange(new \PhpOffice\PhpSpreadsheet\NamedRange($namePPN, $sheet, $colPPN));
            $spreadsheet->addNamedRange(new \PhpOffice\PhpSpreadsheet\NamedRange($nameTotal, $sheet, $colTotal));
        }

        $rekapSheet = $spreadsheet->getSheetByName('Rekap Update');
        $rekapSheet->setCellValue('B3', 'INDOMARET ' . $lpd->kd_toko . ' - ' . $lpd->nama_toko);
        $rekapSheet->setCellValue('B4', DateHelper::formatTanggalExcelLabel('TANGGAL WARALABA', $lpd->tgl_wrlb));
        $rekapSheet->setCellValue('B5', 'NAMA PEMILIK   : ' . $lpd->badan);
        $rekapSheet->setCellValue('C3', $lpd->kd_toko . ' - ' . $lpd->nama_toko);
        
        $jnsTokoMap = [
            'NS' => 'NEW STORE',
            'UP' => 'UPGRADE',
            'PPJ' => 'PERPANJANGAN',
            'RE' => 'RELOKASI',
        ];
        $jnsTokoLabel = $jnsTokoMap[$lpd->jns_toko] ?? $lpd->jns_toko;
        $rekapSheet->setCellValue('H5', $jnsTokoLabel);

        $startModal = 25;
        if ($modalCount > 4) {
            $rowsToInsert = $modalCount - 4;
            $insertAt = $startModal + 4;
            $rekapSheet->insertNewRowBefore($insertAt, $rowsToInsert);
            for ($row = $startModal + $modalCount - 1; $row >= $insertAt; $row--) {
                $fromRow = $row - $rowsToInsert;
                for ($col = 'B'; $col <= 'D'; $col++) {
                    $value = $rekapSheet->getCell("{$col}{$fromRow}")->getValue();
                    $rekapSheet->setCellValue("{$col}{$row}", $value);
                    $rekapSheet->setCellValue("{$col}{$fromRow}", null);
                }
            }
        }


        $currentRow = $startModal;
        foreach ($modalDetails as $modal) {
            $rekapSheet->setCellValue("B{$currentRow}", ExcelDate::PHPToExcel(new \DateTime($modal->tgl_bbt)));
            $rekapSheet->getStyle("B{$currentRow}")->getNumberFormat()->setFormatCode('dd-mmm-yy');
            $rekapSheet->setCellValue("C{$currentRow}", $modal->nilai);
            $rekapSheet->setCellValue("D{$currentRow}", $modal->keterangan);
            $currentRow++;
        }

        // SHEET MONITORING SARANA
        $monitorsarana = DB::table('pp')
            ->where('rab', $no_rab)
            ->get();

        $countmonitorsarana = $monitorsarana->count();
        $sheetmonitor = $spreadsheet->getSheetByName('Monitoring Sarana');
        if (!$sheetmonitor) {
            return response()->json(['message' => 'Sheet "Monitoring Sarana" tidak ditemukan'], 500);
        }

        if ($countmonitorsarana > 1) {
            $sheetmonitor->insertNewRowBefore(3, $countmonitorsarana - 1);
        }

        $row = 2;
        $no = 1;

        foreach ($monitorsarana as $item) {
            $sheetmonitor->setCellValue("A{$row}", $no++);
            $sheetmonitor->setCellValue("B{$row}", $item->rab);
            $sheetmonitor->setCellValue("C{$row}", DateHelper::formatTanggalExcel($item->tgl_rab));
            $sheetmonitor->setCellValue("D{$row}", $item->plu);
            $sheetmonitor->setCellValue("E{$row}", $item->barang);
            $sheetmonitor->setCellValue("F{$row}", $item->qty_barang);
            $sheetmonitor->setCellValue("G{$row}", $item->pp);
            $sheetmonitor->setCellValue("H{$row}", DateHelper::formatTanggalExcel($item->tgl_pp));
            $sheetmonitor->setCellValue("I{$row}", $item->qty_pp);
            $sheetmonitor->setCellValue("J{$row}", $item->sp);
            $sheetmonitor->setCellValue("K{$row}", DateHelper::formatTanggalExcel($item->tgl_sp));
            $sheetmonitor->setCellValue("L{$row}", $item->qty_sp);
            $sheetmonitor->setCellValue("M{$row}", DateHelper::formatTanggalExcel($item->exp_sp));
            $sheetmonitor->setCellValue("N{$row}", $item->sp_awal ?? '');
            $sheetmonitor->setCellValue("O{$row}", DateHelper::formatTanggalExcel($item->tgl_sp_awal));
            $sheetmonitor->setCellValue("P{$row}", DateHelper::formatTanggalExcel($item->exp_sp_awal));
            $sheetmonitor->setCellValue("Q{$row}", $item->btb ?? '');
            $sheetmonitor->setCellValue("R{$row}", DateHelper::formatTanggalExcel($item->tgl_btb));
            $sheetmonitor->setCellValue("S{$row}", $item->qty_btb ?? '');

            // --- Kolom T ---
            $isiKolomT = '';

            // ✅ PRIORITAS: jika surkas = Y
            if (isset($item->surkas) && strtoupper($item->surkas) === 'Y') {
                $isiKolomT = 'Non LPD';

            } else {
                // 🔁 logic lama
                if (!empty($item->btb)) {
                    $kdToko = DB::table('lpd')
                        ->where('no_rab', $item->rab)
                        ->value('kd_toko');

                    if ($kdToko) {
                        $tableName = strtolower($kdToko);

                        try {
                            $adaInvoice = DB::table($tableName)
                                ->where('inv_num', 'like', '%' . $item->btb . '%')
                                ->exists();

                            if ($adaInvoice) {
                                $isiKolomT = 'Y';
                            }
                        } catch (\Exception $e) {
                            // Jika tabel tidak ada atau error, biarkan kosong
                            $isiKolomT = '';
                        }
                    }
                }
            }

            $sheetmonitor->setCellValue("T{$row}", $isiKolomT);
            $row++;
        }

        foreach (range('A', 'T') as $col) {
            $sheetmonitor->getColumnDimension($col)->setAutoSize(true);
        }

        // SHEET KURANG REALISASI
        $kurangRealisasi = DB::table('sarana_toko')
            ->where('rab', $no_rab)
            ->where(function ($query) {
                $query->whereNull('flag_realisasi')
                    ->orWhere('flag_realisasi', '')
                    ->orWhere('flag_realisasi', 'NONPP')
                    ->orWhere('flag_realisasi', 'SEWA')
                    ->orWhere('flag_realisasi', 'SURKAS')
                    ->orWhere('flag_realisasi', 'FRCSEE')
                    ->orWhere('flag_realisasi', 'RENOVASI')
                    ->orWhere('flag_realisasi', 'MKT');
            })
            ->get();

        $lpdDetail = DB::table('lpd_realisasi_detail')
            ->where('rab', $no_rab)
            ->first();

        $sheetKurang = $spreadsheet->getSheetByName('Kurang Realisasi');

        if (!$sheetKurang) {
            return response()->json([
                'message' => 'Sheet "Kurang Realisasi" tidak ditemukan'
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | DATA SARANA TOKO
        |--------------------------------------------------------------------------
        */

        $dataKurang = [];

        foreach ($kurangRealisasi as $item) {

            switch ($item->flag_realisasi) {
                case 'MKT':
                    $keterangan = 'BA Tidak Realisasi';
                    break;
                case 'SEWA':
                    $keterangan = 'Sewa';
                    break;
                case 'FRCSEE':
                    $keterangan = 'By Frcsee';
                    break;
                case 'NONPP':
                    $keterangan = 'Tidak PP';
                    break;
                case 'SURKAS':
                    $keterangan = 'Potong Surkas';
                    break;
                case 'RENOVASI':
                    $keterangan = 'Renovasi Fisik';
                    break;
                default:
                    $keterangan = '';
                    break;
            }

            $dataKurang[] = [
                'kategori' => $item->kategori,
                'kode' => $item->kode,
                'uraian' => $item->uraian,
                'satuan' => $item->satuan,
                'qty' => $item->qty,
                'harga_satuan' => $item->harga_satuan,
                'dpp' => $item->dpp,
                'ppn' => $item->ppn,
                'total' => $item->total,
                'keterangan' => $keterangan
            ];
        }

        // Tambahkan 1 baris kosong sebagai pemisah
        if (count($dataKurang) > 0 && $lpdDetail) {
            $dataKurang[] = [
                'is_empty' => true
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | DATA LPD REALISASI DETAIL
        |--------------------------------------------------------------------------
        */

        if ($lpdDetail) {

            $mapping = [
                [
                    'flag' => 'flag_realisasi_fg',
                    'rab' => 'rab_fg',
                    'realisasi' => 'realisasi_fg',
                    'nama' => 'Pekerjaan Folding Gate'
                ],
                [
                    'flag' => 'flag_realisasi_kanopi',
                    'rab' => 'rab_kanopi',
                    'realisasi' => 'realisasi_kanopi',
                    'nama' => 'Pekerjaan Kanopi'
                ],
                [
                    'flag' => 'flag_realisasi_ins_ac',
                    'rab' => 'rab_ins_ac',
                    'realisasi' => 'realisasi_ins_ac',
                    'nama' => 'Pekerjaan Instalasi AC'
                ],
                [
                    'flag' => 'flag_realisasi_teralis',
                    'rab' => 'rab_teralis',
                    'realisasi' => 'realisasi_teralis',
                    'nama' => 'Pekerjaan Teralis'
                ],
                [
                    'flag' => 'flag_realisasi_halaman',
                    'rab' => 'rab_halaman',
                    'realisasi' => 'realisasi_halaman',
                    'nama' => 'Pekerjaan Halaman'
                ],
                [
                    'flag' => 'flag_realisasi_policarbonate',
                    'rab' => 'rab_policarbonate',
                    'realisasi' => 'realisasi_policarbonate',
                    'nama' => 'Pekerjaan Polycarbonate'
                ],
                [
                    'flag' => 'flag_realisasi_listrik',
                    'rab' => 'rab_listrik',
                    'realisasi' => 'realisasi_listrik',
                    'nama' => 'Pekerjaan Listrik'
                ],
                [
                    'flag' => 'flag_realisasi_aluminium_kaca',
                    'rab' => 'rab_aluminium_kaca',
                    'realisasi' => 'realisasi_aluminium_kaca',
                    'nama' => 'Pekerjaan Aluminium & Kaca'
                ],
                [
                    'flag' => 'flag_realisasi_interior',
                    'rab' => 'rab_interior',
                    'realisasi' => 'realisasi_interior',
                    'nama' => 'Pekerjaan Interior & Eksterior'
                ],
                [
                    'flag' => 'flag_realisasi_lift',
                    'rab' => 'rab_lift',
                    'realisasi' => 'realisasi_lift',
                    'nama' => 'Pekerjaan Lift'
                ],
                [
                    'flag' => 'flag_realisasi_sipil',
                    'rab' => 'rab_sipil',
                    'realisasi' => 'realisasi_sipil',
                    'nama' => 'Pekerjaan Sipil'
                ],
                [
                    'flag' => 'flag_realisasi_signage',
                    'rab' => 'rab_signage',
                    'realisasi' => 'realisasi_signage',
                    'nama' => 'Pekerjaan Signage'
                ],
                [
                    'flag' => 'flag_realisasi_urugan',
                    'rab' => 'rab_urugan',
                    'realisasi' => 'realisasi_urugan',
                    'nama' => 'Pekerjaan Urugan'
                ],
            ];

            foreach ($mapping as $map) {

                $flag = $map['flag'];
                $rab = $map['rab'];
                $realisasi = $map['realisasi'];

                if (
                    $lpdDetail->$flag == 'Y' &&
                    (float)$lpdDetail->$realisasi == 0
                ) {

                    $dataKurang[] = [
                        'kategori' => '',
                        'kode' => '',
                        'uraian' => '',
                        'satuan' => '',
                        'qty' => '',
                        'harga_satuan' => '',
                        'dpp' => '',
                        'ppn' => '',
                        'total' => $lpdDetail->$rab,
                        'keterangan' => '',
                        'nama_pekerjaan' => $map['nama']
                    ];
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT ROW
        |--------------------------------------------------------------------------
        */

        $countKurang = count($dataKurang);

        if ($countKurang > 1) {
            $sheetKurang->insertNewRowBefore(4, $countKurang - 1);
        }

        $row = 3;
        $no = 1;

        foreach ($dataKurang as $item) {
            // Jika baris kosong
            if (isset($item['is_empty']) && $item['is_empty'] === true) {
                $row++;
                continue;
            }

            $sheetKurang->setCellValue("A{$row}", $no++);

            $sheetKurang->setCellValue(
                "B{$row}",
                $item['nama_pekerjaan'] ?? $item['kategori']
            );

            $sheetKurang->setCellValue("C{$row}", $item['kode']);
            $sheetKurang->setCellValue("D{$row}", $item['uraian']);
            $sheetKurang->setCellValue("E{$row}", $item['satuan']);
            $sheetKurang->setCellValue("F{$row}", $item['qty']);
            $sheetKurang->setCellValue("G{$row}", $item['harga_satuan']);
            $sheetKurang->setCellValue("H{$row}", $item['dpp']);
            $sheetKurang->setCellValue("I{$row}", $item['ppn']);
            $sheetKurang->setCellValue("J{$row}", $item['total']);

            $sheetKurang->setCellValue(
                "K{$row}",
                $item['keterangan']
            );

            $row++;
        }

        foreach (range('A', 'K') as $col) {
            $sheetKurang->getColumnDimension($col)->setAutoSize(true);
        }

        // SHEET LEBIH REALISASI
        $lebihRealisasi = DB::table($lpd->kd_toko)
            ->where('rab', $no_rab)
            ->where(function ($query) {
                $query->whereNull('flag_sarana')
                    ->orWhere('flag_sarana', '');
            })
            ->where('kd_group', '030008')
            ->get();

        $lebihCount = $lebihRealisasi->count();
        $sheetLebih = $spreadsheet->getSheetByName('Lebih Realisasi');
        $startLebihRow = 4;

        if ($lebihCount > 0) {
            $sheetLebih->insertNewRowBefore($startLebihRow, $lebihCount);
        }

        $row = 3;
        $no = 1;
        foreach ($lebihRealisasi as $item) {
            $sheetLebih->setCellValue("A{$row}", $no++);
            $sheetLebih->setCellValue("B{$row}", $item->keterangan);
            $sheetLebih->setCellValue("C{$row}", $item->dpp);
            $sheetLebih->setCellValue("D{$row}", $item->ppn);
            $sheetLebih->setCellValue("E{$row}", $item->total);
            $sheetLebih->setCellValue("F{$row}", $item->inv_num);
            $row++;
        }
        
        foreach (range('A', 'F') as $col) {
            $sheetLebih->getColumnDimension($col)->setAutoSize(true);
        }

        // SHEET Trx Blm Input AT-PR
        $atpr = DB::table($lpd->kd_toko)
            ->where('rab', $no_rab)
            ->where(function ($query) {
                $query->whereNull('flag_dat_pr')
                    ->orWhere('flag_dat_pr', '');
            })
            ->get();

        $atprcount = $atpr->count();
        $sheetatpr = $spreadsheet->getSheetByName('Trx Blm Input AT-PR');

        if ($atprcount > 0) {
            $sheetatpr->insertNewRowBefore(3, $atprcount);
        }

        $row = 2;
        $no = 1;
        foreach ($atpr as $item) {
            $sheetatpr->setCellValue("A{$row}", $no++);
            $sheetatpr->setCellValue("B{$row}", $item->keterangan);
            $sheetatpr->setCellValue("C{$row}", $item->dpp);
            $sheetatpr->setCellValue("D{$row}", $item->ppn);
            $sheetatpr->setCellValue("E{$row}", $item->total);
            $sheetatpr->setCellValue("F{$row}", $item->inv_num);
            $row++;
        }
        foreach (range('A', 'F') as $col) {
            $sheetatpr->getColumnDimension($col)->setAutoSize(true);
        }

        // SHEET DAT-PR
        $flags = DB::table('dat_pr')
            ->where('rab', $no_rab)
            ->where('surkas', '!=', 'Y')
            ->distinct()
            ->pluck('flag_realisasi');

        $datPrSheet = $spreadsheet->getSheetByName('DAT-PR');

        $b_klop_x = 3;
        $b_klop = 2;
        $no = 1;

        // === BLOK 1: Data DAT-PR (surkas != 'Y') ===
        foreach ($flags as $reff) {
            $z_data = DB::table('dat_pr')
                ->where('rab', $no_rab)
                ->where('surkas', '!=', 'Y')
                ->where('flag_realisasi', $reff)
                ->get();

            $detail_rows = DB::table($lpd->kd_toko)
                ->where('rab', $no_rab)
                ->where('flag_dat_pr', $reff)
                ->get();

            $insert_rows = max($z_data->count(), $detail_rows->count());
            if ($insert_rows > 0) {
                $datPrSheet->insertNewRowBefore($b_klop_x, $insert_rows + 1);
            }

            for ($i = 0; $i < $insert_rows; $i++) {
                if ($i === 0) {
                    $datPrSheet->setCellValue("A{$b_klop}", $no);
                } else {
                    $datPrSheet->setCellValue("A{$b_klop}", '');
                }

                if (isset($z_data[$i])) {
                    $z = $z_data[$i];
                    $datPrSheet->setCellValue("B{$b_klop}", $z->site);
                    $datPrSheet->setCellValue("C{$b_klop}", $z->seri);
                    $datPrSheet->setCellValue("D{$b_klop}", $z->keterangan);
                    $datPrSheet->setCellValue("E{$b_klop}", $z->surkas);
                    $datPrSheet->setCellValue("F{$b_klop}", $z->harga);
                    $datPrSheet->setCellValue("G{$b_klop}", $z->inv_num);

                    if (!empty($z->tgl_perolehan) && $z->tgl_perolehan !== '0000-00-00') {
                        $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime($z->tgl_perolehan));
                        $datPrSheet->setCellValue("H{$b_klop}", $excelDate);
                        $datPrSheet->getStyle("H{$b_klop}")->getNumberFormat()->setFormatCode('dd-mmm-yy');
                    } else {
                        $datPrSheet->setCellValue("H{$b_klop}", '');
                    }
                }

                if (isset($detail_rows[$i])) {
                    $y = $detail_rows[$i];
                    $datPrSheet->setCellValue("I{$b_klop}", $y->inv_num);
                    $datPrSheet->setCellValue("J{$b_klop}", $y->keterangan);
                    $datPrSheet->setCellValue("K{$b_klop}", $y->dpp);
                }

                $b_klop++;
            }

            // Tambahkan 1 baris kosong antar grup
            $b_klop++;
            $no++;
            $b_klop_x = $b_klop;
        }

        // === BLOK 2: Data DAT-PR dengan surkas = 'Y' ===

        // Geser 6 baris dari data terakhir sebelumnya
        $b_klop += 4;

        $z_data_y = DB::table('dat_pr')
            ->where('rab', $no_rab)
            ->where('surkas', 'Y')
            ->get();

        if ($z_data_y->count() > 0) {
            $datPrSheet->insertNewRowBefore($b_klop, $z_data_y->count());
            $b_klop++;

            foreach ($z_data_y as $z) {
                $datPrSheet->setCellValue("A{$b_klop}", $no);
                $datPrSheet->setCellValue("B{$b_klop}", $z->site);
                $datPrSheet->setCellValue("C{$b_klop}", $z->seri);
                $datPrSheet->setCellValue("D{$b_klop}", $z->keterangan);
                $datPrSheet->setCellValue("E{$b_klop}", $z->surkas);
                $datPrSheet->setCellValue("F{$b_klop}", $z->harga);
                $datPrSheet->setCellValue("G{$b_klop}", $z->inv_num);

                if (!empty($z->tgl_perolehan) && $z->tgl_perolehan !== '0000-00-00') {
                    $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime($z->tgl_perolehan));
                    $datPrSheet->setCellValue("H{$b_klop}", $excelDate);
                    $datPrSheet->getStyle("H{$b_klop}")->getNumberFormat()->setFormatCode('dd-mmm-yy');
                } else {
                    $datPrSheet->setCellValue("H{$b_klop}", '');
                }

                $b_klop++;
                $no++;
            }
        }

        // Auto-size kolom
        foreach (range('A', 'K') as $col) {
            $datPrSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet "Sarana Toko"
        $sheetSarana = $spreadsheet->getSheetByName('Sarana Toko');
        $barisAwal = 3;
        $barisInsert = 4;
        $no = 1;

        $flags = DB::table('sarana_toko')
            ->where('rab', $no_rab)
            ->whereNotIn('flag_realisasi', ['', 'FSEE', 'MKT', 'SEWA', 'RENOVASI'])
            ->distinct()
            ->pluck('flag_realisasi');

        foreach ($flags as $flag) {
            $dataSarana = DB::table('sarana_toko')
                ->where('rab', $no_rab)
                ->where('flag_realisasi', $flag)
                ->get();

            $dataTrx = DB::table($lpd->kd_toko)
                ->where('rab', $no_rab)
                ->where('flag_sarana', $flag)
                ->get();

            $insertRows = max($dataSarana->count(), $dataTrx->count());
            $sheetSarana->insertNewRowBefore($barisInsert, $insertRows);

            for ($i = 0; $i < $insertRows; $i++) {
                $sheetSarana->setCellValue("A{$barisAwal}", $no);

                $sarana = $dataSarana[$i] ?? null;
                $trx = $dataTrx[$i] ?? null;

                $sheetSarana->setCellValue("B{$barisAwal}", $sarana->kategori ?? '');
                $sheetSarana->setCellValue("C{$barisAwal}", $sarana->kode ?? '');
                $sheetSarana->setCellValue("D{$barisAwal}", $sarana->uraian ?? '');
                $sheetSarana->setCellValue("E{$barisAwal}", $sarana->satuan ?? '');
                $sheetSarana->setCellValue("F{$barisAwal}", $sarana->qty ?? '');
                $sheetSarana->setCellValue("G{$barisAwal}", $sarana->harga_satuan ?? '');
                $sheetSarana->setCellValue("H{$barisAwal}", $sarana->dpp ?? '');
                $sheetSarana->setCellValue("I{$barisAwal}", $sarana->ppn ?? '');
                $sheetSarana->setCellValue("J{$barisAwal}", $sarana->total ?? '');

                $sheetSarana->setCellValue("K{$barisAwal}", $trx->inv_num ?? '');
                $sheetSarana->setCellValue("L{$barisAwal}", $trx->plu ?? '');
                $sheetSarana->setCellValue("M{$barisAwal}", $trx->keterangan ?? '');
                $sheetSarana->setCellValue("N{$barisAwal}", $trx->dpp ?? '');
                $sheetSarana->setCellValue("O{$barisAwal}", $trx->ppn ?? '');
                $sheetSarana->setCellValue("P{$barisAwal}", $trx->total ?? '');

                $barisAwal++;
            }

            $no++;
            $barisInsert += $insertRows;
            $barisAwal = $barisInsert - 1;
        }

        $cols = range('B', 'P');
        foreach ($cols as $col) {
            $sheetSarana->getColumnDimension($col)->setAutoSize(true);
        }

        $safeNamaToko = preg_replace('/[\\/:*?"<>|]/', '_', $lpd->nama_toko);
        $safeTgl = str_replace(['-', '/'], '_', $lpd->tgl_wrlb);
        $filename = "{$safeNamaToko}_{$lpd->kd_toko}_{$lpd->jns_toko}_{$safeTgl}.xlsx";
        $outputPath = 'C:\\xampp\\htdocs\\AP_R4\\File\\lpd\\' . $filename;

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        DB::table('lpd')->where('no_rab', $no_rab)->update(['excel' => $filename]);

        return response()->json([
            'message' => 'Laporan berhasil dibuat dan disimpan.',
            'filename' => $filename,
        ]);
    }

    public function exportAll(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request) {

            $cabang = $request->query('cabang');

            $lpdsQuery = DB::table('lpd')
                ->where('status', '<>', 'final')
                ->where('report', '=', 'Y');

            if ($cabang) {
                $lpdsQuery->where('cabang', $cabang);
            }

            $lpds = $lpdsQuery->pluck('no_rab');
            $total = count($lpds);

            foreach ($lpds as $index => $no_rab) {
                try {
                    $req = new \Illuminate\Http\Request();
                    $req->query->set('no_rab', $no_rab);

                    $response = $this->export($req);

                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                    ];

                    if ($response->status() === 200) {
                        $data = $response->getData(true);
                        $result['filename'] = $data['filename'];
                        $result['message'] = $data['message'] ?? 'Berhasil';
                    } else {
                        $result['filename'] = null;
                        $result['message'] = 'Gagal memproses laporan';
                    }
                } catch (\Throwable $e) {
                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                        'filename' => null,
                        'message' => 'Error: ' . $e->getMessage(),
                    ];
                }

                echo 'data: ' . json_encode($result) . "\n\n";
                ob_flush();
                flush();
                usleep(50000);
            }

            echo "event: done\n";
            echo "data: selesai\n\n";
            ob_flush();
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    public function updateKeterangan(Request $request)
    {
        $no_rab = $request->query('no_rab');

        if (!$no_rab) {
            return response()->json(['error' => 'Parameter no_rab wajib diisi.'], 400);
        }

        $lpd = DB::table('lpd')
            ->where('no_rab', $no_rab)
            ->where('report', 'Y')
            ->select('jns_toko', 'status', 'kd_toko')
            ->first();

        if (!$lpd) {
            return response()->json(['error' => 'Data LPD tidak ditemukan.'], 404);
        }

        if (strtoupper($lpd->status) !== 'NEW') {
            return response()->json(['message' => 'Status bukan NEW, tidak dilakukan update.'], 200);
        }

        $modal = DB::table('modal')
            ->where('rab', $no_rab)
            ->select('keterangan')
            ->first();

        $realisasi = DB::table('lpd_realisasi_detail')
            ->where('rab', $no_rab)
            ->select(
                'realisasi_frc_fee',
                'realisasi_promo',
                'realisasi_rekrut_train',
                'flag_realisasi_fg', 'realisasi_fg',
                'flag_realisasi_kanopi', 'realisasi_kanopi',
                'flag_realisasi_ins_ac', 'realisasi_ins_ac',
                'flag_realisasi_teralis', 'realisasi_teralis',
                'flag_realisasi_halaman', 'realisasi_halaman',
                'flag_realisasi_policarbonate', 'realisasi_policarbonate',
                'flag_realisasi_listrik', 'realisasi_listrik',
                'flag_realisasi_aluminium_kaca', 'realisasi_aluminium_kaca',
                'flag_realisasi_signage', 'realisasi_signage',
                'flag_realisasi_interior', 'realisasi_interior',
                'flag_realisasi_sipil', 'realisasi_sipil',
                'keterangan', 'lampiran'
            )
            ->first();

        $keteranganModal = $modal->keterangan ?? null;
        $selrab = $realisasi->keterangan ?? null;
        $lampiran = $realisasi->lampiran ?? null;
        $frcFee = $realisasi->realisasi_frc_fee ?? 0;

        $kekurangan = [];

        // --- Cek Modal & Frc Fee ---
        if ($keteranganModal !== 'Clear') {
            $kekurangan[] = $keteranganModal;
        }
        
        if (
            !empty($selrab) &&
            empty($lampiran) &&
            stripos($selrab, 'clear') === false
        ) {
            $kekurangan[] = $selrab;
        }

        if (strtoupper($lpd->jns_toko) !== 'UP') {
            if ($frcFee == 0) {
                $kekurangan[] = 'Frc Fee';
            }
        }
        
        // --- Jika jns_toko = 'NS', tambahkan pengecekan promo & rekrut train ---
        if (strtoupper($lpd->jns_toko) === 'NS') {
            if (($realisasi->realisasi_promo ?? 0) < 1000000) {
                $kekurangan[] = 'Promosi GO';
            }

            if (($realisasi->realisasi_rekrut_train ?? 0) == 0) {
                $kekurangan[] = 'Jasa Rekrut & Training';
            }
        }

        // --- Tambahkan pengecekan untuk flag pekerjaan ---
        $flagChecks = [
            ['flag_realisasi_fg', 'realisasi_fg', 'Pek Folding Gate'],
            ['flag_realisasi_kanopi', 'realisasi_kanopi', 'Pek Kanopi'],
            ['flag_realisasi_ins_ac', 'realisasi_ins_ac', 'Pek Instalasi AC'],
            ['flag_realisasi_teralis', 'realisasi_teralis', 'Pek Teralis'],
            ['flag_realisasi_halaman', 'realisasi_halaman', 'Pek Halaman'],
            ['flag_realisasi_policarbonate', 'realisasi_policarbonate', 'Pek Polycarbonate'],
            ['flag_realisasi_listrik', 'realisasi_listrik', 'Pek Listrik'],
            ['flag_realisasi_aluminium_kaca', 'realisasi_aluminium_kaca', 'Pek Aluminium & Kaca'],
            ['flag_realisasi_signage', 'realisasi_signage', 'Pek Signage'],
            ['flag_realisasi_interior', 'realisasi_interior', 'Pek Interior & Eksterior'],
            ['flag_realisasi_sipil', 'realisasi_sipil', 'Pek Sipil'],
        ];

        $adaFlagY = false;
        $adaFlagN = false;
        $semuaNull = true;

        foreach ($flagChecks as [$flag, $realisasiField, $label]) {
            $flagValue = $realisasi->$flag ?? null;
            $realValue = $realisasi->$realisasiField ?? 0;

            if (!is_null($flagValue)) {
                $semuaNull = false;
            }

            if ($flagValue === 'Y') {
                $adaFlagY = true;
                if ($realValue == 0) {
                    $kekurangan[] = $label;
                }
            } elseif ($flagValue === 'N') {
                $adaFlagN = true;
            }
        }

        // --- Jika semua flag null ---
        if ($semuaNull) {
            $kekurangan[] = 'Renovasi Fisik';
        }

        $saranaExists = DB::table('sarana_toko')
            ->where('rab', $no_rab)
            ->exists();
            
        // --- Pengecekan tabel PP ---
        $ppRecords = DB::table('pp')
            ->where('rab', $no_rab)
            ->select('barang', 'sp', 'btb', 'plu', 'surkas')
            ->get();

        if ($ppRecords->isEmpty()) {
            if ($saranaExists) {
                $kekurangan[] = "Belum ada PP/SP yang disetujui";
            }
        } else {
            $ppTanpaBtb = $ppRecords->filter(fn($pp) => empty($pp->btb));

            if ($ppTanpaBtb->count() > 10) {
                $spUnik = $ppTanpaBtb->pluck('sp')->unique()->filter()->values();
                foreach ($spUnik as $spNo) {
                    $kekurangan[] = "SP No : {$spNo} Kurang Realisasi";
                }
            } else {
                foreach ($ppTanpaBtb as $pp) {
                    $barangPropper = ucwords(strtolower($pp->barang ?? 'Barang Tidak Diketahui'));
                    $spNo = $pp->sp ?? '-';
                    $kekurangan[] = "{$barangPropper} (SP No : {$spNo})";
                }
            }

            $kdToko = $lpd->kd_toko ?? null;

            if ($kdToko) {
                $uniqueBtbList = $ppRecords
                    ->filter(fn($pp) => ($pp->surkas ?? '') !== 'Y')
                    ->pluck('btb')
                    ->filter()
                    ->map(fn($b) => trim($b))
                    ->unique()
                    ->values();

                foreach ($uniqueBtbList as $btb) {
                    if ($btb === '') continue;

                    $btbExists = DB::table($kdToko)
                        ->where('rab', $no_rab)
                        ->where('inv_num', 'LIKE', '%' . $btb . '%')
                        ->exists();

                    if (!$btbExists) {
                        $kekurangan[] = "BTB No : {$btb} Belum Diproses";
                    }
                }
            }
        }


        // --- Pengecekan tabel Sarana Toko ---
        $saranaRecords = DB::table('sarana_toko')
            ->where('rab', $no_rab)
            ->where(function ($q) {
                $q->whereNull('flag_realisasi')->orWhere('flag_realisasi', '');
            })
            ->select('kode', 'uraian')
            ->get();

        // --- Filter data sarana yang tidak mengandung "Ongkos Kirim" ---
        $filteredSarana = $saranaRecords->filter(function ($sarana) {
            return stripos($sarana->uraian ?? '', 'Ongkos Kirim') === false;
        });

        if ($filteredSarana->isNotEmpty()) {
            $pluPP = $ppRecords->pluck('plu')->filter()->map('strval')->toArray();

            $saranaTidakAdaDiPP = $filteredSarana->filter(function ($sarana) use ($pluPP) {
                return !in_array((string)$sarana->kode, $pluPP, true);
            });

            if ($saranaTidakAdaDiPP->count() > 15) {
                $kekurangan[] = 'Alokasi sarana toko';
            } else {
                foreach ($saranaTidakAdaDiPP as $sarana) {
                    // pastikan lagi agar tidak ada uraian "Ongkos Kirim"
                    if (stripos($sarana->uraian ?? '', 'Ongkos Kirim') === false) {
                        $uraianPropper = ucwords(strtolower($sarana->uraian ?? 'Sarana Tidak Diketahui'));
                        $kekurangan[] = $uraianPropper;
                    }
                }
            }
        }

        if (empty($kekurangan)) {
            $keteranganBaru = 'Konfirmasi Perhitungan Ke Cabang';
        } else {
            $keteranganBaru = "Kekurangan :\n- " . implode("\n- ", $kekurangan);
        }

        DB::table('lpd')
            ->where('no_rab', $no_rab)
            ->update(['keterangan' => $keteranganBaru]);

        return response()->json([
            'message' => 'Keterangan berhasil diupdate.',
            'no_rab' => $no_rab,
            'keterangan_baru' => $keteranganBaru,
        ], 200);
    }

    public function updateKeteranganAll(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request) {

            $cabang = $request->query('cabang');

            // 🔒 optional: validasi cabang
            if (!$cabang) {
                echo 'data: ' . json_encode([
                    'error' => 'Cabang tidak boleh kosong'
                ]) . "\n\n";
                return;
            }

            $lpdsQuery = DB::table('lpd')
                ->where('status', '=', 'New')
                ->where('report', '=', 'Y')
                ->where('cabang', $cabang);

            $lpds = $lpdsQuery->pluck('no_rab');
            $total = count($lpds);

            foreach ($lpds as $index => $no_rab) {
                try {
                    // ❗ jangan overwrite $request
                    $req = new \Illuminate\Http\Request();
                    $req->query->set('no_rab', $no_rab);

                    $response = $this->updateKeterangan($req);

                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                        'cabang' => $cabang,
                    ];

                    if ($response->status() === 200) {
                        $data = $response->getData(true);
                        $result['message'] = $data['message'] ?? 'Berhasil';
                    } else {
                        $result['message'] = 'Gagal memproses laporan';
                    }

                } catch (\Throwable $e) {
                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                        'cabang' => $cabang,
                        'message' => 'Error: ' . $e->getMessage(),
                    ];
                }

                echo 'data: ' . json_encode($result) . "\n\n";
                ob_flush();
                flush();
                usleep(50000);
            }

            echo "event: done\n";
            echo "data: selesai\n\n";
            ob_flush();
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    public function automatchSarana(Request $request)
    {
        $no_rab = $request->query('no_rab');

        if (!$no_rab) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter no_rab wajib diisi'
            ], 400);
        }

        DB::beginTransaction();

        $matchedCount = 0;

        try {
            /* ==================== 1. LPD ==================== */
            $lpd = DB::table('lpd')
                ->where('no_rab', $no_rab)
                ->select('kd_toko')
                ->first();

            if (!$lpd) {
                throw new \Exception('Data LPD tidak ditemukan');
            }

            $kdTokoTable = $lpd->kd_toko;

            /* =====================================================
            | 2. MATCHING BY KODE/PLU (SARANA => REALISASI)
            ===================================================== */
            $byKode = DB::table('sarana_toko')
                ->where('rab', $no_rab)
                ->where(function ($q) {
                    $q->whereNull('flag_realisasi')
                    ->orWhere('flag_realisasi', '');
                })
                ->whereNotNull('kode')
                ->where('kode', '!=', '')
                ->get();

            foreach ($byKode as $sarana) {
                $rows = DB::table($kdTokoTable)
                    ->where('rab', $no_rab)
                    ->where(function ($q) {
                        $q->whereNull('flag_sarana')
                        ->orWhere('flag_sarana', '');
                    })
                    ->where('plu', $sarana->kode)
                    ->get();

                if ($rows->isEmpty()) continue;

                DB::table('sarana_toko')
                    ->where('id', $sarana->id)
                    ->update([
                        'flag_realisasi' => $sarana->id . '-' . $kdTokoTable
                    ]);

                DB::table($kdTokoTable)
                    ->whereIn('id', $rows->pluck('id'))
                    ->update([
                        'flag_sarana' => $sarana->id . '-' . $kdTokoTable
                    ]);

                $matchedCount += $rows->count();
            }

            /* =====================================================
            | 3. MATCHING SEBALIKNYA (REALISASI => SARANA via MASTER)
            ===================================================== */

            // ambil realisasi / kd_toko yang belum ter‐match
            $byRealisasi = DB::table($kdTokoTable)
                ->where('rab', $no_rab)
                ->where(function ($q) {
                    $q->whereNull('flag_sarana')
                    ->orWhere('flag_sarana', '');
                })
                ->whereNotNull('plu')
                ->where('plu', '!=', '')
                ->get();

            foreach ($byRealisasi as $realisasi) {

                /**
                 * STEP 1
                 * ambil keyword dari master_sarana
                 */
                $master = DB::table('master_sarana')
                    ->where('kode', $realisasi->plu)
                    ->first();

                if (!$master || empty($master->keyword)) {
                    continue;
                }

                /**
                 * STEP 2
                 * kumpulkan semua kode master_sarana dengan keyword sama
                 */
                $kodeMaster = DB::table('master_sarana')
                    ->where('keyword', $master->keyword)
                    ->whereNotNull('kode')
                    ->where('kode', '!=', '')
                    ->pluck('kode')
                    ->toArray();

                if (empty($kodeMaster)) {
                    continue;
                }

                /**
                 * STEP 3
                 * cari sarana_toko yang kodenya ada di array master
                 */
                $saranaRows = DB::table('sarana_toko')
                    ->where('rab', $no_rab)
                    ->where(function ($q) {
                        $q->whereNull('flag_realisasi')
                        ->orWhere('flag_realisasi', '');
                    })
                    ->whereIn('kode', $kodeMaster)
                    ->get();

                if ($saranaRows->isEmpty()) {
                    continue;
                }

                /**
                 * STEP 4
                 * update flag (sama seperti pola existing)
                 */
                $flagValue = $saranaRows->first()->id . '-' . $kdTokoTable;

                DB::table('sarana_toko')
                    ->whereIn('id', $saranaRows->pluck('id'))
                    ->update([
                        'flag_realisasi' => $flagValue
                    ]);

                DB::table($kdTokoTable)
                    ->where('id', $realisasi->id)
                    ->update([
                        'flag_sarana' => $flagValue
                    ]);

                $matchedCount += $saranaRows->count();
            }


            /* =====================================================
            | 4. SARANA DENGAN GROUP
            ===================================================== */
            $grouped = DB::table('sarana_toko as st')
                ->join('master_sarana as ms', 'st.kode', '=', 'ms.kode')
                ->where('st.rab', $no_rab)
                ->where(function ($q) {
                    $q->whereNotNull('ms.group')
                    ->where('ms.group', '!=', '');
                })
                ->where(function ($q) {
                    $q->whereNotNull('ms.keyword')
                    ->where('ms.keyword', '!=', '');
                })
                ->where(function ($q) {
                    $q->whereNull('st.flag_realisasi')
                    ->orWhere('st.flag_realisasi', '');
                })
                ->select('st.id', 'ms.group', 'ms.keyword', 'ms.exception')
                ->get()
                ->groupBy('group');

            foreach ($grouped as $groupName => $items) {

                $minId = $items->min('id');
                $keywords = [];
                $exceptions = [];

                foreach ($items as $i) {
                    $keywords = array_merge($keywords, explode(';', $i->keyword));
                    if ($i->exception) {
                        $exceptions = array_merge($exceptions, explode(';', $i->exception));
                    }
                }

                $keywords   = array_unique(array_map('trim', $keywords));
                $exceptions = array_unique(array_map('trim', $exceptions));
                $matchedIds = collect();

                foreach ($keywords as $kw) {

                    $like = '%' . preg_replace('/\s+/', '%', strtoupper($kw)) . '%';

                    $query = DB::table($kdTokoTable)
                        ->where('rab', $no_rab)
                        ->where(function ($q) {
                            $q->whereNull('flag_sarana')
                            ->orWhere('flag_sarana', '');
                        })
                        ->whereIn('kd_group', ['030008', '030009'])
                        ->whereRaw("UPPER(keterangan) LIKE ?", [$like]);

                    foreach ($exceptions as $ex) {
                        $query->whereRaw(
                            "UPPER(keterangan) NOT LIKE ?",
                            ['%' . strtoupper($ex) . '%']
                        );
                    }

                    $rows = $query->get();

                    if ($rows->isNotEmpty()) {
                        $matchedIds = $matchedIds->merge($rows->pluck('id'));
                    }
                }

                if ($matchedIds->isEmpty()) continue;

                DB::table('sarana_toko')
                    ->whereIn('id', $items->pluck('id'))
                    ->update([
                        'flag_realisasi' => $minId . '-' . $kdTokoTable
                    ]);

                DB::table($kdTokoTable)
                    ->whereIn('id', $matchedIds->unique())
                    ->update([
                        'flag_sarana' => $minId . '-' . $kdTokoTable
                    ]);

                $matchedCount += $matchedIds->unique()->count();
            }

            /* =====================================================
            | 5. SARANA TANPA GROUP (SINGLE)
            ===================================================== */
            $single = DB::table('sarana_toko as st')
                ->join('master_sarana as ms', 'st.kode', '=', 'ms.kode')
                ->where('st.rab', $no_rab)
                ->where(function ($q) {
                    $q->whereNull('ms.group')
                    ->orWhere('ms.group', '');
                })
                ->where(function ($q) {
                    $q->whereNotNull('ms.keyword')
                    ->where('ms.keyword', '!=', '');
                })
                ->where(function ($q) {
                    $q->whereNull('st.flag_realisasi')
                    ->orWhere('st.flag_realisasi', '');
                })
                ->select('st.id', 'ms.keyword', 'ms.exception')
                ->get();

            foreach ($single as $sarana) {
                $keywords = array_map('trim', explode(';', $sarana->keyword));
                $exceptions = $sarana->exception
                    ? array_map('trim', explode(';', $sarana->exception))
                    : [];

                $matchedIds = collect();

                foreach ($keywords as $kw) {

                    $like = '%' . preg_replace('/\s+/', '%', strtoupper($kw)) . '%';

                    $query = DB::table($kdTokoTable)
                        ->where('rab', $no_rab)
                        ->where(function ($q) {
                            $q->whereNull('flag_sarana')
                            ->orWhere('flag_sarana', '');
                        })
                        ->whereIn('kd_group', ['030008', '030009'])
                        ->whereRaw('UPPER(keterangan) LIKE ?', [$like]);

                    foreach ($exceptions as $ex) {
                        $query->whereRaw(
                            "UPPER(keterangan) NOT LIKE ?",
                            ['%' . strtoupper($ex) . '%']
                        );
                    }

                    $rows = $query->get();

                    if ($rows->isNotEmpty()) {
                        $matchedIds = $matchedIds->merge($rows->pluck('id'));
                    }
                }

                if ($matchedIds->isEmpty()) continue;

                DB::table('sarana_toko')
                    ->where('id', $sarana->id)
                    ->update([
                        'flag_realisasi' => $sarana->id . '-' . $kdTokoTable
                    ]);

                DB::table($kdTokoTable)
                    ->whereIn('id', $matchedIds->unique())
                    ->update([
                        'flag_sarana' => $sarana->id . '-' . $kdTokoTable
                    ]);

                $matchedCount += $matchedIds->unique()->count();
            }

            /* =====================================================
            | 6. PENANGANAN ONGKOS KIRIM (REALISASI => SARANA TOKO)
            ===================================================== */
            $ongkirRows = DB::table($kdTokoTable)
                ->where('rab', $no_rab)
                ->where(function ($q) {
                    $q->whereNull('flag_sarana')
                    ->orWhere('flag_sarana', '');
                })
                ->whereRaw("UPPER(keterangan) LIKE '%ONGKOS KIRIM%'")
                ->whereNotNull('inv_num')
                ->get();
            
            foreach ($ongkirRows as $ongkir) {
                /*
                -------------------------------------------------
                STEP 2: cari transaksi barang pasangan
                -------------------------------------------------
                */
                $barang = DB::table($kdTokoTable)
                    ->where('rab', $no_rab)
                    ->where('inv_num', $ongkir->inv_num)
                    ->whereNotNull('plu')
                    ->where('plu', '!=', '')
                    ->whereRaw("UPPER(keterangan) NOT LIKE '%ONGKOS KIRIM%'")
                    ->first();

                if (!$barang) {
                    $prevInv = $this->prevInvNum($ongkir->inv_num);

                    $barang = DB::table($kdTokoTable)
                        ->where('rab', $no_rab)
                        ->where('inv_num', $prevInv)
                        ->whereNotNull('plu')
                        ->where('plu', '!=', '')
                        ->whereRaw("UPPER(keterangan) NOT LIKE '%ONGKOS KIRIM%'")
                        ->first();
                }
                
                if (!$barang) {
                    continue;
                }

                /*
                -------------------------------------------------
                STEP 3: ambil SARANA BARANG (untuk kategori)
                -------------------------------------------------
                */
                $saranaBarang = DB::table('sarana_toko')
                    ->where('rab', $no_rab)
                    ->where('kode', $barang->plu)
                    ->first();

                if(!$saranaBarang){
                    $saranaBarang = DB::table('master_sarana')
                        ->where('kode', $barang->plu)
                        ->first();
                }

                if (!$saranaBarang) {
                    continue;
                }

                /*
                -------------------------------------------------
                STEP 4: cari SARANA ONGKOS KIRIM
                -------------------------------------------------
                */
                $saranaOngkir = DB::table('sarana_toko')
                    ->where('rab', $no_rab)
                    ->where(function ($q) {
                        $q->whereNull('flag_realisasi')
                        ->orWhere('flag_realisasi', '');
                    })
                    ->whereRaw("UPPER(uraian) LIKE '%ONGKOS KIRIM%'")
                    ->where('kategori', $saranaBarang->kategori)
                    ->first();
                /*
                -------------------------------------------------
                STEP 5: update flag_sarana
                -------------------------------------------------
                */
                if ($saranaOngkir) {

                    $flag = $saranaOngkir->id . '-' . $kdTokoTable;

                    DB::table($kdTokoTable)
                        ->where('id', $ongkir->id)
                        ->update(['flag_sarana' => $flag]);

                    DB::table('sarana_toko')
                        ->where('id', $saranaOngkir->id)
                        ->update(['flag_realisasi' => $flag]);

                    $matchedCount++;

                } else {

                    // fallback: ikut flag barang
                    if (!empty($saranaBarang->flag_realisasi)) {
                        DB::table($kdTokoTable)
                            ->where('id', $ongkir->id)
                            ->update(['flag_sarana' => $saranaBarang->flag_realisasi]);

                        $matchedCount++;
                    }
                }
            }

            /* =====================================================
            | 7. PENANGANAN ONGKOS KIRIM (SARANA TOKO => REALISASI)
            ===================================================== */

            $saranaOngkirRows = DB::table('sarana_toko')
                ->where('rab', $no_rab)
                ->where(function ($q) {
                    $q->whereNull('flag_realisasi')
                    ->orWhere('flag_realisasi', '');
                })
                ->whereRaw("UPPER(uraian) LIKE '%ONGKOS KIRIM%'")
                ->get();

            foreach ($saranaOngkirRows as $saranaOngkir) {

                /*
                -------------------------------------------------
                STEP 2: kumpulkan kode sarana dengan kategori sama
                -------------------------------------------------
                */
                $kodeSarana = DB::table('sarana_toko')
                    ->where('rab', $no_rab)
                    ->where('kategori', $saranaOngkir->kategori)
                    ->whereNotNull('kode')
                    ->where('kode', '!=', '')
                    ->pluck('kode')
                    ->toArray();

                // kode tambahan dari master_sarana
                $kodeMaster = DB::table('master_sarana')
                    ->where('kategori', $saranaOngkir->kategori)
                    ->whereNotNull('kode')
                    ->where('kode', '!=', '')
                    ->pluck('kode')
                    ->toArray();
                
                // gabungkan + hilangkan duplikasi
                $kodeKategori = array_values(array_unique(
                    array_merge($kodeSarana, $kodeMaster)
                ));

                if (empty($kodeKategori)) {
                    continue;
                }

                /*
                -------------------------------------------------
                STEP 3: cari 1 kd_toko barang yang SUDAH ter‐flag
                -------------------------------------------------
                */
                $barangRealisasi = DB::table($kdTokoTable)
                    ->where('rab', $no_rab)
                    ->whereIn('plu', $kodeKategori)
                    ->whereNotNull('flag_sarana')
                    ->where('flag_sarana', '!=', '')
                    ->first();

                if (!$barangRealisasi) {
                    continue;
                }

                /*
                -------------------------------------------------
                STEP 4: update flag_realisasi sarana ongkos kirim
                -------------------------------------------------
                */
                DB::table('sarana_toko')
                    ->where('id', $saranaOngkir->id)
                    ->update([
                        'flag_realisasi' => $barangRealisasi->flag_sarana
                    ]);

                $matchedCount++;
            }

            /* =====================================================
            | 8. PROPAGASI FLAG UNTUK BRACKET LCD
            ===================================================== */

            // ambil BRACKET LCD yang BELUM punya flag_sarana
            $bracketNoFlag = DB::table($kdTokoTable)
                ->where('rab', $no_rab)
                ->where(function ($q) {
                    $q->whereNull('flag_sarana')
                    ->orWhere('flag_sarana', '');
                })
                ->whereRaw("UPPER(keterangan) LIKE '%BRACKET LCD%'")
                ->get();
            
            if ($bracketNoFlag->isNotEmpty()) {
                // cari BRACKET LCD yang SUDAH punya flag_sarana
                $bracketWithFlag = DB::table($kdTokoTable)
                    ->where('rab', $no_rab)
                    ->whereNotNull('flag_sarana')
                    ->where('flag_sarana', '!=', '')
                    ->whereRaw("UPPER(keterangan) LIKE '%BRACKET%'")
                    ->whereRaw("keterangan NOT LIKE '%+%'")
                    ->first();

                if ($bracketWithFlag) {
                    DB::table($kdTokoTable)
                        ->whereIn('id', $bracketNoFlag->pluck('id'))
                        ->update([
                            'flag_sarana' => $bracketWithFlag->flag_sarana
                        ]);

                    $matchedCount += $bracketNoFlag->count();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Auto matching selesai',
                'matched' => $matchedCount
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            \Log::error('AUTOMATCH SARANA ERROR', [
                'no_rab' => $no_rab,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat proses automatch'
            ], 500);
        }
    }

    public function automatchSaranaAll(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request) {
            $cabang = $request->query('cabang');
            $lpds = DB::table('lpd')
                ->where('status', '=', 'New')
                ->where('report', '=', 'Y')
                ->where('cabang', $cabang)
                ->pluck('no_rab');

            $total = count($lpds);

            foreach ($lpds as $index => $no_rab) {
                try {
                    $request = new \Illuminate\Http\Request();
                    $request->query->set('no_rab', $no_rab);
                    $response = $this->automatchSarana($request);

                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                    ];

                    if ($response->status() === 200) {
                        $data = $response->getData(true);
                        $result['message'] = $data['message'] ?? 'Berhasil';
                    } else {
                        $result['message'] = 'Gagal memproses laporan';
                    }
                } catch (\Throwable $e) {
                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                        'message' => 'Error: ' . $e->getMessage(),
                    ];
                }

                echo 'data: ' . json_encode($result) . "\n\n";
                ob_flush();
                flush();
                usleep(50000);
            }

            echo "event: done\n";
            echo "data: selesai\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    public function exportDatPrCsv(Request $request)
    {
        $cabang = $request->query('cabang');
        $noRabs = DB::table('lpd')
            ->where('status', '!=', 'Final')
            ->where('report', '=', 'Y')
            ->where('cabang', $cabang)
            ->pluck('no_rab')
            ->toArray();

        if (empty($noRabs)) {
            return response()->json(['message' => 'Tidak ada data LPD dengan status selain Final.'], 404);
        }

        $data = DB::table('dat_pr')
            ->whereIn('rab', $noRabs)
            ->select('rab', 'site', 'seri', 'keterangan', 'surkas', 'inv_num', 'tgl_perolehan', 'harga')
            ->get();

        if ($data->isEmpty()) {
            return response()->json(['message' => 'Tidak ada data dat_pr ditemukan.'], 404);
        }

        $delimiter = '|';
        $header = ['rab', 'site', 'seri', 'keterangan', 'surkas', 'inv_num', 'tgl_perolehan', 'harga'];
        $lines = [];
        $lines[] = implode($delimiter, $header);

        foreach ($data as $row) {
            $lines[] = implode($delimiter, [
                $row->rab,
                $row->site,
                $row->seri,
                $row->keterangan,
                $row->surkas,
                $row->inv_num,
                $row->tgl_perolehan,
                $row->harga,
            ]);
        }

        $csvContent = implode("\n", $lines);

        $filename = 'export_dat_pr_' . date('Ymd_His') . '.csv';
        return Response::make($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    public function exportDetailCsv(Request $request)
    {
        $cabang = $request->query('cabang');

        $lpdQuery = DB::table('lpd')
            ->where('status', '!=', 'Final')
            ->where('cabang', $cabang)
            ->where('report', '=', 'Y');

        $lpdList = $lpdQuery
            ->select('no_rab', 'kd_toko')
            ->get();

        if ($lpdList->isEmpty()) {
            return response()->json(['message' => 'Tidak ada data untuk diekspor'], 404);
        }

        $rows = [];

        foreach ($lpdList as $lpd) {
            $tableName = $lpd->kd_toko;

            // ⚠️ optional safety (kalau tabel tidak ada)
            if (!\Schema::hasTable($tableName)) {
                continue;
            }

            $data = DB::table($tableName)
                ->where('rab', $lpd->no_rab)
                ->select('rab', 'kd_group', 'plu', 'keterangan', 'dpp', 'ppn', 'total', 'inv_num')
                ->get();

            foreach ($data as $row) {
                $rows[] = (array) $row;
            }
        }

        if (empty($rows)) {
            return response()->json(['message' => 'Tidak ada data detail ditemukan'], 404);
        }

        // ✅ tambahkan cabang ke filename
        $safeCabang = $cabang ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $cabang) : 'ALL';
        $filename = "Export_Detail_{$safeCabang}.csv";

        $csvContent = '';

        $headers = ['rab', 'kd_group', 'plu', 'keterangan', 'dpp', 'ppn', 'total', 'inv_num'];
        $csvContent .= implode('|', $headers) . "\n";

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $field) {
                $value = $row[$field] ?? '';
                $line[] = str_replace('|', ' ', $value);
            }
            $csvContent .= implode('|', $line) . "\n";
        }

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function reportInvest(Request $request)
    {
        $cabang = $request->query('cabang');
        $aliasCabang = DB::table('cabang')
            ->where('cabang', $cabang)
            ->value('alias');
            
        $data = DB::table('lpd')
            ->join('modal', 'lpd.no_rab', '=', 'modal.rab')
            ->where('lpd.status', '!=', 'Final')
            ->where('lpd.report', '=', 'Y')
            ->where('cabang', $cabang)
            ->whereIn('jns_toko', ['NS', 'PPJ', 'UP', 'TO'])
            ->select('lpd.kd_toko', 'lpd.no_rab', 'lpd.nama_toko', 'lpd.rab_final', 'lpd.tgl_wrlb',
                'lpd.jns_toko', 'modal.setor', 'modal.cad_dana', 'modal.sewa_at', 'modal.pek_by_frcsee', 'modal.sewa_by_frcsee')
            ->get();

        $filePath = 'C:/xampp/htdocs/AP_R4/File/draft/Form Monitoring Investasi.xlsx';
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Draft file not found'], 404);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        if ($aliasCabang) {
            $safeTitle = substr($aliasCabang, 0, 31);
            $safeTitle = str_replace(['\\', '/', '*', '?', ':', '[', ']'], '', $safeTitle);

            $sheet->setTitle($safeTitle);
        }

        $periodeAktif = DB::table('periode')
            ->where('Cabang', $cabang)
            ->where('kategori', 'LPD')
            ->where('status', 'Aktif')
            ->first();

        if ($periodeAktif) {
            $periodeDate = \Carbon\Carbon::parse($periodeAktif->start_date)->format('Y_m');
            $fileName = $periodeDate . "_Rekap RAB VS Investasi R4 Cabang {$cabang}.xlsx";
            
            $startDate = \Carbon\Carbon::parse($periodeAktif->start_date);
            $sheet->setCellValue('A3', ExcelDate::PHPToExcel(new \DateTime($startDate)));
            $sheet->getStyle('A3')->getNumberFormat()->setFormatCode('MMM-YY');
        } else {
            $fileName = now()->format('Y_m') . "_Rekap RAB VS Investasi R4 Cabang {$cabang}.xlsx";
        }

        $row = 5;
        $no = 1;

        if (count($data) > 1) {
            $sheet->insertNewRowBefore(6, count($data) - 1);
        }

        foreach ($data as $a) {
            $cash    = $a->cad_dana + $a->setor;
            $total   = $a->cad_dana + $a->setor + $a->pek_by_frcsee + $a->sewa_by_frcsee;
            $selisih = $total - $a->rab_final;

            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $cabang);
            $sheet->setCellValue("C{$row}", $a->jns_toko);
            $sheet->setCellValue("D{$row}", $a->kd_toko);
            $sheet->setCellValue("E{$row}", $a->nama_toko);
            $sheet->setCellValue("F{$row}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_wrlb)));
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
            $sheet->setCellValue("G{$row}", $a->rab_final);
            $sheet->setCellValue("H{$row}", $a->sewa_by_frcsee);
            $sheet->setCellValue("I{$row}", $a->pek_by_frcsee);
            $sheet->setCellValue("J{$row}", $cash);
            $sheet->setCellValue("K{$row}", $selisih);
            if ($a->sewa_at > 0) {
                $sheet->setCellValue(
                    "L{$row}",
                    'SEWA AT Rp ' . number_format($a->sewa_at, 0, ',', '.')
                );
            }
            $sheet->setCellValue("N{$row}", $a->no_rab);

            $row++;
            $no++;
        }

        $sheet->getColumnDimension('F')->setAutoSize(true);
        $writer = new Xlsx($spreadsheet);
        if (ob_get_level()) {
            ob_end_clean();
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function reportPlusMinus(Request $request)
    {
        $cabang = $request->query('cabang');

        $data = DB::table('lpd')
            ->join('modal', 'modal.rab', '=', 'lpd.no_rab')
            ->join('lpd_realisasi_detail', 'lpd_realisasi_detail.rab', '=', 'lpd.no_rab')
            ->where('lpd.status', '!=', 'Final')
            ->where('lpd.report', '=', 'Y')
            ->where(function ($query) {
                $query->where('lpd.keterangan', 'like', '%Final%')
                    ->orWhere('lpd.keterangan', 'like', '%Pembayaran%');
            })
            ->whereIn('lpd.jns_toko', ['NS', 'PPJ', 'UP', 'TO'])
            ->where('lpd.cabang', $cabang)
            ->get();


        $filePath = 'C:/xampp/htdocs/AP_R4/File/draft/Form Format Standard LPD Plus Minus.xlsx';
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Draft file not found'], 404);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $periodeAktif = DB::table('periode')
            ->where('Cabang', $cabang)
            ->where('kategori', 'LPD')
            ->where('status', 'Aktif')
            ->first();

        if ($periodeAktif) {
            $periodeDate = \Carbon\Carbon::parse($periodeAktif->start_date)->format('Y_m');
            $fileName = $periodeDate . "_Format Standard LPD Plus Minus Cabang {$cabang}.xlsx";
            
            $EndDate = \Carbon\Carbon::parse($periodeAktif->end_date);

            $sheet->setCellValue('K2', ExcelDate::PHPToExcel(new \DateTime($EndDate)));
            $sheet->getStyle('K2')->getNumberFormat()->setFormatCode('DD-MMM-YY');

            $sheet->setCellValue('A2', 'Cut Off ' . $EndDate->format('M-y'));
        } else {
            $fileName = now()->format('Y_m') . "_Format Standard LPD Plus Minus Cabang {$cabang}.xlsx";
        }

        $row = 4;
        
        if (count($data) > 1) {
            $sheet->insertNewRowBefore(5, count($data) - 1);
        }

        foreach ($data as $a) {
            $cash    = $a->cad_dana + $a->setor;
            $selisih = $cash - $a->realisasi_frc_fee - $a->realisasi_promo - $a->realisasi_rekrut_train - $a->realisasi_sw_pph - $a->realisasi_jasa_pihak3 - $a->realisasi_fg -
                        $a->realisasi_kanopi - $a->realisasi_ins_ac - $a->realisasi_teralis - $a->realisasi_halaman - $a->realisasi_policarbonate - $a->realisasi_listrik -
                        $a->realisasi_aluminium_kaca - $a->realisasi_signage - $a->realisasi_sipil - $a->realisasi_prasarana - $a->realisasi_peralatan - $a->realisasi_interior;

            $sheet->setCellValue("A{$row}", $cabang);
            $sheet->setCellValue("B{$row}", $a->kd_toko);
            $sheet->setCellValue("C{$row}", $a->nama_toko);
            $sheet->setCellValue("D{$row}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_wrlb)));
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
            $sheet->setCellValue("E{$row}", $a->jns_toko);
            $sheet->setCellValue("F{$row}", $selisih);

            if ($a->tgl_final && $a->tgl_final != "0000-00-00") {
                $sheet->setCellValue("G{$row}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_final)));
                $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
            } else {
                $sheet->setCellValue("G{$row}", null);
            }
            
            $value = match($a->penyelesaian) {
                'Trf' => 'TRANSFER',
                'Pot Surkas' => 'POT SURKAS',
                'Frcsee' => 'TRF FRCSEE',
                default => ''
            };
            $sheet->setCellValue("H{$row}", $value);
            $sheet->setCellValue("K{$row}", "=\$K\$2-G{$row}");

            $row++;
        }

        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('F')->setAutoSize(true);
        $writer = new Xlsx($spreadsheet);
        if (ob_get_level()) {
            ob_end_clean();
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function reportOuts(Request $request)
    {
        $cabang = $request->query('cabang');
        $aliasCabang = DB::table('cabang')
            ->where('cabang', $cabang)
            ->value('alias');

        $data = DB::table('lpd')
            ->join('modal', 'modal.rab', '=', 'lpd.no_rab')
            ->join('lpd_realisasi_detail', 'lpd_realisasi_detail.rab', '=', 'lpd.no_rab')
            ->where('lpd.status', '!=', 'Final')
            ->where('lpd.report', '=', 'Y')
            ->whereIn('lpd.jns_toko', ['NS', 'PPJ', 'UP', 'TO'])
            ->where('lpd.cabang', $cabang)
            ->select('lpd.*', 'modal.*', 'lpd_realisasi_detail.*', 'lpd.keterangan as lpd_keterangan')
            ->orderBy('lpd.jns_toko', 'asc')
            ->orderBy('lpd.tgl_wrlb', 'asc')
            ->get();


        $filePath = 'C:/xampp/htdocs/AP_R4/File/draft/Form Outs.xlsx';
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Draft file not found'], 404);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        if ($aliasCabang) {
            $safeTitle = substr($aliasCabang, 0, 31);
            $safeTitle = str_replace(['\\', '/', '*', '?', ':', '[', ']'], '', $safeTitle);

            $sheet->setTitle($safeTitle);
        }

        $periodeAktif = DB::table('periode')
            ->where('Cabang', $cabang)
            ->where('kategori', 'LPD')
            ->where('status', 'Aktif')
            ->first();

        if ($periodeAktif) {
            $periodeDate = \Carbon\Carbon::parse($periodeAktif->start_date)->format('Y_m');
            $fileName = $periodeDate . "_Rekap Outs LPD JT R4 Cabang {$cabang}.xlsx";
        } else {
            $fileName = now()->format('Y_m') . "_Rekap Outs LPD JT R4 Cabang {$cabang}.xlsx";
        }

        $sheet->setCellValue('A3', 'Cabang : ' . $cabang);

        $no = 1;
        $row = 6;
        $countNS = collect($data)
            ->filter(function ($item) {
                return $item->jns_toko === 'NS' &&
                    stripos($item->lpd_keterangan, 'Final') === false &&
                    stripos($item->lpd_keterangan, 'Pembayaran') === false;
            })
            ->count();

        if ($countNS > 0) {
            $sheet->insertNewRowBefore(7, $countNS - 1);
        }

        foreach ($data as $a) {
            if (
                $a->jns_toko !== 'NS' ||
                stripos($a->lpd_keterangan, 'Final') !== false ||
                stripos($a->lpd_keterangan, 'Pembayaran') !== false
            ) {
                continue;
            }
            
            $cash      = $a->cad_dana + $a->setor;
            $by_frc    = $a->pek_by_frcsee + $a->sewa_by_frcsee;
            $rab       = $a->rab_frc_fee + $a->rab_promo + $a->rab_rekrut_train + $a->rab_sw_pph + $a->rab_jasa_pihak3 + $a->rab_fg +
                         $a->rab_kanopi + $a->rab_ins_ac + $a->rab_teralis + $a->rab_halaman + $a->rab_policarbonate + $a->rab_listrik +
                         $a->rab_aluminium_kaca + $a->rab_signage + $a->rab_sipil + $a->rab_prasarana + $a->rab_peralatan + $a->rab_interior;
            $realisasi = $a->realisasi_frc_fee + $a->realisasi_promo + $a->realisasi_rekrut_train + $a->realisasi_sw_pph + $a->realisasi_jasa_pihak3 + $a->realisasi_fg +
                         $a->realisasi_kanopi + $a->realisasi_ins_ac + $a->realisasi_teralis + $a->realisasi_halaman + $a->realisasi_policarbonate + $a->realisasi_listrik +
                         $a->realisasi_aluminium_kaca + $a->realisasi_signage + $a->realisasi_sipil + $a->realisasi_prasarana + $a->realisasi_peralatan + $a->realisasi_interior;
            $selisih   = $cash - $realisasi;

            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $cabang);
            $sheet->setCellValue("C{$row}", $a->kd_toko);
            $sheet->setCellValue("D{$row}", $a->nama_toko);
            $sheet->setCellValue("E{$row}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_wrlb)));
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
            $sheet->setCellValue("F{$row}", $a->jns_toko);
            $sheet->setCellValue("G{$row}", $rab);
            $sheet->setCellValue("H{$row}", $by_frc);
            $sheet->setCellValue("I{$row}", $realisasi);
            $sheet->setCellValue("J{$row}", "=G{$row}-H{$row}-I{$row}");
            
            $sheet->setCellValue("K{$row}", $a->lpd_keterangan);
            $sheet->getStyle('K')->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(-1);

            $sheet->setCellValue("M{$row}", $a->no_rab);
            $sheet->setCellValue("N{$row}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_jt)));
            $sheet->getStyle("N{$row}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
            $sheet->setCellValue("O{$row}", "=E{$row}+90");
            $sheet->setCellValue("P{$row}", $cash);

            $sheet->setCellValue("Q{$row}", "=\$Q\$3-N{$row}");
            $sheet->setCellValue("R{$row}", "=IF(Q{$row}<32,1,0)");
            $sheet->setCellValue("S{$row}", "=IF(Q{$row}<63,IF(Q{$row}>31,1,0),0)");
            $sheet->setCellValue("T{$row}", "=IF(Q{$row}>62,1,0)");

            $row++;
            $no++;
        }


        // Perpanjangan
        $noppj = 1;
        $insertppj = $countNS + 9;
        $countPPJ = collect($data)
            ->filter(function ($item) {
                return $item->jns_toko === 'PPJ' &&
                    stripos($item->lpd_keterangan, 'Final') === false &&
                    stripos($item->lpd_keterangan, 'Pembayaran') === false;
            })
            ->count();

        if ($countPPJ > 0) {
            $sheet->insertNewRowBefore($insertppj, $countPPJ);
        }

        foreach ($data as $a) {
            if (
                $a->jns_toko !== 'PPJ' ||
                stripos($a->lpd_keterangan, 'Final') !== false ||
                stripos($a->lpd_keterangan, 'Pembayaran') !== false
            ) {
                continue;
            }
            
            $cash      = $a->cad_dana + $a->setor;
            $by_frc    = $a->pek_by_frcsee + $a->sewa_by_frcsee;
            $rab       = $a->rab_frc_fee + $a->rab_promo + $a->rab_rekrut_train + $a->rab_sw_pph + $a->rab_jasa_pihak3 + $a->rab_fg +
                         $a->rab_kanopi + $a->rab_ins_ac + $a->rab_teralis + $a->rab_halaman + $a->rab_policarbonate + $a->rab_listrik +
                         $a->rab_aluminium_kaca + $a->rab_signage + $a->rab_sipil + $a->rab_prasarana + $a->rab_peralatan + $a->rab_interior;
            $realisasi = $a->realisasi_frc_fee + $a->realisasi_promo + $a->realisasi_rekrut_train + $a->realisasi_sw_pph + $a->realisasi_jasa_pihak3 + $a->realisasi_fg +
                         $a->realisasi_kanopi + $a->realisasi_ins_ac + $a->realisasi_teralis + $a->realisasi_halaman + $a->realisasi_policarbonate + $a->realisasi_listrik +
                         $a->realisasi_aluminium_kaca + $a->realisasi_signage + $a->realisasi_sipil + $a->realisasi_prasarana + $a->realisasi_peralatan + $a->realisasi_interior;
            $selisih   = $cash - $realisasi;

            $sheet->setCellValue("A{$insertppj}", $noppj);
            $sheet->setCellValue("B{$insertppj}", $cabang);
            $sheet->setCellValue("C{$insertppj}", $a->kd_toko);
            $sheet->setCellValue("D{$insertppj}", $a->nama_toko);
            $sheet->setCellValue("E{$insertppj}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_wrlb)));
            $sheet->getStyle("E{$insertppj}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
            $sheet->setCellValue("F{$insertppj}", $a->jns_toko);
            $sheet->setCellValue("G{$insertppj}", $rab);
            $sheet->setCellValue("H{$insertppj}", $by_frc);
            $sheet->setCellValue("I{$insertppj}", $realisasi);
            $sheet->setCellValue("J{$insertppj}", "=G{$insertppj}-H{$insertppj}-I{$insertppj}");
            
            $sheet->setCellValue("K{$insertppj}", $a->lpd_keterangan);
            $sheet->getStyle('K')->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($insertppj)->setRowHeight(-1);

            $sheet->setCellValue("M{$insertppj}", $a->no_rab);
            $sheet->setCellValue("N{$insertppj}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_jt)));
            $sheet->getStyle("N{$insertppj}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
            $sheet->setCellValue("O{$insertppj}", "=E{$insertppj}+90");
            $sheet->setCellValue("P{$insertppj}", $cash);

            $sheet->setCellValue("Q{$insertppj}", "=\$Q\$3-N{$insertppj}");
            $sheet->setCellValue("R{$insertppj}", "=IF(Q{$insertppj}<32,1,0)");
            $sheet->setCellValue("S{$insertppj}", "=IF(Q{$insertppj}<63,IF(Q{$insertppj}>31,1,0),0)");
            $sheet->setCellValue("T{$insertppj}", "=IF(Q{$insertppj}>62,1,0)");

            $insertppj++;
            $noppj++;
        }

        // Ugrade / Relokasi
        $noup = 1;
        $insertup = $countNS + $countPPJ + 12;
        $countUp = collect($data)
            ->filter(function ($item) {
                return in_array($item->jns_toko, ['UP', 'RE', 'TO']) &&
                    stripos($item->lpd_keterangan, 'Final') === false &&
                    stripos($item->lpd_keterangan, 'Pembayaran') === false;
            })
            ->count();


        if ($countUp > 0) {
            $sheet->insertNewRowBefore($insertup, $countUp);
        }

        foreach ($data as $a) {
            if (
                ($a->jns_toko !== 'UP' && $a->jns_toko !== 'RE' && $a->jns_toko !== 'TO') ||
                stripos($a->lpd_keterangan, 'Final') !== false ||
                stripos($a->lpd_keterangan, 'Pembayaran') !== false
            ) {
                continue;
            }
            
            $cash      = $a->cad_dana + $a->setor;
            $by_frc    = $a->pek_by_frcsee + $a->sewa_by_frcsee;
            $rab       = $a->rab_frc_fee + $a->rab_promo + $a->rab_rekrut_train + $a->rab_sw_pph + $a->rab_jasa_pihak3 + $a->rab_fg +
                         $a->rab_kanopi + $a->rab_ins_ac + $a->rab_teralis + $a->rab_halaman + $a->rab_policarbonate + $a->rab_listrik +
                         $a->rab_aluminium_kaca + $a->rab_signage + $a->rab_sipil + $a->rab_prasarana + $a->rab_peralatan + $a->rab_interior;
            $realisasi = $a->realisasi_frc_fee + $a->realisasi_promo + $a->realisasi_rekrut_train + $a->realisasi_sw_pph + $a->realisasi_jasa_pihak3 + $a->realisasi_fg +
                         $a->realisasi_kanopi + $a->realisasi_ins_ac + $a->realisasi_teralis + $a->realisasi_halaman + $a->realisasi_policarbonate + $a->realisasi_listrik +
                         $a->realisasi_aluminium_kaca + $a->realisasi_signage + $a->realisasi_sipil + $a->realisasi_prasarana + $a->realisasi_peralatan + $a->realisasi_interior;
            $selisih   = $cash - $realisasi;

            $sheet->setCellValue("A{$insertup}", $noup);
            $sheet->setCellValue("B{$insertup}", $cabang);
            $sheet->setCellValue("C{$insertup}", $a->kd_toko);
            $sheet->setCellValue("D{$insertup}", $a->nama_toko);
            $sheet->setCellValue("E{$insertup}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_wrlb)));
            $sheet->getStyle("E{$insertup}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
            $sheet->setCellValue("F{$insertup}", $a->jns_toko);
            $sheet->setCellValue("G{$insertup}", $rab);
            $sheet->setCellValue("H{$insertup}", $by_frc);
            $sheet->setCellValue("I{$insertup}", $realisasi);
            $sheet->setCellValue("J{$insertup}", "=G{$insertup}-H{$insertup}-I{$insertup}");
            
            $sheet->setCellValue("K{$insertup}", $a->lpd_keterangan);
            $sheet->getStyle('K')->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($insertup)->setRowHeight(-1);

            $sheet->setCellValue("M{$insertup}", $a->no_rab);
            $sheet->setCellValue("N{$insertup}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_jt)));
            $sheet->getStyle("N{$insertup}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
            $sheet->setCellValue("O{$insertup}", "=E{$insertup}+90");
            $sheet->setCellValue("P{$insertup}", $cash);

            $sheet->setCellValue("Q{$insertup}", "=\$Q\$3-N{$insertup}");
            $sheet->setCellValue("R{$insertup}", "=IF(Q{$insertup}<32,1,0)");
            $sheet->setCellValue("S{$insertup}", "=IF(Q{$insertup}<63,IF(Q{$insertup}>31,1,0),0)");
            $sheet->setCellValue("T{$insertup}", "=IF(Q{$insertup}>62,1,0)");

            $insertup++;
            $noup++;
        }

        // LPD Clear
        $insertclear = $countNS + $countPPJ + $countUp + 18;
        $countClear = collect($data)
            ->filter(function ($item) {
                return 
                    stripos($item->lpd_keterangan, 'Final') !== false ||
                    stripos($item->lpd_keterangan, 'Pembayaran') !== false;
            })
            ->count();
            
        if ($countClear > 0) {
            $noclear = 1;
            $sheet->insertNewRowBefore($insertclear + 1, $countClear - 1);

            foreach ($data as $a) {
                if (
                    stripos($a->lpd_keterangan, 'Final') === false &&
                    stripos($a->lpd_keterangan, 'Pembayaran') === false
                ) {
                    continue;
                }
                
                $cash      = $a->cad_dana + $a->setor;
                $by_frc    = $a->pek_by_frcsee + $a->sewa_by_frcsee;
                $rab       = $a->rab_frc_fee + $a->rab_promo + $a->rab_rekrut_train + $a->rab_sw_pph + $a->rab_jasa_pihak3 + $a->rab_fg +
                            $a->rab_kanopi + $a->rab_ins_ac + $a->rab_teralis + $a->rab_halaman + $a->rab_policarbonate + $a->rab_listrik +
                            $a->rab_aluminium_kaca + $a->rab_signage + $a->rab_sipil + $a->rab_prasarana + $a->rab_peralatan + $a->rab_interior;
                $realisasi = $a->realisasi_frc_fee + $a->realisasi_promo + $a->realisasi_rekrut_train + $a->realisasi_sw_pph + $a->realisasi_jasa_pihak3 + $a->realisasi_fg +
                            $a->realisasi_kanopi + $a->realisasi_ins_ac + $a->realisasi_teralis + $a->realisasi_halaman + $a->realisasi_policarbonate + $a->realisasi_listrik +
                            $a->realisasi_aluminium_kaca + $a->realisasi_signage + $a->realisasi_sipil + $a->realisasi_prasarana + $a->realisasi_peralatan + $a->realisasi_interior;
                $selisih   = $cash - $realisasi;

                $sheet->setCellValue("A{$insertclear}", $noclear);
                $sheet->setCellValue("B{$insertclear}", $cabang);
                $sheet->setCellValue("C{$insertclear}", $a->kd_toko);
                $sheet->setCellValue("D{$insertclear}", $a->nama_toko);
                $sheet->setCellValue("E{$insertclear}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_wrlb)));
                $sheet->getStyle("E{$insertclear}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
                $sheet->setCellValue("F{$insertclear}", $a->jns_toko);
                $sheet->setCellValue("G{$insertclear}", $rab);
                $sheet->setCellValue("H{$insertclear}", $by_frc);
                $sheet->setCellValue("I{$insertclear}", $realisasi);
                $sheet->setCellValue("J{$insertclear}", "=G{$insertclear}-H{$insertclear}-I{$insertclear}");
                
                $sheet->setCellValue("K{$insertclear}", $a->lpd_keterangan);
                $sheet->getStyle('K')->getAlignment()->setWrapText(true);
                $sheet->getRowDimension($insertclear)->setRowHeight(-1);

                $sheet->setCellValue("M{$insertclear}", $a->no_rab);
                $sheet->setCellValue("N{$insertclear}", ExcelDate::PHPToExcel(new \DateTime($a->tgl_jt)));
                $sheet->getStyle("N{$insertclear}")->getNumberFormat()->setFormatCode('DD-MMM-YY');
                $sheet->setCellValue("O{$insertclear}", "=E{$insertclear}+90");
                $sheet->setCellValue("P{$insertclear}", $cash);

                $sheet->setCellValue("Q{$insertclear}", "=\$Q\$3-N{$insertclear}");
                $sheet->setCellValue("R{$insertclear}", "=IF(Q{$insertclear}<32,1,0)");
                $sheet->setCellValue("S{$insertclear}", "=IF(Q{$insertclear}<63,IF(Q{$insertclear}>31,1,0),0)");
                $sheet->setCellValue("T{$insertclear}", "=IF(Q{$insertclear}>62,1,0)");

                $insertclear++;
                $noclear++;
            }
        }else{
            $sheet->removeRow($insertclear, 7);
        }

        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('K')->setAutoSize(true);
        
        $writer = new Xlsx($spreadsheet);
        if (ob_get_level()) {
            ob_end_clean();
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
    
    public function update_atpr(Request $request)
    {
        $noRab = $request->query('no_rab');

        if (!$noRab) {
            return response()->json([
                'message' => 'no_rab tidak ditemukan',
                'matched' => 0,
            ], 400);
        }

        DB::beginTransaction();

        try {

            /*
            =====================================================
            STEP 1 — Ambil kd_toko dari tabel lpd
            =====================================================
            */
            $lpd = DB::table('lpd')
                ->where('no_rab', $noRab)
                ->first();

            if (!$lpd) {
                throw new \Exception('LPD tidak ditemukan');
            }

            $kdToko = $lpd->kd_toko;

            /*
            =====================================================
            STEP 2 — Ambil tgl_perolehan terkecil dari dat_pr
            =====================================================
            */
            $minTglPerolehan = DB::table('dat_pr')
                ->where('rab', $noRab)
                ->min('tgl_perolehan');

            // Jika tidak ditemukan, fallback ke tgl_wrlb - 4 bulan
            if (!$minTglPerolehan) {

                if (!$lpd->tgl_wrlb) {
                    throw new \Exception('tgl_perolehan tidak ditemukan dan tgl_wrlb kosong');
                }

                $minTglPerolehan = \Carbon\Carbon::parse($lpd->tgl_wrlb)
                    ->subMonths(6)
                    ->format('Y-m-d');
            }

            /*
            =====================================================
            STEP 3 — Ambil data dari master_dat_pr
            =====================================================
            Ambil data:
            - berdasarkan kd_toko (dari step 1)
            - tgl_mulai_susut >= tgl_perolehan terkecil (step 2)
            - sampai dengan tgl_mulai_susut terbesar untuk kd_toko tersebut
            =====================================================
            */

            // Ambil tgl_mulai_susut terbesar untuk kd_toko ini
            $maxTglMulaiSusut = DB::table('master_dat_pr')
                ->where('kd_toko', $kdToko)
                ->max('tgl_mulai_susut');

            if (!$maxTglMulaiSusut) {
                throw new \Exception('Tidak ditemukan data master_dat_pr untuk kd_toko ini');
            }

            // Ambil range data master_dat_pr yang relevan
            $masterData = DB::table('master_dat_pr')
                ->where('kd_toko', $kdToko)
                ->where('status', 'Aktif')
                ->whereBetween('tgl_mulai_susut', [
                    $minTglPerolehan,
                    $maxTglMulaiSusut
                ])
                ->whereIn('surkas', ['L', 'N'])
                ->whereNull('modal')
                ->get();

            /*
            =====================================================
            STEP 4 — Validasi & Insert ke dat_pr
            =====================================================
            - Jika seri sudah ada di dat_pr → SKIP
            - Jika belum ada → INSERT
            =====================================================
            */

            $matched = 0;

            foreach ($masterData as $row) {

                $exists = DB::table('dat_pr')
                    ->where('seri', $row->seri)
                    ->exists();

                if ($exists) {
                    // Sudah ada → skip
                    continue;
                }

                // Belum ada → insert
                DB::table('dat_pr')->insert([
                    'rab'            => $noRab,
                    'site'           => $kdToko,
                    'seri'           => $row->seri,
                    'keterangan'     => $row->keterangan ?? null,
                    'surkas'         => $row->surkas ?? null,
                    'inv_num'        => $row->inv_num ?? null,
                    'tgl_perolehan'  => $row->tgl_mulai_susut ?? null,
                    'harga'          => $row->harga_perolehan ?? 0,
                ]);

                $matched++;
            }

            DB::commit();

            return response()->json([
                'message' => 'Update AT/PR selesai',
                'matched' => $matched,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            logger()->error('ATPR MATCH ERROR', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat proses matching',
                'error'   => $e->getMessage(),
                'matched' => 0,
            ], 500);
        }
    }
    
    public function update_atprAll(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request) {
            $cabang = $request->query('cabang');
            $lpds = DB::table('lpd')
                ->where('status', '!=', 'Final')
                ->where('report', '=', 'Y')
                ->where('cabang', $cabang)
                ->pluck('no_rab');

            $total = count($lpds);

            foreach ($lpds as $index => $no_rab) {
                try {
                    $request = new \Illuminate\Http\Request();
                    $request->query->set('no_rab', $no_rab);
                    $response = $this->update_atpr($request);

                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                    ];

                    if ($response->status() === 200) {
                        $data = $response->getData(true);
                        $result['message'] = $data['message'] ?? 'Berhasil';
                    } else {
                        $result['message'] = 'Gagal memproses laporan';
                    }
                } catch (\Throwable $e) {
                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                        'message' => 'Error: ' . $e->getMessage(),
                    ];
                }

                echo 'data: ' . json_encode($result) . "\n\n";
                ob_flush();
                flush();
                usleep(50000);
            }

            echo "event: done\n";
            echo "data: selesai\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    public function automatchDatpr(Request $request)
    {
        $noRab = $request->query('no_rab');

        if (!$noRab) {
            return response()->json([
                'message' => 'no_rab tidak ditemukan',
                'matched' => 0,
            ], 400);
        }

        DB::beginTransaction();

        try {
            /*
            =====================================================
            1. Ambil kd_toko
            =====================================================
            */
            $lpd = DB::table('lpd')
                ->where('no_rab', $noRab)
                ->first();

            if (!$lpd) {
                throw new \Exception('LPD tidak ditemukan');
            }

            $kdToko = $lpd->kd_toko;

            /*
            =====================================================
            2. Ambil realisasi (flag kosong)
            =====================================================
            */
            $realisasiList = DB::table($kdToko)
                ->where('rab', $noRab)
                ->where(function ($q) {
                    $q->whereNull('flag_dat_pr')
                    ->orWhere('flag_dat_pr', '');
                })
                ->get();

            /*
            =====================================================
            3. Ambil dat_pr (flag kosong)
            =====================================================
            */
            $datPrList = DB::table('dat_pr')
                ->where('rab', $noRab)
                ->where('surkas', '!=', 'Y')
                ->where(function ($q) {
                    $q->whereNull('flag_realisasi')
                    ->orWhere('flag_realisasi', '');
                })
                ->get();

            if ($realisasiList->isEmpty() || $datPrList->isEmpty()) {
                DB::commit();
                return response()->json([
                    'message' => 'Tidak ada data yang bisa dimatching',
                    'matched' => 0,
                ]);
            }

            $normalize = fn ($v) =>
                preg_replace('/\s+/', ' ', strtoupper(trim((string) $v)));
            
            $matched = 0;

            /*
            =====================================================
            4. MATCHING EXACT (keterangan + dpp)
            =====================================================
            */
            foreach ($realisasiList as $realisasi) {
                // skip kalau sudah ter-match
                if (!empty($realisasi->flag_dat_pr)) {
                    continue;
                }

                $realKet = $normalize($realisasi->keterangan);
                $realDpp = (float) $realisasi->dpp;
                $invNum  = trim((string) $realisasi->inv_num);

                /*
                =====================================================
                STRATEGY 1: KETERANGAN + HARGA
                =====================================================
                */
                $datPr = $datPrList->first(function ($p) use ($realKet, $realDpp, $realisasi, $normalize) {

                    $prKet = $normalize($p->keterangan);

                    $ketMatch =
                        str_contains($realKet, $prKet) ||
                        str_contains($prKet, $realKet);

                    return
                        $ketMatch &&
                        (float) $p->harga === $realDpp &&
                        $p->rab === $realisasi->rab;
                });

                /*
                =====================================================
                STRATEGY 2: INV_NUM + HARGA (FALLBACK)
                =====================================================
                */
                if (!$datPr && !empty($invNum)) {

                    $datPr = $datPrList->first(function ($p) use ($invNum, $realDpp, $realisasi) {
                        return
                            !empty($p->inv_num) &&
                            trim((string) $p->inv_num) === $invNum &&
                            (float) $p->harga === $realDpp &&
                            $p->rab === $realisasi->rab;
                    });
                }

                if (!$datPr) {
                    continue;
                }

                /*
                =====================================================
                UPDATE FLAG
                =====================================================
                */
                $flag = $datPr->id . '-' . $kdToko;

                DB::table($kdToko)
                    ->where('id', $realisasi->id)
                    ->update(['flag_dat_pr' => $flag]);

                DB::table('dat_pr')
                    ->where('id', $datPr->id)
                    ->update(['flag_realisasi' => $flag]);

                // buang dat_pr yang sudah terpakai
                $datPrList = $datPrList->reject(fn ($x) => $x->id === $datPr->id);

                $matched++;
            }

            /*
            =====================================================
            5. MATCHING BY SARANA_TOKO -> MASTER_SARANA -> KEYWORD
            =====================================================
            */
            $realisasiPlu = DB::table($kdToko)
                ->where('rab', $noRab)
                ->where(function ($q) {
                    $q->whereNull('flag_dat_pr')
                    ->orWhere('flag_dat_pr', '');
                })
                ->whereNotNull('flag_sarana')
                ->where('flag_sarana', '!=', '')
                ->get();

            foreach ($realisasiPlu as $realisasi) {
                /*
                -------------------------------------------------
                5.A MATCHING BY REALISASI.PLU -> MASTER_SARANA -> KEYWORD
                -------------------------------------------------
                */
                if (!empty($realisasi->plu)) {

                    $pluLangsung = trim((string) $realisasi->plu);
                    // cari master_sarana berdasarkan PLU realisasi
                    $masterPlu = DB::table('master_sarana')
                        ->where('kode', $pluLangsung)
                        ->first();

                    /*
                    ================================================
                    logger()->info('ATPR PLU DIRECT MASTER', [
                        'plu' => $pluLangsung,
                        'found' => (bool) $masterPlu,
                        'keyword_at_pr' => $masterPlu->keyword_at_pr ?? null,
                        'flag_ongkir' => $masterPlu->flag_ongkir ?? null,
                    ]);
                    ================================================
                    */

                    if ($masterPlu && !empty($masterPlu->keyword_at_pr)) {
                        /*
                        ---------------------------------------------
                        parsing keyword_at_pr
                        ---------------------------------------------
                        */
                        $keywords = [];
                        foreach (explode(';', strtoupper($masterPlu->keyword_at_pr)) as $chunk) {
                            foreach (explode('%', $chunk) as $kw) {
                                $kw = preg_replace('/\s+/', ' ', trim($kw));
                                if ($kw !== '') {
                                    $keywords[] = $kw;
                                }
                            }
                        }
                        
                        /*
                        ================================================
                        logger()->info('ATPR PLU DIRECT KEYWORDS', [
                            'plu' => $pluLangsung,
                            'keywords' => $keywords,
                        ]);
                        ================================================
                        */
                        if (!empty($keywords)) {
                            $matchedDatPr = $datPrList->filter(function ($p) use ($keywords) {
                                $ket = preg_replace('/\s+/', ' ', strtoupper(trim($p->keterangan)));
                                foreach ($keywords as $kw) {
                                    if (strpos($ket, $kw) !== false) {
                                        return true;
                                    }
                                }
                                return false;
                            });

                            if ($matchedDatPr->isNotEmpty()) {
                                $sumHarga = (float) $matchedDatPr->sum('harga');
                                $dppRealisasi = (float) $realisasi->dpp;
                                /*
                                ================================================
                                logger()->info('ATPR PLU DIRECT NILAI CHECK', [
                                    'plu' => $pluLangsung,
                                    'sum_harga' => $sumHarga,
                                    'dpp' => $dppRealisasi,
                                ]);
                                ================================================
                                */

                                if (abs($sumHarga - $dppRealisasi) <= 1) {

                                    $datPrIdMin = $matchedDatPr->min('id');
                                    $flag = $datPrIdMin . '-' . $kdToko;

                                    DB::table($kdToko)
                                        ->where('id', $realisasi->id)
                                        ->update(['flag_dat_pr' => $flag]);

                                    DB::table('dat_pr')
                                        ->whereIn('id', $matchedDatPr->pluck('id'))
                                        ->update(['flag_realisasi' => $flag]);

                                    // buang dat_pr terpakai
                                    $usedIds = $matchedDatPr->pluck('id')->all();
                                    $datPrList = $datPrList->reject(fn ($x) => in_array($x->id, $usedIds));

                                    $matched++;

                                    continue;
                                }
                            }
                        }
                    }
                }

                /*
                =====================================================
                5.B MATCHING BY GROUP_AT_PR
                =====================================================
                */
                if (empty($realisasi->flag_dat_pr)) {

                    /*
                    -------------------------------------------------
                    STEP 1: RESOLVE PLU (AMAN & DEFENSIF)
                    -------------------------------------------------
                    */
                    $resolvedPlu = null;

                    // 1️⃣ PRIORITAS: pakai realisasi.plu jika ada di master_sarana
                    if (!empty($realisasi->plu)) {
                        $exists = DB::table('master_sarana')
                            ->where('kode', $realisasi->plu)
                            ->exists();

                        if ($exists) {
                            $resolvedPlu = $realisasi->plu;
                        }
                    }

                    // 2️⃣ FALLBACK: ambil dari sarana_toko via flag_sarana
                    if (!$resolvedPlu && !empty($realisasi->flag_sarana)) {

                        $sarana = DB::table('sarana_toko')
                            ->where('rab', $noRab)
                            ->where('flag_realisasi', $realisasi->flag_sarana)
                            ->first();

                        if ($sarana && !empty($sarana->kode)) {
                            $resolvedPlu = $sarana->kode;
                        }
                    }

                    // 3️⃣ Jika tetap tidak ada → skip
                    if (!$resolvedPlu) {
                        goto NEXT_STEP;
                    }

                    /*
                    -------------------------------------------------
                    STEP 2: AMBIL MASTER SARANA (GROUP)
                    -------------------------------------------------
                    */
                    $masterPlu = DB::table('master_sarana')
                        ->where('kode', $resolvedPlu)
                        ->whereNotNull('group_at_pr')
                        ->where('group_at_pr', '!=', '')
                        ->first();

                    if (!$masterPlu || empty($masterPlu->keyword_at_pr)) {
                        goto NEXT_STEP;
                    }

                    /*
                    -------------------------------------------------
                    STEP 3: AMBIL SEMUA MASTER DALAM GROUP
                    -------------------------------------------------
                    */
                    $masters = DB::table('master_sarana')
                        ->where('group_at_pr', $masterPlu->group_at_pr)
                        ->whereNotNull('keyword_at_pr')
                        ->where('keyword_at_pr', '!=', '')
                        ->get();

                    if ($masters->isEmpty()) {
                        goto NEXT_STEP;
                    }

                    /*
                    -------------------------------------------------
                    STEP 4: PARSING KEYWORD_AT_PR (NORMALIZED)
                    -------------------------------------------------
                    */
                    $keywords = [];

                    foreach ($masters as $m) {
                        foreach (explode(';', strtoupper($m->keyword_at_pr)) as $chunk) {
                            foreach (explode('%', $chunk) as $kw) {
                                $kw = preg_replace('/\s+/', ' ', trim($kw));
                                if ($kw !== '') {
                                    $keywords[] = $kw;
                                }
                            }
                        }
                    }

                    $keywords = array_values(array_unique($keywords));

                    if (empty($keywords)) {
                        goto NEXT_STEP;
                    }

                    /*
                    -------------------------------------------------
                    STEP 5: AMBIL REALISASI GROUP
                    -------------------------------------------------
                    */
                    $groupRealisasi = DB::table($kdToko)
                        ->where('rab', $noRab)
                        ->where(function ($q) {
                            $q->whereNull('flag_dat_pr')
                            ->orWhere('flag_dat_pr', '');
                        })
                        ->where(function ($q) use ($keywords) {
                            foreach ($keywords as $kw) {
                                $q->orWhereRaw('UPPER(keterangan) LIKE ?', ['%' . $kw . '%']);
                            }
                        })
                        ->get();

                    if ($groupRealisasi->isEmpty()) {
                        goto NEXT_STEP;
                    }

                    /*
                    -------------------------------------------------
                    STEP 6: AMBIL DAT_PR GROUP
                    -------------------------------------------------
                    */
                    $groupDatPr = $datPrList->filter(function ($p) use ($keywords) {
                        $ket = strtoupper($p->keterangan);
                        foreach ($keywords as $kw) {
                            if (strpos($ket, $kw) !== false) {
                                return true;
                            }
                        }
                        return false;
                    });

                    if ($groupDatPr->isEmpty()) {
                        goto NEXT_STEP;
                    }

                    /*
                    -------------------------------------------------
                    STEP 7: CEK NILAI
                    -------------------------------------------------
                    */
                    $sumRealisasi = (float) $groupRealisasi->sum('dpp');
                    $sumDatPr     = (float) $groupDatPr->sum('harga');

                    if (abs($sumRealisasi - $sumDatPr) > 1) {
                        goto NEXT_STEP;
                    }

                    /*
                    -------------------------------------------------
                    STEP 8: UPDATE FLAG
                    -------------------------------------------------
                    */
                    $datPrIdMin = $groupDatPr->min('id');
                    $flag = $datPrIdMin . '-' . $kdToko;

                    DB::table($kdToko)
                        ->whereIn('id', $groupRealisasi->pluck('id'))
                        ->update(['flag_dat_pr' => $flag]);

                    DB::table('dat_pr')
                        ->whereIn('id', $groupDatPr->pluck('id'))
                        ->update(['flag_realisasi' => $flag]);

                    // buang dat_pr terpakai
                    $usedIds = $groupDatPr->pluck('id')->all();
                    $datPrList = $datPrList->reject(fn ($x) => in_array($x->id, $usedIds));

                    $matched++;
                }

                NEXT_STEP:

                /*
                -------------------------------------------------
                5.1 Ambil sarana_toko
                -------------------------------------------------
                */
                $sarana = DB::table('sarana_toko')
                    ->where('rab', $realisasi->rab)
                    ->where('flag_realisasi', $realisasi->flag_sarana)
                    ->first();

                if (!$sarana || empty($sarana->kode)) {
                    continue;
                }

                /*
                -------------------------------------------------
                5.2 Ambil master_sarana
                -------------------------------------------------
                */
                $master = DB::table('master_sarana')
                    ->where('kode', $sarana->kode)
                    ->first();

                if (!$master || empty($master->keyword_at_pr)) {
                    continue;
                }

                /*
                -------------------------------------------------
                5.X HANDLE ONGKOS KIRIM
                -------------------------------------------------
                */
                $totalRealisasi = (float) $realisasi->dpp;
                $ongkirRows = collect();

                if (strtoupper(trim($master->flag_ongkir ?? '')) === 'Y') {
                    // Cari realisasi ongkos kirim
                    $ongkirRows = DB::table($kdToko)
                        ->where('rab', $realisasi->rab)
                        ->where(function ($q) use ($realisasi) {
                            $q->where('inv_num', $realisasi->inv_num)
                            ->orWhere('inv_num', $this->nextInvNum($realisasi->inv_num));
                        })
                        ->where(function ($q) {
                            $q->where('keterangan', 'like', '%ONGKOS KIRIM%')
                            ->orWhere('keterangan', 'like', '%ONGKIR%');
                        })
                        ->where(function ($q) {
                            $q->whereNull('flag_dat_pr')
                            ->orWhere('flag_dat_pr', '');
                        })
                        ->get();

                    $ongkirTotal = (float) $ongkirRows->sum('dpp');

                    $totalRealisasi += $ongkirTotal;
                    /*
                    ================================================
                    logger()->info('ATPR ONGKIR FOUND', [
                        'realisasi_id' => $realisasi->id,
                        'inv_num_barang' => $realisasi->inv_num,
                        'ongkir_count' => $ongkirRows->count(),
                        'ongkir_total' => $ongkirTotal,
                        'total_realisasi' => $totalRealisasi,
                    ]);
                    ================================================
                    */
                }

                /*
                -------------------------------------------------
                5.3 Parsing keyword_at_pr
                -------------------------------------------------
                */
                $keywords = [];
                foreach (explode(';', strtoupper($master->keyword_at_pr)) as $chunk) {
                    foreach (explode('%', $chunk) as $kw) {
                        $kw = preg_replace('/\s+/', ' ', trim($kw));
                        if ($kw !== '') {
                            $keywords[] = $kw;
                        }
                    }
                }
                /*
                ================================================
                logger()->info('ATPR KEYWORDS', [
                    'kode' => $sarana->kode,
                    'keywords' => $keywords,
                ]);
                ================================================
                */

                if (empty($keywords)) {
                    continue;
                }

                /*
                -------------------------------------------------
                5.4 Cari dat_pr berdasarkan keyword
                -------------------------------------------------
                */
                $matchedDatPr = $datPrList->filter(function ($p) use ($keywords) {

                    $ket = preg_replace('/\s+/', ' ', strtoupper(trim($p->keterangan)));

                    foreach ($keywords as $kw) {
                        if (strpos($ket, $kw) !== false) {
                            /*
                            ====================================================
                            logger()->info('ATPR DAT_PR KEYWORD MATCH', [
                                'dat_pr_id' => $p->id,
                                'keterangan' => $p->keterangan,
                                'keyword' => $kw,
                            ]);
                            ====================================================
                            */
                            return true;
                        }
                    }
                    return false;
                });

                if ($matchedDatPr->isEmpty()) {
                    continue;
                }

                /*
                -------------------------------------------------
                5.5 Cek total harga
                -------------------------------------------------
                */
                $sumHarga = (float) $matchedDatPr->sum('harga');
                $dppRealisasi = $totalRealisasi;
                /*
                ================================================
                logger()->info('ATPR NILAI CHECK', [
                    'sum_harga' => $sumHarga,
                    'dpp' => $dppRealisasi,
                ]);
                ================================================
                */
                
                if (abs($sumHarga - $dppRealisasi) > 1) {
                    continue;
                }

                /*
                -------------------------------------------------
                5.6 Update flag (pakai id terkecil)
                -------------------------------------------------
                */
                $datPrIdMin = $matchedDatPr->min('id');
                $flag = $datPrIdMin . '-' . $kdToko;
                
                /*
                -------------------------------------------------
                logger()->info('ATPR MATCH SUCCESS', [
                    'realisasi_id' => $realisasi->id,
                    'dat_pr_ids' => $matchedDatPr->pluck('id')->all(),
                    'flag' => $flag,
                ]);
                -------------------------------------------------
                */
                DB::table($kdToko)
                    ->where('id', $realisasi->id)
                    ->update(['flag_dat_pr' => $flag]);

                DB::table('dat_pr')
                    ->whereIn('id', $matchedDatPr->pluck('id'))
                    ->update(['flag_realisasi' => $flag]);

                foreach ($ongkirRows as $ongkir) {
                    DB::table($kdToko)
                        ->where('id', $ongkir->id)
                        ->update(['flag_dat_pr' => $flag]);
                }

                // buang dat_pr yang sudah terpakai
                $usedIds = $matchedDatPr->pluck('id')->all();
                $datPrList = $datPrList->reject(fn ($x) => in_array($x->id, $usedIds));

                $matched++;    
            }

            /*
            =====================================================
            6. FINAL MATCHING (INVOICE + PLU + GROUP_AT_PR)
            =====================================================
            */
            $pendingInvoice = DB::table($kdToko)
                ->where('rab', $noRab)
                ->where(fn ($q) => $q->whereNull('flag_dat_pr')->orWhere('flag_dat_pr', ''))
                ->whereNotNull('inv_num')
                ->get()
                ->groupBy('inv_num');

            foreach ($pendingInvoice as $invNum => $rows) {

                /*
                =================================================
                STEP 1: Ambil PLU dari realisasi invoice
                =================================================
                */
                $pluList = $rows->pluck('plu')->filter()->unique()->values();

                if ($pluList->isEmpty()) {
                    continue;
                }

                /*
                =================================================
                STEP 2: Ambil master_sarana dari PLU
                =================================================
                */
                $masters = DB::table('master_sarana')
                    ->whereIn('kode', $pluList)
                    ->get();

                if ($masters->isEmpty()) {
                    continue;
                }

                /*
                =================================================
                STEP 2a: Tambahkan PLU master_sarana dengan kategori sama
                =================================================
                */
                $kategoriList = $masters
                    ->pluck('kategori')
                    ->filter()
                    ->unique();

                if ($kategoriList->isNotEmpty()) {
                    $extraPlu = DB::table('master_sarana')
                        ->whereIn('kategori', $kategoriList)
                        ->whereNotNull('kode')
                        ->where('kode', '!=', '')
                        ->pluck('kode');

                    $pluList = $pluList
                        ->merge($extraPlu)
                        ->unique()
                        ->values();
                }

                /*
                =================================================
                STEP 3: Expand group_at_pr
                =================================================
                */
                $groupCodes = $masters
                    ->pluck('group_at_pr')
                    ->filter()
                    ->unique();

                if ($groupCodes->isNotEmpty()) {
                    $masters = $masters->merge(
                        DB::table('master_sarana')
                            ->whereIn('group_at_pr', $groupCodes)
                            ->get()
                    );
                }

                /*
                =================================================
                STEP 4: Kumpulkan keyword_at_pr
                =================================================
                */
                $keywords = [];
                foreach ($masters as $m) {
                    foreach (explode(';', strtoupper($m->keyword_at_pr ?? '')) as $chunk) {
                        foreach (explode('%', $chunk) as $kw) {
                            $kw = preg_replace('/\s+/', ' ', trim($kw));
                            if ($kw !== '') {
                                $keywords[] = $kw;
                            }
                        }
                    }
                }

                $keywords = array_values(array_unique($keywords));
                /* ==============================================
                logger()->info('ATPR MATCH INV_NUM SUCCESS', [
                    'KEYWORDS' => $keywords,
                ]);
                ============================================== */

                if (empty($keywords)) {
                    continue;
                }

                /*
                =================================================
                STEP 5: Ambil realisasi BARANG invoice
                =================================================
                */
                $barangRealisasi = DB::table($kdToko)
                    ->where('rab', $noRab)
                    ->whereIn('plu', $pluList)
                    ->where(fn ($q) =>
                        $q->whereNull('flag_dat_pr')
                        ->orWhere('flag_dat_pr', '')
                    )
                    ->whereRaw("UPPER(keterangan) NOT LIKE '%ONGKOS KIRIM%'")
                    ->get();

                if ($barangRealisasi->isEmpty()) {
                    continue;
                }

                /*
                =================================================
                STEP 6: CEK APAKAH BUTUH ONGKIR
                =================================================
                */
                $butuhOngkir = $masters
                    ->where('flag_ongkir', 'Y')
                    ->isNotEmpty();

                $ongkirRealisasi = collect();

                if ($butuhOngkir) {

                    // ambil semua invoice unik dari barang realisasi
                    $invList = $barangRealisasi
                        ->pluck('inv_num')
                        ->filter()
                        ->unique()
                        ->values();

                    if ($invList->isNotEmpty()) {

                        // tambahkan kemungkinan invoice berikutnya
                        $invWithNext = $invList->flatMap(function ($inv) {
                            return [$inv, $this->nextInvNum($inv)];
                        })->unique();

                        $ongkirRealisasi = DB::table($kdToko)
                            ->where('rab', $noRab)
                            ->whereIn('inv_num', $invWithNext)
                            ->where(fn ($q) =>
                                $q->whereNull('flag_dat_pr')
                                ->orWhere('flag_dat_pr', '')
                            )
                            ->where(function ($q) {
                                $q->where('keterangan', 'like', '%ONGKIR%')
                                ->orWhere('keterangan', 'like', '%ONGKOS KIRIM%');
                            })
                            ->get();
                    }
                }

                /*
                =================================================
                STEP 7: Gabungkan realisasi barang + ongkir
                =================================================
                */
                $groupRealisasi = $barangRealisasi->merge($ongkirRealisasi);
                /* ==============================================
                logger()->info('REALISASI', [
                    'REALISASI' => $groupRealisasi,
                ]);

                
                =================================================
                STEP 8: Ambil dat_pr berdasarkan keyword
                =================================================
                */
                $groupDatPr = $datPrList->filter(function ($p) use ($keywords) {
                    $ket = strtoupper($p->keterangan);
                    foreach ($keywords as $kw) {
                        if (strpos($ket, $kw) !== false) {
                            return true;
                        }
                    }
                    return false;
                });
                $groupRealisasi = $barangRealisasi->merge($ongkirRealisasi);
                /* ==============================================
                logger()->info('AT/PR', [
                    'AT/PR' => $groupDatPr,
                ]);

                if ($groupDatPr->isEmpty()) {
                    continue;
                }

                
                =================================================
                STEP 9: Validasi nilai
                =================================================
                */
                $sumRealisasi = (float) $groupRealisasi->sum('dpp');
                $sumDatPr     = (float) $groupDatPr->sum('harga');
                $selisih = $sumRealisasi - $sumDatPr;
                if ($selisih < -100 || $selisih > 100) {
                    continue;
                }

                /* ==============================================
                logger()->info('SELISIH', [
                    'Realisasi' => $sumRealisasi,
                    'AT/PR' => $sumDatPr,
                ]);
                
                =================================================
                STEP 10: UPDATE FLAG
                =================================================
                */
                $flag = $groupDatPr->min('id') . '-' . $kdToko;

                DB::table($kdToko)
                    ->whereIn('id', $groupRealisasi->pluck('id'))
                    ->update(['flag_dat_pr' => $flag]);

                DB::table('dat_pr')
                    ->whereIn('id', $groupDatPr->pluck('id'))
                    ->update(['flag_realisasi' => $flag]);

                // buang dat_pr terpakai
                $usedIds = $groupDatPr->pluck('id')->all();
                $datPrList = $datPrList->reject(fn ($x) => in_array($x->id, $usedIds));

                $matched++;
            }

            DB::commit();

            return response()->json([
                'message' => 'Auto matching AT/PR selesai',
                'matched' => $matched,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            /*
            ==================================================
            logger()->error('ATPR MATCH ERROR', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            ==================================================
            */

            return response()->json([
                'message' => 'Terjadi kesalahan saat proses matching',
                'error'   => $e->getMessage(),
                'matched'=> 0,
            ], 500);
        }
    }

    public function automatchDatprAll(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request) {
            $cabang = $request->query('cabang');
            $lpds = DB::table('lpd')
                ->where('status', '=', 'New')
                ->where('report', '=', 'Y')
                ->where('cabang', $cabang)
                ->pluck('no_rab');

            $total = count($lpds);

            foreach ($lpds as $index => $no_rab) {
                try {
                    $request = new \Illuminate\Http\Request();
                    $request->query->set('no_rab', $no_rab);
                    $response = $this->automatchDatpr($request);

                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                    ];

                    if ($response->status() === 200) {
                        $data = $response->getData(true);
                        $result['message'] = $data['message'] ?? 'Berhasil';
                    } else {
                        $result['message'] = 'Gagal memproses laporan';
                    }
                } catch (\Throwable $e) {
                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                        'message' => 'Error: ' . $e->getMessage(),
                    ];
                }

                echo 'data: ' . json_encode($result) . "\n\n";
                ob_flush();
                flush();
                usleep(50000);
            }

            echo "event: done\n";
            echo "data: selesai\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    public function syncPP(Request $request)
    {
        $noRab = $request->query('no_rab');
        $cabang = $request->input('cabang');
        
        if (!$noRab) {
            return response()->json([
                'success' => false,
                'message' => 'No RAB tidak ditemukan'
            ], 400);
        }

        try {
            $this->processSyncPP($noRab, $cabang);

            return response()->json([
                'success' => true,
                'message' => 'Sync PP berhasil'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function syncPPAll(Request $request): StreamedResponse
    {
        $cabang = $request->query('cabang');

        return response()->stream(function () use ($cabang) {

            $lpds = DB::table('lpd')
                ->where('status', 'New')
                ->where('report', 'Y')
                ->where('cabang', $cabang)
                ->pluck('no_rab');

            $total = count($lpds);

            foreach ($lpds as $index => $no_rab) {

                try {
                    $this->processSyncPP($no_rab, $cabang);

                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                        'message' => 'Berhasil'
                    ];

                } catch (\Throwable $e) {

                    $result = [
                        'progress' => $index + 1,
                        'total' => $total,
                        'no_rab' => $no_rab,
                        'message' => 'Error: ' . $e->getMessage()
                    ];
                }

                echo "data: " . json_encode($result) . "\n\n";
                ob_flush();
                flush();
            }

            echo "event: done\n";
            echo "data: selesai\n\n";
            ob_flush();
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    private function processSyncPP(string $noRab, string $cabang)
    {
        $response = Http::timeout(600)->post(
            'http://127.0.0.1:5000/run-job',
            [
                'type' => 'sarana',
                'payload' => [
                    'listRAB' => [$noRab]
                ]
            ]
        );

        if (!$response->successful()) {
            throw new \Exception('Download gagal dari worker: ' . $response->body());
        }

        // ================= AMBIL FILE HASIL DOWNLOAD
        $saranaPath = base_path('../automation/sarana');
        $files = glob($saranaPath . '/*.xlsx');

        $service = app(\App\Services\ImportPPService::class);

        foreach ($files as $file) {
            $service->handleServerFile($file, $cabang);
        }
    }

    /*
    -----------------------------------
    Helper
    -----------------------------------
    */
    private function nextInvNum(string $inv): string
    {
        return preg_replace_callback('/(\d+)(?!.*\d)/', function ($m) {
            return str_pad(((int)$m[1]) + 1, strlen($m[1]), '0', STR_PAD_LEFT);
        }, $inv);
    }

    private function prevInvNum(string $inv): string
    {
        return preg_replace_callback('/(\d+)(?!.*\d)/', function ($m) {
            return str_pad(((int)$m[1]) - 1, strlen($m[1]), '0', STR_PAD_LEFT);
        }, $inv);
    }
}