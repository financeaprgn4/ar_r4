<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\JournalRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Statement;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;

class StatementController extends Controller
{
    public function getPeriode(Request $request)
    {
        $cabang = $request->cabang;

        $periode = DB::table('periode')
            ->where('Cabang', $cabang)
            ->where('kategori', 'Mutasi')
            ->orderByRaw("CASE WHEN status = 'Aktif' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($periode);
    }

    public function StatementList(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter cabang tidak ditemukan'
            ], 400);
        }

        $data = Statement::where('cabang', $cabang)
                ->select('id', 'cabang', 'nama_bank', 'jns_rek', 'no_rek', 'file', 'periode')
                ->orderBy('id', 'desc')
                ->get();

        return response()->json($data);
    }

    public function getMutasi(Request $request)
    {
        $cabang   = $request->cabang;
        $periode  = $request->periode;
        $kategori = $request->kategori ?? 'Reguler';

        // Ambil periode berdasarkan cabang dan periode aktif
        $periodeData = DB::table('periode')
            ->where('cabang', $cabang)
            ->where('kategori', 'Mutasi')
            ->where('periode', $periode)
            ->select('start_date', 'end_date')
            ->first();

        if (!$periodeData) {
            return response()->json([
                'success' => false,
                'message' => 'Periode tidak ditemukan'
            ], 404);
        }

        $start = $periodeData->start_date;
        $end   = $periodeData->end_date;

        // Pilih tabel berdasarkan kategori
        $table = $kategori === 'Franchise'
            ? 'mutasi_frc'
            : 'mutasi';

        // Ambil mutasi yang rentang tanggalnya
        // bersinggungan dengan periode aktif
        $data = DB::table($table)
            ->where('cabang', $cabang)
            ->where('tgl_awal', '<=', $end)
            ->where('tgl_akhir', '>=', $start)
            ->orderBy('tgl_awal', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function deleteMutasi($file, $cabang)
    {
        try {

            $file = basename($file);
            
            /*
            ===========================================
            DETEKSI REG / FRC
            ===========================================
            */

            $site = "REG";

            $mutasi = DB::table('mutasi')
                ->where('file', $file)
                ->where('cabang', $cabang)
                ->first();

            if (!$mutasi) {

                $mutasi = DB::table('mutasi_frc')
                    ->where('file', $file)
                    ->where('cabang', $cabang)
                    ->first();

                if ($mutasi) {
                    $site = "FRC";
                }

            }

            if (!$mutasi) {

                return response()->json([
                    'success'=>false,
                    'message'=>"Mutasi tidak ditemukan."
                ]);

            }

            /*
            ===========================================
            TABLE
            ===========================================
            */

            $tableMutasi = $site == "REG" ? "mutasi" : "mutasi_frc";
            $tableDetail = $site == "REG" ? "mutasi_detail" : "mutasi_detail_frc";
            $tableRekap = $site == "REG" ? "mutasi_rekap" : "mutasi_rekap_frc";
            $tableUnrec = $site == "REG" ? "mutasi_rekap_unrec" : "mutasi_rekap_unrec_frc";

            DB::beginTransaction();

            /*
            ====================================================
            AMBIL DAFTAR REKENING
            ====================================================
            */

            if ($site == "REG") {

                $rekening = collect([$mutasi->no_rek]);

            } else {

                $rekening = DB::table($tableDetail)
                    ->where('src', $file)
                    ->distinct()
                    ->pluck('no_rek');

            }

            /*
            ====================================================
            REFF
            ====================================================
            */

            // Ambil semua reff dari detail sebelum data dihapus
            $reffList = DB::table($tableDetail)
                ->where('src', $file)
                ->where('cabang', $cabang)
                ->whereNotNull('reff')
                ->where('reff', '<>', '')
                ->distinct()
                ->pluck('reff')
                ->filter(fn($reff) => trim((string) $reff) !== '')
                ->map(fn($reff) => trim((string) $reff))
                ->values()
                ->toArray();


            /*
            ====================================================
            RESET REFF RECEIPT
            ====================================================
            */

            if (!empty($reffList)) {

                DB::table('receipt')
                    ->whereIn('reff', $reffList)
                    ->update([
                        'reff' => null
                    ]);

            }

            /*
            ====================================================
            DETAIL
            ====================================================
            */

            DB::table($tableDetail)
                ->where('src', $file)
                ->where('cabang', $cabang)
                ->delete();

            /*
            ====================================================
            REKAP
            ====================================================
            */

            DB::table($tableRekap)
                ->whereIn('no_rek', $rekening)
                ->where('cabang', $cabang)
                ->whereBetween('tgl', [
                    $mutasi->tgl_awal,
                    $mutasi->tgl_akhir
                ])
                ->delete();

            DB::table($tableUnrec)
                ->whereIn('no_rek', $rekening)
                ->where('cabang', $cabang)
                ->whereBetween('tgl', [
                    $mutasi->tgl_awal,
                    $mutasi->tgl_akhir
                ])
                ->delete();

            /*
            ====================================================
            HEADER
            ====================================================
            */

            DB::table($tableMutasi)
                ->where('file', $file)
                ->where('cabang', $cabang)
                ->delete();

            DB::commit();

            /*
            ===========================================
            FILE
            ===========================================
            */

            $basePath = "C:/xampp/htdocs/AP_R4/File/mutasi/{$cabang}/";

            $fullPath = $basePath . $file;

            if (is_file($fullPath)) {
                unlink($fullPath);
            }

            return response()->json([
                'success'=>true,
                'message'=>"File berhasil dihapus."
            ]);

        } catch (\Throwable $e) {

            \Log::error("DELETE ERROR", [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
        
            try {
                DB::rollBack();
            } catch (\Throwable $rollbackError) {
        
                \Log::error("ROLLBACK ERROR", [
                    'message' => $rollbackError->getMessage()
                ]);
        
            }
        
            return response()->json([
                'success' => false,
                'message' => "Gagal menghapus file.",
                'error' => $e->getMessage()
            ],500);

        }

    }

    public function importMutasi(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file',
            'cabang'  => 'required|string'
        ]);

        $cabang = $request->cabang;
        $folder = DB::table('setting')
            ->where('cabang', $cabang)
            ->where('category', 'rk_storage')
            ->value('value');

        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $errors = [];

        foreach ($request->file('files') as $file) {

            $filename = $file->getClientOriginalName();
            $upper = strtoupper($filename);
            
            // IDENTIFIKASI BANK
            $bank = match (true) {
                // REG
                str_contains($upper, "(BCA)")      => "BCA",
                str_contains($upper, "(MDR)")      => "MANDIRI",
                str_contains($upper, "(BRI)")      => "BRI",
                str_contains($upper, "(BNI)")      => "BNI",
                str_contains($upper, "(BSI)")      => "BSI",
                str_contains($upper, "(BTN)")      => "BTN",
                str_contains($upper, "(NIAGA)")    => "CIMB NIAGA",
                str_contains($upper, "TEMP")       => "INA PERDANA",
            
                // FRC
                str_contains($upper, "MCM_")               => "MANDIRI",
                str_contains($upper, "NIAGA_")             => "CIMB NIAGA",
                str_contains($upper, "MUTASI_BCA")         => "BCA",
                str_contains($upper, "BRI_MTS_")           => "BRI",
                str_contains($upper, "BNI_MTS_")           => "BNI",
                str_contains($upper, "ACCOUNT STATEMENT")  => "BSI",
            
                default => null
            };

            $site = match (true) {
                str_contains($upper, "MCM_"),
                str_contains($upper, "NIAGA_"),
                str_contains($upper, "MUTASI_BCA"),
                str_contains($upper, "BRI_MTS_"),
                str_contains($upper, "BNI_MTS_"),
                str_contains($upper, "ACCOUNT STATEMENT") => "FRC",
            
                default => "REG"
            };
            
            $bankFrc = [
                "BCA"         => "BCA Frc",
                "MANDIRI"     => "MDR Frc",
                "BRI"         => "BRI Frc",
                "CIMB NIAGA"  => "CIMB NIAGA Frc",
                "BNI"         => "BNI Frc",
                "BSI"         => "BSI Frc",
            ];
            
            $bankMutasi = $site == "REG"
                ? $bank
                : ($bankFrc[$bank] ?? $bank);

            if (!$bank) {
                $errors[] = ["file" => $filename, "reason" => "Nama file tidak standar"];
                continue;
            }

            // IDENTIFIKASI NO REKENING
            if ($site == "REG") {
                if ($bank !== "INA PERDANA") {
                    $pos1 = strpos($filename, ')');
                    $pos2 = strpos($filename, '_');
            
                    if ($pos1 === false || $pos2 === false) {
                        $errors[] = [
                            "file" => $filename,
                            "reason" => "Format filename tidak sesuai pola rekening"
                        ];
                        continue;
                    }
            
                    $no_rek = substr($filename, $pos1 + 1, $pos2 - $pos1 - 1);
            
                } else {

                    // =====================================================
                    // INA PERDANA
                    // =====================================================
                
                    $handle = fopen($file->getRealPath(), 'r');
                
                    if (!$handle) {
                
                        $errors[] = [
                            "file"   => $filename,
                            "reason" => "File tidak dapat dibuka"
                        ];
                
                        continue;
                    }
                
                    /*
                    =====================================================
                    HEADER
                    =====================================================
                    */
                
                    $header = fgetcsv($handle, 0, ',');
                
                    if ($header === false) {
                
                        fclose($handle);
                
                        $errors[] = [
                            "file"   => $filename,
                            "reason" => "File INA PERDANA kosong atau header tidak ditemukan."
                        ];
                
                        continue;
                    }
                
                    /*
                    =====================================================
                    CARI KOLOM NOMOR REKENING
                    =====================================================
                    */
                
                    $accountIndex = null;
                
                    foreach ($header as $index => $column) {
                
                        $column = strtoupper(
                            trim(
                                preg_replace('/^\xEF\xBB\xBF/', '', $column)
                            )
                        );
                
                        if (
                            $column === "BANK ACCOUNT NUMBER" ||
                            str_contains($column, "BANK ACCOUNT NUMBER")
                        ) {
                
                            $accountIndex = $index;
                            break;
                        }
                    }
                
                
                    /*
                    =====================================================
                    FALLBACK
                    =====================================================
                    
                    Jika header tidak berhasil dikenali,
                    gunakan kolom index 2 seperti format INA
                    =====================================================
                    */
                
                    if ($accountIndex === null) {
                        $accountIndex = 2;
                    }
                
                
                    /*
                    =====================================================
                    AMBIL NOMOR REKENING
                    =====================================================
                    */
                
                    $no_rek = null;
                    $invalid = false;
                
                    while (($row = fgetcsv($handle, 0, ',')) !== false) {
                
                        /*
                        -----------------------------------------------
                        Abaikan baris kosong
                        -----------------------------------------------
                        */
                
                        if (
                            empty($row) ||
                            !isset($row[$accountIndex])
                        ) {
                            continue;
                        }
                
                
                        /*
                        -----------------------------------------------
                        Ambil nomor rekening
                        -----------------------------------------------
                        */
                
                        $currentNoRek = trim(
                            preg_replace(
                                '/^\xEF\xBB\xBF/',
                                '',
                                (string) $row[$accountIndex]
                            )
                        );
                
                
                        /*
                        -----------------------------------------------
                        Abaikan rekening kosong
                        -----------------------------------------------
                        */
                
                        if ($currentNoRek === '') {
                            continue;
                        }
                
                
                        /*
                        -----------------------------------------------
                        Rekening pertama
                        -----------------------------------------------
                        */
                
                        if ($no_rek === null) {
                
                            $no_rek = $currentNoRek;
                
                        }
                
                
                        /*
                        -----------------------------------------------
                        Pastikan semua transaksi menggunakan
                        rekening yang sama
                        -----------------------------------------------
                        */
                
                        elseif ($currentNoRek !== $no_rek) {
                
                            $invalid = true;
                            break;
                
                        }
                
                    }
                
                    fclose($handle);
                
                
                    /*
                    =====================================================
                    VALIDASI REKENING TIDAK KONSISTEN
                    =====================================================
                    */
                
                    if ($invalid) {
                
                        $errors[] = [
                            "file"   => $filename,
                            "reason" => "Nomor rekening pada file tidak konsisten."
                        ];
                
                        continue;
                    }
                
                
                    /*
                    =====================================================
                    VALIDASI REKENING TIDAK DITEMUKAN
                    =====================================================
                    */
                
                    if ($no_rek === null) {
                
                        $errors[] = [
                            "file"   => $filename,
                            "reason" => "Nomor rekening tidak ditemukan."
                        ];
                
                        continue;
                    }
                
                }
            
            } else {
                
                // =========================
                // FRANCHISE
                // =========================
            
                switch ($bank) {

                    case 'BCA':
                    case 'MANDIRI':
                    case 'BNI':
                    case 'CIMB NIAGA':
                
                        // Satu file berisi banyak rekening.
                        // Nomor rekening akan diperoleh saat parsing file.
                        $no_rek = null;
                        break;
                
                    case 'BRI':
                
                        // contoh :
                        // BRI_MTS_IDM_FZJA_090726.csv
                
                        if (!preg_match('/BRI_MTS_IDM_([A-Z0-9]+)_/i', $filename, $match)) {
                
                            $errors[] = [
                                "file" => $filename,
                                "reason" => "Kode toko tidak ditemukan pada filename"
                            ];
                
                            continue 2;
                
                        }
                
                        $kode = strtoupper($match[1]);
                
                        $no_rek = DB::table('bank')
                            ->where('cabang', $cabang)
                            ->where('site', $kode)
                            ->where('bank', 'BRI')
                            ->value('no_rek');
                
                        if (!$no_rek) {
                
                            $errors[] = [
                                "file" => $filename,
                                "reason" => "Kode toko {$kode} belum terdaftar"
                            ];
                
                            continue 2;
                
                        }
                
                        break;
                
                    case 'BSI':
                
                        // contoh :
                        // Account Statement - 7219564176 - PT INDOMARCO PRISMATAMA - FLHX.csv
                
                        if (!preg_match('/(\d{8,20})/', $filename, $match)) {
                
                            $errors[] = [
                                "file" => $filename,
                                "reason" => "Nomor rekening tidak ditemukan pada filename"
                            ];
                
                            continue 2;
                
                        }
                
                        $no_rek = $match[1];
                
                        break;
                
                    default:
                
                        $errors[] = [
                            "file" => $filename,
                            "reason" => "Bank Franchise belum didukung"
                        ];
                
                        continue 2;
                }
            }
            
            // TABLE
            $tableMutasi = $site == "REG" ? "mutasi" : "mutasi_frc";
            $tableDetail = $site == "REG" ? "mutasi_detail" : "mutasi_detail_frc";
            $tableRekap = $site == "REG" ? "mutasi_rekap" : "mutasi_rekap_frc";
            $tableRekapUnrec = $site == "REG" ? "mutasi_rekap_unrec" : "mutasi_rekap_unrec_frc";
            
            if (
                DB::table($tableMutasi)
                    ->where('file', $filename)
                    ->where('cabang', $cabang)
                    ->exists()
            ) {
                $errors[] = [
                    "file" => $filename,
                    "reason" => "File sudah pernah diimport untuk cabang tersebut"
                ];
            
                continue;
            }

            try {
                /*
                ==========================================================
                LOAD FILE
                ==========================================================
                */
                $rekeningTidakTerdaftar = [];

                if ($site == "FRC" && in_array($bank, [
                    "BCA",
                    "CIMB NIAGA"
                ])) {
                
                    $rows = file(
                        $file->getPathname(),
                        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
                    );
                
                } else {
                
                    $delimiter = ",";
                
                    if ($bank == "MANDIRI" && $site == "REG") {
                        $delimiter = ";";
                    }
                
                    $rows = array_map(
                        fn($line) => str_getcsv($line, $delimiter),
                        file($file->getPathname())
                    );
                
                }
                
                /* =================== PARSER ================== */
                /* ==================== BCA ==================== */
                if ($bank === "BCA") {
                    if ($site == "REG") {
                        /*
                        ==========================================================
                        PARSER REG
                        ==========================================================
                        */
                        $noTransaksiDetected = collect($rows)
                            ->contains(fn($r) =>
                                isset($r[0]) &&
                                str_contains(strtoupper($r[0]), "TIDAK ADA TRANSAKSI")
                            );
                    
                        $periodeLine = $noTransaksiDetected
                            ? ($rows[6][0] ?? null)
                            : ($rows[4][0] ?? null);
                    
                        preg_match(
                            '/(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})/',
                            $periodeLine,
                            $tanggal
                        );
                    
                        if (count($tanggal) < 3) {
                            throw new \Exception("Periode tidak ditemukan pada file BCA");
                        }
                    
                        $tgl_awal = Carbon::createFromFormat(
                            'd/m/Y',
                            $tanggal[1]
                        )->format('Y-m-d');
                    
                        $tgl_akhir = Carbon::createFromFormat(
                            'd/m/Y',
                            $tanggal[2]
                        )->format('Y-m-d');
                    
                        /*
                        ==========================================================
                        Samakan struktur dengan FRC
                        ==========================================================
                        */
                        $dataRows = [];
                        for ($i = 7; $i < count($rows); $i++) {

                            if (
                                isset($rows[$i][0]) &&
                                str_contains($rows[$i][0], "Saldo Awal")
                            ) {
                                break;
                            }

                            if (
                                empty($rows[$i]) ||
                                !isset($rows[$i][0]) ||
                                trim($rows[$i][0]) === ''
                            ) {
                                continue;
                            }

                            $dataRows[] = $rows[$i];
                        }

                        /*
                        ==========================================================
                        FILE BCA REG TIDAK ADA TRANSAKSI
                        ==========================================================
                        */

                        if (
                            $noTransaksiDetected ||
                            empty($dataRows)
                        ) {

                            throw new \Exception("__NO_TRANSACTION__");

                        }

                        $rekening = [];
                    
                        $rekening[$no_rek] = [
                            'tgl_awal' => $tgl_awal,
                            'tgl_akhir' => $tgl_akhir,
                            'rows' => $dataRows,
                            'no_transaksi'=>false
                        ];
                    
                    } else {
                        /*
                        ==========================================================
                        PARSER FRC
                        ==========================================================
                        */
                    
                        $rekening = [];

                        $rekeningValid = DB::table('bank')
                            ->where('cabang', $cabang)
                            ->where('site','<>' ,'REG')
                            ->where('jns_bank', 'BCA Frc')
                            ->pluck('no_rek')
                            ->map(fn($rek) => trim($rek))
                            ->filter(fn($rek) => $rek !== '')
                            ->toArray();

                        $rekeningValid = array_flip($rekeningValid);

                        // Rekening REG yang ikut muncul di file FRC harus diabaikan.
                        $rekeningReg = DB::table('bank')
                            ->where('cabang', $cabang)
                            ->where('site', 'REG')
                            ->pluck('no_rek')
                            ->map(fn($rek) => trim($rek))
                            ->filter(fn($rek) => $rek !== '')
                            ->flip()
                            ->toArray();
                            
                        // Mulai dari data transaksi
                        for ($i = 4; $i < count($rows); $i++) {
                    
                            $row = str_getcsv($rows[$i], '|');
                    
                            if (count($row) < 9) {
                                continue;
                            }
                    
                            $noRek = trim($row[1]);
                            
                            if ($noRek == '') {
                                continue;
                            }

                            // Rekening REG yang ikut muncul di file FRC diabaikan.
                            if (isset($rekeningReg[$noRek])) {
                                continue;
                            }

                            // Rekening yang bukan REG dan bukan FRC terdaftar dicatat sebagai error.
                            if (!isset($rekeningValid[$noRek])) {
                                $rekeningTidakTerdaftar[$noRek] = true;
                                continue;
                            }
                    
                            $tgl = substr(trim($row[3]), 0, 10);
                    
                            if (!isset($rekening[$noRek])) {
                    
                                $rekening[$noRek] = [
                                    'tgl_awal' => $tgl,
                                    'tgl_akhir' => $tgl,
                                    'rows' => []
                                ];
                    
                            }
                    
                            if ($tgl < $rekening[$noRek]['tgl_awal']) {
                                $rekening[$noRek]['tgl_awal'] = $tgl;
                            }
                    
                            if ($tgl > $rekening[$noRek]['tgl_akhir']) {
                                $rekening[$noRek]['tgl_akhir'] = $tgl;
                            }
                    
                            $rekening[$noRek]['rows'][] = $row;
                        }
                        
                        if (empty($rekening)) {
                            throw new \Exception("Tidak ditemukan transaksi BCA Franchise");
                        }
                    
                    }

                    if ($site == "FRC") {
                        $tglAwalFile = min(array_column($rekening, 'tgl_awal'));
                        $tglAkhirFile = max(array_column($rekening, 'tgl_akhir'));
                
                        $filename = sprintf(
                            'BCA_MTS_IDM_Multiple_%s.csv',
                            date('dmy', strtotime($tglAkhirFile))
                        );
                
                    }
                }

                /* ==================== INA ==================== */
                elseif ($bank === "INA PERDANA") {
                    if (count($rows) < 2) {
                        throw new \Exception("Format file mutasi INA PERDANA tidak valid atau kosong");
                    }
                
                    $rekening = [];
                    $dataRows = [];
                
                    $tglAwal = null;
                    $tglAkhir = null;
                
                    // Lewati header
                    for ($i = 1; $i < count($rows); $i++) {
                
                        $row = $rows[$i];
                
                        if (count($row) < 7) {
                            continue;
                        }
                
                        $rawDate = trim($row[3]);
                
                        try {
                
                            $tgl = Carbon::createFromFormat(
                                'd-M-y',
                                $rawDate
                            )->format('Y-m-d');
                
                        } catch (\Exception $e) {
                
                            continue;
                
                        }
                
                        /*
                        ==========================================================
                        Simpan tanggal awal & akhir
                        ==========================================================
                        */
                
                        if ($tglAwal === null || $tgl < $tglAwal) {
                            $tglAwal = $tgl;
                        }
                
                        if ($tglAkhir === null || $tgl > $tglAkhir) {
                            $tglAkhir = $tgl;
                        }
                
                        /*
                        ==========================================================
                        Samakan format row dengan parser lain
                        ==========================================================
                        */
                
                        $row[3] = $tgl;
                
                        $dataRows[] = $row;
                
                    }
                
                    if (empty($dataRows)) {
                        throw new \Exception("Tidak ditemukan transaksi INA PERDANA");
                    }
                
                    /*
                    ==========================================================
                    Samakan struktur parser lain
                    ==========================================================
                    */
                
                    $rekening[$no_rek] = [
                
                        'tgl_awal' => $tglAwal,
                        'tgl_akhir' => $tglAkhir,
                        'rows' => $dataRows,
                        'no_transaksi'  => false
                
                    ];
                
                }

                /* ================= CIMB NIAGA ================ */
                elseif ($bank === "CIMB NIAGA") {

                    if ($site == "REG") {
                
                        /*
                        ==========================================================
                        PARSER CIMB NIAGA REG
                        ==========================================================
                        */
                
                        if (count($rows) < 5) {
                            throw new \Exception("Format file CIMB NIAGA tidak valid atau kosong");
                        }
                
                        $periodeLine = $rows[2][0] ?? '';
                
                        preg_match(
                            '/(\d{2})-(\w+)-(\d{4})\s*-\s*(\d{2})-(\w+)-(\d{4})/i',
                            $periodeLine,
                            $tanggal
                        );
                
                        if (count($tanggal) < 7) {
                            throw new \Exception("Periode tidak ditemukan pada file CIMB NIAGA");
                        }
                
                        $bulanMap = [
                            "JANUARY"=>"01",
                            "FEBRUARY"=>"02",
                            "MARCH"=>"03",
                            "APRIL"=>"04",
                            "MAY"=>"05",
                            "JUNE"=>"06",
                            "JULY"=>"07",
                            "AUGUST"=>"08",
                            "SEPTEMBER"=>"09",
                            "OCTOBER"=>"10",
                            "NOVEMBER"=>"11",
                            "DECEMBER"=>"12"
                        ];
                
                        $tgl_awal = "{$tanggal[3]}-{$bulanMap[strtoupper($tanggal[2])]}-{$tanggal[1]}";
                        $tgl_akhir = "{$tanggal[6]}-{$bulanMap[strtoupper($tanggal[5])]}-{$tanggal[4]}";
                
                        $noTransaksi = collect($rows)
                            ->contains(fn($r) =>
                                isset($r[0]) &&
                                str_contains(strtoupper($r[0]), "NO RECORD FOUND")
                            );
                
                        $dataRows = [];
                
                        if (!$noTransaksi) {
                
                            for ($i = 4; $i < count($rows); $i++) {
                
                                $row = $rows[$i];
                
                                if (count($row) < 7) {
                                    continue;
                                }
                
                                $tmp = explode(" ", trim($row[0]));
                                $row[0] = $tmp[0];
                
                                $dataRows[] = $row;
                
                            }
                
                        }
                
                        $rekening = [];
                
                        $rekening[$no_rek] = [
                
                            'tgl_awal'      => $tgl_awal,
                            'tgl_akhir'     => $tgl_akhir,
                            'rows'          => $dataRows,
                            'no_transaksi'  => $noTransaksi
                
                        ];
                
                    }else {

                        /*
                        ==========================================================
                        PARSER CIMB NIAGA FRC
                        ==========================================================
                        */
                    
                        $rekening = [];
                    
                        $rekeningValid = DB::table('bank')
                            ->where('cabang', $cabang)
                            ->where('site','<>','REG')
                            ->where('jns_bank','CIMB NIAGA Frc')
                            ->pluck('no_rek')
                            ->map(fn($rek) => trim($rek))
                            ->filter(fn($rek) => $rek !== '')
                            ->toArray();
                    
                        $rekeningValid = array_flip($rekeningValid);

                        // Rekening REG yang ikut muncul di file FRC harus diabaikan.
                        $rekeningReg = DB::table('bank')
                            ->where('cabang', $cabang)
                            ->where('site', 'REG')
                            ->pluck('no_rek')
                            ->map(fn($rek) => trim($rek))
                            ->filter(fn($rek) => $rek !== '')
                            ->flip()
                            ->toArray();
                    
                        $bulanMap = [
                            "JANUARY"=>"01",
                            "FEBRUARY"=>"02",
                            "MARCH"=>"03",
                            "APRIL"=>"04",
                            "MAY"=>"05",
                            "JUNE"=>"06",
                            "JULY"=>"07",
                            "AUGUST"=>"08",
                            "SEPTEMBER"=>"09",
                            "OCTOBER"=>"10",
                            "NOVEMBER"=>"11",
                            "DECEMBER"=>"12"
                        ];
                    
                        $currentRek = null;
                    
                        for ($i=0;$i<count($rows);$i++) {
                    
                            $line = $rows[$i];
                    
                            /*
                            ===========================================
                            ACCOUNT
                            ===========================================
                            */
                    
                            if (str_starts_with($line,"Account")) {
                    
                                preg_match('/:\s*(\d+)\//', $line, $match);
                    
                                if (empty($match[1])) {
                    
                                    $currentRek = null;
                                    continue;
                    
                                }
                    
                                $currentRek = trim($match[1]);

                                // Account REG yang muncul di file FRC diabaikan seluruh bloknya.
                                if (isset($rekeningReg[$currentRek])) {
                                    $currentRek = null;
                                    continue;
                                }

                                // Account yang bukan REG dan bukan FRC terdaftar dicatat sebagai error.
                                if (!isset($rekeningValid[$currentRek])) {
                                    $rekeningTidakTerdaftar[$currentRek] = true;
                                    $currentRek = null;
                                    continue;
                                }

                                $rekening[$currentRek] = [
                    
                                    'tgl_awal'=>null,
                                    'tgl_akhir'=>null,
                                    'rows'=>[],
                                    'no_transaksi'=>false
                    
                                ];
                    
                                continue;
                    
                            }
                    
                            /*
                            ===========================================
                            PERIODE
                            ===========================================
                            */
                    
                            if ($currentRek && str_starts_with($line,"Periode")) {
                    
                                preg_match(
                                    '/(\d{2})-(\w+)-(\d{4})\s*-\s*(\d{2})-(\w+)-(\d{4})/i',
                                    $line,
                                    $tanggal
                                );
                    
                                if (count($tanggal)==7) {
                    
                                    $rekening[$currentRek]['tgl_awal'] =
                                        "{$tanggal[3]}-{$bulanMap[strtoupper($tanggal[2])]}-{$tanggal[1]}";
                    
                                    $rekening[$currentRek]['tgl_akhir'] =
                                        "{$tanggal[6]}-{$bulanMap[strtoupper($tanggal[5])]}-{$tanggal[4]}";
                    
                                }
                    
                                continue;
                    
                            }
                    
                            /*
                            ===========================================
                            NO RECORD
                            ===========================================
                            */
                    
                            if ($currentRek && strtoupper(trim($line))=="NO RECORD FOUND") {
                    
                                $rekening[$currentRek]['no_transaksi']=true;
                                $currentRek = null;
                                continue;
                    
                            }
                    
                            /*
                            ===========================================
                            TRANSAKSI
                            ===========================================
                            */
                    
                            if ($currentRek && preg_match('/^\d{2}\/\d{2}\/\d{2}/',$line)) {
                    
                                $row = str_getcsv($line);
                    
                                if (count($row)<9) {
                                    continue;
                                }
                    
                                $rekening[$currentRek]['rows'][]=$row;
                    
                            }
                    
                        }
                    
                        if (!empty($rekening)) {

                            $tglAwalFile = min(array_column($rekening, 'tgl_awal'));
                            $tglAkhirFile = max(array_column($rekening, 'tgl_akhir'));
                        }

                        if (empty($rekening)) {
                    
                            throw new \Exception("Tidak ditemukan rekening CIMB Franchise");
                    
                        }
                    
                    }
                }
                
                /* ================== MANDIRI ================== */
                elseif ($bank === "MANDIRI") {
                    if ($site == "REG") {
                        /*
                        ==========================================================
                        PARSER MANDIRI REG
                        ==========================================================
                        */

                        if (count($rows) < 1) {
                            throw new \Exception("Format file MANDIRI tidak valid / kosong");
                        }

                        $noTransaksi = collect($rows)
                            ->contains(fn($r) =>
                                isset($r[0]) &&
                                str_contains(strtoupper($r[0]), "DATA NOT FOUND")
                            );
                        
                        if ($noTransaksi) {
                            throw new \Exception("__NO_TRANSACTION__");
                        }
                        
                        $dataRows = [];
                        $tglAwal = null;
                        $tglAkhir = null;

                        if (!$noTransaksi) {

                            foreach ($rows as $row) {

                                if (count($row) < 6) {
                                    continue;
                                }

                                $rawDate = substr(trim($row[2]),0,10);

                                try {

                                    $rawDate = substr(trim($row[2]), 0, 10);
                                    $tmp = explode('/', $rawDate);
                                    $tgl = "{$tmp[2]}-{$tmp[1]}-{$tmp[0]}";

                                } catch (\Exception $e) {

                                    continue;

                                }

                                if ($tglAwal === null || $tgl < $tglAwal) {
                                    $tglAwal = $tgl;
                                }

                                if ($tglAkhir === null || $tgl > $tglAkhir) {
                                    $tglAkhir = $tgl;
                                }

                                $row[2] = $tgl;

                                $dataRows[] = $row;

                            }

                        }

                        $rekening = [];

                        $rekening[$no_rek] = [

                            'tgl_awal'      => $tglAwal,
                            'tgl_akhir'     => $tglAkhir,
                            'rows'          => $dataRows,
                            'no_transaksi'  => $noTransaksi

                        ];
                    }else {
                        /*
                        ==========================================================
                        PARSER MANDIRI FRC
                        ==========================================================
                        */

                        $rekening = [];

                        $rekeningValid = DB::table('bank')
                            ->where('cabang',$cabang)
                            ->where('site','<>','REG')
                            ->where('jns_bank','MDR Frc')
                            ->pluck('no_rek')
                            ->map(fn($rek) => trim($rek))
                            ->filter(fn($rek) => $rek !== '')
                            ->toArray();

                        $rekeningValid = array_flip($rekeningValid);

                        // Rekening REG yang ikut muncul di file FRC harus diabaikan.
                        $rekeningReg = DB::table('bank')
                            ->where('cabang', $cabang)
                            ->where('site', 'REG')
                            ->pluck('no_rek')
                            ->map(fn($rek) => trim($rek))
                            ->filter(fn($rek) => $rek !== '')
                            ->flip()
                            ->toArray();

                        for ($i=1;$i<count($rows);$i++) {

                            $row = $rows[$i];

                            if (count($row) < 9) {
                                continue;
                            }

                            $noRek = trim($row[0]);

                            if ($noRek == '') {
                                continue;
                            }

                            // Rekening REG yang ikut muncul di file FRC diabaikan.
                            if (isset($rekeningReg[$noRek])) {
                                continue;
                            }

                            // Rekening yang bukan REG dan bukan FRC terdaftar dicatat sebagai error.
                            if (!isset($rekeningValid[$noRek])) {
                                $rekeningTidakTerdaftar[$noRek] = true;
                                continue;
                            }

                            try {

                                $tgl = Carbon::createFromFormat(
                                    'd/m/y',
                                    trim($row[2])
                                )->format('Y-m-d');

                            } catch (\Exception $e) {

                                continue;

                            }

                            $row[2] = $tgl;

                            if (!isset($rekening[$noRek])) {

                                $rekening[$noRek] = [

                                    'tgl_awal'      => $tgl,
                                    'tgl_akhir'     => $tgl,
                                    'rows'          => [],
                                    'no_transaksi'  => false

                                ];

                            }

                            if ($tgl < $rekening[$noRek]['tgl_awal']) {
                                $rekening[$noRek]['tgl_awal'] = $tgl;
                            }

                            if ($tgl > $rekening[$noRek]['tgl_akhir']) {
                                $rekening[$noRek]['tgl_akhir'] = $tgl;
                            }

                            $rekening[$noRek]['rows'][] = $row;
                        }

                        if (empty($rekening)) {

                            throw new \Exception("Tidak ditemukan rekening Mandiri Franchise");

                        }

                        $tglAwalFile = min(array_column($rekening,'tgl_awal'));
                        $tglAkhirFile = max(array_column($rekening,'tgl_akhir'));
                    }
                }
                
                /* ==================== BNI ==================== */
                elseif ($bank === "BNI") {
                    if ($site == "REG") {
                        if (count($rows) < 2) {
                            throw new \Exception("Format file BNI tidak valid");
                        }
                        
                        $rekening = [];
                        $dataRows = [];
                        
                        $tglAwal = null;
                        $tglAkhir = null;
                        
                        for ($i = 1; $i < count($rows); $i++) {
                        
                            $row = $rows[$i];
                        
                            if (!isset($row[0]) || trim($row[0]) == '') {
                                continue;
                            }
                        
                            $rawDate = trim($row[0]);
                        
                            $tgl = null;
                        
                            foreach ([
                                'd/m/y H.i.s',
                                'd/m/Y H.i.s',
                                'd/m/y',
                                'd/m/Y',
                                'd-m-y',
                                'd-m-Y'
                            ] as $fmt) {
                        
                                try {
                        
                                    $tgl = Carbon::createFromFormat(
                                        $fmt,
                                        $rawDate
                                    )->format('Y-m-d');
                        
                                    break;
                        
                                } catch (\Exception $e) {}
                        
                            }
                        
                            if (!$tgl) {
                                continue;
                            }
                        
                            if ($tglAwal === null || $tgl < $tglAwal) {
                                $tglAwal = $tgl;
                            }
                        
                            if ($tglAkhir === null || $tgl > $tglAkhir) {
                                $tglAkhir = $tgl;
                            }
                        
                            $row[0] = $tgl;
                        
                            $dataRows[] = $row;
                        
                        }
                        
                        if (empty($dataRows)) {
                            throw new \Exception("Tidak ditemukan transaksi BNI");
                        }
                        
                        $rekening[$no_rek] = [
                        
                            'tgl_awal'      => $tglAwal,
                            'tgl_akhir'     => $tglAkhir,
                            'rows'          => $dataRows,
                            'no_transaksi'  => false
                        
                        ];
                    }else{
                        $rekening = [];

                        $rekeningValid = DB::table('bank')
                            ->where('cabang', $cabang)
                            ->where('site', '<>', 'REG')
                            ->where('jns_bank', 'BNI Frc')
                            ->pluck('no_rek')
                            ->map(fn($rek) => trim($rek))
                            ->filter(fn($rek) => $rek !== '')
                            ->toArray();

                        $rekeningValid = array_flip($rekeningValid);

                        // Rekening REG yang ikut muncul di file FRC harus diabaikan.
                        $rekeningReg = DB::table('bank')
                            ->where('cabang', $cabang)
                            ->where('site', 'REG')
                            ->pluck('no_rek')
                            ->map(fn($rek) => trim($rek))
                            ->filter(fn($rek) => $rek !== '')
                            ->flip()
                            ->toArray();

                        for ($i = 1; $i < count($rows); $i++) {

                            $row = $rows[$i];

                            /*
                            ==========================================
                            Skip header
                            ==========================================
                            */

                            if (
                                isset($row[0]) &&
                                strtoupper(trim($row[0])) == "ACCOUNT NO"
                            ) {
                                continue;
                            }

                            if (count($row) < 8) {
                                continue;
                            }

                            $noRek = trim($row[0]);

                            if ($noRek == '') {
                                continue;
                            }

                            // Rekening REG yang ikut muncul di file FRC diabaikan.
                            if (isset($rekeningReg[$noRek])) {
                                continue;
                            }

                            // Rekening yang bukan REG dan bukan FRC terdaftar dicatat sebagai error.
                            if (!isset($rekeningValid[$noRek])) {
                                $rekeningTidakTerdaftar[$noRek] = true;
                                continue;
                            }

                            try {

                                $tgl = Carbon::createFromFormat(
                                    'd/m/y H.i.s',
                                    trim($row[1])
                                )->format('Y-m-d');

                            } catch (\Exception $e) {

                                continue;

                            }

                            $row[1] = $tgl;

                            if (!isset($rekening[$noRek])) {

                                $rekening[$noRek] = [

                                    'tgl_awal'      => $tgl,
                                    'tgl_akhir'     => $tgl,
                                    'rows'          => [],
                                    'no_transaksi'  => false

                                ];
                            }

                            if ($tgl < $rekening[$noRek]['tgl_awal']) {
                                $rekening[$noRek]['tgl_awal'] = $tgl;
                            }

                            if ($tgl > $rekening[$noRek]['tgl_akhir']) {
                                $rekening[$noRek]['tgl_akhir'] = $tgl;
                            }

                            $rekening[$noRek]['rows'][] = $row;

                        }

                        if (empty($rekening)) {

                            throw new \Exception("Tidak ditemukan rekening BNI Franchise");

                        }

                        $tglAwalFile = min(array_column($rekening, 'tgl_awal'));
                        $tglAkhirFile = max(array_column($rekening, 'tgl_akhir'));
                    }
                }

                /* ==================== BRI ==================== */
                elseif ($bank === "BRI") {

                    if ($site == "REG") {
                
                        /*
                        ==========================================================
                        PARSER BRI REG
                        ==========================================================
                        */
                
                        if (count($rows) < 2) {
                            throw new \Exception("Format file BRI tidak valid");
                        }
                
                        $rekening = [];
                        $dataRows = [];
                
                        $tglAwal = null;
                        $tglAkhir = null;
                
                        for ($i = 1; $i < count($rows); $i++) {
                
                            $row = $rows[$i];
                
                            if (!isset($row[6]) || trim($row[6]) == '') {
                                continue;
                            }
                
                            $tgl = null;
                
                            foreach ([
                                'd/m/y',
                                'd/m/Y',
                                'd-m-y',
                                'd-m-Y'
                            ] as $fmt) {
                
                                try {
                
                                    $tgl = Carbon::createFromFormat(
                                        $fmt,
                                        trim($row[6])
                                    )->format('Y-m-d');
                
                                    break;
                
                                } catch (\Exception $e) {}
                
                            }
                
                            if (!$tgl) {
                                continue;
                            }
                
                            if ($tglAwal === null || $tgl < $tglAwal) {
                                $tglAwal = $tgl;
                            }
                
                            if ($tglAkhir === null || $tgl > $tglAkhir) {
                                $tglAkhir = $tgl;
                            }
                
                            $row[6] = $tgl;
                
                            $dataRows[] = $row;
                
                        }
                
                        if (empty($dataRows)) {
                            throw new \Exception("Tidak ditemukan transaksi BRI");
                        }
                
                        $rekening[$no_rek] = [
                
                            'tgl_awal'      => $tglAwal,
                            'tgl_akhir'     => $tglAkhir,
                            'rows'          => $dataRows,
                            'no_transaksi'  => false
                
                        ];
                
                    } else {
                
                        /*
                        ==========================================================
                        PARSER BRI FRC
                        ==========================================================
                        */

                        if (count($rows) < 5) {
                            throw new \Exception("Format file BRI Franchise tidak valid");
                        }

                        $rekening = [];

                        /*
                        ==========================================================
                        AMBIL NOMOR REKENING
                        ==========================================================
                        */

                        $accountLine = $rows[1];

                        $noRek = preg_replace(
                            '/[^0-9]/',
                            '',
                            trim($accountLine[1] ?? '')
                        );

                        if ($noRek != $no_rek) {

                            throw new \Exception(
                                "Nomor rekening pada file ({$noRek}) tidak sesuai dengan rekening master ({$no_rek})"
                            );

                        }

                        /*
                        ==========================================================
                        AMBIL PERIODE
                        ==========================================================
                        */

                        preg_match(
                            '/(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})/',
                            $accountLine[9] ?? '',
                            $tanggal
                        );

                        if (count($tanggal) < 3) {
                            throw new \Exception("Periode tidak ditemukan");
                        }

                        $tglAwal = Carbon::createFromFormat(
                            'd/m/Y',
                            $tanggal[1]
                        )->format('Y-m-d');

                        $tglAkhir = Carbon::createFromFormat(
                            'd/m/Y',
                            $tanggal[2]
                        )->format('Y-m-d');
                        
                        $tglAwalFile = $tglAwal;
                        $tglAkhirFile = $tglAkhir;
                        /*
                        ==========================================================
                        DATA TRANSAKSI
                        ==========================================================
                        */

                        $dataRows = [];
                        $noTransaksi = false;

                        for ($i = 4; $i < count($rows); $i++) {

                            $row = $rows[$i];

                            /*
                            ======================================================
                            SELESAI DATA TRANSAKSI
                            ======================================================
                            */

                            if (
                                isset($row[0]) &&
                                strtoupper(trim($row[0])) == "OPENING BALANCE"
                            ) {
                                break;
                            }

                            /*
                            ======================================================
                            FORMAT TIDAK SESUAI
                            ======================================================
                            */

                            if (count($row) < 13) {
                                continue;
                            }

                            /*
                            ======================================================
                            FILE TIDAK ADA TRANSAKSI
                            ======================================================
                            */

                            if (
                                isset($row[8]) &&
                                str_contains(
                                    strtoupper(trim($row[8])),
                                    "TRANSAKSI TIDAK DITEMUKAN"
                                )
                            ) {

                                $noTransaksi = true;
                                break;

                            }

                            /*
                            ======================================================
                            TANGGAL
                            ======================================================
                            */

                            try {

                                $tgl = Carbon::createFromFormat(
                                    'd/m/y',
                                    trim($row[6])
                                )->format('Y-m-d');

                            } catch (\Exception $e) {

                                continue;

                            }

                            $row[6] = $tgl;

                            $dataRows[] = $row;

                        }

                        /*
                        ==========================================================
                        SAMAKAN STRUKTUR PARSER LAIN
                        ==========================================================
                        */

                        $rekening[$noRek] = [

                            'tgl_awal'      => $tglAwal,
                            'tgl_akhir'     => $tglAkhir,
                            'rows'          => $dataRows,
                            'no_transaksi'  => $noTransaksi

                        ];
                
                    }
                }

                /* ==================== BSI ==================== */
                elseif ($bank === "BSI") {

                    /*
                    ==========================================================
                    BSI REG
                    ==========================================================
                    */
                    if ($site == "REG") {

                        /*
                        ----------------------------------------------------------
                        FILE KOSONG
                        ----------------------------------------------------------
                        */
                        if (empty($rows)) {

                            throw new \Exception(
                                "File BSI kosong / tidak ditemukan transaksi"
                            );
                        }

                        $dataRows = [];
                        $tglAwal  = null;
                        $tglAkhir = null;

                        /*
                        ----------------------------------------------------------
                        DATA TRANSAKSI BSI REG
                        ----------------------------------------------------------
                        */

                        for ($i = 1; $i < count($rows); $i++) {

                            $row = $rows[$i];

                            /*
                            ------------------------------------------------------
                            BARIS KOSONG
                            ------------------------------------------------------
                            */

                            if (
                                empty($row) ||
                                !isset($row[0]) ||
                                trim((string) $row[0]) === ''
                            ) {
                                continue;
                            }

                            /*
                            ------------------------------------------------------
                            FORMAT TANGGAL
                            ------------------------------------------------------
                            */

                            $tgl = null;

                            foreach ([
                                'Y-m-d H:i:s',
                                'Y-m-d H:i',
                                'Y-m-d',
                                'd/m/Y H:i:s',
                                'd/m/Y'
                            ] as $fmt) {

                                try {

                                    $tgl = Carbon::createFromFormat(
                                        $fmt,
                                        trim((string) $row[0])
                                    )->format('Y-m-d');

                                    break;

                                } catch (\Exception $e) {
                                    // lanjut format berikutnya
                                }
                            }

                            /*
                            ------------------------------------------------------
                            TANGGAL TIDAK VALID
                            ------------------------------------------------------
                            */

                            if (!$tgl) {
                                continue;
                            }

                            /*
                            ------------------------------------------------------
                            TANGGAL AWAL
                            ------------------------------------------------------
                            */

                            if (
                                $tglAwal === null ||
                                $tgl < $tglAwal
                            ) {
                                $tglAwal = $tgl;
                            }

                            /*
                            ------------------------------------------------------
                            TANGGAL AKHIR
                            ------------------------------------------------------
                            */

                            if (
                                $tglAkhir === null ||
                                $tgl > $tglAkhir
                            ) {
                                $tglAkhir = $tgl;
                            }

                            /*
                            ------------------------------------------------------
                            NORMALISASI TANGGAL
                            ------------------------------------------------------
                            */

                            $row[0] = $tgl;

                            /*
                            ------------------------------------------------------
                            SIMPAN TRANSAKSI
                            ------------------------------------------------------
                            */

                            $dataRows[] = $row;
                        }

                        /*
                        ==========================================================
                        VALIDASI TRANSAKSI BSI REG
                        ==========================================================
                        */

                        if (empty($dataRows)) {

                            throw new \Exception(
                                "Tidak ditemukan transaksi BSI"
                            );
                        }

                        /*
                        ==========================================================
                        SIMPAN HASIL PARSER
                        ==========================================================
                        */

                        $rekening[$no_rek] = [
                            'tgl_awal'      => $tglAwal,
                            'tgl_akhir'     => $tglAkhir,
                            'rows'          => $dataRows,
                            'no_transaksi'  => false
                        ];

                    }else {

                    /*
                    ==========================================================
                    BSI FRC
                    ==========================================================
                    */
                        $no_rek = preg_replace(
                            '/[^0-9]/',
                            '',
                            trim((string) $no_rek)
                        );

                        /*
                        ----------------------------------------------------------
                        VALIDASI NOMOR REKENING
                        ----------------------------------------------------------
                        */

                        if ($no_rek === '') {

                            throw new \Exception(
                                "Nomor rekening BSI tidak ditemukan dari filename"
                            );
                        }

                        /*
                        ==========================================================
                        VALIDASI FILE
                        ==========================================================
                        
                        BSI FRC tanpa transaksi masih mempunyai header:

                        Date, FT Number, Description, Currency,
                        Amount, DB, CR, Balance
                        ==========================================================
                        */

                        if (empty($rows)) {

                            throw new \Exception(
                                "Tidak ditemukan transaksi BSI"
                            );
                        }

                        /*
                        ==========================================================
                        BERSIHKAN BARIS KOSONG
                        ==========================================================
                        */

                        $cleanRows = [];

                        foreach ($rows as $row) {

                            if (empty($row)) {
                                continue;
                            }

                            $hasValue = false;

                            foreach ($row as $value) {

                                if (
                                    $value !== null &&
                                    trim((string) $value) !== ''
                                ) {
                                    $hasValue = true;
                                    break;
                                }
                            }

                            if ($hasValue) {
                                $cleanRows[] = $row;
                            }
                        }

                        $rows = $cleanRows;

                        /*
                        ==========================================================
                        SETELAH BARIS KOSONG DIBERSIHKAN
                        ==========================================================
                        */

                        if (empty($rows)) {

                            throw new \Exception(
                                "Tidak ditemukan transaksi BSI"
                            );
                        }

                        /*
                        ==========================================================
                        HEADER-ONLY
                        ==========================================================
                        
                        Contoh file yang Anda kirim:

                        Date, "FT Number", "Description", "Currency",
                        "Amount", "DB", "CR", "Balance",

                        Hanya ada 1 baris.
                        
                        Artinya TIDAK ADA TRANSAKSI.
                        ==========================================================
                        */

                        if (count($rows) <= 1) {

                            throw new \Exception(
                                "Tidak ditemukan transaksi BSI"
                            );
                        }

                        /*
                        ==========================================================
                        DATA TRANSAKSI
                        ==========================================================
                        */

                        $dataRows = [];

                        $tglAwalFile  = null;
                        $tglAkhirFile = null;

                        /*
                        ----------------------------------------------------------
                        HEADER ADA DI BARIS 0
                        ----------------------------------------------------------
                        */

                        for ($i = 1; $i < count($rows); $i++) {

                            $row = $rows[$i];

                            /*
                            ------------------------------------------------------
                            BARIS KOSONG
                            ------------------------------------------------------
                            */

                            if (
                                empty($row) ||
                                !isset($row[0]) ||
                                trim((string) $row[0]) === ''
                            ) {
                                continue;
                            }

                            /*
                            ======================================================
                            CEK PESAN TIDAK ADA TRANSAKSI
                            ======================================================
                            */

                            $rowText = strtoupper(
                                trim(
                                    implode(
                                        ' ',
                                        array_map(
                                            fn($value) => (string) $value,
                                            $row
                                        )
                                    )
                                )
                            );

                            if (
                                str_contains(
                                    $rowText,
                                    'TRANSAKSI TIDAK DITEMUKAN'
                                ) ||
                                str_contains(
                                    $rowText,
                                    'NO TRANSACTION'
                                ) ||
                                str_contains(
                                    $rowText,
                                    'NO DATA'
                                ) ||
                                str_contains(
                                    $rowText,
                                    'NO RECORD FOUND'
                                )
                            ) {

                                throw new \Exception(
                                    "Tidak ditemukan transaksi BSI"
                                );
                            }

                            /*
                            ======================================================
                            VALIDASI JUMLAH KOLOM
                            ======================================================
                            
                            Format BSI FRC:
                            
                            0 = Date
                            1 = FT Number
                            2 = Description
                            3 = Currency
                            4 = Amount
                            5 = DB
                            6 = CR
                            7 = Balance
                            ======================================================
                            */

                            if (count($row) < 8) {
                                continue;
                            }

                            /*
                            ======================================================
                            TANGGAL
                            ======================================================
                            */

                            $tgl = null;

                            foreach ([
                                'Y-m-d H:i:s',
                                'Y-m-d H:i',
                                'Y-m-d',
                                'd/m/Y H:i:s',
                                'd/m/Y',
                                'd-m-Y H:i:s',
                                'd-m-Y'
                            ] as $format) {

                                try {

                                    $tgl = Carbon::createFromFormat(
                                        $format,
                                        trim((string) $row[0])
                                    )->format('Y-m-d');

                                    break;

                                } catch (\Exception $e) {
                                    // lanjut format berikutnya
                                }
                            }

                            /*
                            ------------------------------------------------------
                            TANGGAL TIDAK VALID
                            ------------------------------------------------------
                            */

                            if (!$tgl) {
                                continue;
                            }

                            /*
                            ======================================================
                            TANGGAL AWAL
                            ======================================================
                            */

                            if (
                                $tglAwalFile === null ||
                                $tgl < $tglAwalFile
                            ) {
                                $tglAwalFile = $tgl;
                            }

                            /*
                            ======================================================
                            TANGGAL AKHIR
                            ======================================================
                            */

                            if (
                                $tglAkhirFile === null ||
                                $tgl > $tglAkhirFile
                            ) {
                                $tglAkhirFile = $tgl;
                            }

                            /*
                            ======================================================
                            NORMALISASI TANGGAL
                            ======================================================
                            */

                            $row[0] = $tgl;

                            /*
                            ======================================================
                            SIMPAN TRANSAKSI
                            ======================================================
                            */

                            $dataRows[] = $row;
                        }

                        /*
                        ==========================================================
                        VALIDASI AKHIR
                        ==========================================================
                        
                        Ini bagian paling penting.

                        Kalau hanya ada header atau semua baris gagal diparse,
                        jangan membuat tgl_awal/tgl_akhir NULL.

                        Langsung hentikan proses seperti BRI FRC.
                        ==========================================================
                        */

                        if (empty($dataRows)) {

                            throw new \Exception(
                                "Tidak ditemukan transaksi BSI"
                            );
                        }

                        /*
                        ==========================================================
                        VALIDASI TANGGAL FILE
                        ==========================================================
                        */

                        if (
                            empty($tglAwalFile) ||
                            empty($tglAkhirFile)
                        ) {

                            throw new \Exception(
                                "Tanggal transaksi BSI tidak ditemukan"
                            );
                        }

                        /*
                        ==========================================================
                        SAMAKAN STRUKTUR DENGAN PARSER LAIN
                        ==========================================================
                        */

                        $rekening[$no_rek] = [

                            'tgl_awal'      => $tglAwalFile,

                            'tgl_akhir'     => $tglAkhirFile,

                            'rows'          => $dataRows,

                            'no_transaksi'  => false
                        ];
                    }
                }
                
                /* ==================== BTN ==================== */
                elseif ($bank === "BTN") {

                    /*
                    ==========================================================
                    PARSER BTN REG
                    ==========================================================
                    */

                    if ($site !== "REG") {

                        throw new \Exception(
                            "Site BTN tidak valid. BTN hanya mendukung site REG."
                        );

                    }

                    /*
                    ==========================================================
                    VALIDASI FORMAT FILE
                    ==========================================================
                    */

                    if (count($rows) < 5) {

                        throw new \Exception(
                            "Format file BTN tidak valid atau kosong"
                        );

                    }


                    /*
                    ==========================================================
                    NOMOR REKENING
                    ==========================================================
                    */

                    if (empty($no_rek)) {

                        throw new \Exception(
                            "Nomor rekening BTN tidak ditemukan"
                        );

                    }


                    /*
                    ==========================================================
                    DATA TRANSAKSI
                    ==========================================================
                    */

                    $dataRows = [];

                    $tglAwal = null;

                    $tglAkhir = null;

                    $noTransaksi = false;


                    /*
                    ==========================================================
                    LOOP DATA
                    ==========================================================
                    */

                    for ($i = 0; $i < count($rows); $i++) {

                        $row = $rows[$i];

                        /*
                        ======================================================
                        SKIP BARIS KOSONG
                        ======================================================
                        */

                        if (empty($row)) {
                            continue;
                        }


                        /*
                        ======================================================
                        CEK TOTAL
                        ======================================================
                        */

                        $firstColumn = strtoupper(
                            trim(
                                (string) (
                                    $row[0] ?? ''
                                )
                            )
                        );


                        if (
                            str_starts_with(
                                $firstColumn,
                                "TOTAL PER"
                            )
                            ||
                            str_starts_with(
                                $firstColumn,
                                "GRAND TOTAL PER"
                            )
                        ) {

                            break;

                        }


                        /*
                        ======================================================
                        HANYA BARIS TRANSAKSI
                        ======================================================
                        */

                        if (
                            !isset($row[0]) ||
                            !preg_match(
                                '/^\d+$/',
                                trim($row[0])
                            )
                        ) {

                            continue;

                        }


                        /*
                        ======================================================
                        VALIDASI KOLOM
                        ======================================================
                        */

                        if (count($row) < 10) {
                            continue;
                        }


                        /*
                        ======================================================
                        POST DATE
                        ======================================================
                        */

                        $rawDate = trim(
                            (string) (
                                $row[1] ?? ''
                            )
                        );


                        if ($rawDate === '') {
                            continue;
                        }


                        /*
                        ======================================================
                        KONVERSI TANGGAL
                        ======================================================
                        */

                        $tgl = null;

                        foreach ([
                            'd/m/Y',
                            'd-m-Y',
                            'Y-m-d'
                        ] as $format) {

                            try {

                                $tgl = Carbon::createFromFormat(
                                    $format,
                                    $rawDate
                                )->format('Y-m-d');

                                break;

                            } catch (\Exception $e) {

                                // lanjut format berikutnya

                            }

                        }


                        if (!$tgl) {
                            continue;
                        }


                        /*
                        ======================================================
                        TANGGAL AWAL
                        ======================================================
                        */

                        if (
                            $tglAwal === null ||
                            $tgl < $tglAwal
                        ) {

                            $tglAwal = $tgl;

                        }


                        /*
                        ======================================================
                        TANGGAL AKHIR
                        ======================================================
                        */

                        if (
                            $tglAkhir === null ||
                            $tgl > $tglAkhir
                        ) {

                            $tglAkhir = $tgl;

                        }


                        /*
                        ======================================================
                        NORMALISASI TANGGAL
                        ======================================================
                        */

                        $row[1] = $tgl;


                        /*
                        ======================================================
                        SIMPAN TRANSAKSI
                        ======================================================
                        */

                        $dataRows[] = $row;

                    }


                    /*
                    ==========================================================
                    TIDAK ADA TRANSAKSI
                    ==========================================================
                    */

                    if (empty($dataRows)) {

                        $noTransaksi = true;

                    }


                    /*
                    ==========================================================
                    SAMAKAN STRUKTUR DENGAN PARSER LAIN
                    ==========================================================
                    */

                    $rekening = [];

                    $rekening[$no_rek] = [

                        'tgl_awal'      => $tglAwal,
                        'tgl_akhir'     => $tglAkhir,
                        'rows'          => $dataRows,
                        'no_transaksi'  => $noTransaksi

                    ];

                }

                /*
                ==========================================================
                VALIDASI REKENING MASTER
                ==========================================================
                */

                // Validasi tambahan untuk parser single-account.
                // Parser multi-account FRC sudah memvalidasi rekening di bloknya masing-masing.
                if (!empty($no_rek)) {
                    $rekeningMasterAda = DB::table('bank')
                        ->where('cabang', $cabang)
                        ->where('no_rek', trim($no_rek))
                        ->exists();

                    if (!$rekeningMasterAda) {
                        $rekeningTidakTerdaftar[trim($no_rek)] = true;
                    }
                }

                if (!empty($rekeningTidakTerdaftar)) {

                    $rekeningList = implode(
                        ', ',
                        array_keys($rekeningTidakTerdaftar)
                    );

                    throw new \Exception(
                        "Import dibatalkan. Nomor rekening berikut belum terdaftar di tabel bank: {$rekeningList}"
                    );
                }

                /*
                ==========================================================
                CEK DUPLIKASI FILE
                ==========================================================
                */

                if (
                    DB::table($tableMutasi)
                        ->where('cabang', $cabang)
                        ->where('file', $filename)
                        ->exists()
                ) {

                    $errors[] = [
                        "file"   => $filename,
                        "reason" => "File sudah pernah diimport"
                    ];

                    continue;

                }

                DB::beginTransaction();
                /* ==================== INSERT HEADER ==================== */
                $multipleHeader = [
                    'BCA',
                    'CIMB NIAGA',
                    'MANDIRI',
                    'BNI'
                ];
                
                if ($site == "REG") {
                
                    foreach ($rekening as $rek => $data) {
                
                        DB::table($tableMutasi)->insert([
                
                            "cabang"    => $cabang,
                            "no_rek"    => $rek,
                            "bank"      => $bankMutasi,
                            "file"      => $filename,
                            "tgl_awal"  => $data['tgl_awal'],
                            "tgl_akhir" => $data['tgl_akhir'],
                            "path"      => $folder
                        ]);
                
                    }
                
                } else {
                
                    DB::table($tableMutasi)->insert([
                
                        "cabang"    => $cabang,
                        "no_rek"    => in_array($bank, $multipleHeader)
                                            ? "Multiple"
                                            : $no_rek,
                        "bank"      => $bankMutasi,
                        "file"      => $filename,
                        "tgl_awal"  => $tglAwalFile,
                        "tgl_akhir" => $tglAkhirFile,
                        "path"      => $folder
                
                    ]);
                
                }

                /*
                ==========================================================
                INSERT DETAIL (BULK INSERT)
                ==========================================================
                */

                $detailInsert = [];

                foreach ($rekening as $rek => $data) {

                    foreach ($data['rows'] as $row) {

                        /*
                        ==========================================================
                        TRANSLATOR
                        ==========================================================
                        */

                        switch ("{$bank}_{$site}") {

                            /*
                            ==========================================================
                            BCA REG
                            ==========================================================
                            */
                        
                            case "BCA_REG":
                        
                                $tmp = explode('/', trim($row[0]));
                        
                                $tgl = "{$tmp[2]}-{$tmp[1]}-{$tmp[0]}";
                        
                                $remark = trim($row[1]);
                        
                                $saldo = (float) str_replace(",", "", $row[4]);
                        
                                $jumlahRaw = trim($row[3]);
                        
                                $db = str_contains($jumlahRaw, "DB")
                                    ? (float) str_replace([" DB", ","], "", $jumlahRaw)
                                    : 0;
                        
                                $cr = str_contains($jumlahRaw, "CR")
                                    ? (float) str_replace([" CR", ","], "", $jumlahRaw)
                                    : 0;
                        
                                break;
                        
                            /*
                            ==========================================================
                            CIMB NIAGA REG
                            ==========================================================
                            */
                        
                            case "CIMB NIAGA_REG":
                        
                                $tmp = explode('/', trim($row[0]));
                        
                                $tgl = "20{$tmp[2]}-{$tmp[0]}-{$tmp[1]}";
                        
                                $remark = trim($row[3]);
                        
                                $saldo = (float) str_replace(",", "", $row[6]);
                        
                                $db = (float) str_replace(",", "", ($row[4] ?: 0));
                        
                                $cr = (float) str_replace(",", "", ($row[5] ?: 0));
                        
                                break;
                        
                            
                            /*
                            ==========================================================
                            MANDIRI REG
                            ==========================================================
                            */

                            case "MANDIRI_REG":

                                $tgl = trim($row[2]);

                                $remark = trim($row[3]);

                                $remark1 = trim($row[4]);

                                $saldo = 0;

                                $rawAmount = strtoupper(trim($row[5]));

                                $isDb = str_ends_with($rawAmount, "DR");

                                $amount = (float) preg_replace('/[^0-9]/', '', $rawAmount);

                                if ($isDb) {

                                    $db = $amount;
                                    $cr = 0;

                                } else {

                                    $db = 0;
                                    $cr = $amount;

                                }

                                break;
                                
                            /*
                            ==========================================================
                            BNI REG
                            ==========================================================
                            */

                            case "BNI_REG":

                                $tgl = $row[0];

                                $remark = trim($row[4]);

                                $saldo = 0;

                                $db = (float) str_replace(",", "", $row[5]);

                                $cr = (float) str_replace(",", "", $row[6]);

                                break;

                            /*
                            ==========================================================
                            BRI REG & FRC
                            ==========================================================
                            */

                            case "BRI_REG":
                            case "BRI_FRC":
                            
                                $tgl = $row[6];
                            
                                $remark = trim($row[8]);
                            
                                $saldo = 0;
                            
                                $db = (float) preg_replace('/[^0-9.]/', '', $row[9]);
                            
                                $cr = (float) preg_replace('/[^0-9.]/', '', $row[10]);
                            
                                break;

                            /*
                            ==========================================================
                            BSI REG & FRC
                            ==========================================================
                            */

                            case "BSI_REG":
                            case "BSI_FRC":
                            
                                $tgl = $row[0];
                            
                                $remark = trim($row[2]);
                            
                                $saldo = 0;
                            
                                $amount = (float) preg_replace('/[^0-9.]/', '', $row[4]);

                                $db = strtoupper(trim($row[5])) == "DB"
                                    ? $amount
                                    : 0;

                                $cr = strtoupper(trim($row[6])) == "CR"
                                    ? $amount
                                    : 0;
                            
                                break;
                            
                            /*
                            ==========================================================
                            INA PERDANA REG
                            ==========================================================
                            */
                        
                            case "INA PERDANA_REG":
                        
                                $tgl = $row[3];
                        
                                $remark = trim($row[4]);
                        
                                $saldo = 0;
                        
                                $db = (float) str_replace(",", "", $row[5]);
                        
                                $cr = (float) str_replace(",", "", $row[6]);
                        
                                break;
                        
                            /*
                            ==========================================================
                            BTN REG
                            ==========================================================
                            */

                            case "BTN_REG":

                                $tgl = trim($row[1]);

                                $remark = trim($row[5]);

                                $saldo = (float) str_replace(
                                    ",",
                                    "",
                                    trim($row[8] ?? "0")
                                );

                                $db = (float) str_replace(
                                    ",",
                                    "",
                                    trim($row[6] ?? "0")
                                );

                                $cr = (float) str_replace(
                                    ",",
                                    "",
                                    trim($row[7] ?? "0")
                                );


                                break;

                            /*
                            ==========================================================
                            BCA FRANCHISE
                            ==========================================================
                            */
                        
                            case "BCA_FRC":
                        
                                $tgl = trim($row[3]);
                        
                                $remark = trim($row[6]);
                        
                                if (!empty(trim($row[7]))) {
                                    $remark .= ' ' . trim($row[7]);
                                }
                        
                                $saldo = 0;
                        
                                $nominal = (float) str_replace(",", "", $row[5]);
                        
                                if ($row[4] == "D") {
                        
                                    $db = $nominal;
                                    $cr = 0;
                        
                                } else {
                        
                                    $db = 0;
                                    $cr = $nominal;
                        
                                }
                        
                                break;
                        
                            /*
                            ==========================================================
                            CIMB NIAGA FRANCHISE
                            ==========================================================
                            */
                            
                            case "CIMB NIAGA_FRC":
                                $tgl = Carbon::createFromFormat(
                                    'm/d/y H:i',
                                    trim($row[0])
                                )->format('Y-m-d');
                        
                                $remark = trim($row[3]);
                        
                                $saldo = (float) str_replace(",", "", ($row[6] ?: 0));
                        
                                $db = (float) str_replace(",", "", ($row[4] ?: 0));
                        
                                $cr = (float) str_replace(",", "", ($row[5] ?: 0));
                        
                                break;
                        
                            
                            /*
                            ==========================================================
                            MANDIRI FRANCHISE
                            ==========================================================
                            */

                            case "MANDIRI_FRC":

                                $tgl = trim($row[2]);

                                $remark = trim($row[4]);

                                $remark1 = trim($row[5]);

                                $saldo = 0;

                                $db = (float) str_replace(',', '', $row[7]);
                                $cr = (float) str_replace(',', '', $row[8]);

                                break;
                            
                            /*
                            ==========================================================
                            BNI FRANCHISE
                            ==========================================================
                            */

                            case "BNI_FRC":

                                $tgl = $row[1];

                                $remark = trim($row[5]);

                                $saldo = 0;

                                $db = (float) str_replace(
                                    [",", ".00"],
                                    "",
                                    $row[6]
                                );

                                $cr = (float) str_replace(
                                    [",", ".00"],
                                    "",
                                    $row[7]
                                );

                                break;

                            /*
                            ==========================================================
                            DEFAULT
                            ==========================================================
                            */
                        
                            default:
                        
                                throw new \Exception(
                                    "Translator belum tersedia untuk {$bank} {$site}"
                                );
                        
                        }
                        
                        /*
                        ==========================================================
                        BULK INSERT
                        ==========================================================
                        */

                        $insert = [

                            "cabang" => $cabang,
                            "no_rek" => $rek,
                            "tgl"    => $tgl,
                            "remark" => $remark,
                            "db"     => $db,
                            "cr"     => $cr,
                            "saldo"  => $saldo,
                            "src"    => $filename
                        ];

                        if ($bank === "MANDIRI") {

                            $insert["remark1"] = $remark1;
                        
                        }
                        
                        $detailInsert[] = $insert;
                    }
                }

                /*
                ==========================================================
                INSERT DATABASE
                ==========================================================
                */

                foreach (array_chunk($detailInsert, 2000) as $chunk) {

                    DB::table($tableDetail)->insert($chunk);

                }              
                                
                /*
                |--------------------------------------------------------------------------
                | UPDATE DEPT / SITE / RECONCILED BERDASARKAN DATABASE
                |--------------------------------------------------------------------------
                */

                $key = "{$bank}_{$site}";

                $rules = DB::table('mutasi_update_rules')
                    ->where('bank_site', $key)
                    ->orderBy('id')
                    ->get();

                foreach ($rules as $rule) {

                    /*
                    |--------------------------------------------------------------------------
                    | 1. VALIDASI FIELD
                    |--------------------------------------------------------------------------
                    */

                    if (!in_array($rule->field, ['db', 'cr'], true)) {
                        continue;
                    }

                    $pattern = trim((string) ($rule->pattern ?? ''));
                    $remark1Pattern = trim(
                        (string) ($rule->remark1_pattern ?? '')
                    );
                    $excludePattern = trim(
                        (string) ($rule->exclude_pattern ?? '')
                    );

                    $hasIdSetor = !empty($rule->id_setor_field);

                    /*
                    |--------------------------------------------------------------------------
                    | 2. VALIDASI PATTERN
                    |--------------------------------------------------------------------------
                    | Rule biasa harus memiliki pattern atau remark1_pattern.
                    | Rule ekstraksi boleh menggunakan keduanya dalam keadaan kosong.
                    */

                    if (
                        $pattern === ''
                        && $remark1Pattern === ''
                        && !$hasIdSetor
                    ) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 3. DATA UPDATE DARI RULE
                    |--------------------------------------------------------------------------
                    */

                    $updateData = [];

                    if ($rule->dept_value !== null) {
                        $updateData['dept'] = $rule->dept_value;
                    }

                    if ($rule->reconciled_value !== null) {
                        $updateData['reconciled'] = $rule->reconciled_value;
                    }

                    if ($rule->site_value !== null) {
                        $updateData['site'] = $rule->site_value;
                    }

                    if (empty($updateData) && !$hasIdSetor) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 4. QUERY DASAR TRANSAKSI
                    |--------------------------------------------------------------------------
                    */

                    $query = DB::table($tableDetail)
                        ->where('src', $filename)
                        ->where($rule->field, '!=', 0);

                    /*
                    |--------------------------------------------------------------------------
                    | 5. FILTER PATTERN
                    |--------------------------------------------------------------------------
                    | Pattern umum dicari pada remark ATAU remark1.
                    |
                    | Jika remark1_pattern diisi, syarat tambahan tersebut juga wajib
                    | terpenuhi. Dengan demikian, pola umum tidak membuat dua transaksi
                    | yang mirip masuk ke rule yang sama.
                    */

                    if ($pattern !== '') {
                        $query->where(function ($q) use ($pattern) {
                            $q->where('remark', 'LIKE', $pattern)
                                ->orWhere('remark1', 'LIKE', $pattern);
                        });
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 6. FILTER KHUSUS REMARK1
                    |--------------------------------------------------------------------------
                    | Kondisi AND terhadap filter pattern umum.
                    |--------------------------------------------------------------------------
                    */

                    if ($remark1Pattern !== '') {
                        $query->where(
                            'remark1',
                            'LIKE',
                            $remark1Pattern
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 7. FILTER EXCLUDE PATTERN
                    |--------------------------------------------------------------------------
                    | Transaksi dikecualikan jika pola ditemukan pada remark ATAU remark1.
                    | NULL diperlakukan sebagai kolom yang tidak mengandung pola.
                    |--------------------------------------------------------------------------
                    */

                    if ($excludePattern !== '') {
                        $query->where(function ($q) use ($excludePattern) {
                            $q->where(function ($subQuery) use ($excludePattern) {
                                $subQuery->whereNull('remark')
                                    ->orWhere(
                                        'remark',
                                        'NOT LIKE',
                                        $excludePattern
                                    );
                            })->where(function ($subQuery) use ($excludePattern) {
                                $subQuery->whereNull('remark1')
                                    ->orWhere(
                                        'remark1',
                                        'NOT LIKE',
                                        $excludePattern
                                    );
                            });
                        });
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 8. RULE DENGAN EKSTRAKSI SITE DINAMIS
                    |--------------------------------------------------------------------------
                    */

                    if ($hasIdSetor) {

                        $sourceField = trim(
                            (string) $rule->id_setor_field
                        );

                        if (!in_array(
                            $sourceField,
                            ['remark', 'remark1'],
                            true
                        )) {
                            continue;
                        }

                        $method = strtoupper(trim(
                            (string) ($rule->id_setor_method ?? '')
                        ));

                        $length = (int) ($rule->id_setor_length ?? 0);
                        $start = (int) ($rule->id_setor_start ?? 0);

                        if (
                            !in_array($method, ['LEFT', 'RIGHT', 'MID'], true)
                            || $length <= 0
                            || ($method === 'MID' && $start < 1)
                        ) {
                            continue;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | 8A. SUMBER SITE
                        |--------------------------------------------------------------------------
                        */

                        $siteSource = strtoupper(trim(
                            (string) ($rule->site_source ?? 'MASTER_SETOR')
                        ));

                        if (!in_array(
                            $siteSource,
                            ['MASTER_SETOR', 'REMARK', 'REMARK1'],
                            true
                        )) {
                            continue;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | 8B. AMBIL TRANSAKSI SESUAI SELURUH FILTER
                        |--------------------------------------------------------------------------
                        */

                        $rows = $query
                            ->select('id', 'remark', 'remark1')
                            ->get();

                        foreach ($rows as $row) {

                            $sourceValue = (string) (
                                $row->{$sourceField} ?? ''
                            );

                            if ($sourceValue === '') {
                                continue;
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | 8C. EKSTRAKSI NILAI
                            |--------------------------------------------------------------------------
                            */

                            switch ($method) {

                                case 'LEFT':
                                    $extractedValue = substr(
                                        $sourceValue,
                                        0,
                                        $length
                                    );
                                    break;

                                case 'RIGHT':
                                    $extractedValue = substr(
                                        $sourceValue,
                                        -$length
                                    );
                                    break;

                                case 'MID':
                                    // Posisi MID dimulai dari 1.
                                    $extractedValue = substr(
                                        $sourceValue,
                                        $start - 1,
                                        $length
                                    );
                                    break;

                                default:
                                    $extractedValue = '';
                                    break;
                            }

                            $extractedValue = trim($extractedValue);

                            if ($extractedValue === '') {
                                continue;
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | 8D. SIAPKAN DATA UPDATE
                            |--------------------------------------------------------------------------
                            */

                            $rowUpdate = $updateData;

                            /*
                            |--------------------------------------------------------------------------
                            | 8E. SUMBER SITE: REMARK / REMARK1
                            |--------------------------------------------------------------------------
                            */

                            if (
                                (
                                    $siteSource === 'REMARK'
                                    && $sourceField === 'remark'
                                )
                                || (
                                    $siteSource === 'REMARK1'
                                    && $sourceField === 'remark1'
                                )
                            ) {
                                $rowUpdate['site'] = $extractedValue;

                            } elseif ($siteSource === 'MASTER_SETOR') {

                                /*
                                |--------------------------------------------------------------------------
                                | SUMBER SITE: MASTER_SETOR
                                |--------------------------------------------------------------------------
                                */

                                $master = DB::table('master_setor')
                                    ->where('cabang', $cabang)
                                    ->where('bank', $bank)
                                    ->where('id_setor', $extractedValue)
                                    ->first(['site']);

                                /*
                                * Untuk rule SLS, jangan update jika site master
                                * tidak ditemukan atau nilainya kosong.
                                */

                                if (
                                    strtoupper(trim(
                                        (string) ($rule->dept_value ?? '')
                                    )) === 'SLS'
                                    && (
                                        !$master
                                        || trim(
                                            (string) ($master->site ?? '')
                                        ) === ''
                                    )
                                ) {
                                    continue;
                                }

                                if ($master && $master->site !== null) {
                                    $rowUpdate['site'] = $master->site;
                                }
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | 8F. UPDATE SATU TRANSAKSI
                            |--------------------------------------------------------------------------
                            */

                            if (!empty($rowUpdate)) {
                                DB::table($tableDetail)
                                    ->where('id', $row->id)
                                    ->where('src', $filename)
                                    ->update($rowUpdate);
                            }
                        }

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | 9. RULE BIASA: UPDATE MASSAL
                        |--------------------------------------------------------------------------
                        */

                        if (empty($updateData)) {
                            continue;
                        }

                        $affectedRows = $query->update($updateData);
                    }
                }

                /* ============ REKAP TRANSAKSI & UNREC ============ */
                $rekap = DB::table($tableDetail)
                    ->selectRaw('
                        no_rek,
                        tgl,
                        SUM(db) db,
                        SUM(cr) cr
                    ')
                    ->where('src', $filename)
                    ->groupBy('no_rek', 'tgl')
                    ->get();

                $insert = [];

                foreach ($rekap as $row) {
                
                    $insert[] = [
                
                        "cabang"=>$cabang,
                        "no_rek"=>$row->no_rek,
                        "tgl"=>$row->tgl,
                        "db"=>$row->db,
                        "cr"=>$row->cr
                
                    ];
                
                }
                
                DB::table($tableRekap)->insert($insert);

                $rekap_unrec = DB::table($tableDetail)
                    ->selectRaw('
                        no_rek,
                        tgl,
                        SUM(db) db,
                        SUM(cr) cr
                    ')
                    ->where('src',$filename)
                    ->where(function($q){

                        $q->whereNull('reconciled')
                        ->orWhere('reconciled','');

                    })
                    ->groupBy('no_rek','tgl')
                    ->get();
                
                $insert_unrec = [];

                foreach ($rekap_unrec as $row) {
                
                    $insert_unrec[] = [
                
                        "cabang"=>$cabang,
                        "no_rek"=>$row->no_rek,
                        "tgl"=>$row->tgl,
                        "db"=>$row->db,
                        "cr"=>$row->cr
                
                    ];
                
                }
                DB::table($tableRekapUnrec)->insert($insert_unrec);

                $file->move($folder, $filename);
                DB::commit();
            } 
            catch (\Throwable $e) {

                // Rollback jika transaksi sedang aktif
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            
                if ($e->getMessage() === "__NO_TRANSACTION__") {
            
                    $errors[] = [
                        "file"   => $filename,
                        "reason" => "File mutasi tidak ada transaksi"
                    ];
            
                    continue;
                }
            
                $errors[] = [
                    "file"   => $filename,
                    "reason" => $e->getMessage()
                ];
            }
        }

        return $errors
            ? response()->json(["success" => false, "message" => "Sebagian file gagal diimport", "errors" => $errors])
            : response()->json(["success" => true, "message" => "Semua file berhasil diproses"]);
    }

    public function getBankList(Request $request)
    {
        $request->validate([
            'cabang'   => 'required',
            'jns_bank' => 'required'
        ]);

        $cabang  = $request->cabang;
        $jnsBank = $request->jns_bank;

        /*
        |--------------------------------------------------------------------------
        | REGULER
        |--------------------------------------------------------------------------
        */
        if ($jnsBank === 'REG') {

            $query = DB::table('bank')
                ->where('site', 'REG')
                ->where('bank', '!=', 'Titipan');

            if ($cabang !== 'ALL') {
                $query->where('cabang', $cabang);
            }

            $rows = $query
                ->orderBy('bank')
                ->get();

            $data = [];

            $data[] = [
                'no_rek' => 'ALL',
                'label'  => 'ALL Rekening Reguler'
            ];

            foreach ($rows as $row) {

                $data[] = [
                    'no_rek' => $row->no_rek,
                    'label'  => "({$row->jns_bank} - {$row->no_rek})"
                ];
            }

            return response()->json($data);
        }

        /*
        |--------------------------------------------------------------------------
        | BANK FRC
        |--------------------------------------------------------------------------
        */
        $query = DB::table('bank')
            ->where('jns_bank', $jnsBank);

        if ($cabang !== 'ALL') {
            $query->where('cabang', $cabang);
        }

        $rows = $query
            ->orderBy('site')
            ->get();

        $data = [];

        $data[] = [
            'no_rek' => 'ALL',
            'label'  => "ALL Rekening {$jnsBank}"
        ];

        foreach ($rows as $row) {

            $data[] = [
                'no_rek' => $row->no_rek,
                'label'  => "({$row->jns_bank} - {$row->no_rek} - {$row->site})"
            ];
        }

        return response()->json($data);
    }

    public function search(Request $request)
    {
        $request->validate([
            'bank'       => 'required',
            'tgl_awal'   => 'required',
            'tgl_akhir'  => 'required',
        ]);

        $bank       = $request->bank;
        $noRek      = $request->no_rek;
        $cabang     = $request->cabang;
        $tglAwal    = $request->tgl_awal;
        $tglAkhir   = $request->tgl_akhir;
        $filterType = $request->filter_type;

        /*
        |--------------------------------------------------------------------------
        | Tentukan tabel
        |--------------------------------------------------------------------------
        */

        if ($bank === 'REG') {

            $query = DB::table('mutasi_detail as m')
                ->leftJoin('bank as b', 'm.no_rek', '=', 'b.no_rek')
                ->whereBetween('m.tgl', [$tglAwal, $tglAkhir]);
        
            $prefix = 'm.';
        
        } else {
        
            $query = DB::table('mutasi_detail_frc as m')
                ->leftJoin('bank as b', 'm.no_rek', '=', 'b.no_rek')
                ->whereBetween('m.tgl', [$tglAwal, $tglAkhir]);
        
            $prefix = 'm.';
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Cabang
        |--------------------------------------------------------------------------
        */

        if (
            !empty($cabang) &&
            strtoupper($cabang) !== 'ALL'
        ) {
            $query->where($prefix . 'cabang', $cabang);
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Nomor Rekening
        |--------------------------------------------------------------------------
        */

        if ($bank === 'REG') {

            if (
                !empty($noRek) &&
                strtoupper($noRek) !== 'ALL'
            ) {
                $query->where('m.no_rek', $noRek);
            }
        
        } else {
        
            if (
                empty($noRek) ||
                strtoupper($noRek) === 'ALL'
            ) {
        
                $rekeningList = DB::table('bank')
                    ->where('cabang', $cabang)
                    ->where('jns_bank', $bank)
                    ->pluck('no_rek')
                    ->toArray();
        
                $query->whereIn(
                    'm.no_rek',
                    $rekeningList
                );
        
            } else {
        
                $query->where(
                    'm.no_rek',
                    $noRek
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Tambahan
        |--------------------------------------------------------------------------
        */

        switch ($filterType) {

            case 'debit_only':

                $query->where(
                    $prefix . 'db',
                    '>',
                    0
                );

                break;

            case 'credit_only':

                $query->where(
                    $prefix . 'cr',
                    '>',
                    0
                );

                break;

            case 'debit_belum_jurnal':

                $query->where(
                    $prefix . 'db',
                    '>',
                    0
                )

                ->where(function ($q) use ($prefix) {

                    $q->whereNull($prefix . 'reconciled')
                        ->orWhere($prefix . 'reconciled', '');

                })

                ->where(function ($q) use ($prefix) {

                    $q->whereNull($prefix . 'dept')
                        ->orWhere($prefix . 'dept', '');

                });

                break;

            default:
                break;
        }

        /*
        |--------------------------------------------------------------------------
        | Ambil Data
        |--------------------------------------------------------------------------
        */

        $data = $query
            ->select([
                'm.id',
                'm.cabang',
                'm.no_rek',
                'b.site',
                'b.jns_bank',
                'm.tgl',
                'm.trx_code',
                'm.remark',
                'm.remark1',
                'm.db',
                'm.cr',
                'm.saldo',
                'm.src',
                'm.reconciled',
                'm.dept',
                'm.inv',
            ])
            ->orderBy('m.tgl', 'asc')
            ->orderBy('m.id', 'asc')
            ->get();

        return response()->json($data);
    }

    public function reconcileSelected(Request $request)
    {
        DB::beginTransaction();

        try {

            $ids = $request->ids;
            $bank = $request->bank;

            $tableMutasi =
                $bank === 'REG'
                    ? 'mutasi_detail'
                    : 'mutasi_detail_frc';

            $tableRekapUnrec =
                $bank === 'REG'
                    ? 'mutasi_rekap_unrec'
                    : 'mutasi_rekap_unrec_frc';

            /*
            |--------------------------------------------------------------------------
            | UPDATE RECONCILED
            |--------------------------------------------------------------------------
            */

            DB::table($tableMutasi)
                ->whereIn('id', $ids)
                ->update([
                    'reconciled' => 'Y'
                ]);

            /*
            |--------------------------------------------------------------------------
            | AMBIL COMBINASI TGL + REKENING
            |--------------------------------------------------------------------------
            */

            $rows = DB::table($tableMutasi)
                ->whereIn('id', $ids)
                ->select(
                    'cabang',
                    'tgl',
                    'no_rek'
                )
                ->get();

            $unique = [];

            foreach ($rows as $row) {

                $key =
                    $row->tgl .
                    '_' .
                    $row->no_rek;

                $unique[$key] = [
                    'cabang' => $row->cabang,
                    'tgl' => $row->tgl,
                    'no_rek' => $row->no_rek,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE REKAP UNREC
            |--------------------------------------------------------------------------
            */

            foreach ($unique as $item) {

                $dbTotal = DB::table($tableMutasi)
                    ->where('tgl', $item['tgl'])
                    ->where('no_rek', $item['no_rek'])
                    ->where(function ($q) {
                        $q->whereNull('reconciled')
                        ->orWhere('reconciled', '');
                    })
                    ->sum('db');

                $crTotal = DB::table($tableMutasi)
                    ->where('tgl', $item['tgl'])
                    ->where('no_rek', $item['no_rek'])
                    ->where(function ($q) {
                        $q->whereNull('reconciled')
                        ->orWhere('reconciled', '');
                    })
                    ->sum('cr');

                DB::table($tableRekapUnrec)
                    ->updateOrInsert(
                        [
                            'tgl' => $item['tgl'],
                            'no_rek' => $item['no_rek'],
                        ],
                        [
                            'cabang' => $item['cabang'],
                            'db' => $dbTotal,
                            'cr' => $crTotal,
                        ]
                    );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($ids) . ' record berhasil direconcile'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function unreconcileSelected(Request $request)
    {
        DB::beginTransaction();

        try {

            $ids = $request->ids;
            $bank = $request->bank;

            $tableMutasi =
                $bank === 'REG'
                    ? 'mutasi_detail'
                    : 'mutasi_detail_frc';

            $tableRekapUnrec =
                $bank === 'REG'
                    ? 'mutasi_rekap_unrec'
                    : 'mutasi_rekap_unrec_frc';

            /*
            |--------------------------------------------------------------------------
            | UPDATE RECONCILED
            |--------------------------------------------------------------------------
            */

            DB::table($tableMutasi)
                ->whereIn('id', $ids)
                ->update([
                    'reconciled' => ''
                ]);

            /*
            |--------------------------------------------------------------------------
            | AMBIL COMBINASI TGL + REKENING
            |--------------------------------------------------------------------------
            */

            $rows = DB::table($tableMutasi)
                ->whereIn('id', $ids)
                ->select(
                    'cabang',
                    'tgl',
                    'no_rek'
                )
                ->get();

            $unique = [];

            foreach ($rows as $row) {

                $key =
                    $row->tgl .
                    '_' .
                    $row->no_rek;

                $unique[$key] = [
                    'cabang' => $row->cabang,
                    'tgl' => $row->tgl,
                    'no_rek' => $row->no_rek,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE REKAP UNREC
            |--------------------------------------------------------------------------
            */

            foreach ($unique as $item) {

                $dbTotal = DB::table($tableMutasi)
                    ->where('tgl', $item['tgl'])
                    ->where('no_rek', $item['no_rek'])
                    ->where(function ($q) {
                        $q->whereNull('reconciled')
                        ->orWhere('reconciled', '');
                    })
                    ->sum('db');

                $crTotal = DB::table($tableMutasi)
                    ->where('tgl', $item['tgl'])
                    ->where('no_rek', $item['no_rek'])
                    ->where(function ($q) {
                        $q->whereNull('reconciled')
                        ->orWhere('reconciled', '');
                    })
                    ->sum('cr');

                DB::table($tableRekapUnrec)
                    ->updateOrInsert(
                        [
                            'tgl' => $item['tgl'],
                            'no_rek' => $item['no_rek'],
                        ],
                        [
                            'cabang' => $item['cabang'],
                            'db' => $dbTotal,
                            'cr' => $crTotal,
                        ]
                    );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($ids) . ' record berhasil diunreconcile'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function journalSelected(Request $request)
    {
        $ids     = $request->ids;
        $bank    = $request->bank;
        $cabang  = $request->cabang;

        DB::beginTransaction();

        try {

            foreach ($ids as $id) {

                /*
                |--------------------------------------------------------------------------
                | Ambil data mutasi
                |--------------------------------------------------------------------------
                */

                if ($bank === 'REG') {

                    $mutasi = DB::table('mutasi_detail as m')
                        ->leftJoin('bank as b', 'm.no_rek', '=', 'b.no_rek')
                        ->where('m.id', $id)
                        ->select(
                            'm.*',
                            'b.jns_bank',
                            'b.site'
                        )
                        ->first();

                } else {

                    $mutasi = DB::table('mutasi_detail_frc as m')
                        ->leftJoin('bank as b', 'm.no_rek', '=', 'b.no_rek')
                        ->where('m.id', $id)
                        ->select(
                            'm.*',
                            'b.jns_bank',
                            'b.site'
                        )
                        ->first();
                }

                if (!$mutasi) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Generate Rule Journal
                |--------------------------------------------------------------------------
                */

                $rule = JournalRuleService::generate($mutasi);

                /*
                |--------------------------------------------------------------------------
                | Default value jika rule tidak ditemukan
                |--------------------------------------------------------------------------
                */

                $invNum = '';
                $descHeader = '';
                $account = '';
                $costCenter = '';

                if ($rule) {

                    $invNum = $this->generateInvoiceNumber(
                        $rule['inv_num']
                    );

                    $descHeader = $rule['desc_header'] ?? null;
                    $account = $rule['account'] ?? null;
                    $costCenter = $rule['cost_center'] ?? null;

                } else {

                    \Log::warning('RULE TIDAK DITEMUKAN', [
                        'id'       => $mutasi->id,
                        'jns_bank' => $mutasi->jns_bank,
                        'remark'   => $mutasi->remark,
                        'remark1'  => $mutasi->remark1,
                        'db'       => $mutasi->db,
                        'cr'       => $mutasi->cr,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Insert Journal Entry
                |--------------------------------------------------------------------------
                */

                DB::table('journal_entry')->insert([

                    'id_mutasi'         => $mutasi->id,
                    'cabang'            => $mutasi->cabang,
                    'bank'              => $mutasi->site,
                    'no_rek'            => $mutasi->no_rek,
                    'tgl'               => $mutasi->tgl,

                    'keterangan'        => trim(
                        ($mutasi->remark ?? '') .
                        ' ' .
                        ($mutasi->remark1 ?? '')
                    ),

                    'inv_num'           => $invNum,

                    'desc_header'       => $descHeader,

                    'desc_distribution' => $descHeader,

                    'acct'              => $account,

                    'cost_center'       => $costCenter,

                    'supplier_num'      => $bank === 'REG'
                        ? 'R0272'
                        : 'SG009',

                    'supplier_site'     => $bank === 'REG'
                        ? 'R0272'
                        : 'ITD',

                    'db'                => $mutasi->db,
                    'cr'                => $mutasi->cr,

                ]);

                /*
                |--------------------------------------------------------------------------
                | Tandai sudah dijurnal
                |--------------------------------------------------------------------------
                */

                if ($bank === 'REG') {

                    DB::table('mutasi_detail')
                        ->where('id', $id)
                        ->update([
                            'inv' => 'I'
                        ]);

                } else {

                    DB::table('mutasi_detail_frc')
                        ->where('id', $id)
                        ->update([
                            'inv' => 'I'
                        ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Journal berhasil dibuat'
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function generateInvoiceNumber($baseInv)
    {
        $count = DB::table('journal_entry')
            ->where('inv_num', 'like', $baseInv . '%')
            ->count();

        if ($count === 0) {
            return $baseInv;
        }

        return $baseInv . '-' . ($count + 1);
    }

    public function importReceipt(Request $request)
    {
        $request->validate([
            'files'   => 'required',
            'files.*' => 'required|file|mimes:csv,txt',
            'cabang'  => 'required|string|max:50',
        ]);

        $cabang = trim($request->cabang);

        $errors = [];
        $insertData = [];
        $updateData = [];

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            /*
            |--------------------------------------------------------------------------
            | Ambil periode aktif
            |--------------------------------------------------------------------------
            */

            $activePeriod = $this->findActivePeriod($cabang);

            if (!$activePeriod) {
                throw new \Exception(
                    "Periode Mutasi aktif untuk cabang {$cabang} tidak ditemukan."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Ambil konfigurasi bank
            |--------------------------------------------------------------------------
            */

            $bankConfigs = $this->getBankConfigurations($cabang);

            /*
            |--------------------------------------------------------------------------
            | Mapping rekening
            |--------------------------------------------------------------------------
            */

            $validAccounts = [];

            foreach ($bankConfigs as $bank) {
                $account = trim($bank->no_rek ?? '');

                if ($account !== '') {
                    $validAccounts[$account] = $bank;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Ambil receipt existing
            |--------------------------------------------------------------------------
            */

            $existingReceipt = DB::table('receipt')
                ->select([
                    'id',
                    'receipt_number',
                    'remittance_bank_account',
                    'receipt_amount',
                    'receipt_date',
                    'receipt_status',
                    'receipt_state',
                    'reff',
                ])
                ->whereBetween('receipt_date', [
                    $activePeriod->start_date,
                    $activePeriod->end_date,
                ])
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Mapping receipt existing
            |--------------------------------------------------------------------------
            */

            $existingMap = [];

            foreach ($existingReceipt as $receipt) {
                $account = trim(
                    $receipt->remittance_bank_account ?? ''
                );

                $number = trim(
                    $receipt->receipt_number ?? ''
                );

                $key = $account . '|' . $number;

                $existingMap[$key] = $receipt;
            }

            /*
            |--------------------------------------------------------------------------
            | Loop file
            |--------------------------------------------------------------------------
            */

            foreach ($request->file('files') as $file) {
                $handle = fopen($file->getRealPath(), 'r');

                if (!$handle) {
                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'issues' => [
                            'File tidak dapat dibaca.',
                        ],
                    ];

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Header
                |--------------------------------------------------------------------------
                */

                $header = fgetcsv($handle, 0, '|');

                if (!$header) {
                    fclose($handle);

                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'issues' => [
                            'Header CSV tidak ditemukan.',
                        ],
                    ];

                    continue;
                }

                $header = array_map('trim', $header);

                $expectedHeader = [
                    'Receipt Method',
                    'Remittance Bank Account',
                    'Receipt Number',
                    'Receipt Amount',
                    'Receipt Date',
                    'GL Date',
                    'Type',
                    'Status',
                    'State',
                    'Comments',
                    'Activity',
                    'Paid By',
                ];

                if ($header !== $expectedHeader) {
                    fclose($handle);

                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'issues' => [
                            'Format Header CSV tidak sesuai.',
                        ],
                    ];

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Loop data CSV
                |--------------------------------------------------------------------------
                */

                $line = 1;

                while (($row = fgetcsv($handle, 0, '|')) !== false) {
                    $line++;

                    if (count($row) !== 12) {
                        $errors[] = [
                            'file' => $file->getClientOriginalName(),
                            'issues' => [
                                "Baris {$line} jumlah kolom tidak sesuai.",
                            ],
                        ];

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Ambil data
                    |--------------------------------------------------------------------------
                    */

                    $receiptMethod = trim($row[0]);
                    $remittanceBankAccount = trim($row[1]);
                    $receiptNumber = trim($row[2]);

                    $receiptAmount = str_replace(
                        ',',
                        '',
                        trim($row[3])
                    );

                    $receiptDate = trim($row[4]);
                    $glDate = trim($row[5]);
                    $receiptType = trim($row[6]);
                    $receiptStatus = trim($row[7]);
                    $receiptState = trim($row[8]);
                    $comments = trim($row[9]);
                    $activity = trim($row[10]);
                    $paidBy = trim($row[11]);

                    /*
                    |--------------------------------------------------------------------------
                    | Validasi rekening dan tentukan tabel mutasi
                    |--------------------------------------------------------------------------
                    */

                    if (!isset($validAccounts[$remittanceBankAccount])) {
                        $errors[] = [
                            'file' => $file->getClientOriginalName(),
                            'issues' => [
                                "Baris {$line}: Remittance Bank Account '{$remittanceBankAccount}' tidak terdaftar pada cabang {$cabang}.",
                            ],
                        ];

                        continue;
                    }

                    $bankConfig = $validAccounts[$remittanceBankAccount];

                    $site = strtoupper(
                        trim((string) ($bankConfig->site ?? ''))
                    );

                    // REG menggunakan mutasi_detail.
                    // Site selain REG, termasuk NULL, menggunakan mutasi_detail_frc.
                    $mutasiTable = $site === 'REG'
                        ? 'mutasi_detail'
                        : 'mutasi_detail_frc';

                    /*
                    |--------------------------------------------------------------------------
                    | Parse tanggal
                    |--------------------------------------------------------------------------
                    */

                    try {
                        $receiptDate = Carbon::createFromFormat(
                            'd/m/y',
                            $receiptDate
                        )->format('Y-m-d');

                        $glDate = Carbon::createFromFormat(
                            'd/m/y',
                            $glDate
                        )->format('Y-m-d');

                        /*
                        |--------------------------------------------------------------------------
                        | Receipt Date harus berada dalam periode aktif
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $receiptDate < $activePeriod->start_date ||
                            $receiptDate > $activePeriod->end_date
                        ) {
                            $errors[] = [
                                'file' => $file->getClientOriginalName(),
                                'issues' => [
                                    "Baris {$line}: Receipt Date {$receiptDate} berada di luar periode aktif Mutasi.",
                                ],
                            ];

                            continue;
                        }
                    } catch (\Throwable $e) {
                        $errors[] = [
                            'file' => $file->getClientOriginalName(),
                            'issues' => [
                                "Baris {$line}: format tanggal salah.",
                            ],
                        ];

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Composite key
                    |--------------------------------------------------------------------------
                    */

                    $key = $remittanceBankAccount . '|' . $receiptNumber;

                    /*
                    |--------------------------------------------------------------------------
                    | Cek receipt sudah ada
                    |--------------------------------------------------------------------------
                    */

                    if (isset($existingMap[$key])) {
                        $old = $existingMap[$key];

                        /*
                        |--------------------------------------------------------------------------
                        | Normalisasi status
                        |--------------------------------------------------------------------------
                        */

                        $newStatus = strtoupper(
                            trim((string) $receiptStatus)
                        );

                        $oldStatus = strtoupper(
                            trim((string) $old->receipt_status)
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | Reff receipt lama
                        |--------------------------------------------------------------------------
                        */

                        $oldReff = trim(
                            (string) ($old->reff ?? '')
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | Receipt lama sudah REVERSED
                        |--------------------------------------------------------------------------
                        */

                        if ($oldStatus === 'REVERSED') {
                            if ($oldReff !== '') {
                                $updateData[] = [
                                    'id' => $old->id,
                                    'receipt_status' => 'Reversed',
                                    'receipt_state' => $receiptState,
                                    'clear_trx' => true,
                                    'clear_reff' => true,
                                    'old_reff' => $oldReff,

                                    // Informasi tabel dan rekening.
                                    'mutasi_table' => $mutasiTable,
                                    'no_rek' => $remittanceBankAccount,
                                ];

                                $updated++;
                            } else {
                                $skipped++;
                            }

                            continue;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Cek perubahan status / state
                        |--------------------------------------------------------------------------
                        */

                        $statusChanged = strcasecmp(
                            (string) $old->receipt_status,
                            $receiptStatus
                        ) !== 0;

                        $stateChanged = strcasecmp(
                            (string) $old->receipt_state,
                            $receiptState
                        ) !== 0;

                        if (!$statusChanged && !$stateChanged) {
                            $skipped++;
                            continue;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Receipt berubah menjadi REVERSED
                        |--------------------------------------------------------------------------
                        */

                        $isReversed = $newStatus === 'REVERSED';

                        $updateData[] = [
                            'id' => $old->id,
                            'receipt_status' => $receiptStatus,
                            'receipt_state' => $receiptState,
                            'clear_trx' => $isReversed,
                            'clear_reff' => $isReversed,
                            'old_reff' => $isReversed
                                ? $oldReff
                                : null,

                            // Informasi tabel dan rekening.
                            'mutasi_table' => $mutasiTable,
                            'no_rek' => $remittanceBankAccount,
                        ];

                        $updated++;

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Insert receipt baru
                    |--------------------------------------------------------------------------
                    */

                    $insertData[] = [
                        'receipt_method' => $receiptMethod,
                        'remittance_bank_account' => $remittanceBankAccount,
                        'receipt_number' => $receiptNumber,
                        'receipt_amount' => $receiptAmount,
                        'receipt_date' => $receiptDate,
                        'gl_date' => $glDate,
                        'receipt_type' => $receiptType,
                        'receipt_status' => $receiptStatus,
                        'receipt_state' => $receiptState,
                        'comments' => $comments,
                        'activity' => $activity,
                        'paid_by' => $paidBy,
                    ];

                    /*
                    |--------------------------------------------------------------------------
                    | Masukkan ke map
                    |--------------------------------------------------------------------------
                    */

                    $existingMap[$key] = (object) [
                        'id' => 0,
                        'receipt_number' => $receiptNumber,
                        'remittance_bank_account' => $remittanceBankAccount,
                        'receipt_amount' => $receiptAmount,
                        'receipt_date' => $receiptDate,
                        'receipt_status' => $receiptStatus,
                        'receipt_state' => $receiptState,
                        'reff' => null,
                    ];

                    $inserted++;
                }

                fclose($handle);
            }

            /*
            |--------------------------------------------------------------------------
            | Bulk insert receipt
            |--------------------------------------------------------------------------
            */

            if (!empty($insertData)) {
                foreach (array_chunk($insertData, 1000) as $chunk) {
                    DB::table('receipt')->insert($chunk);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Update receipt
            |--------------------------------------------------------------------------
            */

            foreach ($updateData as $row) {
                /*
                |--------------------------------------------------------------------------
                | Reset reff pada tabel mutasi yang sesuai
                |--------------------------------------------------------------------------
                */

                if (
                    !empty($row['clear_reff']) &&
                    !empty($row['old_reff'])
                ) {
                    $mutasiTable = $row['mutasi_table'] ?? null;

                    // Validasi nama tabel agar hanya tabel yang diizinkan dipakai.
                    if (!in_array(
                        $mutasiTable,
                        ['mutasi_detail', 'mutasi_detail_frc'],
                        true
                    )) {
                        throw new \RuntimeException(
                            'Tabel mutasi untuk update REVERSED tidak valid.'
                        );
                    }

                    DB::table($mutasiTable)
                        ->where('cabang', $cabang)
                        ->where('no_rek', $row['no_rek'])
                        ->where('reff', $row['old_reff'])
                        ->update([
                            'reff' => null,
                        ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Update receipt
                |--------------------------------------------------------------------------
                */

                $update = [
                    'receipt_status' => $row['receipt_status'],
                    'receipt_state' => $row['receipt_state'],
                ];

                if (!empty($row['clear_reff'])) {
                    $update['reff'] = DB::raw('NULL');
                }

                DB::table('receipt')
                    ->where('id', $row['id'])
                    ->update($update);
            }

            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'Import Receipt selesai.',
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'period' => [
                    'periode' => $activePeriod->periode ?? null,
                    'start_date' => $activePeriod->start_date,
                    'end_date' => $activePeriod->end_date,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Import Receipt Error', [
                'cabang' => $cabang,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function findActivePeriod(string $cabang)
    {
        return DB::table('periode')
            ->where('Cabang', $cabang)
            ->where('kategori', 'Mutasi')
            ->where('status', 'Aktif')
            ->orderByDesc('start_date')
            ->first();
    }

    private function getBankConfigurations(string $cabang)
    {
        return DB::table('bank')
            ->where('cabang', $cabang)
            ->select(
                'no_rek',
                'jns_bank',
                'no_rek',
                'site'
            )
            ->get();
    }

    public function uploadRK(Request $request)
    {
        try {

            // ==========================================================
            // VALIDASI REQUEST
            // ==========================================================
            $request->validate([
                'cabang' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'files' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'files.*' => [
                    'required',
                    'file',
                    'max:102400',
                ],
            ]);

            $cabang = trim($request->cabang);

            $berhasil = [];
            $error = [];


            // ==========================================================
            // 1. AMBIL FOLDER DARI SETTING
            // ==========================================================
            $setting = DB::table('setting')
                ->where('cabang', $cabang)
                ->where('category', 'rk_storage')
                ->first();

            if (!$setting) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        "Setting rk_storage untuk cabang {$cabang} tidak ditemukan.",
                    'berhasil' => [],
                    'error' => [
                        [
                            'file' => '-',
                            'message' =>
                                "Setting rk_storage untuk cabang {$cabang} tidak ditemukan."
                        ]
                    ]
                ], 422);
            }

            $storagePath = trim($setting->value);


            // ==========================================================
            // 2. CEK FOLDER
            // ==========================================================
            if (!is_dir($storagePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder storage tidak ditemukan.',
                    'berhasil' => [],
                    'error' => [
                        [
                            'message' => "Folder tidak ditemukan: {$storagePath}"
                        ]
                    ]
                ], 422);
            }

            // ==========================================================
            // 3. AMBIL PERIODE AKTIF
            //
            // cabang     = cabang
            // kategori   = Mutasi
            // status     = Aktif
            // ==========================================================
            $periodeAktif = DB::table('periode')
                ->where('cabang', $cabang)
                ->where('kategori', 'Mutasi')
                ->where('status', 'Aktif')
                ->first();

            if (!$periodeAktif) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        "Periode aktif untuk cabang {$cabang} tidak ditemukan.",
                    'berhasil' => [],
                    'error' => [
                        [
                            'file' => '-',
                            'message' =>
                                "Tidak ditemukan periode Mutasi dengan status Aktif untuk cabang {$cabang}."
                        ]
                    ]
                ], 422);
            }

            $periode = trim($periodeAktif->periode);


            // ==========================================================
            // 4. PROSES SETIAP FILE
            // ==========================================================
            foreach ($request->file('files') as $file) {

                $originalName = $file->getClientOriginalName();

                try {

                    // ==================================================
                    // 4.1 AMBIL NO REK DARI NAMA FILE
                    //
                    // 0372780400.pdf
                    //        ↓
                    // 0372780400
                    // ==================================================
                    $noRek = trim(
                        pathinfo(
                            $originalName,
                            PATHINFO_FILENAME
                        )
                    );


                    // ==================================================
                    // 4.2 VALIDASI NAMA FILE
                    // ==================================================
                    if ($noRek === '') {

                        $error[] = [
                            'file' => $originalName,
                            'message' =>
                                'Nama file tidak valid. Format harus no_rek.pdf.'
                        ];

                        continue;
                    }


                    // ==================================================
                    // 4.3 VALIDASI EXTENSION
                    // ==================================================
                    if (
                        strtolower(
                            $file->getClientOriginalExtension()
                        ) !== 'pdf'
                    ) {

                        $error[] = [
                            'file' => $originalName,
                            'message' =>
                                'File harus berformat PDF.'
                        ];

                        continue;
                    }


                    // ==================================================
                    // 4.4 CARI DATA BANK
                    //
                    // cabang + no_rek
                    // ==================================================
                    $bank = DB::table('bank')
                        ->where('cabang', $cabang)
                        ->where('no_rek', $noRek)
                        ->first();


                    // ==================================================
                    // 4.5 NO REK TIDAK DITEMUKAN
                    // ==================================================
                    if (!$bank) {

                        $error[] = [
                            'file' => $originalName,
                            'no_rek' => $noRek,
                            'message' =>
                                "No Rek {$noRek} tidak ditemukan pada tabel bank untuk cabang {$cabang}."
                        ];

                        continue;
                    }


                    // ==================================================
                    // 4.6 AMBIL DATA BANK
                    // ==================================================
                    $namaBank = trim($bank->bank);
                    $jnsRek = trim($bank->jns_bank);


                    // ==================================================
                    // 4.7 BERSIHKAN NAMA BANK
                    // ==================================================
                    $namaBankFile = preg_replace(
                        '/[\\\\\/:*?"<>|]/',
                        '_',
                        $namaBank
                    );


                    // ==================================================
                    // 4.8 BERSIHKAN PERIODE
                    // ==================================================
                    $periodeFile = preg_replace(
                        '/[\\\\\/:*?"<>|]/',
                        '_',
                        $periode
                    );


                    // ==================================================
                    // 4.9 RENAME FILE
                    //
                    // nama_bank_no_rek_periode.pdf
                    // ==================================================
                    $newFileName =
                        $namaBankFile .
                        '_' .
                        $noRek .
                        '_' .
                        $periodeFile .
                        '.pdf';


                    // ==================================================
                    // 4.10 PATH FILE
                    // ==================================================
                    $destinationPath = rtrim(
                        $storagePath,
                        DIRECTORY_SEPARATOR
                    );

                    $destinationFile =
                        $destinationPath .
                        DIRECTORY_SEPARATOR .
                        $newFileName;


                    // ==================================================
                    // 4.11 CEK FILE SUDAH ADA
                    // ==================================================
                    if (File::exists($destinationFile)) {

                        $error[] = [
                            'file' => $originalName,
                            'no_rek' => $noRek,
                            'message' =>
                                "File {$newFileName} sudah ada."
                        ];

                        continue;
                    }


                    // ==================================================
                    // 4.12 CEK DATA RK
                    //
                    // Untuk mencegah duplicate record
                    // ==================================================
                    $existingRK = DB::table('rk')
                        ->where('cabang', $cabang)
                        ->where('no_rek', $noRek)
                        ->where('periode', $periode)
                        ->first();

                    if ($existingRK) {

                        $error[] = [
                            'file' => $originalName,
                            'no_rek' => $noRek,
                            'message' =>
                                "Data rekening {$noRek} untuk periode {$periode} sudah ada di tabel rk."
                        ];

                        continue;
                    }


                    // ==================================================
                    // 4.13 PINDAHKAN FILE
                    // ==================================================
                    $file->move(
                        $destinationPath,
                        $newFileName
                    );


                    // ==================================================
                    // 4.14 INSERT KE TABEL RK
                    // ==================================================
                    try {

                        DB::table('rk')->insert([
                            'cabang' => $cabang,
                            'nama_bank' => $namaBank,
                            'jns_rek' => $jnsRek,
                            'no_rek' => $noRek,
                            'file' => $newFileName,
                            'periode' => $periode,
                            'path' => $storagePath,
                        ]);

                    } catch (\Throwable $dbError) {

                        // ---------------------------------------------
                        // Jika insert database gagal,
                        // hapus kembali file yang sudah dipindahkan
                        // ---------------------------------------------
                        if (File::exists($destinationFile)) {
                            File::delete($destinationFile);
                        }

                        throw $dbError;
                    }


                    // ==================================================
                    // 4.15 BERHASIL
                    // ==================================================
                    $berhasil[] = [
                        'file_asli' => $originalName,
                        'no_rek' => $noRek,
                        'nama_bank' => $namaBank,
                        'jns_rek' => $jnsRek,
                        'periode' => $periode,
                        'file_baru' => $newFileName,
                        'path' => $storagePath,
                    ];

                } catch (\Throwable $e) {

                    Log::error('Upload RK File Error', [
                        'cabang' => $cabang,
                        'file' => $originalName,
                        'message' => $e->getMessage(),
                        'line' => $e->getLine(),
                    ]);

                    $error[] = [
                        'file' => $originalName,
                        'message' =>
                            'Gagal memproses file: ' .
                            $e->getMessage()
                    ];

                    continue;
                }
            }


            // ==========================================================
            // 5. RESPONSE
            // ==========================================================
            $totalBerhasil = count($berhasil);
            $totalError = count($error);


            // ==========================================================
            // SEMUA BERHASIL
            // ==========================================================
            if ($totalBerhasil > 0 && $totalError === 0) {

                return response()->json([
                    'success' => true,
                    'partial' => false,
                    'message' =>
                        "{$totalBerhasil} file berhasil diupload.",
                    'berhasil' => $berhasil,
                    'error' => [],
                ], 200);
            }


            // ==========================================================
            // SEBAGIAN BERHASIL
            // ==========================================================
            if ($totalBerhasil > 0 && $totalError > 0) {

                return response()->json([
                    'success' => true,
                    'partial' => true,
                    'message' =>
                        "{$totalBerhasil} file berhasil, {$totalError} file gagal.",
                    'berhasil' => $berhasil,
                    'error' => $error,
                ], 200);
            }


            // ==========================================================
            // SEMUA GAGAL
            // ==========================================================
            return response()->json([
                'success' => false,
                'partial' => false,
                'message' =>
                    'Tidak ada file yang berhasil diupload.',
                'berhasil' => [],
                'error' => $error,
            ], 422);


        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $e->errors(),
            ], 422);


        } catch (\Throwable $e) {

            Log::error('Upload RK Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' =>
                    'Terjadi kesalahan saat proses upload.',
            ], 500);
        }
    }

    public function deleteRK($id)
    {
        try {

            // =====================================================
            // 1. Ambil data RK
            // =====================================================
            $rk = DB::table('rk')
                ->where('id', $id)
                ->first();

            if (!$rk) {

                return response()->json([
                    'success' => false,
                    'message' => 'Data rekening koran tidak ditemukan.'
                ], 404);
            }


            // =====================================================
            // 2. Bentuk lokasi file
            // =====================================================
            $path = trim($rk->path);
            $file = trim($rk->file);

            $filePath =
                $path .
                DIRECTORY_SEPARATOR .
                $file;


            // =====================================================
            // 3. Hapus file fisik
            // =====================================================
            $fileDeleted = false;

            if (file_exists($filePath)) {

                if (!unlink($filePath)) {

                    return response()->json([
                        'success' => false,
                        'message' => 'File rekening koran gagal dihapus.'
                    ], 500);
                }

                $fileDeleted = true;
            }


            // =====================================================
            // 4. Hapus data dari tabel RK
            // =====================================================
            DB::table('rk')
                ->where('id', $id)
                ->delete();


            // =====================================================
            // 5. Response
            // =====================================================
            return response()->json([
                'success' => true,
                'message' => 'Rekening koran berhasil dihapus.',
                'file_deleted' => $fileDeleted,
                'id' => $id,
            ]);

        } catch (\Throwable $e) {

            Log::error('Delete RK Error', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus rekening koran.'
            ], 500);
        }
    }
        
    public function exportMutasi(Request $request)
    {
        // ==========================================
        // 1. VALIDASI PARAMETER
        // ==========================================
        $request->validate([
            'cabang'   => 'required|string',
            'periode'  => 'required|string',
            'kategori' => 'required|in:Reguler,Franchise',
        ]);

        $cabang   = $request->cabang;
        $periode  = $request->periode;
        $kategori = $request->kategori;

        // ==========================================
        // 2. AMBIL PERIODE AKTIF
        // ==========================================
        $periodeData = DB::table('periode')
            ->where('cabang', $cabang)
            ->where('kategori', 'Mutasi')
            ->where('periode', $periode)
            ->where('status', 'Aktif')
            ->select(
                'id',
                'cabang',
                'kategori',
                'periode',
                'start_date',
                'end_date',
                'status'
            )
            ->first();

        if (!$periodeData) {
            return response()->json([
                'success' => false,
                'message' => 'Periode tidak ditemukan atau bukan periode aktif.'
            ], 403);
        }

        $start = $periodeData->start_date;
        $end   = $periodeData->end_date;

        // ==========================================
        // 3. TENTUKAN TABEL DETAIL
        // ==========================================
        $detailTable = $kategori === 'Reguler'
            ? 'mutasi_detail'
            : 'mutasi_detail_frc';

        $dateColumn = 'tgl';

        // ==========================================
        // 4. AMBIL GROUP DARI TABEL BANK
        // ==========================================
        $bankQuery = DB::table('bank')
            ->where('cabang', $cabang);

        if ($kategori === 'Reguler') {

            // REGULER:
            // Satu no_rek = satu sheet
            $groups = $bankQuery
                ->where('site', 'REG')
                ->whereNotNull('no_rek')
                ->where('no_rek', '<>', '')
                ->distinct()
                ->orderBy('no_rek')
                ->pluck('no_rek')
                ->values()
                ->all();

        } else {

            // FRANCHISE:
            // Satu jns_bank = satu sheet
            // Site selain REG
            $groups = $bankQuery
                ->where('site', '<>', 'REG')
                ->whereNotNull('jns_bank')
                ->where('jns_bank', '<>', '')
                ->distinct()
                ->orderBy('jns_bank')
                ->pluck('jns_bank')
                ->values()
                ->all();
        }

        if (empty($groups)) {
            return response()->json([
                'success' => false,
                'message' => 'Data rekening atau jenis bank tidak ditemukan.',
                'kategori' => $kategori,
                'cabang' => $cabang,
            ], 404);
        }

        // ==========================================
        // 5. INISIALISASI WORKBOOK
        // ==========================================
        $spreadsheet = new Spreadsheet();

        $usedSheetNames = [];
        $sheetCreated = false;

        // ==========================================
        // 6. FUNGSI MEMBUAT NAMA SHEET
        // ==========================================
        $makeSheetName = function ($name) use (&$usedSheetNames) {

            $name = preg_replace(
                '/[\\\\\/\?\*\:\[\]]/',
                '-',
                (string) $name
            );

            $name = trim($name, "' ");

            if ($name === '') {
                $name = 'Sheet';
            }

            $base = mb_substr($name, 0, 31);
            $sheetName = $base;
            $counter = 1;

            while (in_array(
                mb_strtolower($sheetName),
                $usedSheetNames,
                true
            )) {
                $suffix = '_' . $counter++;

                $sheetName = mb_substr(
                    $base,
                    0,
                    31 - mb_strlen($suffix)
                ) . $suffix;
            }

            $usedSheetNames[] = mb_strtolower($sheetName);

            return $sheetName;
        };

        // ==========================================
        // 7. FUNGSI WARNA TAB BERDASARKAN BANK
        // ==========================================
        $getBankTabColor = function ($bankName) {

            $bankKey = strtoupper(trim((string) $bankName));

            // Warna khusus bank
            $bankColors = [
                'BCA'           => '0070C0',
                'BRI'           => '008000',
                'MANDIRI'       => 'FFC000',
                'BNI'           => 'F79646',
                'BSI'           => '7030A0',
                'CIMB NIAGA'    => 'C00000',
                'CIMB'          => 'C00000',
                'INA PERDANA'   => '00B0F0',
                'BTN'           => '00B050',
                'MDR'           => 'FFC000',
                'TITIPAN'       => '808080',
            ];

            if (isset($bankColors[$bankKey])) {
                return $bankColors[$bankKey];
            }

            // Warna alternatif untuk bank lainnya
            $palette = [
                '4472C4',
                '70AD47',
                'ED7D31',
                'A5A5A5',
                '5B9BD5',
                '8064A2',
                'C55A11',
                '548235',
                'BF9000',
                '31869B',
                '7030A0',
                'C00000',
            ];

            $hash = sprintf(
                '%u',
                crc32($bankKey)
            );

            $index = (int) ($hash % count($palette));

            return $palette[$index];
        };

        // ==========================================
        // 8. PROSES SETIAP GROUP
        // ==========================================
        foreach ($groups as $index => $group) {

            // ======================================
            // TENTUKAN QUERY BANK DAN NAMA SHEET
            // ======================================
            if ($kategori === 'Reguler') {

                // Ambil nama bank berdasarkan rekening
                $bankData = DB::table('bank')
                    ->where('cabang', $cabang)
                    ->where('site', 'REG')
                    ->where('no_rek', $group)
                    ->select('bank', 'no_rek', 'site')
                    ->first();

                if (!$bankData) {
                    continue;
                }

                $bankName = $bankData->bank ?? 'BANK';

                // Format BANK-NO_REK
                $sheetName = $bankName . '-' . $group;

                // Warna berdasarkan nama bank
                $tabColor = $getBankTabColor($bankName);

            } else {

                // Franchise:
                // Nama sheet berdasarkan jns_bank
                $sheetName = $group;

                // Warna berdasarkan jns_bank
                $tabColor = $getBankTabColor($group);
            }

            // ======================================
            // 9. QUERY DATA DETAIL
            // ======================================
            $query = DB::table($detailTable . ' as md')
                ->join('bank as b', function ($join) use ($cabang) {
                    $join->on('b.no_rek', '=', 'md.no_rek')
                        ->where('b.cabang', '=', $cabang);
                })
                ->whereBetween(
                    'md.' . $dateColumn,
                    [$start, $end]
                );

            if ($kategori === 'Reguler') {

                // REGULER:
                // Satu rekening per sheet
                $query->where('b.site', 'REG')
                    ->where('md.no_rek', $group);

            } else {

                // FRANCHISE:
                // Satu jns_bank per sheet
                // Site selain REG
                $query->where('b.site', '<>', 'REG')
                    ->where('b.jns_bank', $group);
            }

            // ======================================
            // 10. SELECT KOLOM
            // ======================================
            if ($kategori === 'Reguler') {

                // Struktur REGULER
                $query->select([
                    'md.id',
                    'b.cabang as cabang',
                    'md.no_rek',
                    'md.tgl',
                    'md.remark',
                    'md.remark1',
                    'md.db',
                    'md.cr',
                ]);

                $headers = [
                    'CABANG',
                    'NO REK',
                    'TGL',
                    'KETERANGAN',
                    'KETERANGAN',
                    'DEBET',
                    'KREDIT',
                ];

                $dateColumnIndex = 2;

            } else {

                // Struktur FRANCHISE
                // SITE diambil dari tabel bank
                $query->select([
                    'md.id',
                    'b.cabang as cabang',
                    'md.no_rek',
                    'b.site as site',
                    'md.tgl',
                    'md.remark',
                    'md.remark1',
                    'md.db',
                    'md.cr',
                ]);

                $headers = [
                    'CABANG',
                    'NO REK',
                    'SITE',
                    'TGL',
                    'KETERANGAN',
                    'KETERANGAN',
                    'DEBET',
                    'KREDIT',
                ];

                $dateColumnIndex = 3;
            }

            $query->orderBy('md.id');

            // ======================================
            // 11. PASTIKAN DATA TERSEDIA
            // ======================================
            $firstRecord = (clone $query)->first();

            if (!$firstRecord) {
                continue;
            }

            // ======================================
            // 12. BUAT SHEET
            // ======================================
            if (!$sheetCreated) {

                $sheet = $spreadsheet->getActiveSheet();

                $sheetCreated = true;

            } else {

                $sheet = $spreadsheet->createSheet();
            }

            $sheet->setTitle(
                $makeSheetName($sheetName)
            );

            // ======================================
            // 13. WARNA TAB SHEET
            // ======================================
            $sheet->getTabColor()->setRGB($tabColor);

            // ======================================
            // 14. TULIS HEADER
            // ======================================
            foreach ($headers as $colIndex => $header) {

                $cell = Coordinate::stringFromColumnIndex(
                    $colIndex + 1
                ) . '1';

                $sheet->setCellValue($cell, $header);
            }

            $lastColumn = Coordinate::stringFromColumnIndex(
                count($headers)
            );

            // Format header
            $sheet->getStyle("A1:{$lastColumn}1")
                ->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => [
                            'rgb' => 'FFFFFF'
                        ],
                        'size' => 11,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => $kategori === 'Reguler'
                                ? '2563EB'
                                : '7C3AED',
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical' => 'center',
                    ],
                ]);

            $sheet->getRowDimension(1)->setRowHeight(25);

            // Freeze header
            $sheet->freezePane('A2');

            // ======================================
            // 15. TULIS DATA BERTAHAP
            // ======================================
            $rowNumber = 2;

            $query->chunkById(
                1000,
                function ($records) use (
                    $sheet,
                    $kategori,
                    $dateColumnIndex,
                    &$rowNumber
                ) {

                    foreach ($records as $record) {

                        // ==================================
                        // CABANG
                        // ==================================
                        $sheet->setCellValueExplicit(
                            'A' . $rowNumber,
                            (string) $record->cabang,
                            DataType::TYPE_STRING
                        );

                        // ==================================
                        // NO REK
                        // ==================================
                        $sheet->setCellValueExplicit(
                            'B' . $rowNumber,
                            (string) $record->no_rek,
                            DataType::TYPE_STRING
                        );

                        if ($kategori === 'Reguler') {

                            // ==================================
                            // REGULER
                            // ==================================

                            // TGL
                            $dateColumn = 'C';

                            if ($record->tgl !== null) {

                                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
                                    new \DateTime($record->tgl)
                                );

                                $sheet->setCellValue(
                                    $dateColumn . $rowNumber,
                                    $date
                                );
                            }

                            // REMARK
                            $sheet->setCellValueExplicit(
                                'D' . $rowNumber,
                                (string) ($record->remark ?? ''),
                                DataType::TYPE_STRING
                            );

                            // REMARK1
                            $sheet->setCellValueExplicit(
                                'E' . $rowNumber,
                                (string) ($record->remark1 ?? ''),
                                DataType::TYPE_STRING
                            );

                            // DB
                            $sheet->setCellValue(
                                'F' . $rowNumber,
                                $record->db !== null
                                    ? (float) $record->db
                                    : null
                            );

                            // CR
                            $sheet->setCellValue(
                                'G' . $rowNumber,
                                $record->cr !== null
                                    ? (float) $record->cr
                                    : null
                            );

                        } else {

                            // ==================================
                            // FRANCHISE
                            // ==================================

                            // SITE dari tabel bank
                            $sheet->setCellValueExplicit(
                                'C' . $rowNumber,
                                (string) $record->site,
                                DataType::TYPE_STRING
                            );

                            // TGL
                            if ($record->tgl !== null) {

                                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
                                    new \DateTime($record->tgl)
                                );

                                $sheet->setCellValue(
                                    'D' . $rowNumber,
                                    $date
                                );
                            }

                            // REMARK
                            $sheet->setCellValueExplicit(
                                'E' . $rowNumber,
                                (string) ($record->remark ?? ''),
                                DataType::TYPE_STRING
                            );

                            // REMARK1
                            $sheet->setCellValueExplicit(
                                'F' . $rowNumber,
                                (string) ($record->remark1 ?? ''),
                                DataType::TYPE_STRING
                            );

                            // DB
                            $sheet->setCellValue(
                                'G' . $rowNumber,
                                $record->db !== null
                                    ? (float) $record->db
                                    : null
                            );

                            // CR
                            $sheet->setCellValue(
                                'H' . $rowNumber,
                                $record->cr !== null
                                    ? (float) $record->cr
                                    : null
                            );
                        }

                        $rowNumber++;
                    }
                },
                'md.id',
                'id'
            );

            // ======================================
            // 16. FORMAT KOLOM
            // ======================================
            $lastRow = max(1, $rowNumber - 1);

            if ($kategori === 'Reguler') {

                // Lebar kolom REGULER
                $widths = [
                    'A' => 20,
                    'B' => 22,
                    'C' => 15,
                    'D' => 40,
                    'E' => 40,
                    'F' => 18,
                    'G' => 18,
                ];

            } else {

                // Lebar kolom FRANCHISE
                $widths = [
                    'A' => 20,
                    'B' => 22,
                    'C' => 15,
                    'D' => 15,
                    'E' => 40,
                    'F' => 40,
                    'G' => 18,
                    'H' => 18,
                ];
            }

            foreach ($widths as $column => $width) {
                $sheet->getColumnDimension($column)
                    ->setWidth($width);
            }

            // ======================================
            // 17. FORMAT TANGGAL
            // ======================================
            $dateLetter = Coordinate::stringFromColumnIndex(
                $dateColumnIndex + 1
            );

            if ($lastRow >= 2) {

                $sheet->getStyle(
                    "{$dateLetter}2:{$dateLetter}{$lastRow}"
                )
                    ->getNumberFormat()
                    ->setFormatCode('dd-mmm-yy');

                $sheet->getStyle(
                    "{$dateLetter}2:{$dateLetter}{$lastRow}"
                )
                    ->getAlignment()
                    ->setHorizontal('center');
            }

            // ======================================
            // 18. FORMAT NOMINAL
            // ======================================
            $nominalStart = $kategori === 'Reguler' ? 'F' : 'G';
            $nominalEnd   = $kategori === 'Reguler' ? 'G' : 'H';

            if ($lastRow >= 2) {

                $sheet->getStyle(
                    "{$nominalStart}2:{$nominalEnd}{$lastRow}"
                )
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00;[Red]-#,##0.00');

                $sheet->getStyle(
                    "{$nominalStart}2:{$nominalEnd}{$lastRow}"
                )
                    ->getAlignment()
                    ->setHorizontal('right');
            }

            // ======================================
            // 19. BORDER SELURUH CELL
            // ======================================
            $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
                ->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => [
                                'rgb' => '808080'
                            ],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => 'center',
                    ],
                ]);

            // ======================================
            // 20. AUTO FILTER
            // ======================================
            $sheet->setAutoFilter(
                "A1:{$lastColumn}{$lastRow}"
            );
        }

        // ==========================================
        // 21. PERIKSA DATA
        // ==========================================
        if (!$sheetCreated) {

            $spreadsheet->disconnectWorksheets();

            return response()->json([
                'success' => false,
                'message' => 'Tidak ditemukan transaksi mutasi pada periode tersebut.'
            ], 404);
        }

        // ==========================================
        // 22. NAMA FILE
        // ==========================================
        $filename = 'Mutasi_' .
            $kategori . '_' .
            $cabang . '_' .
            $periode . '.xlsx';

        // ==========================================
        // 23. DOWNLOAD EXCEL
        // ==========================================
        return response()->streamDownload(
            function () use ($spreadsheet) {

                $writer = new Xlsx($spreadsheet);

                $writer->save('php://output');

                $spreadsheet->disconnectWorksheets();
            },
            $filename,
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }


}
