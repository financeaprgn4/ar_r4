<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

class ImportSaranaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $paths;
    protected string $taskId;
    protected string $cabang;

    /**
     * Create a new job instance.
     *
     * @param array $paths  Full file paths in storage
     * @param string $taskId
     * @param string $cabang
     */
    public function __construct(array $paths, string $taskId, string $cabang)
    {
        $this->paths  = $paths;
        $this->taskId = $taskId;
        $this->cabang = $cabang;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $totalFiles = count($this->paths);
            $fileIndex = 0;

            foreach ($this->paths as $filePath) {
                $fileIndex++;

                // Load spreadsheet (PhpSpreadsheet)
                $spreadsheet = IOFactory::load($filePath);

                // Iterate worksheets like di kode lama
                foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                    $totalRow = (int) $sheet->getHighestRow();
                    // total tasks = rows to process (15 .. totalRow-1)
                    $startRow = 15;
                    $endRow = max($startRow, $totalRow - 1);
                    $totalTasks = max(1, ($endRow - $startRow + 1));
                    $processed = 0;

                    for ($row = $startRow; $row <= $endRow; $row++) {
                        $processed++;

                        // ambil cell sesuai kode lama
                        $rab              = $sheet->getCell("B{$row}")->getValue();
                        $tgl_rab_val      = $sheet->getCell("C{$row}")->getValue();
                        $plu              = $sheet->getCell("D{$row}")->getValue();
                        $barang           = $sheet->getCell("E{$row}")->getValue();
                        $qty_barang       = $sheet->getCell("G{$row}")->getValue();
                        $pp               = $sheet->getCell("H{$row}")->getValue();
                        $tgl_pp_val       = $sheet->getCell("I{$row}")->getValue();
                        $qty_pp           = $sheet->getCell("K{$row}")->getValue();
                        $sp               = $sheet->getCell("L{$row}")->getValue();
                        $tgl_sp_val       = $sheet->getCell("M{$row}")->getValue();
                        $qty_sp           = $sheet->getCell("N{$row}")->getValue();
                        $exp_sp_val       = $sheet->getCell("O{$row}")->getValue();
                        $sp_awal          = $sheet->getCell("P{$row}")->getValue();
                        $tgl_sp_awal_val  = $sheet->getCell("Q{$row}")->getValue();
                        $exp_sp_awal_val  = $sheet->getCell("S{$row}")->getValue();
                        $btb              = $sheet->getCell("V{$row}")->getValue();
                        $tgl_btb_val      = $sheet->getCell("Y{$row}")->getValue();
                        $qty_btb          = $sheet->getCell("Z{$row}")->getValue();

                        // konversi tanggal sesuai pola lama (angka excel -> Y-m-d)
                        $tgl_rab      = $this->convertExcelDate($tgl_rab_val);
                        $tgl_pp       = $this->convertExcelDate($tgl_pp_val);
                        $tgl_sp       = $this->convertExcelDate($tgl_sp_val);
                        $exp_sp       = $this->convertExcelDate($exp_sp_val);
                        $tgl_sp_awal  = $this->convertExcelDateAllowEmpty($tgl_sp_awal_val);
                        $exp_sp_awal  = $this->convertExcelDateAllowEmpty($exp_sp_awal_val);
                        $tgl_btb      = $this->convertExcelDateAllowEmpty($tgl_btb_val);

                        // Insert ke pp_int (sama struktur seperti kode lama)
                        DB::table('pp_int')->insert([
                            'cabang'       => $this->cabang,
                            'rab'          => $rab,
                            'tgl_rab'      => $tgl_rab,
                            'plu'          => $plu,
                            'barang'       => $barang,
                            'qty_barang'   => $qty_barang,
                            'pp'           => $pp,
                            'tgl_pp'       => $tgl_pp,
                            'qty_pp'       => $qty_pp,
                            'sp'           => $sp,
                            'tgl_sp'       => $tgl_sp,
                            'qty_sp'       => $qty_sp,
                            'exp_sp'       => $exp_sp,
                            'sp_awal'      => $sp_awal,
                            'tgl_sp_awal'  => $tgl_sp_awal,
                            'exp_sp_awal'  => $exp_sp_awal,
                            'btb'          => $btb,
                            'tgl_btb'      => $tgl_btb,
                            'qty_btb'      => $qty_btb,
                        ]);

                        // Setelah insert, lakukan update/insert ke table pp (mirip logic lama)
                        $exists = DB::table('pp')->where('rab', $rab)->where('plu', $plu)->first();

                        if ($exists) {
                            DB::table('pp')
                                ->where('rab', $rab)
                                ->where('plu', $plu)
                                ->update([
                                    'sp_awal' => $sp_awal,
                                    'tgl_sp_awal' => $tgl_sp_awal,
                                    'exp_sp_awal' => $exp_sp_awal,
                                    'btb' => $btb,
                                    'tgl_btb' => $tgl_btb,
                                    'qty_btb' => $qty_btb,
                                ]);
                        } else {
                            // insert baru (sama fields seperti kode lama)
                            DB::table('pp')->insert([
                                'cabang' => $this->cabang,
                                'rab' => $rab,
                                'tgl_rab' => $tgl_rab,
                                'plu' => $plu,
                                'barang' => $barang,
                                'qty_barang' => $qty_barang,
                                'pp' => $pp,
                                'tgl_pp' => $tgl_pp,
                                'qty_pp' => $qty_pp,
                                'sp' => $sp,
                                'tgl_sp' => $tgl_sp,
                                'qty_sp' => $qty_sp,
                                'exp_sp' => $exp_sp,
                                'sp_awal' => $sp_awal,
                                'tgl_sp_awal' => $tgl_sp_awal,
                                'exp_sp_awal' => $exp_sp_awal,
                                'btb' => $btb,
                                'tgl_btb' => $tgl_btb,
                                'qty_btb' => $qty_btb,
                            ]);
                        }

                        // update progress - hitung persentase per sheet
                        $percent = intval(($processed / $totalTasks) * 100);
                        Cache::put("import_progress_{$this->taskId}", [
                            'progress' => $percent,
                            'message'  => "Memproses file {$fileIndex}/{$totalFiles} - baris {$row} dari {$totalRow}"
                        ], now()->addMinutes(60));
                    } // end for rows

                    // hapus pp_int untuk cabang sesuai behavior lama (kosongkan per file)
                    DB::table('pp_int')->where('cabang', $this->cabang)->delete();
                } // end foreach sheet
            } // end foreach files

            // selesai — set progress 100
            Cache::put("import_progress_{$this->taskId}", [
                'progress' => 100,
                'message'  => 'Import selesai'
            ], now()->addMinutes(60));
        } catch (Exception $e) {
            // jika error set progress negatif dan log
            Cache::put("import_progress_{$this->taskId}", [
                'progress' => -1,
                'message'  => 'Error: ' . $e->getMessage()
            ], now()->addMinutes(60));

            Log::error('ImportSaranaJob Error: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
        }
    }

    /**
     * Konversi tanggal excel (numerik) sesuai metode lama (nilai - 2)
     */
    private function convertExcelDate($value)
    {
        if ($value === null || $value === '') return null;

        // jika numeric, lakukan perhitungan sama seperti kode lama:
        // $t = $value - 2; strtotime('1900-01-01 +'.$t.' days')
        if (is_numeric($value)) {
            $t = floor($value) - 2;
            $ts = strtotime('1900-01-01 +'.$t.' days');
            return date('Y-m-d', $ts);
        }

        // jika sudah string/datetime, return apa adanya
        return $value;
    }

    /**
     * Convert tanggal excel, namun boleh kosong (kembalikan "" jika kosong)
     */
    private function convertExcelDateAllowEmpty($value)
    {
        if ($value === null || $value === '') return "";
        if (is_numeric($value)) {
            $t = floor($value) - 2;
            $ts = strtotime('1900-01-01 +'.$t.' days');
            return date('Y-m-d', $ts);
        }
        return $value;
    }
}
