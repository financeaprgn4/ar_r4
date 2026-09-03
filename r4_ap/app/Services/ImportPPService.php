<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use App\Helpers\DateHelper;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportPPService
{
    public function handleUploadFiles(array $files, $cabang)
    {
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension());

            if (in_array($ext, ['xls', 'xlsx'])) {
                $this->importFromExcel($file, $cabang);
            } elseif ($ext === 'pdf') {
                $this->importFromPdf($file, $cabang);
            } else {
                throw new \Exception("Format file .$ext tidak didukung");
            }
        }
    }

    public function handleServerFile(string $filePath, $cabang)
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if (in_array($ext, ['xls', 'xlsx'])) {
            $this->importFromExcel($filePath, $cabang);
        } elseif ($ext === 'pdf') {
            $this->importFromPdf($filePath, $cabang);
        } else {
            throw new \Exception("Format file .$ext tidak didukung");
        }
    }

    private function importFromExcel($file, string $cabang)
    {
        // ===============================
        // Tentukan path file
        // ===============================

        if ($file instanceof \Illuminate\Http\UploadedFile) {
            // Mode upload biasa
            $path = $file->store('imports');
            $fullPath = \Storage::path($path);
        } else {
            // Mode server file (hasil automation)
            $fullPath = $file;
        }

        // ===============================
        // Load Excel
        // ===============================

        $spreadsheet = IOFactory::load($fullPath);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $totalRow = $sheet->getHighestRow();

            for ($row = 13; $row <= $totalRow; $row++) {

                $rab = trim((string) $sheet->getCell("A$row")->getValue());
                if ($rab === '') continue;

                $plu = trim((string) $sheet->getCell("C$row")->getValue());
                $barang = trim((string) $sheet->getCell("D$row")->getValue());

                if (empty($barang) && !empty($plu)) {
                    $barang = DB::table('master_sarana')
                        ->where('kode', $plu)
                        ->value('uraian') ?? null;
                }

                $data = [
                    'rab'             => $rab,
                    'tgl_rab'         => DateHelper::excelDateToDate($sheet->getCell("B$row")->getValue()),
                    'plu'             => $plu,
                    'barang'          => $barang,
                    'qty_barang'      => $sheet->getCell("E$row")->getValue(),
                    'pp'              => $sheet->getCell("F$row")->getValue(),
                    'tgl_pp'          => DateHelper::excelDateToDate($sheet->getCell("G$row")->getValue()),
                    'qty_pp'          => $sheet->getCell("H$row")->getValue(),
                    'sp'              => $sheet->getCell("I$row")->getValue(),
                    'tgl_sp'          => DateHelper::excelDateToDate($sheet->getCell("J$row")->getValue()),
                    'qty_sp'          => $sheet->getCell("K$row")->getValue(),
                    'exp_sp'          => DateHelper::excelDateToDate($sheet->getCell("L$row")->getValue()),
                    'sp_awal'         => $sheet->getCell("M$row")->getValue(),
                    'tgl_sp_awal'     => DateHelper::excelDateToDate($sheet->getCell("N$row")->getValue()),
                    'exp_sp_awal'     => DateHelper::excelDateToDate($sheet->getCell("O$row")->getValue()),
                    'btb'             => $sheet->getCell("P$row")->getValue(),
                    'tgl_btb'         => DateHelper::excelDateToDate($sheet->getCell("Q$row")->getValue()),
                    'qty_btb'         => $sheet->getCell("R$row")->getValue(),
                    'cabang'          => $cabang,
                ];

                DB::table('pp')->updateOrInsert(
                    ['rab' => $data['rab'], 'plu' => $data['plu']],
                    $data
                );
            }
        }
    }

    private function importFromPdf($file, string $cabang)
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($file->getPathname());

            $lines = preg_split('/\r\n|\r|\n/', $pdf->getText());

            $rows = [];
            $current = '';

            /**
             * ==========================================================
             * STEP 1 — GABUNG BARIS PDF MENJADI LOGICAL ROW
             * ==========================================================
             */
            foreach ($lines as $line) {
                $line = trim(preg_replace('/\s+/', ' ', $line));
                if ($line === '') continue;

                // skip header / footer / noise
                if (
                    str_contains($line, 'LAPORAN MONITORING') ||
                    str_contains($line, 'Kode - Nama Cabang') ||
                    str_contains($line, 'Permintaan Pembelian') ||
                    str_contains($line, 'Surat Pesanan') ||
                    str_contains($line, 'BTB') ||
                    str_contains($line, 'User ID') ||
                    str_contains($line, '©') ||
                    preg_match('/^\d+\s+of\s+\d+$/i', $line)
                ) {
                    continue;
                }

                // baris baru jika diawali kode 03G
                if (preg_match('/^03G\d+\/\d+\/\d+\/\d+/', $line)) {
                    if ($current !== '') {
                        $rows[] = $current;
                    }
                    $current = $line;
                } else {
                    $current .= ' ' . $line;
                }
            }

            if ($current !== '') {
                $rows[] = $current;
            }

            /**
             * ==========================================================
             * STATE UNTUK BARIS LANJUTAN (SP SEBELUMNYA / BTB)
             * ==========================================================
             */
            $lastRab = null;
            $lastPlu = null;

            /**
             * ==========================================================
             * STEP 2 — PARSING PER ROW
             * ==========================================================
             */
            foreach ($rows as $rowText) {

                /**
                 * ----------------------------------
                 * NORMALISASI KRITIS (URUTAN WAJIB)
                 * ----------------------------------
                 */

                // Satukan tanggal wrap
                $rowText = preg_replace('/(\d{2})-\s*(\d{2})-\s*(\d{4})/', '$1-$2-$3', $rowText);

                // Pisahkan RAB + tanggal RAB  (INI YANG KURANG)
                $rowText = preg_replace(
                    '/(03G\d+\/\d+\/\d+\/\d{2})(\d{2}-\d{2}-\d{4})/',
                    '$1 $2',
                    $rowText
                );

                //pisahkan tanggal + PLU (10-10-202400237395 → 10-10-2024 00237395)
                $rowText = preg_replace(
                    '/(\d{2}-\d{2}-\d{4})(\d{6,10})/',
                    '$1 $2',
                    $rowText
                );

                // Pisahkan PLU & deskripsi
                $rowText = preg_replace(
                    '/(\d{6,10})([A-Z])/',
                    '$1 $2',
                    $rowText
                );

                // pisahkan DESKRIPSI + QTY BARANG (CID2.00 → CID 2.00)
                $rowText = preg_replace(
                    '/([A-Z])(\d+\.\d{2})/',
                    '$1 $2',
                    $rowText
                );

                // Pisahkan qty + kode
                $rowText = preg_replace(
                    '/(\d+\.\d{2})(03G)/',
                    '$1 $2',
                    $rowText
                );

                // Pisahkan kode PP/SP + tanggal
                $rowText = preg_replace(
                    '/(03G\d+\/\d+\/\d+\/\d{4})(\d{2}-\d{2}-\d{4})/',
                    '$1 $2',
                    $rowText
                );

                // pisahkan TGL PP + QTY PP (22-12-202510.00 → 22-12-2025 10.00)
                $rowText = preg_replace(
                    '/(\d{2}-\d{2}-\d{4})(\d+\.\d{2})/',
                    '$1 $2',
                    $rowText
                );

                // 4. Pisahkan qty + tanggal expired
                $rowText = preg_replace(
                    '/(\d+\.\d{2})(\d{2}-\d{2}-\d{4})/',
                    '$1 $2',
                    $rowText
                );

                // 7. Rapikan spasi (HARUS TERAKHIR)
                $rowText = trim(preg_replace('/\s+/', ' ', $rowText));

                if (
                    str_contains($rowText, 'No Tanggal AT') ||
                    str_contains($rowText, 'Kode PP') ||
                    str_contains($rowText, 'PLU Deskripsi')
                ) {
                    continue;
                }

                \Log::info('PDF CLEAN ROW', ['row' => $rowText]);

                /**
                 * ======================================================
                 * A. ROW UTAMA (RAB – PP – SP)
                 * ======================================================
                 */
                if (preg_match(
                    '/^
                    (?<rab>03G\d+\/\d+\/\d+\/\d+)\s+
                    (?<tgl_rab>\d{2}-\d{2}-\d{4})\s+
                    (?<plu>\d{6,10})\s+
                    (?<barang>.+?)\s+
                    (?<qty_barang>\d+\.\d{2})\s+
                    (?<pp>03G\d+\/\d+\/\d+\/\d+)\s+
                    (?<tgl_pp>\d{2}-\d{2}-\d{4})\s+
                    (?<qty_pp>\d+\.\d{2})\s+
                    (?<sp>03G\d+\/\d+\/\d+\/\d+)\s+
                    (?<tgl_sp>\d{2}-\d{2}-\d{4})\s+
                    (?<qty_sp>\d+\.\d{2})\s+
                    (?<exp_sp>\d{2}-\d{2}-\d{4})
                    /x',
                    $rowText,
                    $m
                )) {

                    DB::table('pp')->updateOrInsert(
                        [
                            'rab' => $m['rab'],
                            'plu' => $m['plu'],
                        ],
                        [
                            'cabang'     => $cabang,
                            'tgl_rab'    => $this->parsePdfDate($m['tgl_rab']),
                            'barang'     => trim($m['barang']),
                            'qty_barang' => (float)$m['qty_barang'],
                            'pp'         => $m['pp'],
                            'tgl_pp'     => $this->parsePdfDate($m['tgl_pp']),
                            'qty_pp'     => (float)$m['qty_pp'],
                            'sp'         => $m['sp'],
                            'tgl_sp'     => $this->parsePdfDate($m['tgl_sp']),
                            'qty_sp'     => (float)$m['qty_sp'],
                            'exp_sp'     => $this->parsePdfDate($m['exp_sp']),
                        ]
                    );

                    // simpan state untuk baris lanjutan
                    $lastRab = $m['rab'];
                    $lastPlu = $m['plu'];

                    \Log::info('PDF MAIN ROW OK', [
                        'rab' => $lastRab,
                        'plu' => $lastPlu,
                    ]);

                    continue;
                }

                /**
                 * ======================================================
                 * B. ROW LANJUTAN (SP SEBELUMNYA + BTB)
                 * ======================================================
                 */
                if (
                    $lastRab &&
                    $lastPlu &&
                    preg_match(
                        '/^
                        (?<sp_prev>03G\d+\/\d+\/\d+\/\d+)\s+
                        (?<tgl1>\d{2}-\d{2}-\d{4})\s+
                        (?<tgl2>\d{2}-\d{2}-\d{4})\s+
                        (?<btb>\d+)\s+
                        (?<tgl_btb>\d{2}-\d{2}-\d{4})\s+
                        (?<qty_btb>\d+\.\d{2})
                        /x',
                        $rowText,
                        $m2
                    )
                ) {
                    DB::table('pp')
                        ->where('rab', $lastRab)
                        ->where('plu', $lastPlu)
                        ->update([
                            'sp_awal'       => $m2['sp_prev'],
                            'tgl_sp_awal'   => $this->parsePdfDate($m2['tgl1']),
                            'exp_sp_awal'   => $this->parsePdfDate($m2['tgl2']),
                            'btb'           => $m2['btb'],
                            'tgl_btb'       => $this->parsePdfDate($m2['tgl_btb']),
                            'qty_btb'       => (float)$m2['qty_btb'],
                        ]);

                    \Log::info('PDF BTB ROW OK', [
                        'rab' => $lastRab,
                        'plu' => $lastPlu,
                        'btb' => $m2['btb'],
                    ]);

                    continue;
                }

                \Log::warning('PDF ROW UNMATCHED', ['row' => $rowText]);
            }

        } catch (\Throwable $e) {
            \Log::error('Import PP Error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
