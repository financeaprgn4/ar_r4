<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\JournalRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Statement;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
        $periode  = $request->periode; // contoh format: 2024-05
        $kategori = $request->kategori ?? 'Reguler'; // default Reguler jika tidak dikirim

        // Ambil periode dari tabel periode
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
        $table = $kategori === 'Franchise' ? 'mutasi_frc' : 'mutasi';

        $data = DB::table($table)
            ->where('cabang', $cabang)
            ->whereBetween('tgl_awal', [$start, $end])
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
            DETAIL
            ====================================================
            */

            DB::table($tableDetail)
                ->where('src', $file)
                ->delete();

            /*
            ====================================================
            REKAP
            ====================================================
            */

            DB::table($tableRekap)
                ->whereIn('no_rek', $rekening)
                ->whereBetween('tgl', [
                    $mutasi->tgl_awal,
                    $mutasi->tgl_akhir
                ])
                ->delete();

            DB::table($tableUnrec)
                ->whereIn('no_rek', $rekening)
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
        $folder = "C:/xampp/htdocs/AP_R4/File/mutasi/" . $cabang;

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
            
                    // INA PERDANA
                    $handle = fopen($file->getRealPath(), 'r');

                    if (!$handle) {
                        $errors[] = [
                            "file" => $filename,
                            "reason" => "File tidak dapat dibuka"
                        ];
                        continue;
                    }

                    // Lewati header
                    fgetcsv($handle);

                    $no_rek = null;
                    $invalid = false;

                    while (($row = fgetcsv($handle)) !== false) {

                        if (!isset($row[2])) {
                            continue;
                        }

                        $currentNoRek = trim($row[2]);

                        // Abaikan baris kosong
                        if ($currentNoRek === '') {
                            continue;
                        }

                        if ($no_rek === null) {

                            $no_rek = $currentNoRek;

                        } elseif ($currentNoRek !== $no_rek) {

                            $invalid = true;
                            break;

                        }
                    }

                    fclose($handle);

                    if ($invalid) {

                        $errors[] = [
                            "file"   => $filename,
                            "reason" => "Nomor rekening pada file tidak konsisten."
                        ];

                        continue;
                    }

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
            
            if (DB::table($tableMutasi)->where('file', $filename)->exists()) {
                $errors[] = ["file" => $filename, "reason" => "File sudah pernah diimport"];
                continue;
            }

            try {
                /*
                ==========================================================
                LOAD FILE
                ==========================================================
                */

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

                            $dataRows[] = $rows[$i];
                        }

                        $rekening = [];
                    
                        $rekening[$no_rek] = [
                            'tgl_awal' => $tgl_awal,
                            'tgl_akhir' => $tgl_akhir,
                            'rows' => $dataRows,
                            'no_transaksi'=>$noTransaksiDetected
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
                            ->toArray();

                        $rekeningValid = array_flip($rekeningValid);
                            
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

                            // Skip jika rekening bukan Franchise
                            if (!isset($rekeningValid[$noRek])) {
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
                            ->toArray();
                    
                        $rekeningValid = array_flip($rekeningValid);
                    
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
                    
                                $currentRek = $match[1];
                    
                                if (!isset($rekeningValid[$currentRek])) {
                    
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
                            ->toArray();

                        $rekeningValid = array_flip($rekeningValid);

                        for ($i=1;$i<count($rows);$i++) {

                            $row = $rows[$i];

                            if (count($row) < 9) {
                                continue;
                            }

                            $noRek = trim($row[0]);

                            if ($noRek == '') {
                                continue;
                            }

                            if (!isset($rekeningValid[$noRek])) {
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
                            ->toArray();

                        $rekeningValid = array_flip($rekeningValid);

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

                            if (!isset($rekeningValid[$noRek])) {
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
                    if ($site == "REG") {
                        /*
                        ==========================================================
                        PARSER BSI REG
                        ==========================================================
                        */

                        if (count($rows) < 2) {
                            throw new \Exception("Format file BSI tidak valid atau kosong");
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

                            $tgl = null;

                            foreach ([
                                'Y-m-d H:i:s',
                                'Y-m-d',
                                'd/m/Y H:i:s',
                                'd/m/Y'
                            ] as $fmt) {

                                try {

                                    $tgl = Carbon::createFromFormat(
                                        $fmt,
                                        trim($row[0])
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
                            throw new \Exception("Tidak ditemukan transaksi BSI");
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
                        PARSER BSI FRC
                        ==========================================================
                        */

                        if (count($rows) < 2) {
                            throw new \Exception("Format file BSI Franchise tidak valid");
                        }

                        $rekening = [];

                        /*
                        ==========================================================
                        VALIDASI NOMOR REKENING
                        ==========================================================
                        */

                        $noRekFile = null;

                        if (isset($rows[1][1])) {

                            preg_match('/(\d{8,20})/', $rows[1][1], $match);

                            if (!empty($match[1])) {
                                $noRekFile = $match[1];
                            }

                        }

                        if ($noRekFile && $noRekFile != $no_rek) {

                            throw new \Exception(
                                "Nomor rekening pada file ({$noRekFile}) tidak sesuai dengan rekening master ({$no_rek})"
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

                        foreach ($rows as $i => $row) {

                            if ($i == 0) {
                                continue;
                            }

                            if (count($row) < 8) {
                                continue;
                            }

                            try {

                                $tgl = Carbon::createFromFormat(
                                    'n/j/Y H:i',
                                    trim($row[0])
                                )->format('Y-m-d');

                            } catch (\Exception $e) {

                                try {

                                    $tgl = Carbon::createFromFormat(
                                        'm/d/Y H:i',
                                        trim($row[0])
                                    )->format('Y-m-d');

                                } catch (\Exception $e) {

                                    continue;

                                }

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

                        $rekening[$no_rek] = [

                            'tgl_awal'      => $tglAwal,
                            'tgl_akhir'     => $tglAkhir,
                            'rows'          => $dataRows,
                            'no_transaksi'  => empty($dataRows)

                        ];
                    }
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
                            "tgl_akhir" => $data['tgl_akhir']
                
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
                ==========================================================
                UPDATE RULE
                ==========================================================
                */

                $updateRules = [

                    /*
                    ======================================================
                    BCA REG
                    ======================================================
                    */

                    "BCA_REG" => [

                        [
                            "field" => "db",
                            "like" => [
                                "%TARIKAN%",
                                "%TRSF E-BANKING DB%",
                                "FLAZZ BCA TOPUP%",
                                "FLAZZZ BCA ADM%"
                            ],
                            "update" => [
                                "reconciled" => "Y"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "%SENT DR MDN_BCA%"
                            ],
                            "update" => [
                                "reconciled" => "",
                                "dept" => "HO"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "%ND - LAINNYA%"
                            ],
                            "update" => [
                                "reconciled" => "",
                                "dept" => "CABANG"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "FLAZZ BCA TOPUP%",
                                "FLAZZ BCA ADM%",
                                "%DB OTOMATIS KOR FLAZZ%"
                            ],
                            "update" => [
                                "dept" => "VIR"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "%DB OTOMATIS KBCINDOMAR SMEMFTS%"
                            ],
                            "update" => [
                                "dept" => "HRD"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "%TRSF UNTUK ATS%",
                                "%TRSF E-BANKING%INDOMARCO PRISMATA%"
                            ],
                            "update" => [
                                "dept" => "HO"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "%TRSF UNTUK ATS KE%"
                            ],
                            "update" => [
                                "dept" => "ATS"
                            ]
                        ],

                        [
                            "field" => "cr",
                            "like" => [
                                "%KARTU KREDIT MID%",
                                "%KR OTOMATIS KOR DEBIT%",
                                "%KR OTOMATIS MID%DDR%",
                                "%KR OTOMATIS MID%REDEEM%",
                                "%KR OTOMATIS TANGGAL%DDR%",
                                "%KOR FLAZZ BCA%",
                                "%KR OTOMATIS TANGGAL%FTB%"
                            ],
                            "update" => [
                                "dept" => "VIR"
                            ]
                        ],

                        [
                            "field" => "cr",
                            "like" => [
                                "%TRSF DARI ATS DARI%"
                            ],
                            "update" => [
                                "dept" => "ATS"
                            ]
                        ]

                    ],

                    /*
                    ======================================================
                    BCA FRC
                    ======================================================
                    */

                    "BCA_FRC" => null, // sementara sama dengan REG nanti bisa dipisah jika berbeda

                    /*
                    ======================================================
                    INA
                    ======================================================
                    */

                    "INA PERDANA_REG" => [

                        [
                            "field" => "db",
                            "like" => [
                                "%PINBUK%",
                                "%1000238968%"
                            ],
                            "update" => [
                                "dept" => "HO"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "%SEL%KRG%"
                            ],
                            "update" => [
                                "dept" => "CABANG"
                            ]
                        ]

                    ],
                    
                    /*
                    ======================================================
                    MANDIRI REG
                    ======================================================
                    */
                    "MANDIRI_REG" => [
                        /*
                        ==========================================================
                        DEBIT
                        ==========================================================
                        */

                        [
                            "field" => "db",
                            "like" => [
                                "%SENT DR MDN_MDR01 KE HO%"
                            ],
                            "update" => [
                                "dept" => "HO"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "%MCM Inhouse%",
                                "%MCM Outw%"
                            ],
                            "update" => [
                                "reconciled" => "Y"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "%DbSalaryKtComp%"
                            ],
                            "update" => [
                                "dept" => "HRD"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "%AD Tagihan%"
                            ],
                            "update" => [
                                "dept" => "GA"
                            ]
                        ],

                        /*
                        ==========================================================
                        CREDIT
                        ==========================================================
                        */

                        [
                            "field" => "cr",
                            "like" => [
                                "%MCM InhouseTrf%DARI INDOMARCO PRISMATAMA%"
                            ],
                            "update" => [
                                "dept" => "ATS"
                            ]
                        ]

                    ],

                    /*
                    ======================================================
                    BRI REG
                    ======================================================
                    */
                    "BRI_REG" => [

                        [
                            "field" => "db",
                            "like" => [
                                "%TOPUP TUNAI%"
                            ],
                            "update" => [
                                "dept" => "VIR"
                            ]
                        ],

                        [
                            "field" => "db",
                            "like" => [
                                "%CMSPOOL%"
                            ],
                            "update" => [
                                "dept" => "HO"
                            ]
                        ],

                        [
                            "field" => "cr",
                            "like" => [
                                "%CMSPOOL%"
                            ],
                            "update" => [
                                "dept" => "ATS"
                            ]
                        ],

                        [
                            "field" => "cr",
                            "like" => [
                                "TT1%",
                                "CC %",
                                "SETD%",
                                "OnUs%",
                                "OffUs%",
                                "EMONEY%",
                                "FTT%"
                            ],
                            "update" => [
                                "dept" => "VIR",
                                "reconciled" => "Y"
                            ]
                        ]

                    ],
                ];

                /*
                ==========================================================
                UPDATE DEPT / RECONCILED
                ==========================================================
                */

                $key = "{$bank}_{$site}";

                if (!empty($updateRules[$key])) {

                    foreach ($updateRules[$key] as $rule) {

                        $query = DB::table($tableDetail)
                            ->where("src", $filename)
                            ->where($rule["field"], "!=", 0);

                        $query->where(function ($q) use ($rule) {

                            foreach ($rule["like"] as $i => $like) {

                                if ($i == 0) {

                                    $q->where("remark", "LIKE", $like);

                                } else {

                                    $q->orWhere("remark", "LIKE", $like);

                                }

                            }

                        });

                        $query->update($rule["update"]);

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
                        ->orWhere($prefix . 'reconciled', '')
                        ->orWhere($prefix . 'reconciled', '0');

                })

                ->where(function ($q) use ($prefix) {

                    $q->whereNull($prefix . 'dept')
                        ->orWhere($prefix . 'dept', '')
                        ->orWhere($prefix . 'dept', '0');

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
            'cabang'  => 'required|string|max:15'
        ]);

        $cabang = trim($request->cabang);

        $errors = [];

        $insertData = [];

        $updateData = [];

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Ambil seluruh Receipt Method yang valid untuk cabang
            |--------------------------------------------------------------------------
            */

            $validReceiptMethod = DB::table('bank')
                ->where('cabang', $cabang)
                ->distinct()
                ->pluck('receipt_method')
                ->map(fn($v) => trim($v))
                ->flip();

            /*
            |--------------------------------------------------------------------------
            | Ambil seluruh receipt yang pernah diimport
            |--------------------------------------------------------------------------
            */

            $existingReceipt = DB::table('receipt')
                ->select(
                    'id',
                    'receipt_number',
                    'receipt_status',
                    'receipt_state'
                )
                ->get()
                ->keyBy('receipt_number');

            /*
            |--------------------------------------------------------------------------
            | Loop File
            |--------------------------------------------------------------------------
            */

            foreach ($request->file('files') as $file) {

                $handle = fopen($file->getRealPath(), "r");

                if (!$handle) {

                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'issues' => [
                            'File tidak dapat dibaca.'
                        ]
                    ];

                    continue;

                }

                /*
                |--------------------------------------------------------------------------
                | Header CSV
                |--------------------------------------------------------------------------
                */

                $header = fgetcsv($handle, 0, "|");

                if (!$header) {

                    fclose($handle);

                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'issues' => [
                            'Header CSV tidak ditemukan.'
                        ]
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
                    'Paid By'

                ];

                if ($header != $expectedHeader) {

                    fclose($handle);

                    $errors[] = [

                        'file' => $file->getClientOriginalName(),

                        'issues' => [
                            'Format Header CSV tidak sesuai.'
                        ]

                    ];

                    continue;

                }

                /*
                |--------------------------------------------------------------------------
                | Loop Isi CSV
                |--------------------------------------------------------------------------
                */

                $line = 1;

                while (($row = fgetcsv($handle, 0, "|")) !== false) {

                    $line++;

                    if (count($row) != 12) {

                        $errors[] = [
                            'file' => $file->getClientOriginalName(),
                            'issues' => [
                                "Baris {$line} jumlah kolom tidak sesuai."
                            ]
                        ];

                        continue;
                    }

                    $receiptMethod          = trim($row[0]);
                    $remittanceBankAccount = trim($row[1]);
                    $receiptNumber         = trim($row[2]);
                    $receiptAmount         = str_replace(",", "", trim($row[3]));
                    $receiptDate           = trim($row[4]);
                    $glDate                = trim($row[5]);
                    $receiptType           = trim($row[6]);
                    $receiptStatus         = trim($row[7]);
                    $receiptState          = trim($row[8]);
                    $comments              = trim($row[9]);
                    $activity              = trim($row[10]);
                    $paidBy                = trim($row[11]);

                    /*
                    |--------------------------------------------------------------------------
                    | Validasi Receipt Method
                    |--------------------------------------------------------------------------
                    */

                    if (!isset($validReceiptMethod[$receiptMethod])) {

                        $errors[] = [

                            'file' => $file->getClientOriginalName(),

                            'issues' => [

                                "Baris {$line} : Receipt Method '{$receiptMethod}' tidak terdaftar pada cabang {$cabang}."

                            ]

                        ];

                        continue;

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Format Tanggal
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

                    } catch (\Exception $e) {

                        $errors[] = [

                            'file' => $file->getClientOriginalName(),

                            'issues' => [

                                "Baris {$line} format tanggal salah."

                            ]

                        ];

                        continue;

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Cek Receipt Number
                    |--------------------------------------------------------------------------
                    */

                    if ($existingReceipt->has($receiptNumber)) {

                        $old = $existingReceipt[$receiptNumber];
                    
                        /*
                        |--------------------------------------------------------------------------
                        | Jika status di database sudah Reversed, abaikan
                        |--------------------------------------------------------------------------
                        */
                    
                        if (strcasecmp($old->receipt_status, 'Reversed') === 0) {
                    
                            $skipped++;
                            continue;
                    
                        }
                    
                        /*
                        |--------------------------------------------------------------------------
                        | Cek apakah Status / State berubah
                        |--------------------------------------------------------------------------
                        */
                    
                        $statusChanged = strcasecmp($old->receipt_status, $receiptStatus) !== 0;
                        $stateChanged  = strcasecmp($old->receipt_state, $receiptState) !== 0;
                    
                        /*
                        |--------------------------------------------------------------------------
                        | Tidak ada perubahan
                        |--------------------------------------------------------------------------
                        */
                    
                        if (!$statusChanged && !$stateChanged) {
                    
                            $skipped++;
                            continue;
                    
                        }
                    
                        /*
                        |--------------------------------------------------------------------------
                        | Siapkan Update
                        |--------------------------------------------------------------------------
                        */
                    
                        $updateData[] = [
                    
                            'id'             => $old->id,
                            'receipt_status' => $receiptStatus,
                            'receipt_state'  => $receiptState,
                    
                            // trx_id dikosongkan hanya jika status baru Reversed
                            'clear_trx'      => (strcasecmp($receiptStatus, 'Reversed') === 0)
                    
                        ];
                    
                        $updated++;
                    
                        continue;
                    
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Belum Ada -> Siapkan Insert
                    |--------------------------------------------------------------------------
                    */

                    $insertData[] = [

                        'receipt_method'            => $receiptMethod,
                        'remittance_bank_account'  => $remittanceBankAccount,
                        'receipt_number'           => $receiptNumber,
                        'receipt_amount'           => $receiptAmount,
                        'receipt_date'             => $receiptDate,
                        'gl_date'                  => $glDate,
                        'receipt_type'             => $receiptType,
                        'receipt_status'           => $receiptStatus,
                        'receipt_state'            => $receiptState,
                        'comments'                 => $comments,
                        'activity'                 => $activity,
                        'paid_by'                  => $paidBy,
                        'trx_id'                  => ''

                    ];

                    $existingReceipt->put($receiptNumber, (object)[
                        'id' => 0,
                        'receipt_status' => $receiptStatus,
                        'receipt_state'=>$receiptState
                    ]);

                    $inserted++;

                }

                fclose($handle);

            }

            /*
            |--------------------------------------------------------------------------
            | Bulk Insert
            |--------------------------------------------------------------------------
            */

            if (!empty($insertData)) {

                foreach (array_chunk($insertData, 1000) as $chunk) {

                    DB::table('receipt')->insert($chunk);

                }

            }

            /*
            |--------------------------------------------------------------------------
            | Bulk Update Receipt
            |--------------------------------------------------------------------------
            */

            if (!empty($updateData)) {

                foreach ($updateData as $row) {

                    $update = [
                
                        'receipt_status' => $row['receipt_status'],
                        'receipt_state'  => $row['receipt_state']
                
                    ];
                
                    if ($row['clear_trx']) {
                
                        $update['trx_id'] = '';
                
                    }
                
                    DB::table('receipt')
                        ->where('id',$row['id'])
                        ->update($update);
                
                }

            }

            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            DB::commit();

            return response()->json([

                'success' => true,

                'message' =>
                    "Import Receipt selesai.<br>
                    Insert : {$inserted} data<br>
                    Update : {$updated} data<br>
                    Skip : {$skipped} data",

                'inserted' => $inserted,

                'updated' => $updated,

                'skipped' => $skipped,

                'errors' => $errors

            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([

                'success' => false,

                'message' => $e->getMessage()

            ],500);

        }
    }
}
