<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\ReconciliationService;
use App\Services\RegularReconciliationService;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ReconciliationController extends Controller
{
    protected ReconciliationService $service;
    private const MONEY_TOLERANCE = 1.00;

    public function __construct(
        ReconciliationService $service
    ) {
        $this->service = $service;
    }

    public function getTypeBank(Request $request)
    {
        try {

            $cabang = $request->cabang;

            $data = DB::table('bank')
                ->select('jns_bank')
                ->where('cabang', $cabang)
                ->where('site', '<>', 'REG')
                ->whereNotNull('jns_bank')
                ->distinct()
                ->orderBy('jns_bank')
                ->get();

            return response()->json($data);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }
    }

    public function getRekeningReg(Request $request)
    {
        try {

            $cabang = $request->cabang;

            $data = DB::table('bank')
                ->select(
                    'no_rek',
                    'bank',
                    'jns_bank',
                    'akun',
                )
                ->where('cabang', $cabang)
                ->where('site', 'REG')
                ->where('bank', '<>', 'Titipan')
                ->orderBy('akun')
                ->get();

            return response()->json($data);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }
    }

    public function getRekeningFrc(Request $request)
    {
        try {

            $cabang = $request->cabang;
            $jnsBank = $request->jns_bank;

            $data = DB::table('bank')
                ->select(
                    'no_rek',
                    'jns_bank',
                    'akun',
                    'site',
                )
                ->where('cabang', $cabang)
                ->where('site', '<>', 'REG')
                ->where('jns_bank', $jnsBank)
                ->orderBy('site')
                ->get();

            return response()->json($data);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }
    }

    public function summary(Request $request)
    {
        $request->validate([
            'cabang'     => 'required',
            'jenis_bank' => 'required'
        ]);

        try {

            $result = $this->service->summary(
                $request->cabang,
                $request->jenis_bank,
                $request->type_bank
            );

            return response()->json([
                'success' => true,
                'periode' => $result['periode'],
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }
    }

    public function detail(Request $request)
    {
        $request->validate([
            'cabang'     => 'required',
            'jenis_bank' => 'required',
            'no_rek'     => 'required'
        ]);

        try {

            $result = $this->service->detail(
                $request->cabang,
                $request->jenis_bank,
                $request->no_rek
            );

            return response()->json([
                'success' => true,
                'periode' => $result['periode'],
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function unrecDetail(Request $request)
    {
        $request->validate([
            'bank'   => 'required',
            'no_rek' => 'required',
            'tgl'    => 'required',
            'jenis'  => 'required'
        ]);

        try {

            $result = $this->service->unrecDetail(
                $request->bank,
                $request->no_rek,
                $request->tgl,
                $request->jenis
            );

            return response()->json([

                'success' => true,

                'summary' => $result['summary'],

                'data' => $result['data']

            ]);

        } catch (\Exception $e) {

            return response()->json([

                'success' => false,

                'message' => $e->getMessage()

            ], 500);

        }
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'cabang'    => 'required',
            'bank'      => 'required',
            'action'    => 'required',
            'no_rek'    => 'required',
            'tgl'       => 'required|date',
            'jenis'     => 'required|in:db,cr'
        ]);

        if (in_array($request->action, [
            'RECON_SELECTED',
            'UNRECON_SELECTED'
        ])) {

            $request->validate([
                'rows' => 'required|array|min:1'
            ]);

        }

        $debug = [];

        $totalStart = microtime(true);

        try {

            DB::beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | TABLE
            |--------------------------------------------------------------------------
            */

            $start = microtime(true);

            $table = $this->service->getTables(
                $request->bank
            );

            $debug['getTables'] = round(
                (microtime(true) - $start) * 1000,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | UPDATE RECONCILE
            |--------------------------------------------------------------------------
            */

            $start = microtime(true);

            $this->service->updateReconcile(
                $table['mutasi_detail'],
                $request->action,
                $request->no_rek,
                $request->tgl,
                $request->jenis,
                collect($request->rows ?? [])
                    ->pluck('id')
                    ->unique()
                    ->values()
                    ->toArray()
            );

            $debug['updateReconcile'] = round(
                (microtime(true) - $start) * 1000,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | RECALCULATE UNRECONCILED
            |--------------------------------------------------------------------------
            */

            $start = microtime(true);

            $this->service->recalculateUnrec(
                $table['mutasi_detail'],
                $table['unrec'],
                $request->no_rek,
                $request->tgl
            );

            $debug['recalculateUnrec'] = round(
                (microtime(true) - $start) * 1000,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | DETAIL ROW
            |--------------------------------------------------------------------------
            */

            $start = microtime(true);

            $detailRow = $this->service->getDetailRow(
                $request->cabang,
                $request->bank,
                $request->no_rek,
                $request->tgl
            );

            $debug['getDetailRow'] = round(
                (microtime(true) - $start) * 1000,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | DRAWER
            |--------------------------------------------------------------------------
            */

            $start = microtime(true);

            $drawer = $this->service->unrecDetail(
                $request->bank,
                $request->no_rek,
                $request->tgl,
                $request->jenis
            );

            $debug['unrecDetail'] = round(
                (microtime(true) - $start) * 1000,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $start = microtime(true);

            DB::commit();

            $debug['commit'] = round(
                (microtime(true) - $start) * 1000,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | TOTAL
            |--------------------------------------------------------------------------
            */

            $debug['total'] = round(
                (microtime(true) - $totalStart) * 1000,
                2
            );

            Log::info('Bulk Action Profiling', $debug);

            return response()->json([
                'success' => true,
                'detail_row' => $detailRow,
                'drawer' => [
                    'summary' => $drawer['summary'],
                    'data' => $drawer['data']
                ]
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            $debug['total'] = round(
                (microtime(true) - $totalStart) * 1000,
                2
            );

            Log::error('Bulk Action Error', [
                'error' => $e->getMessage(),
                'profiling' => $debug
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }
    }

    public function getActivePeriod(Request $request)
    {
        $request->validate([
            'cabang' => 'required|string|max:50'
        ]);

        $cabang = trim($request->cabang);

        $period = DB::table('periode')
            ->where('Cabang', $cabang)
            ->where('kategori', 'Mutasi')
            ->where('status', 'Aktif')
            ->orderByDesc('start_date')
            ->first();

        if (!$period) {
            return response()->json([
                'success' => false,
                'message' => "Periode Mutasi aktif untuk cabang {$cabang} tidak ditemukan.",
                'period'  => null
            ], 404);
        }

        return response()->json([
            'success' => true,

            'period' => [
                'id'         => $period->id ?? null,
                'periode'    => $period->periode ?? null,
                'cabang'     => $period->Cabang ?? $cabang,
                'kategori'   => $period->kategori ?? null,
                'status'     => $period->status ?? null,
                'start_date' => $period->start_date,
                'end_date'   => $period->end_date
            ]
        ]);
    }

    private function resolveReceiptAccount(
        $receipt
    ) {

        return trim(
            $receipt->remittance_bank_account
            ?? ''
        );
    }

    private function emptySummary()
    {
        return [

            'total' =>
                0,

            'match' =>
                0,

            'mutasi_only' =>
                0,

            'receipt_only' =>
                0,

            'nominal_different' =>
                0,

            'account_different' =>
                0,

            'mutasi_amount' =>
                0,

            'receipt_amount' =>
                0,

            'difference_amount' =>
                0
        ];
    }

    private function buildSummary(
        array $rows
    ) {

        $summary =
            $this->emptySummary();


        $summary['total'] =
            count($rows);


        foreach ($rows as $row) {

            $status =
                strtoupper(
                    $row['status'] ?? ''
                );


            if (
                $status === 'MATCH'
            ) {

                $summary['match']++;

            } elseif (
                $status === 'MUTASI_ONLY'
            ) {

                $summary['mutasi_only']++;

            } elseif (
                $status === 'RECEIPT_ONLY'
            ) {

                $summary['receipt_only']++;

            } elseif (
                $status === 'NOMINAL_DIFFERENT'
            ) {

                $summary[
                    'nominal_different'
                ]++;

            }


            if (
                isset(
                    $row['mutasi_amount']
                )
            ) {

                $summary[
                    'mutasi_amount'
                ] +=
                    (float)
                    $row['mutasi_amount'];
            }


            if (
                isset(
                    $row['receipt_amount']
                )
            ) {

                $summary[
                    'receipt_amount'
                ] +=
                    (float)
                    $row['receipt_amount'];
            }


            if (
                isset(
                    $row['difference_amount']
                )
            ) {

                $summary[
                    'difference_amount'
                ] +=
                    (float)
                    $row['difference_amount'];
            }


            if (
                isset(
                    $row['account_match']
                )
                &&
                !$row['account_match']
                &&
                $row['receipt_id'] !== null
                &&
                $row['mutation_id'] !== null
            ) {

                $summary[
                    'account_different'
                ]++;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Rounding
        |--------------------------------------------------------------------------
        */

        $summary[
            'mutasi_amount'
        ] =
            round(
                $summary['mutasi_amount'],
                2
            );


        $summary[
            'receipt_amount'
        ] =
            round(
                $summary['receipt_amount'],
                2
            );


        $summary[
            'difference_amount'
        ] =
            round(
                $summary['difference_amount'],
                2
            );


        return $summary;
    }

    public function export(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | VALIDASI REQUEST
            |--------------------------------------------------------------------------
            */

            $cabang = trim((string) $request->input('cabang'));
            $periodId = $request->input('period_id');
            $type = strtoupper(trim((string) $request->input('type')));

            if ($cabang === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang wajib diisi.'
                ], 422);
            }

            if (!$periodId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode wajib dipilih.'
                ], 422);
            }

            if (!in_array($type, ['FRC', 'REG'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis rekonsiliasi tidak valid.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | AMBIL DATA PERIODE
            |--------------------------------------------------------------------------
            */

            $period = DB::table('periode')
                ->where('id', $periodId)
                ->first();

            if (!$period) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode tidak ditemukan.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDASI TANGGAL PERIODE
            |--------------------------------------------------------------------------
            */

            if (
                empty($period->start_date) ||
                empty($period->end_date)
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal awal atau tanggal akhir periode tidak tersedia.'
                ], 422);

            }


            $startDate = $period->start_date;
            $endDate   = $period->end_date;

            /*
            |--------------------------------------------------------------------------
            | EXPORT REGULER
            |--------------------------------------------------------------------------
            */
            if ($type === 'REG') {
                return $this->exportRegularReconciliation(
                    $cabang,
                    $period,
                    $startDate,
                    $endDate
                );
            }

            /*
            |--------------------------------------------------------------------------
            | EXPORT FRC
            |--------------------------------------------------------------------------
            */

            $jenisBanks = DB::table('bank')
                ->where('cabang', $cabang)
                ->whereNotNull('jns_bank')
                ->where('jns_bank', '<>', '')
                ->where(function ($query) {

                    $query->whereNull('site')
                        ->orWhere('site', '<>', 'REG');

                })
                ->select('jns_bank')
                ->distinct()
                ->orderBy('jns_bank')
                ->pluck('jns_bank')
                ->values();


            if ($jenisBanks->isEmpty()) {

                return response()->json([
                    'success' => false,
                    'message' => "Tidak ditemukan rekening Franchise untuk cabang {$cabang}."
                ], 404);

            }


            /*
            |--------------------------------------------------------------------------
            | BUAT WORKBOOK
            |--------------------------------------------------------------------------
            */

            $spreadsheet = new Spreadsheet();


            /*
            |--------------------------------------------------------------------------
            | SHEET REPORT
            |--------------------------------------------------------------------------
            */

            $defaultSheet = $spreadsheet->getActiveSheet();

            $defaultSheet->setTitle('REPORT');

            $defaultSheet->setShowGridlines(false);


            /*
            |--------------------------------------------------------------------------
            | WARNA TAB REPORT
            |--------------------------------------------------------------------------
            |
            | Hijau
            |
            */

            $defaultSheet
                ->getTabColor()
                ->setRGB('00B050');


            /*
            |--------------------------------------------------------------------------
            | BUILD REPORT
            |--------------------------------------------------------------------------
            */

            $this->buildFranchiseReportSheet(
                $defaultSheet,
                $cabang,
                $period,
                $jenisBanks,
                $startDate,
                $endDate
            );


            /*
            |--------------------------------------------------------------------------
            | SHEET RECEIPT
            |--------------------------------------------------------------------------
            |
            | Menampung receipt yang:
            | - cabang sesuai request
            | - periode sesuai request
            | - reff NULL atau kosong
            |
            */

            $receiptSheet = $spreadsheet->createSheet();


            /*
            |--------------------------------------------------------------------------
            | NAMA SHEET RECEIPT
            |--------------------------------------------------------------------------
            */

            $receiptSheet->setTitle(
                $this->makeSheetName(
                    'Receipt Unmatch',
                    $spreadsheet
                )
            );


            /*
            |--------------------------------------------------------------------------
            | GRIDLINE
            |--------------------------------------------------------------------------
            */

            $receiptSheet->setShowGridlines(false);


            /*
            |--------------------------------------------------------------------------
            | WARNA TAB RECEIPT
            |--------------------------------------------------------------------------
            |
            | Merah
            |
            */

            $receiptSheet
                ->getTabColor()
                ->setRGB('FF0000');


            /*
            |--------------------------------------------------------------------------
            | BUILD RECEIPT
            |--------------------------------------------------------------------------
            */

            $this->buildFranchiseReceiptSheet(
                $receiptSheet,
                $cabang,
                $startDate,
                $endDate
            );


            /*
            |--------------------------------------------------------------------------
            | SHEET PER JENIS BANK
            |--------------------------------------------------------------------------
            */

            foreach ($jenisBanks as $jnsBank) {

                /*
                |--------------------------------------------------------------------------
                | SHEET MUTASI BELUM KLOP
                |--------------------------------------------------------------------------
                */

                $mutasiSheet = $spreadsheet->createSheet();


                /*
                |--------------------------------------------------------------------------
                | NAMA SHEET MUTASI
                |--------------------------------------------------------------------------
                */

                $mutasiSheetName =
                    $this->makeSheetName(
                        $jnsBank,
                        $spreadsheet
                    );


                $mutasiSheet->setTitle(
                    $mutasiSheetName
                );


                /*
                |--------------------------------------------------------------------------
                | GRIDLINE
                |--------------------------------------------------------------------------
                */

                $mutasiSheet->setShowGridlines(false);


                /*
                |--------------------------------------------------------------------------
                | BUILD MUTASI
                |--------------------------------------------------------------------------
                */

                $this->buildFranchiseMutasiSheet(
                    $mutasiSheet,
                    $cabang,
                    $jnsBank,
                    $startDate,
                    $endDate
                );


                /*
                |--------------------------------------------------------------------------
                | SHEET KLOP
                |--------------------------------------------------------------------------
                */

                $klopSheet = $spreadsheet->createSheet();


                /*
                |--------------------------------------------------------------------------
                | NAMA SHEET KLOP
                |--------------------------------------------------------------------------
                */

                $klopSheetName =
                    $this->makeSheetName(
                        $jnsBank . ' KLOP',
                        $spreadsheet
                    );


                $klopSheet->setTitle(
                    $klopSheetName
                );


                /*
                |--------------------------------------------------------------------------
                | GRIDLINE
                |--------------------------------------------------------------------------
                */

                $klopSheet->setShowGridlines(false);


                /*
                |--------------------------------------------------------------------------
                | WARNA TAB KLOP
                |--------------------------------------------------------------------------
                |
                | Kuning
                |
                */

                $klopSheet
                    ->getTabColor()
                    ->setRGB('FFC000');


                /*
                |--------------------------------------------------------------------------
                | BUILD KLOP
                |--------------------------------------------------------------------------
                */

                $this->buildFranchiseKlopSheet(
                    $klopSheet,
                    $cabang,
                    $jnsBank,
                    $startDate,
                    $endDate
                );
            }


            /*
            |--------------------------------------------------------------------------
            | AKTIFKAN SHEET REPORT
            |--------------------------------------------------------------------------
            */

            $spreadsheet->setActiveSheetIndex(0);


            /*
            |--------------------------------------------------------------------------
            | NAMA FILE
            |--------------------------------------------------------------------------
            */

            $periodeName =
                !empty($period->periode)
                    ? $period->periode
                    : $startDate . '_' . $endDate;


            $safeCabang =
                preg_replace(
                    '/[^A-Za-z0-9_\-]/',
                    '_',
                    $cabang
                );


            $safePeriode =
                preg_replace(
                    '/[^A-Za-z0-9_\-]/',
                    '_',
                    $periodeName
                );


            $filename =
                "Rekonsiliasi_Franchise_{$safeCabang}_{$safePeriode}.xlsx";


            /*
            |--------------------------------------------------------------------------
            | TULIS EXCEL
            |--------------------------------------------------------------------------
            */

            return response()->streamDownload(

                function () use ($spreadsheet) {

                    $writer = new Xlsx($spreadsheet);

                    /*
                    |--------------------------------------------------------------------------
                    | MATIKAN PRE-CALCULATE FORMULA
                    |--------------------------------------------------------------------------
                    */

                    $writer->setPreCalculateFormulas(false);

                    $writer->save('php://output');
                },

                $filename,

                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

                    'Cache-Control' =>
                        'max-age=0, must-revalidate',

                    'Pragma' =>
                        'public',
                ]
            );


        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | LOG ERROR
            |--------------------------------------------------------------------------
            */

            Log::error(
                'EXPORT REKONSILIASI FRC ERROR',
                [
                    'cabang'    => $request->input('cabang'),
                    'period_id' => $request->input('period_id'),
                    'type'      => $request->input('type'),

                    'message'   => $e->getMessage(),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),

                    'trace'     => $e->getTraceAsString(),
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | RESPONSE ERROR
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => false,
                'message' => 'File Excel gagal dibuat.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    /*
    |-----------------------------------------------------------------------------
    | EXPORT REGULER
    |-----------------------------------------------------------------------------
    | Fungsi utama hanya mengatur data, workbook, sheet, dan download.
    | Pola query, aging, formula, dan format laporan tetap mengikuti kode lama.
    */
    private function exportRegularReconciliation(
        string $cabang,
        $period,
        string $startDate,
        string $endDate
    ) {
        try {
            ini_set('memory_limit', '1024M');
            /*
            |--------------------------------------------------------------------------
            | 1. DAFTAR REKENING REGULER
            |--------------------------------------------------------------------------
            */

            $rekeningList = DB::table('bank')
                ->where('cabang', $cabang)
                ->where('site', 'REG')
                ->whereRaw('UPPER(TRIM(bank)) <> ?', ['TITIPAN'])
                ->whereNotNull('no_rek')
                ->where('no_rek', '<>', '')
                ->orderBy('akun')
                ->get([
                    'no_rek',
                    'bank',
                    'akun',
                    'jns_bank',
                ]);

            if ($rekeningList->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        "Tidak ditemukan rekening Reguler non-Titipan untuk cabang {$cabang}.",
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | 2. PERIODE AKTIF DAN RENTANG AGING
            |--------------------------------------------------------------------------
            */

            $periodStart = \Carbon\Carbon::parse($startDate)->startOfDay();
            $periodEnd = \Carbon\Carbon::parse($endDate)->startOfDay();

            if ($periodStart->gt($periodEnd)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal awal periode melebihi tanggal akhir periode.',
                ], 422);
            }

            $agingRanges = [
                [
                    'label' => '01-08',
                    'start' => $periodStart->copy()->startOfMonth(),
                    'end' => $periodStart->copy()->startOfMonth()->addDays(7),
                ],
                [
                    'label' => '09-16',
                    'start' => $periodStart->copy()->startOfMonth()->addDays(8),
                    'end' => $periodStart->copy()->startOfMonth()->addDays(15),
                ],
                [
                    'label' => '17-24',
                    'start' => $periodStart->copy()->startOfMonth()->addDays(16),
                    'end' => $periodStart->copy()->startOfMonth()->addDays(23),
                ],
                [
                    'label' => '25-' . $periodEnd->format('d'),
                    'start' => $periodStart->copy()->startOfMonth()->addDays(24),
                    'end' => $periodEnd->copy(),
                ],
            ];

            foreach ($agingRanges as &$range) {
                if ($range['start']->lt($periodStart)) {
                    $range['start'] = $periodStart->copy();
                }

                if ($range['end']->gt($periodEnd)) {
                    $range['end'] = $periodEnd->copy();
                }
            }
            unset($range);

            /*
            |--------------------------------------------------------------------------
            | 3. BUAT WORKBOOK DAN REPORT
            |--------------------------------------------------------------------------
            */

            $spreadsheet = new Spreadsheet();
            $report = $spreadsheet->getActiveSheet();
            $report->setTitle('REPORT');

            $this->buildRegularReportSheet(
                $report,
                $cabang,
                $period,
                $rekeningList,
                $agingRanges,
                $periodStart,
                $periodEnd
            );

            /*
            |--------------------------------------------------------------------------
            | SHEET RECEIPT UNMATCH
            |--------------------------------------------------------------------------
            | Diletakkan setelah REPORT dan sebelum detail rekening.
            */

            $receiptSheet = $spreadsheet->createSheet();
            $receiptSheet->setTitle(
                $this->makeSheetName('Receipt Unmatch', $spreadsheet)
            );

            $receiptSheet->setShowGridlines(false);

            // Warna tab khusus receipt unmatch: merah.
            $receiptSheet->getTabColor()->setRGB('FF0000');

            $this->buildRegularReceiptUnmatchSheet(
                $receiptSheet,
                $cabang,
                $startDate,
                $endDate
            );

            /*
            |--------------------------------------------------------------------------
            | BUAT SHEET PER REKENING
            |--------------------------------------------------------------------------
            */

            foreach ($rekeningList as $rekening) {
                $sheetName = trim((string) $rekening->bank)
                    . ' (' . trim((string) $rekening->akun) . ')';

                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle(
                    $this->makeSheetName($sheetName, $spreadsheet)
                );

                $this->buildRegularAccountSheet(
                    $sheet,
                    $cabang,
                    $period,
                    $startDate,
                    $endDate,
                    $rekening,
                    $agingRanges
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 5. AKTIFKAN REPORT SEBAGAI SHEET PERTAMA
            |--------------------------------------------------------------------------
            */

            $spreadsheet->setActiveSheetIndex(0);

            /*
            |--------------------------------------------------------------------------
            | 6. NAMA FILE
            |--------------------------------------------------------------------------
            */

            $periodeName = $period->periode
                ?? ($startDate . '_' . $endDate);

            $safeCabang = preg_replace('/[^A-Za-z0-9_-]/', '_', $cabang);
            $safePeriode = preg_replace('/[^A-Za-z0-9_-]/', '_', $periodeName);

            $filename =
                "Rekonsiliasi_Reguler_{$safeCabang}_{$safePeriode}.xlsx";

            /*
            |--------------------------------------------------------------------------
            | 7. DOWNLOAD EXCEL
            |--------------------------------------------------------------------------
            */

            return response()->streamDownload(
                function () use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->setPreCalculateFormulas(false);
                    $writer->save('php://output');
                },
                $filename,
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'max-age=0, must-revalidate',
                    'Pragma' => 'public',
                ]
            );

        } catch (\Throwable $e) {
            Log::error('EXPORT REKONSILIASI REG ERROR', [
                'cabang' => $cabang,
                'period_id' => $period->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File Excel Reguler gagal dibuat.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Terjadi kesalahan pada server.',
            ], 500);
        }
    }

    private function getRegularBankTabColor(?string $bank): string
    {
        $bank = strtoupper(trim((string) $bank));

        $colors = [
            'BCA'           => '00B050', // Hijau
            'MANDIRI'       => 'FFC000', // Kuning
            'BRI'           => '4472C4', // Biru
            'BNI'           => 'ED7D31', // Oranye
            'BSI'           => '7030A0', // Ungu
            'CIMB NIAGA'    => 'FF0000', // Merah
            'INA PERDANA'   => '00B0F0', // Biru muda
            'BTN'           => 'A5A5A5', // Abu-abu
        ];

        foreach ($colors as $name => $color) {
            if (str_contains($bank, $name)) {
                return $color;
            }
        }

        return '808080'; // Warna default
    }

    private function buildRegularReportSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $report,
        string $cabang,
        $period,
        $rekeningList,
        array $agingRanges,
        \Carbon\Carbon $periodStart,
        \Carbon\Carbon $periodEnd
    ): void {
        $report->setShowGridlines(false);
        $report->getTabColor()->setRGB('4472C4');

        /* JUDUL DAN INFORMASI REPORT */
        $report->mergeCells('A1:I1');
        $report->setCellValue('A1', 'REPORT REKONSILIASI BANK REGULER');

        $report->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        $report->setCellValue('A3', 'Cabang : ' . $cabang);
        $report->mergeCells('D3:F3');
        $report->setCellValue('D3', 'Periode : ' . ($period->periode ?? ''));
        $report->setCellValue(
            'I3',
            'Tanggal : ' . $periodStart->format('d M y')
                . ' s/d ' . $periodEnd->format('d M y')
        );

        $report->getStyle('D3:F3')->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        );
        $report->getStyle('I3')->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
        );

        /* HEADER REPORT */
        $report->mergeCells('A5:A6');
        $report->mergeCells('B5:B6');
        $report->mergeCells('C5:C6');
        $report->mergeCells('D5:G5');
        $report->mergeCells('H5:H6');
        $report->mergeCells('I5:I6');

        $report->setCellValue('A5', 'No');
        $report->setCellValue('B5', 'JENIS BANK');
        $report->setCellValue('C5', 'NOMOR REKENING');
        $report->setCellValue('D5', 'PENERIMAAN BELUM DIBUKUKAN');

        foreach ($agingRanges as $index => $range) {
            $report->setCellValue(
                chr(ord('D') + $index) . '6',
                $range['label']
            );
        }

        $report->setCellValue('H5', "MUTASI\nOUTSTANDING");
        $report->setCellValue('I5', 'KETERANGAN');

        $report->getStyle('A5:I6')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        /* DATA AGING PER REKENING */
        $firstDataRow = 7;
        $reportRow = $firstDataRow;

        foreach ($rekeningList as $index => $rekening) {
            $noRek = trim((string) $rekening->no_rek);
            $jnsBank = trim((string) $rekening->jns_bank);

            $agingCounts = [];
            foreach ($agingRanges as $range) {
                $agingCounts[] = (int) $this->countRegularOutstanding(
                    $cabang,
                    $noRek,
                    $range
                );
            }

            $report->setCellValue("A{$reportRow}", $index + 1);
            $report->setCellValue("B{$reportRow}", $jnsBank);
            $report->setCellValueExplicit(
                "C{$reportRow}",
                $noRek,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );

            foreach (['D', 'E', 'F', 'G'] as $i => $column) {
                $report->setCellValueExplicit(
                    "{$column}{$reportRow}",
                    (int) ($agingCounts[$i] ?? 0),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                );
            }

            $report->setCellValue("H{$reportRow}", "=SUM(D{$reportRow}:G{$reportRow})");
            $report->setCellValue(
                "I{$reportRow}",
                "=IF(H{$reportRow}>0,\"Cabang Belum Pengajuan\",\"Clear\")"
            );

            $reportRow++;
        }

        $lastDataRow = $reportRow - 1;
        $totalRow = $reportRow;

        $report->mergeCells("A{$totalRow}:C{$totalRow}");
        $report->setCellValue("A{$totalRow}", 'TOTAL');

        foreach (['D', 'E', 'F', 'G', 'H'] as $column) {
            $formula = $lastDataRow >= $firstDataRow
                ? "=SUM({$column}{$firstDataRow}:{$column}{$lastDataRow})"
                : '=0';
            $report->setCellValue("{$column}{$totalRow}", $formula);
        }

        /* FORMAT ANGKA, BORDER, DAN TOTAL */
        $report->getStyle("A5:I{$totalRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $report->getStyle("A{$firstDataRow}:A{$lastDataRow}")
            ->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );

        if ($lastDataRow >= $firstDataRow) {
            $report->getStyle("C{$firstDataRow}:C{$lastDataRow}")
                ->getNumberFormat()->setFormatCode('@');

            $report->getStyle("C{$firstDataRow}:H{$lastDataRow}")
                ->getAlignment()->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                );

            $report->getStyle("H{$firstDataRow}:H{$lastDataRow}")
                ->getFont()->getColor()->setRGB('FF0000');
        }

        $report->getStyle("D{$firstDataRow}:H{$totalRow}")
            ->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0;0;@');

        $report->getStyle("A{$totalRow}:I{$totalRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        $report->getRowDimension(1)->setRowHeight(25);
        $report->getColumnDimension('A')->setWidth(10);
        $report->getColumnDimension('B')->setWidth(25);
        $report->getColumnDimension('C')->setWidth(22);
        $report->getColumnDimension('H')->setWidth(20);
        $report->getColumnDimension('I')->setWidth(30);
        $report->freezePane('A7');
    }
        
    private function buildRegularReceiptUnmatchSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $cabang,
        string $startDate,
        string $endDate
    ): void {
        $sheet->setShowGridlines(false);
        $sheet->getTabColor()->setRGB('FF0000');

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        $headers = [
            'A1' => 'No',
            'B1' => 'Bank',
            'C1' => 'No Rekening',
            'D1' => 'Site',
            'E1' => 'Receipt Number',
            'F1' => 'Receipt Date',
            'G1' => 'Receipt Amount',
            'H1' => 'Comments',
            'I1' => 'Paid By',
            'J1' => 'Status',
            'K1' => 'Activity',
            'L1' => 'Note',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' =>
                    \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' =>
                        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];

        $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(32);

        /*
        |--------------------------------------------------------------------------
        | AMBIL RECEIPT REGULER YANG BELUM MATCH
        |--------------------------------------------------------------------------
        | EXISTS digunakan agar receipt tidak berlipat jika tabel bank
        | memiliki lebih dari satu baris untuk nomor rekening yang sama.
        */

        $receipts = DB::table('receipt as r')
            ->whereBetween('r.receipt_date', [$startDate, $endDate])
            ->where(function ($query) {
                $query->whereNull('r.reff')
                    ->orWhereRaw("TRIM(r.reff) = ''");
            })
            ->whereExists(function ($query) use ($cabang) {
                $query->selectRaw('1')
                    ->from('bank as b')
                    ->whereColumn(
                        'b.no_rek',
                        'r.remittance_bank_account'
                    )
                    ->where('b.cabang', $cabang)
                    ->where('b.site', 'REG')
                    ->whereNotNull('b.no_rek')
                    ->where('b.no_rek', '<>', '')
                    ->whereRaw(
                        'UPPER(TRIM(b.bank)) <> ?',
                        ['TITIPAN']
                    );
            })
            ->orderBy('r.receipt_date')
            ->orderBy('r.id')
            ->get([
                'r.id',
                'r.remittance_bank_account',
                'r.receipt_number',
                'r.receipt_date',
                'r.receipt_amount',
                'r.comments',
                'r.paid_by',
                'r.receipt_status',
                'r.activity',
                'r.note',
            ]);

        /*
        |--------------------------------------------------------------------------
        | AMBIL INFORMASI BANK SEKALIGUS
        |--------------------------------------------------------------------------
        | Menghindari query bank berulang untuk setiap receipt.
        */

        $bankAccounts = $receipts
            ->pluck('remittance_bank_account')
            ->filter(fn ($account) => $account !== null && $account !== '')
            ->unique()
            ->values()
            ->all();

        $bankInfoMap = [];

        if (!empty($bankAccounts)) {
            $bankInfos = DB::table('bank')
                ->where('cabang', $cabang)
                ->where('site', 'REG')
                ->whereIn('no_rek', $bankAccounts)
                ->whereNotNull('no_rek')
                ->where('no_rek', '<>', '')
                ->whereRaw(
                    'UPPER(TRIM(bank)) <> ?',
                    ['TITIPAN']
                )
                ->get([
                    'no_rek',
                    'bank',
                    'site',
                ]);

            foreach ($bankInfos as $info) {
                $accountKey = (string) $info->no_rek;

                if (!isset($bankInfoMap[$accountKey])) {
                    $bankInfoMap[$accountKey] = $info;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | TULIS DATA
        |--------------------------------------------------------------------------
        */

        $row = 2;

        foreach ($receipts as $receipt) {
            $accountKey = (string) $receipt->remittance_bank_account;
            $bankInfo = $bankInfoMap[$accountKey] ?? null;

            $sheet->setCellValue('A' . $row, $row - 1);

            $sheet->setCellValue(
                'B' . $row,
                $bankInfo->bank ?? ''
            );

            $sheet->setCellValueExplicit(
                'C' . $row,
                $accountKey,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );

            $sheet->setCellValue(
                'D' . $row,
                $bankInfo->site ?? ''
            );

            $sheet->setCellValue(
                'E' . $row,
                $receipt->receipt_number
            );

            $this->writeExcelDateCell(
                $sheet,
                'F' . $row,
                $receipt->receipt_date
            );

            $sheet->setCellValue(
                'G' . $row,
                (float) $receipt->receipt_amount
            );

            $sheet->setCellValue(
                'H' . $row,
                $receipt->comments
            );

            $sheet->setCellValue(
                'I' . $row,
                $receipt->paid_by
            );

            // Status diambil dari field receipt_status.
            $sheet->setCellValue(
                'J' . $row,
                $receipt->receipt_status
            );

            // Activity bergeser ke kolom K.
            $sheet->setCellValue(
                'K' . $row,
                $receipt->activity
            );

            // Note bergeser ke kolom L.
            $sheet->setCellValue(
                'L' . $row,
                $receipt->note ?? ''
            );

            $row++;
        }

        /*
        |--------------------------------------------------------------------------
        | FORMAT TABEL
        |--------------------------------------------------------------------------
        */

        $lastRow = max(1, $row - 1);

        $sheet->getStyle("A1:L{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );

        if ($lastRow >= 2) {
            // Nomor rekening sebagai teks agar angka nol di depan tidak hilang.
            $sheet->getStyle("C2:C{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('@');

            // Format tanggal receipt.
            $sheet->getStyle("F2:F{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('dd-mmm-yy');

            // Format nominal receipt.
            $sheet->getStyle("G2:G{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0;[Red]\(#,##0);0');

            // Nomor urut rata tengah.
            $sheet->getStyle("A2:A{$lastRow}")
                ->getAlignment()
                ->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                );
        }

        /*
        |--------------------------------------------------------------------------
        | LEBAR KOLOM
        |--------------------------------------------------------------------------
        */

        $widths = [
            'A' => 8,
            'B' => 20,
            'C' => 22,
            'D' => 12,
            'E' => 28,
            'F' => 15,
            'G' => 20,
            'H' => 45,
            'I' => 20,
            'J' => 18,
            'K' => 30,
            'L' => 35,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        /*
        |--------------------------------------------------------------------------
        | ALIGNMENT, FREEZE PANE, DAN FILTER
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle("A1:L{$lastRow}")
            ->getAlignment()
            ->setVertical(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
            );

        // Pastikan header tetap rata tengah secara horizontal dan vertikal.
        $sheet->getStyle('A1:L1')
            ->getAlignment()
            ->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );

        $sheet->getStyle('A1:L1')
            ->getAlignment()
            ->setVertical(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            );

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:L{$lastRow}");
    }

    private function buildRegularAccountSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $cabang,
        $period,
        string $startDate,
        string $endDate,
        $rekening,
        array $agingRanges
    ): void {
        $noRek = trim((string) $rekening->no_rek);
    
        $sheet->setShowGridlines(false);
        $sheet->getTabColor()->setRGB(
            $this->getRegularBankTabColor(
                (string) ($rekening->bank ?? '')
            )
        );
    
        /*
        |--------------------------------------------------------------------------
        | 1. HEADER
        |--------------------------------------------------------------------------
        */
    
        $isMandiri = stripos(trim((string) ($rekening->bank ?? '')), 'MANDIRI') !== false;
    
        // Untuk Mandiri, kolom C dan D memuat remark dan remark1.
        // Kredit/REFF bergeser dua kolom ke kanan; sisi receipt dan hasil juga bergeser.
        if ($isMandiri) {
            $headers = [
                'A1' => 'No Rek',
                'B1' => 'Tanggal',
                'C1' => 'Keterangan',
                'D1' => 'Keterangan 2',
                'E1' => 'Kredit',
                'F1' => 'REFF',
                'H1' => 'Comments',
                'I1' => 'Paid By',
                'J1' => 'Activity',
                'K1' => 'Receipt Number',
                'L1' => 'Receipt Date',
                'M1' => 'Receipt Amount',
                'N1' => 'Selisih',
                'O1' => 'Keterangan',
            ];
        } else {
            $headers = [
                'A1' => 'No Rek',
                'B1' => 'Tanggal',
                'C1' => 'Keterangan',
                'D1' => 'Kredit',
                'E1' => 'REFF',
                'G1' => 'Comments',
                'H1' => 'Paid By',
                'I1' => 'Activity',
                'J1' => 'Receipt Number',
                'K1' => 'Receipt Date',
                'L1' => 'Receipt Amount',
                'M1' => 'Selisih',
                'N1' => 'Keterangan',
            ];
        }
    
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
    
        $mutasiHeaderStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
    
        $receiptHeaderStyle = $mutasiHeaderStyle;
        $receiptHeaderStyle['fill']['startColor']['rgb'] = '548235';
    
        $resultHeaderStyle = $mutasiHeaderStyle;
        $resultHeaderStyle['fill']['startColor']['rgb'] = 'C65911';
    
        if ($isMandiri) {
            $sheet->getStyle('A1:F1')->applyFromArray($mutasiHeaderStyle);
            $sheet->getStyle('H1:M1')->applyFromArray($receiptHeaderStyle);
            $sheet->getStyle('N1:O1')->applyFromArray($resultHeaderStyle);
        } else {
            $sheet->getStyle('A1:E1')->applyFromArray($mutasiHeaderStyle);
            $sheet->getStyle('G1:L1')->applyFromArray($receiptHeaderStyle);
            $sheet->getStyle('M1:N1')->applyFromArray($resultHeaderStyle);
        }
        $sheet->getRowDimension(1)->setRowHeight(32);
    
        /*
        |--------------------------------------------------------------------------
        | 2. AMBIL DATA MUTASI DAN RECEIPT
        |--------------------------------------------------------------------------
        | Transaksi virtual dikecualikan jika REFF mengandung VIR.
        | Receipt diambil semua status agar data ber-REFF yang sama dapat
        | dikelompokkan sebagai match. Untuk kategori receipt REFF kosong,
        | status REVERSED akan dikecualikan di tahap pemilahan.
        |--------------------------------------------------------------------------
        */
    
        $mutasiRows = DB::table('mutasi_detail')
            ->where('cabang', $cabang)
            ->where('no_rek', $noRek)
            ->whereBetween('tgl', [$startDate, $endDate])
            ->where('cr', '<>', 0)
            ->where(function ($query) {
                $query->whereNull('reff')
                    ->orWhereRaw("UPPER(reff) NOT LIKE ?", ['%VIR%']);
            })
            ->orderBy('tgl')
            ->orderBy('id')
            ->get([
                'id',
                'no_rek',
                'tgl',
                'remark',
                'remark1',
                'cr',
                'reff',
            ]);
    
        $receiptRows = DB::table('receipt')
            ->where('remittance_bank_account', $noRek)
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->where(function ($query) {
                $query->whereNull('reff')
                    ->orWhereRaw("UPPER(reff) NOT LIKE ?", ['%VIR%']);
            })
            ->orderBy('receipt_date')
            ->orderBy('id')
            ->get([
                'id',
                'remittance_bank_account',
                'receipt_number',
                'receipt_date',
                'receipt_amount',
                'comments',
                'paid_by',
                'activity',
                'reff',
                'receipt_status',
                'note',
            ]);
    
        /*
        |--------------------------------------------------------------------------
        | 3. KELOMPOKKAN BERDASARKAN REFF
        |--------------------------------------------------------------------------
        */
    
        $mutasiByReff = [];
        $receiptByReff = [];
        $mutasiEmpty = [];
        $receiptEmpty = [];
    
        foreach ($mutasiRows as $mutasi) {
            $reff = trim((string) ($mutasi->reff ?? ''));
    
            if ($reff === '') {
                $mutasiEmpty[] = $mutasi;
                continue;
            }
    
            $mutasiByReff[$reff][] = $mutasi;
        }
    
        foreach ($receiptRows as $receipt) {
            $reff = trim((string) ($receipt->reff ?? ''));
    
            if ($reff === '') {
                // Receipt REFF kosong ditampilkan kecuali status REVERSED.
                if (strtolower(trim((string) ($receipt->receipt_status ?? ''))) !== 'reversed') {
                    $receiptEmpty[] = $receipt;
                }
                continue;
            }
    
            $receiptByReff[$reff][] = $receipt;
        }
    
        unset($mutasiRows, $receiptRows);
    
        /*
        |--------------------------------------------------------------------------
        | 4. SUSUN KATEGORI
        |--------------------------------------------------------------------------
        | Kategori 1: match, REFF ada pada mutasi dan receipt.
        | Kategori 2: receipt REFF kosong dan bukan REVERSED.
        | Kategori 3: mutasi REFF kosong.
        |--------------------------------------------------------------------------
        */
    
        $matchGroups = [];
        $matchedReff = [];
    
        // Urutan REFF mengikuti urutan kemunculan pada data mutasi.
        foreach ($mutasiByReff as $reff => $mutasiGroup) {
            if (!isset($receiptByReff[$reff])) {
                continue;
            }
    
            $receiptGroup = $receiptByReff[$reff];
            $matchedReff[$reff] = true;
    
            $matchGroups[] = [
                'reff' => $reff,
                'mutasi' => $mutasiGroup,
                'receipt' => $receiptGroup,
            ];
        }
    
        // Bersihkan kelompok ber-REFF yang tidak mempunyai pasangan.
        // Sesuai tiga kategori yang diminta, hanya pasangan dengan REFF sama
        // yang masuk kategori match.
        unset($mutasiByReff, $receiptByReff, $matchedReff);
    
        /*
        |--------------------------------------------------------------------------
        | 5. FUNGSI INTERNAL UNTUK MENULIS BARIS
        |--------------------------------------------------------------------------
        */
    
        $row = 2;
    
        $columns = $isMandiri
            ? [
                'mutasi_account' => 'A', 'mutasi_date' => 'B',
                'mutasi_remark' => 'C', 'mutasi_remark1' => 'D',
                'mutasi_amount' => 'E', 'mutasi_reff' => 'F',
                'separator' => 'G',
                'receipt_comments' => 'H', 'receipt_paid_by' => 'I',
                'receipt_activity' => 'J', 'receipt_number' => 'K',
                'receipt_date' => 'L', 'receipt_amount' => 'M',
                'difference' => 'N', 'note' => 'O',
            ]
            : [
                'mutasi_account' => 'A', 'mutasi_date' => 'B',
                'mutasi_remark' => 'C', 'mutasi_remark1' => null,
                'mutasi_amount' => 'D', 'mutasi_reff' => 'E',
                'separator' => 'F',
                'receipt_comments' => 'G', 'receipt_paid_by' => 'H',
                'receipt_activity' => 'I', 'receipt_number' => 'J',
                'receipt_date' => 'K', 'receipt_amount' => 'L',
                'difference' => 'M', 'note' => 'N',
            ];
    
        $writeMatchedRows = function (array $mutasiGroup, array $receiptGroup) use ($sheet, &$row, $columns, $isMandiri): void {
            $maxRows = max(count($mutasiGroup), count($receiptGroup));
    
            for ($i = 0; $i < $maxRows; $i++) {
                $mutasi = $mutasiGroup[$i] ?? null;
                $receipt = $receiptGroup[$i] ?? null;
    
                if ($mutasi) {
                    $sheet->setCellValueExplicit($columns['mutasi_account'] . $row, (string) $mutasi->no_rek, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $this->writeExcelDateCell($sheet, $columns['mutasi_date'] . $row, $mutasi->tgl);
                    $sheet->setCellValue($columns['mutasi_remark'] . $row, $mutasi->remark);
                    if ($isMandiri) {
                        $sheet->setCellValue($columns['mutasi_remark1'] . $row, $mutasi->remark1 ?? '');
                    }
                    $sheet->setCellValue($columns['mutasi_amount'] . $row, (float) $mutasi->cr);
                    $sheet->setCellValue($columns['mutasi_reff'] . $row, $mutasi->reff);
                
                } else {
                    // Data MATCH: jika data mutasi kosong, isi X pada kolom mutasi.
                    $mutasiColumns = $isMandiri
                        ? ['A', 'B', 'C', 'D', 'E', 'F']
                        : ['A', 'B', 'C', 'D', 'E'];

                    foreach ($mutasiColumns as $column) {
                        $sheet->setCellValue($column . $row, 'X');
                    }
                }

    
                $sheet->setCellValue($columns['separator'] . $row, '');
    
                if ($receipt) {
                    $sheet->setCellValue($columns['receipt_comments'] . $row, $receipt->comments);
                    $sheet->setCellValue($columns['receipt_paid_by'] . $row, $receipt->paid_by);
                    $sheet->setCellValue($columns['receipt_activity'] . $row, $receipt->activity);
                    $sheet->setCellValue($columns['receipt_number'] . $row, $receipt->receipt_number);
                    $this->writeExcelDateCell($sheet, $columns['receipt_date'] . $row, $receipt->receipt_date);
                    $sheet->setCellValue($columns['receipt_amount'] . $row, (float) $receipt->receipt_amount);
                } else {
                    // Data MATCH: jika receipt kosong, isi X pada kolom receipt
                    // sampai Receipt Date. Receipt Amount tetap kosong.
                    foreach ([
                        $columns['receipt_comments'],
                        $columns['receipt_paid_by'],
                        $columns['receipt_activity'],
                        $columns['receipt_number'],
                        $columns['receipt_date'],
                    ] as $column) {
                        $sheet->setCellValue($column . $row, 'X');
                    }
                
                    $sheet->setCellValue(
                        $columns['receipt_amount'] . $row,
                        null
                    );
                }
    
                $sheet->setCellValue(
                    $columns['difference'] . $row,
                    '=N(' . $columns['mutasi_amount'] . $row . ')-N(' . $columns['receipt_amount'] . $row . ')'
                );
                $sheet->setCellValue($columns['note'] . $row, '');
                $row++;
            }
        };
    
        $writeReceiptEmptyRow = function (object $receipt) use ($sheet, &$row, $columns, $isMandiri): void {
            // Receipt belum match: sisi mutasi dikosongkan.
            $mutasiColumns = $isMandiri ? ['A', 'B', 'C', 'D', 'E', 'F'] : ['A', 'B', 'C', 'D', 'E'];
            foreach ($mutasiColumns as $column) {
                $sheet->setCellValue($column . $row, null);
            }
            $sheet->setCellValue($columns['separator'] . $row, '');
    
            $sheet->setCellValue($columns['receipt_comments'] . $row, $receipt->comments);
            $sheet->setCellValue($columns['receipt_paid_by'] . $row, $receipt->paid_by);
            $sheet->setCellValue($columns['receipt_activity'] . $row, $receipt->activity);
            $sheet->setCellValue($columns['receipt_number'] . $row, $receipt->receipt_number);
            $this->writeExcelDateCell($sheet, $columns['receipt_date'] . $row, $receipt->receipt_date);
            $sheet->setCellValue($columns['receipt_amount'] . $row, (float) $receipt->receipt_amount);
            $sheet->setCellValue($columns['difference'] . $row, null);
    
            $note = trim((string) ($receipt->note ?? ''));
            $sheet->setCellValue($columns['note'] . $row, $note !== '' ? $note : '');
            $row++;
        };
    
        $writeMutasiEmptyRow = function (object $mutasi) use ($sheet, &$row, $columns, $isMandiri): void {
            $sheet->setCellValueExplicit($columns['mutasi_account'] . $row, (string) $mutasi->no_rek, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $this->writeExcelDateCell($sheet, $columns['mutasi_date'] . $row, $mutasi->tgl);
            $sheet->setCellValue($columns['mutasi_remark'] . $row, $mutasi->remark);
            if ($isMandiri) {
                $sheet->setCellValue($columns['mutasi_remark1'] . $row, $mutasi->remark1 ?? '');
            }
            $sheet->setCellValue($columns['mutasi_amount'] . $row, (float) $mutasi->cr);
            $sheet->setCellValue($columns['mutasi_reff'] . $row, $mutasi->reff);
            $sheet->setCellValue($columns['separator'] . $row, '');
    
            // Mutasi belum match: sisi receipt dikosongkan.
            foreach ([
                $columns['receipt_comments'], $columns['receipt_paid_by'],
                $columns['receipt_activity'], $columns['receipt_number'],
                $columns['receipt_date'], $columns['receipt_amount'],
            ] as $column) {
                $sheet->setCellValue($column . $row, null);
            }
            $sheet->setCellValue($columns['difference'] . $row, null);
            $sheet->setCellValue($columns['note'] . $row, '');
            $row++;
        };
    
        /*
        |--------------------------------------------------------------------------
        | 6. KATEGORI 1 — MATCH DI BAGIAN PALING ATAS
        |--------------------------------------------------------------------------
        */
    
        foreach ($matchGroups as $group) {
            $writeMatchedRows($group['mutasi'], $group['receipt']);
        }
    
        /*
        |--------------------------------------------------------------------------
        | PEMISAH 3 BARIS ANTAR KATEGORI
        |--------------------------------------------------------------------------
        | Baris kedua dari tiga baris pemisah diberi warna hijau.
        |--------------------------------------------------------------------------
        */
    
        $addCategorySeparator = function () use ($sheet, &$row): void {
            $separatorStart = $row;
    
            for ($i = 0; $i < 3; $i++) {
                for ($col = 1; $col <= 14; $col++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $sheet->setCellValue("{$columnLetter}{$row}", null);
                }
                $row++;
            }
    
            $greenRow = $separatorStart + 1;
            $sheet->getStyle("A{$greenRow}:N{$greenRow}")->applyFromArray([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'C6E0B4'],
                ],
            ]);
        };
    
        if (count($matchGroups) > 0 && (count($receiptEmpty) > 0 || count($mutasiEmpty) > 0)) {
            $addCategorySeparator();
        }
    
        /*
        |--------------------------------------------------------------------------
        | 7. KATEGORI 2 — RECEIPT REFF KOSONG, STATUS BUKAN REVERSED
        |--------------------------------------------------------------------------
        */
    
        foreach ($receiptEmpty as $receipt) {
            $writeReceiptEmptyRow($receipt);
        }
    
        if (count($receiptEmpty) > 0 && count($mutasiEmpty) > 0) {
            $addCategorySeparator();
        }
    
        /*
        |--------------------------------------------------------------------------
        | 8. KATEGORI 3 — MUTASI REFF KOSONG
        |--------------------------------------------------------------------------
        */
    
        foreach ($mutasiEmpty as $mutasi) {
            $writeMutasiEmptyRow($mutasi);
        }
    
        unset($matchGroups, $receiptEmpty, $mutasiEmpty, $writeMatchedRows, $writeReceiptEmptyRow, $writeMutasiEmptyRow);
    
        /*
        |--------------------------------------------------------------------------
        | 9. FORMAT TABEL, ANGKA, TANGGAL, DAN CETAK
        |--------------------------------------------------------------------------
        */
    
        $lastRow = max(1, $row - 1);
        $lastColumn = $isMandiri ? 'O' : 'N';
    
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    
        if ($lastRow >= 2) {
            $sheet->getStyle($columns['mutasi_amount'] . "2:" . $columns['mutasi_amount'] . $lastRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0;[Red](#,##0);0');
    
            $sheet->getStyle($columns['receipt_amount'] . "2:" . $columns['receipt_amount'] . $lastRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0;[Red](#,##0);0');
    
            $sheet->getStyle($columns['difference'] . "2:" . $columns['difference'] . $lastRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0;[Red](#,##0);0');
    
            $sheet->getStyle("A2:{$lastColumn}{$lastRow}")
                ->getAlignment()
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    
            $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
                ->getAlignment()
                ->setWrapText(false);
    
            $sheet->getStyle($columns['mutasi_date'] . "2:" . $columns['mutasi_date'] . $lastRow)
                ->getNumberFormat()
                ->setFormatCode('dd-mmm-yy');
    
            $sheet->getStyle($columns['receipt_date'] . "2:" . $columns['receipt_date'] . $lastRow)
                ->getNumberFormat()
                ->setFormatCode('dd-mmm-yy');
        }
    
        $widths = $isMandiri
            ? [
                'A' => 18, 'B' => 13, 'C' => 45, 'D' => 45,
                'E' => 18, 'F' => 18, 'G' => 3, 'H' => 45,
                'I' => 13, 'J' => 30, 'K' => 28, 'L' => 14,
                'M' => 20, 'N' => 18, 'O' => 30,
            ]
            : [
                'A' => 18, 'B' => 13, 'C' => 55, 'D' => 18,
                'E' => 18, 'F' => 3, 'G' => 55, 'H' => 13,
                'I' => 30, 'J' => 28, 'K' => 14, 'L' => 20,
                'M' => 18, 'N' => 30,
            ];
    
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->freezePane('A2');
    
        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    
        $sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
    
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
    
        $sheet->getPageMargins()->setTop(0.25);
        $sheet->getPageMargins()->setBottom(0.25);
        $sheet->getPageMargins()->setLeft(0.25);
        $sheet->getPageMargins()->setRight(0.25);
    
        $sheet->getPageSetup()->setPrintArea("A1:{$lastColumn}{$lastRow}");
    
    }
    
    /**
     * Menulis tanggal sebagai serial date Excel agar number format
     * dd-mmm-yy berfungsi meskipun nilai dari database berupa string Y-m-d.
     */
    private function writeExcelDateCell(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $cell,
        $value
    ): void {
        if ($value === null || trim((string) $value) === '') {
            $sheet->setCellValue($cell, null);
            return;
        }
    
        try {
            $date = \Carbon\Carbon::parse($value);
            $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date);
            $sheet->setCellValue($cell, $excelDate);
            $sheet->getStyle($cell)
                ->getNumberFormat()
                ->setFormatCode('dd-mmm-yy');
        } catch (\Throwable $e) {
            // Fallback bila nilai tanggal tidak valid agar data tidak hilang.
            $sheet->setCellValue($cell, (string) $value);
        }
    }
    
    private function countRegularOutstanding(
        string $cabang,
        string $noRek,
        array $range
    ): int {
        if ($range['start']->gt($range['end'])) {
            return 0;
        }

        return (int) DB::table('mutasi_detail')
            ->where('cabang', $cabang)
            ->where('no_rek', $noRek)
            ->where('cr', '<>', 0)
            ->whereBetween('tgl', [
                $range['start']->format('Y-m-d'),
                $range['end']->format('Y-m-d'),
            ])
            ->where(function ($query) {
                $query->whereNull('reff')
                    ->orWhere('reff', '');
            })
            ->count();
    }

    private function buildFranchiseReportSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $cabang,
        $period,
        $jenisBanks,
        string $startDate,
        string $endDate
    ) {
        /*
        |--------------------------------------------------------------------------
        | GRIDLINES
        |--------------------------------------------------------------------------
        */
    
        $sheet->setShowGridlines(false);
    
    
        /*
        |--------------------------------------------------------------------------
        | TANGGAL PERIODE
        |--------------------------------------------------------------------------
        */
    
        $periodStart = \Carbon\Carbon::parse($startDate);
        $periodEnd   = \Carbon\Carbon::parse($endDate);
    
        $year  = $periodStart->year;
        $month = $periodStart->month;
    
    
        /*
        |--------------------------------------------------------------------------
        | AGING RANGE
        |--------------------------------------------------------------------------
        */
    
        $agingRanges = [
            [
                'start' => $periodStart->copy()->startOfMonth(),
                'end'   => $periodStart->copy()->startOfMonth()->addDays(7),
            ],
    
            [
                'start' => $periodStart->copy()->startOfMonth()->addDays(8),
                'end'   => $periodStart->copy()->startOfMonth()->addDays(15),
            ],
    
            [
                'start' => $periodStart->copy()->startOfMonth()->addDays(16),
                'end'   => $periodStart->copy()->startOfMonth()->addDays(23),
            ],
    
            [
                'start' => $periodStart->copy()->startOfMonth()->addDays(24),
                'end'   => $periodEnd->copy(),
            ],
        ];
    
    
        /*
        |--------------------------------------------------------------------------
        | POTONG AGING AGAR TIDAK KELUAR DARI PERIODE AKTIF
        |--------------------------------------------------------------------------
        */
    
        foreach ($agingRanges as &$range) {
    
            if ($range['start']->lt($periodStart)) {
                $range['start'] = $periodStart->copy();
            }
    
            if ($range['end']->gt($periodEnd)) {
                $range['end'] = $periodEnd->copy();
            }
        }
    
        unset($range);
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT AGING UNTUK HEADER
        |--------------------------------------------------------------------------
        */
    
        $agingLabels = [];
    
        foreach ($agingRanges as $range) {
    
            $agingLabels[] =
                $range['start']->format('d') .
                '-' .
                $range['end']->format('d');
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | TANGGAL MUTASI TERAKHIR
        |--------------------------------------------------------------------------
        */
    
        $maxMutasiDate = DB::table('mutasi_detail_frc as m')
            ->where('m.cabang', $cabang)
            ->whereBetween(
                'm.tgl',
                [
                    $startDate,
                    $endDate
                ]
            )
            ->max('m.tgl');
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT TANGGAL REPORT
        |--------------------------------------------------------------------------
        */
    
        $displayStartDate = $periodStart->format('d M y');
    
        $displayEndDate = $maxMutasiDate
            ? \Carbon\Carbon::parse($maxMutasiDate)->format('d M y')
            : $periodEnd->format('d M y');
    
    
        /*
        |--------------------------------------------------------------------------
        | HEADER REPORT
        |--------------------------------------------------------------------------
        */
    
        $sheet->mergeCells('A1:I1');
    
        $sheet->setCellValue(
            'A1',
            'REPORT REKONSILIASI BANK FRANCHISE'
        );
        
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | INFORMASI CABANG
        |--------------------------------------------------------------------------
        */
    
        $sheet->setCellValue(
            'A3',
            'Cabang : '.$cabang
        );
    
        /*
        |--------------------------------------------------------------------------
        | INFORMASI PERIODE
        |--------------------------------------------------------------------------
        */
        
        $sheet->mergeCells('C3:H3');
        $sheet->setCellValue(
            'C3',
            'Periode : '.$period->periode ?? ''
        );
        
        $sheet->getStyle('C3')->applyFromArray([
            'alignment' => [
                'horizontal' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | RANGE TANGGAL
        |--------------------------------------------------------------------------
        */
    
        $sheet->setCellValue(
            'I3',
            'Tanggal : '.$displayStartDate.' s/d '.$displayEndDate
        );
        
        /*
        |--------------------------------------------------------------------------
        | HEADER TABEL
        |--------------------------------------------------------------------------
        */
    
        $headerRow = 5;
    
        $sheet->setCellValue(
            "A{$headerRow}",
            'No'
        );
    
        $sheet->setCellValue(
            "B{$headerRow}",
            'JENIS BANK'
        );
    
        $sheet->setCellValue(
            "C{$headerRow}",
            'NOMOR REKENING'
        );
    
        $sheet->setCellValue(
            "D{$headerRow}",
            $agingLabels[0]
        );
    
        $sheet->setCellValue(
            "E{$headerRow}",
            $agingLabels[1]
        );
    
        $sheet->setCellValue(
            "F{$headerRow}",
            $agingLabels[2]
        );
    
        $sheet->setCellValue(
            "G{$headerRow}",
            $agingLabels[3]
        );
    
        $sheet->setCellValue(
            "H{$headerRow}",
            'MUTASI OUTSTANDING'
        );
    
        $sheet->setCellValue(
            "I{$headerRow}",
            'KETERANGAN'
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | QUERY REPORT - SATU QUERY UNTUK SEMUA JNS_BANK
        |--------------------------------------------------------------------------
        |
        | Inilah bagian utama optimasi.
        |
        |--------------------------------------------------------------------------
        */
    
        $reportQuery = DB::table('mutasi_detail_frc as m')
    
            /*
            |--------------------------------------------------------------------------
            | JOIN BANK
            |--------------------------------------------------------------------------
            */
    
            ->join(
                'bank as b',
                function ($join) use ($cabang) {
    
                    $join
                        ->on(
                            'b.no_rek',
                            '=',
                            'm.no_rek'
                        )
                        ->where(
                            'b.cabang',
                            '=',
                            $cabang
                        )
                        ->where(
                            'b.site',
                            '<>',
                            'REG'
                        );
                }
            )
    
            /*
            |--------------------------------------------------------------------------
            | CABANG MUTASI
            |--------------------------------------------------------------------------
            */
    
            ->where(
                'm.cabang',
                $cabang
            )
    
            /*
            |--------------------------------------------------------------------------
            | PERIODE
            |--------------------------------------------------------------------------
            */
    
            ->whereBetween(
                'm.tgl',
                [
                    $startDate,
                    $endDate
                ]
            )
    
            /*
            |--------------------------------------------------------------------------
            | HANYA MUTASI KREDIT
            |--------------------------------------------------------------------------
            */
    
            ->where(
                'm.cr',
                '!=',
                0
            )
    
            /*
            |--------------------------------------------------------------------------
            | HANYA YANG BELUM MEMPUNYAI REFF
            |--------------------------------------------------------------------------
            */
    
            ->where(function ($query) {
    
                $query
                    ->whereNull('m.reff')
                    ->orWhere(
                        'm.reff',
                        ''
                    );
    
            })
    
            /*
            |--------------------------------------------------------------------------
            | SELECT AGREGASI
            |--------------------------------------------------------------------------
            */
    
            ->select(
                'b.jns_bank',
    
                /*
                |--------------------------------------------------------------------------
                | AGING 1
                |--------------------------------------------------------------------------
                */
    
                DB::raw("
                    SUM(
                        CASE
                            WHEN m.tgl BETWEEN '{$agingRanges[0]['start']->format('Y-m-d')}'
                            AND '{$agingRanges[0]['end']->format('Y-m-d')}'
                            THEN 1
                            ELSE 0
                        END
                    ) AS aging1
                "),
    
                /*
                |--------------------------------------------------------------------------
                | AGING 2
                |--------------------------------------------------------------------------
                */
    
                DB::raw("
                    SUM(
                        CASE
                            WHEN m.tgl BETWEEN '{$agingRanges[1]['start']->format('Y-m-d')}'
                            AND '{$agingRanges[1]['end']->format('Y-m-d')}'
                            THEN 1
                            ELSE 0
                        END
                    ) AS aging2
                "),
    
                /*
                |--------------------------------------------------------------------------
                | AGING 3
                |--------------------------------------------------------------------------
                */
    
                DB::raw("
                    SUM(
                        CASE
                            WHEN m.tgl BETWEEN '{$agingRanges[2]['start']->format('Y-m-d')}'
                            AND '{$agingRanges[2]['end']->format('Y-m-d')}'
                            THEN 1
                            ELSE 0
                        END
                    ) AS aging3
                "),
    
                /*
                |--------------------------------------------------------------------------
                | AGING 4
                |--------------------------------------------------------------------------
                */
    
                DB::raw("
                    SUM(
                        CASE
                            WHEN m.tgl BETWEEN '{$agingRanges[3]['start']->format('Y-m-d')}'
                            AND '{$agingRanges[3]['end']->format('Y-m-d')}'
                            THEN 1
                            ELSE 0
                        END
                    ) AS aging4
                ")
            )
    
            /*
            |--------------------------------------------------------------------------
            | GROUP PER JNS_BANK
            |--------------------------------------------------------------------------
            */
    
            ->groupBy(
                'b.jns_bank'
            )
    
            ->orderBy(
                'b.jns_bank'
            )
    
            ->get();
    
    
        /*
        |--------------------------------------------------------------------------
        | INDEX HASIL QUERY BERDASARKAN JNS_BANK
        |--------------------------------------------------------------------------
        |
        | Supaya nantinya kita dapat memastikan jns_bank yang ada di tabel bank
        | tetap muncul walaupun tidak mempunyai mutasi outstanding.
        |
        |--------------------------------------------------------------------------
        */
    
        $reportMap = $reportQuery
            ->keyBy(
                'jns_bank'
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | TOTAL
        |--------------------------------------------------------------------------
        */
    
        $grandAging1 = 0;
        $grandAging2 = 0;
        $grandAging3 = 0;
        $grandAging4 = 0;
    
        $row = $headerRow + 1;
        $no  = 1;
    
    
        /*
        |--------------------------------------------------------------------------
        | LOOP JNS_BANK
        |--------------------------------------------------------------------------
        */
    
        foreach ($jenisBanks as $jnsBank) {
    
            /*
            |--------------------------------------------------------------------------
            | NORMALISASI NAMA JNS_BANK
            |--------------------------------------------------------------------------
            */
    
            $jnsBankName = trim(
                (string) $jnsBank
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | AMBIL HASIL AGREGASI
            |--------------------------------------------------------------------------
            */
    
            $result = $reportMap->get(
                $jnsBankName
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | JIKA TIDAK ADA DATA
            |--------------------------------------------------------------------------
            */
    
            $aging1 = $result
                ? (int) $result->aging1
                : 0;
    
            $aging2 = $result
                ? (int) $result->aging2
                : 0;
    
            $aging3 = $result
                ? (int) $result->aging3
                : 0;
    
            $aging4 = $result
                ? (int) $result->aging4
                : 0;
    
    
            /*
            |--------------------------------------------------------------------------
            | OUTSTANDING
            |--------------------------------------------------------------------------
            */
    
            $outstanding =
                $aging1 +
                $aging2 +
                $aging3 +
                $aging4;
    
    
            /*
            |--------------------------------------------------------------------------
            | KETERANGAN
            |--------------------------------------------------------------------------
            */
    
            $keterangan =
                $outstanding === 0
                    ? 'Clear'
                    : 'Cabang Belum Pengajuan';
    
    
            /*
            |--------------------------------------------------------------------------
            | ISI EXCEL
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValue(
                "A{$row}",
                $no
            );
    
            $sheet->setCellValue(
                "B{$row}",
                $jnsBankName
            );
    
            /*
            |--------------------------------------------------------------------------
            | REPORT BERDASARKAN JNS_BANK
            |--------------------------------------------------------------------------
            |
            | Tidak berdasarkan satu nomor rekening.
            |
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValue(
                "C{$row}",
                'ALL'
            );
    
            $sheet->setCellValue(
                "D{$row}",
                $aging1
            );
    
            $sheet->setCellValue(
                "E{$row}",
                $aging2
            );
    
            $sheet->setCellValue(
                "F{$row}",
                $aging3
            );
    
            $sheet->setCellValue(
                "G{$row}",
                $aging4
            );
    
            $sheet->setCellValue(
                "H{$row}",
                $outstanding
            );
    
            $sheet->setCellValue(
                "I{$row}",
                $keterangan
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | GRAND TOTAL
            |--------------------------------------------------------------------------
            */
    
            $grandAging1 += $aging1;
            $grandAging2 += $aging2;
            $grandAging3 += $aging3;
            $grandAging4 += $aging4;
    
    
            $row++;
            $no++;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | GRAND TOTAL
        |--------------------------------------------------------------------------
        */
    
        $grandOutstanding =
            $grandAging1 +
            $grandAging2 +
            $grandAging3 +
            $grandAging4;
    
    
        $sheet->setCellValue(
            "A{$row}",
            ''
        );
    
        $sheet->setCellValue(
            "B{$row}",
            'TOTAL'
        );
    
        $sheet->setCellValue(
            "C{$row}",
            ''
        );
    
        $sheet->setCellValue(
            "D{$row}",
            $grandAging1
        );
    
        $sheet->setCellValue(
            "E{$row}",
            $grandAging2
        );
    
        $sheet->setCellValue(
            "F{$row}",
            $grandAging3
        );
    
        $sheet->setCellValue(
            "G{$row}",
            $grandAging4
        );
    
        $sheet->setCellValue(
            "H{$row}",
            $grandOutstanding
        );
    
        /*
        |--------------------------------------------------------------------------
        | STYLE HEADER
        |--------------------------------------------------------------------------
        */
    
        $this->styleHeader(
            $sheet,
            "A{$headerRow}:I{$headerRow}"
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | STYLE TOTAL
        |--------------------------------------------------------------------------
        */
    
        $this->styleHeader(
            $sheet,
            "A{$row}:I{$row}"
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | ALIGNMENT
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getStyle(
                "A{$headerRow}:I{$row}"
            )
            ->getAlignment()
            ->setVertical(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            );
    
    
        $sheet
            ->getStyle(
                "A{$headerRow}:I{$row}"
            )
            ->getAlignment()
            ->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | BORDER
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getStyle(
                "A{$headerRow}:I{$row}"
            )
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | COLUMN WIDTH
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getColumnDimension('A')
            ->setWidth(7);
    
        $sheet
            ->getColumnDimension('B')
            ->setWidth(20);
    
        $sheet
            ->getColumnDimension('C')
            ->setWidth(20);
    
        $sheet
            ->getColumnDimension('D')
            ->setWidth(14);
    
        $sheet
            ->getColumnDimension('E')
            ->setWidth(14);
    
        $sheet
            ->getColumnDimension('F')
            ->setWidth(14);
    
        $sheet
            ->getColumnDimension('G')
            ->setWidth(18);
    
        $sheet
            ->getColumnDimension('H')
            ->setWidth(20);
    
        $sheet
            ->getColumnDimension('I')
            ->setWidth(28);
    
    
        /*
        |--------------------------------------------------------------------------
        | WRAP TEXT
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getStyle(
                "A{$headerRow}:I{$row}"
            )
            ->getAlignment()
            ->setWrapText(true);
    
    
        /*
        |--------------------------------------------------------------------------
        | FREEZE HEADER
        |--------------------------------------------------------------------------
        */
    
        $sheet->freezePane(
            "A" . ($headerRow + 1)
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | PAGE SETUP
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getPageSetup()
            ->setOrientation(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
            );
    
        $sheet
            ->getPageSetup()
            ->setPaperSize(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4
            );
    
    
        $sheet
            ->getPageSetup()
            ->setFitToWidth(1);
    
        $sheet
            ->getPageSetup()
            ->setFitToHeight(0);
    
        $sheet
            ->getPageMargins()
            ->setTop(0.25);
    
        $sheet
            ->getPageMargins()
            ->setBottom(0.25);
    
        $sheet
            ->getPageMargins()
            ->setLeft(0.25);
    
        $sheet
            ->getPageMargins()
            ->setRight(0.25);
    
    
        /*
        |--------------------------------------------------------------------------
        | PRINT AREA
        |--------------------------------------------------------------------------
        */
    
        $sheet->getPageSetup()->setPrintArea(
            "A1:I{$row}"
        );
    }

    private function buildFranchiseReceiptSheet(
        $sheet,
        $cabang,
        $startDate,
        $endDate
    ) {
        /*
        |--------------------------------------------------------------------------
        | SETTING SHEET
        |--------------------------------------------------------------------------
        */
    
        $sheet->setShowGridlines(false);
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT TANGGAL UNTUK JUDUL PERIODE
        |--------------------------------------------------------------------------
        */
    
        try {
    
            $startDateFormatted = \Carbon\Carbon::parse($startDate)
                ->locale('id')
                ->translatedFormat('d M y');
    
            $endDateFormatted = \Carbon\Carbon::parse($endDate)
                ->locale('id')
                ->translatedFormat('d M y');
    
        } catch (\Throwable $e) {
    
            $startDateFormatted = $startDate;
            $endDateFormatted   = $endDate;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | JUDUL
        |--------------------------------------------------------------------------
        */
    
        $sheet->mergeCells('A1:L1');
    
        $sheet->setCellValue(
            'A1',
            'Receipt Unmatch'   
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | INFORMASI CABANG
        |--------------------------------------------------------------------------
        */
    
        $sheet->setCellValue(
            'A3',
            'Cabang'
        );
    
        $sheet->setCellValue(
            'B3',
            ': ' . $cabang
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | JENIS BANK
        |--------------------------------------------------------------------------
        */
    
        $sheet->setCellValue(
            'A4',
            'Jenis Bank'
        );
    
        $sheet->setCellValue(
            'B4',
            ': Franchise'
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | PERIODE
        |--------------------------------------------------------------------------
        */
    
        $sheet->setCellValue(
            'A5',
            'Periode'
        );
    
        $sheet->setCellValue(
            'B5',
            ': ' . $startDateFormatted . ' s/d ' . $endDateFormatted
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | STYLE JUDUL
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getStyle('A1:L1')
            ->getFont()
            ->setBold(true)
            ->setSize(16);
    
        $sheet
            ->getStyle('A1:L1')
            ->getAlignment()
            ->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            )
            ->setVertical(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | STYLE INFORMASI
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getStyle('A3:A5')
            ->getFont()
            ->setBold(true);
    
        $sheet
            ->getStyle('B3:B5')
            ->getFont()
            ->setBold(true);
    
    
        /*
        |--------------------------------------------------------------------------
        | TINGGI BARIS JUDUL
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getRowDimension(1)
            ->setRowHeight(25);
    
    
        /*
        |--------------------------------------------------------------------------
        | QUERY RECEIPT
        |--------------------------------------------------------------------------
        |
        | Relasi:
        |
        | receipt.remittance_bank_account
        |              =
        | bank.no_rek
        |
        | Site diambil dari:
        |
        | bank.kd_toko
        |
        |--------------------------------------------------------------------------
        */
    
        $receipts = DB::table('receipt as r')
    
            ->join(
                'bank as b',
                'b.no_rek',
                '=',
                'r.remittance_bank_account'
            )
    
            /*
            |--------------------------------------------------------------------------
            | SELECT
            |--------------------------------------------------------------------------
            */
    
            ->select([
                'r.id',
                'r.receipt_number',
                'r.receipt_date',
                'r.receipt_amount',
                'r.remittance_bank_account',
                'r.receipt_type',
                'r.receipt_status',
                'r.paid_by',
                'r.activity',
                'r.comments',
                'r.note',
                'b.jns_bank',
                'b.site as site',
            ])
    
            /*
            |--------------------------------------------------------------------------
            | FILTER CABANG
            |--------------------------------------------------------------------------
            */
    
            ->where('b.cabang', $cabang)
    
            /*
            |--------------------------------------------------------------------------
            | KHUSUS FRC
            |--------------------------------------------------------------------------
            |
            | Sama dengan filter jenis bank FRC yang digunakan
            | pada function export().
            |
            |--------------------------------------------------------------------------
            */
    
            ->where(function ($query) {
    
                $query->whereNull('b.site')
                    ->orWhere('b.site', '<>', 'REG');
    
            })
    
            /*
            |--------------------------------------------------------------------------
            | FILTER PERIODE
            |--------------------------------------------------------------------------
            */
    
            ->whereBetween(
                DB::raw('DATE(r.receipt_date)'),
                [
                    $startDate,
                    $endDate
                ]
            )
    
            /*
            |--------------------------------------------------------------------------
            | HANYA RECEIPT YANG BELUM KLOP
            |--------------------------------------------------------------------------
            */
    
            ->where(function ($query) {
    
                $query->whereNull('r.reff')
                    ->orWhere('r.reff', '');
    
            })
    
            /*
            |--------------------------------------------------------------------------
            | SORTING
            |--------------------------------------------------------------------------
            */
    
            ->orderBy(
                'b.site',
                'asc'
            )
    
            ->orderBy(
                'r.receipt_date',
                'asc'
            )
    
            ->get();
    
    
        /*
        |--------------------------------------------------------------------------
        | HEADER TABEL
        |--------------------------------------------------------------------------
        */
    
        $headers = [
            'Site',
            'Receipt Number',
            'Receipt Date',
            'Amount',
            'Jenis Bank',
            'Bank Account',
            'Type',
            'Status',
            'Paid By',
            'Activity',
            'Comments',
            'Note',
        ];
    
        $sheet->fromArray(
            $headers,
            null,
            'A7'
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | DATA RECEIPT
        |--------------------------------------------------------------------------
        */
    
        $row = 8;
    
        foreach ($receipts as $receipt) {
    
            /*
            |--------------------------------------------------------------------------
            | SITE
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValueExplicit(
                "A{$row}",
                (string) ($receipt->site ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | RECEIPT NUMBER
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValueExplicit(
                "B{$row}",
                (string) ($receipt->receipt_number ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | RECEIPT DATE
            |--------------------------------------------------------------------------
            */
    
            if (!empty($receipt->receipt_date)) {
    
                try {
    
                    $date = new \DateTime(
                        $receipt->receipt_date
                    );
    
                    $excelDate =
                        \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
                            $date
                        );
    
                    $sheet->setCellValue(
                        "C{$row}",
                        $excelDate
                    );
    
                } catch (\Throwable $e) {
    
                    $sheet->setCellValue(
                        "C{$row}",
                        $receipt->receipt_date
                    );
                }
    
            } else {
    
                $sheet->setCellValue(
                    "C{$row}",
                    ''
                );
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | RECEIPT AMOUNT
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValue(
                "D{$row}",
                $receipt->receipt_amount
            );
    
            /*
            |--------------------------------------------------------------------------
            | JENIS BANK
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValueExplicit(
                "E{$row}",
                (string) ($receipt->jns_bank ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );

            /*
            |--------------------------------------------------------------------------
            | REKENING BANK
            |--------------------------------------------------------------------------
        */
    
            $sheet->setCellValueExplicit(
                "F{$row}",
                (string) ($receipt->remittance_bank_account ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | RECEIPT TYPE
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValue(
                "G{$row}",
                $receipt->receipt_type
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | RECEIPT STATUS
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValue(
                "H{$row}",
                $receipt->receipt_status
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | PAID BY
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValue(
                "I{$row}",
                $receipt->paid_by
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | ACTIVITY
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValue(
                "J{$row}",
                $receipt->activity
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | COMMENTS
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValue(
                "K{$row}",
                $receipt->comments
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | REFF
            |--------------------------------------------------------------------------
            */
    
            $sheet->setCellValue(
                "L{$row}",
                $receipt->note
            );
    
    
            $row++;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | LAST ROW
        |--------------------------------------------------------------------------
        */
    
        $lastRow = max(
            7,
            $row - 1
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | STYLE HEADER TABEL
        |--------------------------------------------------------------------------
        */
    
        $headerRange = 'A7:L7';
    
        $sheet
            ->getStyle($headerRange)
            ->getFont()
            ->setBold(true)
            ->setColor(
                new \PhpOffice\PhpSpreadsheet\Style\Color(
                    \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE
                )
            );
    
        $sheet
            ->getStyle($headerRange)
            ->getAlignment()
            ->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            )
            ->setVertical(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            )
            ->setWrapText(true);
    
        $sheet
            ->getStyle($headerRange)
            ->getFill()
            ->setFillType(
                \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB('4472C4');
    
    
        /*
        |--------------------------------------------------------------------------
        | TINGGI HEADER
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getRowDimension(7)
            ->setRowHeight(28);
    
    
        /*
        |--------------------------------------------------------------------------
        | BORDER
        |--------------------------------------------------------------------------
        */
    
        if ($lastRow >= 7) {
    
            $sheet
                ->getStyle("A7:L{$lastRow}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT TANGGAL
        |--------------------------------------------------------------------------
        |
        | dd-mmm-yy
        | Contoh:
        | 01-Sep-26
        |
        |--------------------------------------------------------------------------
        */
    
        if ($lastRow >= 8) {
    
            $sheet
                ->getStyle("C8:C{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('dd-mmm-yy');
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT NOMINAL
        |--------------------------------------------------------------------------
        */
    
        if ($lastRow >= 8) {
    
            $sheet
                ->getStyle("D8:D{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT SITE DAN REKENING SEBAGAI TEXT
        |--------------------------------------------------------------------------
        */
    
        if ($lastRow >= 8) {
    
            $sheet
                ->getStyle("A8:A{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('@');
    
            $sheet
                ->getStyle("E8:E{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('@');
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | ALIGNMENT
        |--------------------------------------------------------------------------
        */
    
        if ($lastRow >= 8) {
    
            $sheet
                ->getStyle("A8:L{$lastRow}")
                ->getAlignment()
                ->setVertical(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
                );
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | ALIGNMENT HEADER / DATA TERTENTU
        |--------------------------------------------------------------------------
        */
    
        if ($lastRow >= 8) {
    
            $sheet
                ->getStyle("A8:A{$lastRow}")
                ->getAlignment()
                ->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                );
            
            $sheet
                ->getStyle("C8:C{$lastRow}")
                ->getAlignment()
                ->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                );
    
            $sheet
                ->getStyle("G8:H{$lastRow}")
                ->getAlignment()
                ->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                );
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | WRAP TEXT
        |--------------------------------------------------------------------------
        */
        if ($lastRow >= 8) {
    
            $sheet
                ->getStyle("J8:K{$lastRow}")
                ->getAlignment()
                ->setWrapText(true);
        }
    
        /*
        |--------------------------------------------------------------------------
        | WIDTH KOLOM
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getColumnDimension('A')
            ->setWidth(15);
    
        $sheet
            ->getColumnDimension('B')
            ->setWidth(25);
    
        $sheet
            ->getColumnDimension('C')
            ->setWidth(15);
    
        $sheet
            ->getColumnDimension('D')
            ->setWidth(18);
        
        $sheet
            ->getColumnDimension('E')
            ->setWidth(18);

        $sheet
            ->getColumnDimension('F')
            ->setWidth(25);
    
        $sheet
            ->getColumnDimension('G')
            ->setWidth(18);
    
        $sheet
            ->getColumnDimension('H')
            ->setWidth(18);
    
        $sheet
            ->getColumnDimension('I')
            ->setWidth(20);
    
        $sheet
            ->getColumnDimension('J')
            ->setWidth(35);
    
        $sheet
            ->getColumnDimension('K')
            ->setWidth(45);
    
        $sheet
            ->getColumnDimension('L')
            ->setWidth(25);
    
    
        /*
        |--------------------------------------------------------------------------
        | FREEZE HEADER
        |--------------------------------------------------------------------------
        */
    
        $sheet->freezePane('A8');
    
    
        /*
        |--------------------------------------------------------------------------
        | AUTOFILTER
        |--------------------------------------------------------------------------
        */
    
        $sheet->setAutoFilter(
            "A7:L{$lastRow}"
        );
    }

    private function buildFranchiseMutasiSheet(
        $sheet,
        string $cabang,
        string $jnsBank,
        string $startDate,
        string $endDate
    ) {
        /*
        |--------------------------------------------------------------------------
        | GRIDLINES
        |--------------------------------------------------------------------------
        */
    
        $sheet->setShowGridlines(false);
    
    
        /*
        |--------------------------------------------------------------------------
        | JUDUL
        |--------------------------------------------------------------------------
        */
    
        // Sekarang sampai kolom F
        $sheet->mergeCells('A1:F1');
    
        $sheet->setCellValue(
            'A1',
            "Mutasi Outstanding - {$jnsBank}"
        );
    
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
    
            'alignment' => [
                'horizontal' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    
                'vertical' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
    
        $sheet->getRowDimension(1)->setRowHeight(24);
    
    
        /*
        |--------------------------------------------------------------------------
        | INFORMASI
        |--------------------------------------------------------------------------
        */
    
        $sheet->setCellValue(
            'A3',
            'Cabang'
        );
    
        $sheet->setCellValue(
            'B3',
            ': ' . $cabang
        );
    
    
        $sheet->setCellValue(
            'A4',
            'Jenis Bank'
        );
    
        $sheet->setCellValue(
            'B4',
            ': ' . $jnsBank
        );
    
    
        $sheet->setCellValue(
            'A5',
            'Periode'
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT PERIODE
        |--------------------------------------------------------------------------
        */
    
        try {
    
            $displayStartDate =
                \Carbon\Carbon::parse($startDate)
                    ->format('d M y');
    
            $displayEndDate =
                \Carbon\Carbon::parse($endDate)
                    ->format('d M y');
    
            $displayPeriod =
                $displayStartDate .
                ' s/d ' .
                $displayEndDate;
    
        } catch (\Throwable $e) {
    
            $displayPeriod =
                $startDate .
                ' s/d ' .
                $endDate;
        }
    
    
        $sheet->setCellValue(
            'B5',
            ': ' . $displayPeriod
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | STYLE INFORMASI
        |--------------------------------------------------------------------------
        */
    
        $sheet->getStyle('A3:B5')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
    
            'alignment' => [
                'vertical' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
    
    
        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */
    
        $headerRow = 7;
    
        $headers = [
            'No Rekening',
            'Kd Toko',
            'Tanggal',
            'Remark',
            'Remark 1',
            'Kredit',
        ];
    
    
        /*
        |--------------------------------------------------------------------------
        | TULIS HEADER
        |--------------------------------------------------------------------------
        */
    
        $sheet->fromArray(
            $headers,
            null,
            "A{$headerRow}"
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | STYLE HEADER
        |--------------------------------------------------------------------------
        */
    
        $this->styleHeader(
            $sheet,
            "A{$headerRow}:F{$headerRow}"
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | QUERY MUTASI
        |--------------------------------------------------------------------------
        */
    
        $query = DB::table(
            'mutasi_detail_frc as m'
        )
    
            /*
            |--------------------------------------------------------------------------
            | JOIN BANK
            |--------------------------------------------------------------------------
            */
    
            ->join(
                'bank as b',
                function ($join) use (
                    $cabang,
                    $jnsBank
                ) {
    
                    $join->on(
                        'b.no_rek',
                        '=',
                        'm.no_rek'
                    );
    
                    $join->where(
                        'b.cabang',
                        '=',
                        $cabang
                    );
    
                    $join->where(
                        'b.jns_bank',
                        '=',
                        $jnsBank
                    );
    
                    /*
                    |--------------------------------------------------------------------------
                    | KHUSUS FRANCHISE
                    |--------------------------------------------------------------------------
                    */
    
                    $join->where(
                        'b.site',
                        '<>',
                        'REG'
                    );
                }
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | CABANG MUTASI
            |--------------------------------------------------------------------------
            */
    
            ->where(
                'm.cabang',
                '=',
                $cabang
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | PERIODE
            |--------------------------------------------------------------------------
            */
    
            ->whereBetween(
                'm.tgl',
                [
                    $startDate,
                    $endDate
                ]
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | HANYA MUTASI KREDIT
            |--------------------------------------------------------------------------
            */
    
            ->where(
                'm.cr',
                '!=',
                0
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | HANYA MUTASI BELUM KLOP
            |--------------------------------------------------------------------------
            */
    
            ->where(function ($query) {
    
                $query
                    ->whereNull('m.reff')
                    ->orWhere(
                        'm.reff',
                        ''
                    );
    
            })
    
    
            /*
            |--------------------------------------------------------------------------
            | SORTING
            |--------------------------------------------------------------------------
            */
    
            ->orderBy(
                'm.tgl',
                'asc'
            )
    
            ->orderBy(
                'm.id',
                'asc'
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | SELECT
            |--------------------------------------------------------------------------
            |
            | Kd Toko diambil dari tabel bank.
            |
            */
    
            ->select([
                'm.no_rek',
                'b.site',
                'm.tgl',
                'm.remark',
                'm.remark1',
                'm.cr',
            ]);
    
    
        /*
        |--------------------------------------------------------------------------
        | BARIS DATA
        |--------------------------------------------------------------------------
        */
    
        $row = $headerRow + 1;
    
    
        /*
        |--------------------------------------------------------------------------
        | BUFFER
        |--------------------------------------------------------------------------
        */
    
        $buffer = [];
    
        $bufferSize = 1000;
    
    
        /*
        |--------------------------------------------------------------------------
        | TOTAL DATA
        |--------------------------------------------------------------------------
        */
    
        $totalRows = 0;
    
    
        /*
        |--------------------------------------------------------------------------
        | CURSOR
        |--------------------------------------------------------------------------
        */
    
        foreach ($query->cursor() as $item) {
    
            /*
            |--------------------------------------------------------------------------
            | NO REKENING
            |--------------------------------------------------------------------------
            */
    
            $noRek = trim(
                (string) $item->no_rek
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | KD TOKO
            |--------------------------------------------------------------------------
            */
    
            $kdToko = trim(
                (string) $item->site
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | TANGGAL
            |--------------------------------------------------------------------------
            |
            | Ubah tanggal database menjadi Excel date serial.
            |
            */
    
            try {
    
                $excelDate =
                    \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
                        \Carbon\Carbon::parse($item->tgl)
                            ->startOfDay()
                            ->toDateTime()
                    );
    
            } catch (\Throwable $e) {
    
                $excelDate = $item->tgl;
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | BUFFER
            |--------------------------------------------------------------------------
            */
    
            $buffer[] = [
    
                /*
                |--------------------------------------------------------------------------
                | NO REKENING
                |--------------------------------------------------------------------------
                */
    
                $noRek,
    
    
                /*
                |--------------------------------------------------------------------------
                | KD TOKO
                |--------------------------------------------------------------------------
                */
    
                $kdToko,
    
    
                /*
                |--------------------------------------------------------------------------
                | TANGGAL
                |--------------------------------------------------------------------------
                */
    
                $excelDate,
    
    
                /*
                |--------------------------------------------------------------------------
                | REMARK
                |--------------------------------------------------------------------------
                */
    
                $item->remark,
    
    
                /*
                |--------------------------------------------------------------------------
                | REMARK 1
                |--------------------------------------------------------------------------
                */
    
                $item->remark1,
    
    
                /*
                |--------------------------------------------------------------------------
                | KREDIT
                |--------------------------------------------------------------------------
                */
    
                $item->cr,
    
            ];
    
    
            $totalRows++;
    
    
            /*
            |--------------------------------------------------------------------------
            | TULIS SETIAP 1.000 ROW
            |--------------------------------------------------------------------------
            */
    
            if (count($buffer) >= $bufferSize) {
    
                $sheet->fromArray(
                    $buffer,
                    null,
                    "A{$row}"
                );
    
                $row += count($buffer);
    
                $buffer = [];
            }
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | TULIS SISA BUFFER
        |--------------------------------------------------------------------------
        */
    
        if (!empty($buffer)) {
    
            $sheet->fromArray(
                $buffer,
                null,
                "A{$row}"
            );
    
            $row += count($buffer);
    
            $buffer = [];
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | BARIS TERAKHIR DATA
        |--------------------------------------------------------------------------
        */
    
        $lastDataRow = $row - 1;
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT NO REKENING
        |--------------------------------------------------------------------------
        |
        | Tetap menggunakan TEXT agar nomor rekening:
        |
        | 0300869025
        |
        | tidak berubah menjadi:
        |
        | 300869025
        |
        | atau scientific notation.
        |
        */
    
        if ($lastDataRow >= ($headerRow + 1)) {
    
            $sheet
                ->getStyle(
                    "A" .
                    ($headerRow + 1) .
                    ":A" .
                    $lastDataRow
                )
                ->getNumberFormat()
                ->setFormatCode('@');
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT KD TOKO
        |--------------------------------------------------------------------------
        */
    
        if ($lastDataRow >= ($headerRow + 1)) {
    
            $sheet
                ->getStyle(
                    "B" .
                    ($headerRow + 1) .
                    ":B" .
                    $lastDataRow
                )
                ->getNumberFormat()
                ->setFormatCode('@');
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT TANGGAL
        |--------------------------------------------------------------------------
        |
        | dd-mmm-yy
        |
        | Contoh:
        | 20-Sep-26
        |
        */
    
        if ($lastDataRow >= ($headerRow + 1)) {
    
            $sheet
                ->getStyle(
                    "C" .
                    ($headerRow + 1) .
                    ":C" .
                    $lastDataRow
                )
                ->getNumberFormat()
                ->setFormatCode(
                    'dd-mmm-yy'
                );
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT KREDIT
        |--------------------------------------------------------------------------
        |
        | Tidak menggunakan .00
        |
        | Contoh:
        | 15,216,200
        |
        */
    
        if ($lastDataRow >= ($headerRow + 1)) {
    
            $sheet
                ->getStyle(
                    "F" .
                    ($headerRow + 1) .
                    ":F" .
                    $lastDataRow
                )
                ->getNumberFormat()
                ->setFormatCode(
                    '#,##0'
                );
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | BORDER DATA
        |--------------------------------------------------------------------------
        */
    
        if ($lastDataRow >= $headerRow + 1) {
    
            $sheet
                ->getStyle(
                    "A{$headerRow}:F{$lastDataRow}"
                )
                ->applyFromArray([
    
                    'borders' => [
    
                        'allBorders' => [
    
                            'borderStyle' =>
                                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
    
                        ],
    
                    ],
    
                ]);
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | ALIGNMENT
        |--------------------------------------------------------------------------
        */
    
        if ($lastDataRow >= $headerRow + 1) {
    
            /*
            |--------------------------------------------------------------------------
            | NO REKENING
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "A" .
                    ($headerRow + 1) .
                    ":A" .
                    $lastDataRow
                )
                ->getAlignment()
                ->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | KD TOKO
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "B" .
                    ($headerRow + 1) .
                    ":B" .
                    $lastDataRow
                )
                ->getAlignment()
                ->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | TANGGAL
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "C" .
                    ($headerRow + 1) .
                    ":C" .
                    $lastDataRow
                )
                ->getAlignment()
                ->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | KREDIT
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "F" .
                    ($headerRow + 1) .
                    ":F" .
                    $lastDataRow
                )
                ->getAlignment()
                ->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
                );
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | COLUMN WIDTH
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getColumnDimension('A')
            ->setWidth(22);
    
        $sheet
            ->getColumnDimension('B')
            ->setWidth(18);
    
        $sheet
            ->getColumnDimension('C')
            ->setWidth(15);
    
        $sheet
            ->getColumnDimension('D')
            ->setWidth(45);
    
        $sheet
            ->getColumnDimension('E')
            ->setWidth(35);
    
        $sheet
            ->getColumnDimension('F')
            ->setWidth(20);
    
    
        /*
        |--------------------------------------------------------------------------
        | FREEZE HEADER
        |--------------------------------------------------------------------------
        */
    
        $sheet->freezePane(
            'A8'
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | AUTOFILTER
        |--------------------------------------------------------------------------
        */
    
        $sheet->setAutoFilter(
            "A7:F7"
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | PAGE SETUP
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getPageSetup()
            ->setOrientation(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
            );
    
        $sheet
            ->getPageSetup()
            ->setPaperSize(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4
            );
    
        $sheet
            ->getPageSetup()
            ->setFitToWidth(1);
    
        $sheet
            ->getPageSetup()
            ->setFitToHeight(0);
    
    
        /*
        |--------------------------------------------------------------------------
        | MARGIN
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getPageMargins()
            ->setTop(0.25);
    
        $sheet
            ->getPageMargins()
            ->setBottom(0.25);
    
        $sheet
            ->getPageMargins()
            ->setLeft(0.25);
    
        $sheet
            ->getPageMargins()
            ->setRight(0.25);
    
    
        /*
        |--------------------------------------------------------------------------
        | PRINT AREA
        |--------------------------------------------------------------------------
        */
    
        $sheet->getPageSetup()->setPrintArea(
            "A1:F" .
            max(
                $lastDataRow,
                $headerRow
            )
        );
    }

    private function buildFranchiseKlopSheet(
        $sheet,
        string $cabang,
        string $jnsBank,
        string $startDate,
        string $endDate
    ) {
        /*
        |--------------------------------------------------------------------------
        | GRIDLINES
        |--------------------------------------------------------------------------
        */
    
        $sheet->setShowGridlines(false);
    
    
        /*
        |--------------------------------------------------------------------------
        | JUDUL
        |--------------------------------------------------------------------------
        */
    
        $sheet->mergeCells('A1:J1');
    
        $sheet->setCellValue(
            'A1',
            "MUTASI/RECEIPT MATCH - {$jnsBank}"
        );
    
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
    
            'alignment' => [
                'horizontal' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    
                'vertical' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
    
        $sheet
            ->getRowDimension(1)
            ->setRowHeight(24);
    
    
        /*
        |--------------------------------------------------------------------------
        | INFORMASI
        |--------------------------------------------------------------------------
        */
    
        $sheet->setCellValue(
            'A3',
            'Cabang'
        );
    
        $sheet->setCellValue(
            'B3',
            ': ' . $cabang
        );
    
    
        $sheet->setCellValue(
            'A4',
            'Jenis Bank'
        );
    
        $sheet->setCellValue(
            'B4',
            ': ' . $jnsBank
        );
    
    
        $sheet->setCellValue(
            'A5',
            'Periode'
        );
    
    
        try {
    
            $displayStartDate =
                \Carbon\Carbon::parse($startDate)
                    ->format('d M y');
    
            $displayEndDate =
                \Carbon\Carbon::parse($endDate)
                    ->format('d M y');
    
            $displayPeriod =
                $displayStartDate .
                ' s/d ' .
                $displayEndDate;
    
        } catch (\Throwable $e) {
    
            $displayPeriod =
                $startDate .
                ' s/d ' .
                $endDate;
        }
    
    
        $sheet->setCellValue(
            'B5',
            ': ' . $displayPeriod
        );
    
    
        $sheet
            ->getStyle('A3:B5')
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
    
                'alignment' => [
                    'vertical' =>
                        \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);
    
    
        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */
    
        $headerRow = 7;
    
        $headers = [
            'Kd Toko',
            'No Rek',
            'Tanggal',
            'Remark1',
            'CR',
            'Paid By',
            'Activity',
            'Comments',
            'Receipt Number',
            'Reff',
        ];
    
    
        $sheet->fromArray(
            $headers,
            null,
            "A{$headerRow}"
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | STYLE HEADER
        |--------------------------------------------------------------------------
        */
    
        $this->styleHeader(
            $sheet,
            "A{$headerRow}:J{$headerRow}"
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | QUERY MUTASI
        |--------------------------------------------------------------------------
        |
        | Ketentuan:
        |
        | - cabang sesuai
        | - jns_bank sesuai
        | - site bukan REG
        | - periode sesuai
        | - cr != 0
        | - reff terisi
        |
        */
    
        $mutasiQuery = DB::table(
            'mutasi_detail_frc as m'
        )
    
    
            /*
            |--------------------------------------------------------------------------
            | JOIN BANK
            |--------------------------------------------------------------------------
            */
    
            ->join(
                'bank as b',
                function ($join) use (
                    $cabang,
                    $jnsBank
                ) {
    
                    $join->on(
                        'b.no_rek',
                        '=',
                        'm.no_rek'
                    );
    
                    $join->where(
                        'b.cabang',
                        '=',
                        $cabang
                    );
    
                    $join->where(
                        'b.jns_bank',
                        '=',
                        $jnsBank
                    );
    
                    $join->where(function ($query) {
    
                        $query
                            ->whereNull('b.site')
                            ->orWhere(
                                'b.site',
                                '<>',
                                'REG'
                            );
    
                    });
                }
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | FILTER CABANG
            |--------------------------------------------------------------------------
            */
    
            ->where(
                'm.cabang',
                '=',
                $cabang
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | FILTER PERIODE
            |--------------------------------------------------------------------------
            */
    
            ->whereBetween(
                'm.tgl',
                [
                    $startDate,
                    $endDate
                ]
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | HANYA MUTASI KREDIT
            |--------------------------------------------------------------------------
            */
    
            ->where(
                'm.cr',
                '!=',
                0
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | HANYA MUTASI YANG SUDAH KLOP
            |--------------------------------------------------------------------------
            */
    
            ->whereNotNull(
                'm.reff'
            )
    
            ->where(
                'm.reff',
                '<>',
                ''
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | SELECT MUTASI
            |--------------------------------------------------------------------------
            */
    
            ->select([
    
                /*
                |--------------------------------------------------------------------------
                | NO REKENING
                |--------------------------------------------------------------------------
                */
    
                'm.no_rek',
    
    
                /*
                |--------------------------------------------------------------------------
                | TANGGAL
                |--------------------------------------------------------------------------
                */
    
                'm.tgl',
    
    
                /*
                |--------------------------------------------------------------------------
                | KD TOKO
                |--------------------------------------------------------------------------
                |
                | Diambil dari tabel bank
                |
                */
    
                'b.site as kd_toko',
    
    
                /*
                |--------------------------------------------------------------------------
                | REMARK1
                |--------------------------------------------------------------------------
                |
                | Gabungan:
                |
                | remark + remark1
                |
                */
    
                DB::raw("
                    TRIM(
                        CONCAT(
                            COALESCE(m.remark, ''),
                            CASE
                                WHEN
                                    COALESCE(m.remark, '') <> ''
                                    AND COALESCE(m.remark1, '') <> ''
                                THEN ' '
                                ELSE ''
                            END,
                            COALESCE(m.remark1, '')
                        )
                    ) as remark1
                "),
    
    
                /*
                |--------------------------------------------------------------------------
                | CR
                |--------------------------------------------------------------------------
                */
    
                'm.cr',
    
    
                /*
                |--------------------------------------------------------------------------
                | PENANDA BARIS
                |--------------------------------------------------------------------------
                */
    
                DB::raw(
                    "'MUTASI' as row_type"
                ),
    
    
                /*
                |--------------------------------------------------------------------------
                | FIELD RECEIPT
                |--------------------------------------------------------------------------
                |
                | Mutasi tidak memiliki data receipt.
                |
                */
    
                DB::raw(
                    "NULL as receipt_kd"
                ),
    
                DB::raw(
                    "NULL as activity"
                ),
    
                DB::raw(
                    "NULL as comments"
                ),
    
                DB::raw(
                    "NULL as receipt_number"
                ),
    
    
                /*
                |--------------------------------------------------------------------------
                | REFF MUTASI
                |--------------------------------------------------------------------------
                */
    
                'm.reff',
    
    
                /*
                |--------------------------------------------------------------------------
                | SORTING
                |--------------------------------------------------------------------------
                */
    
                'm.id as sort_id',
            ]);
    
    
        /*
        |--------------------------------------------------------------------------
        | QUERY RECEIPT
        |--------------------------------------------------------------------------
        |
        | PERBAIKAN UTAMA:
        |
        | JANGAN JOIN receipt langsung ke mutasi.
        |
        | Sebelumnya:
        |
        | receipt JOIN mutasi
        |
        | Akibat:
        |
        | 1 receipt
        | +
        | 4 mutasi dengan reff sama
        | =
        | 4 receipt
        |
        | Sekarang:
        |
        | receipt
        | WHERE EXISTS mutasi yang cocok
        |
        | Sehingga:
        |
        | 1 receipt = 1 baris.
        |
        */
    
        $receiptQuery = DB::table(
            'receipt as r'
        )
    
    
            /*
            |--------------------------------------------------------------------------
            | JOIN BANK
            |--------------------------------------------------------------------------
            |
            | Receipt dihubungkan ke rekening bank.
            |
            */
    
            ->join(
                'bank as b',
                function ($join) use (
                    $cabang,
                    $jnsBank
                ) {
    
                    $join->on(
                        'b.no_rek',
                        '=',
                        'r.remittance_bank_account'
                    );
    
                    $join->where(
                        'b.cabang',
                        '=',
                        $cabang
                    );
    
                    $join->where(
                        'b.jns_bank',
                        '=',
                        $jnsBank
                    );
    
                    $join->where(function ($query) {
    
                        $query
                            ->whereNull('b.site')
                            ->orWhere(
                                'b.site',
                                '<>',
                                'REG'
                            );
    
                    });
                }
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | FILTER TANGGAL RECEIPT
            |--------------------------------------------------------------------------
            */
    
            ->whereBetween(
                'r.receipt_date',
                [
                    $startDate,
                    $endDate
                ]
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | RECEIPT HARUS MEMPUNYAI PASANGAN MUTASI
            |--------------------------------------------------------------------------
            |
            | Menggunakan EXISTS.
            |
            | Ini bagian penting untuk mencegah duplicate receipt.
            |
            */
    
            ->whereExists(function ($query) use (
                $cabang,
                $startDate,
                $endDate
            ) {
    
                $query
                    ->select(
                        DB::raw('1')
                    )
    
                    ->from(
                        'mutasi_detail_frc as mx'
                    )
    
                    ->whereColumn(
                        'mx.reff',
                        'r.reff'
                    )
    
                    ->whereColumn(
                        'mx.no_rek',
                        'r.remittance_bank_account'
                    )
    
                    /*
                    |--------------------------------------------------------------------------
                    | CABANG MUTASI
                    |--------------------------------------------------------------------------
                    */
    
                    ->where(
                        'mx.cabang',
                        '=',
                        $cabang
                    )
    
                    /*
                    |--------------------------------------------------------------------------
                    | PERIODE MUTASI
                    |--------------------------------------------------------------------------
                    */
    
                    ->whereBetween(
                        'mx.tgl',
                        [
                            $startDate,
                            $endDate
                        ]
                    )
    
                    /*
                    |--------------------------------------------------------------------------
                    | HANYA KREDIT
                    |--------------------------------------------------------------------------
                    */
    
                    ->where(
                        'mx.cr',
                        '!=',
                        0
                    )
    
                    /*
                    |--------------------------------------------------------------------------
                    | REFF HARUS TERISI
                    |--------------------------------------------------------------------------
                    */
    
                    ->whereNotNull(
                        'mx.reff'
                    )
    
                    ->where(
                        'mx.reff',
                        '<>',
                        ''
                    );
            })
    
    
            /*
            |--------------------------------------------------------------------------
            | SELECT RECEIPT
            |--------------------------------------------------------------------------
            */
    
            ->select([
    
                /*
                |--------------------------------------------------------------------------
                | NO REKENING
                |--------------------------------------------------------------------------
                */
    
                'r.remittance_bank_account as no_rek',
    
    
                /*
                |--------------------------------------------------------------------------
                | TANGGAL RECEIPT
                |--------------------------------------------------------------------------
                */
    
                'r.receipt_date as tgl',
    
    
                /*
                |--------------------------------------------------------------------------
                | KD TOKO
                |--------------------------------------------------------------------------
                */
    
                'b.site as kd_toko',
    
    
                /*
                |--------------------------------------------------------------------------
                | REMARK1
                |--------------------------------------------------------------------------
                |
                | Receipt:
                | Remark1 = comments
                |
                */
    
                DB::raw(
                    "COALESCE(r.comments, '') as remark1"
                ),
    
    
                /*
                |--------------------------------------------------------------------------
                | RECEIPT AMOUNT
                |--------------------------------------------------------------------------
                |
                | Dibalik:
                |
                | positif -> negatif
                | negatif -> positif
                |
                */
    
                DB::raw(
                    "(-1 * COALESCE(r.receipt_amount, 0)) as cr"
                ),
    
    
                /*
                |--------------------------------------------------------------------------
                | PENANDA BARIS
                |--------------------------------------------------------------------------
                */
    
                DB::raw(
                    "'RECEIPT' as row_type"
                ),
    
    
                /*
                |--------------------------------------------------------------------------
                | RECEIPT KD / BANK
                |--------------------------------------------------------------------------
                */
    
                DB::raw(
                    "COALESCE(b.site, '') as receipt_kd"
                ),
    
    
                /*
                |--------------------------------------------------------------------------
                | ACTIVITY
                |--------------------------------------------------------------------------
                */
    
                DB::raw(
                    "COALESCE(r.activity, '') as activity"
                ),
    
    
                /*
                |--------------------------------------------------------------------------
                | COMMENTS
                |--------------------------------------------------------------------------
                */
    
                DB::raw(
                    "COALESCE(r.comments, '') as comments"
                ),
    
    
                /*
                |--------------------------------------------------------------------------
                | RECEIPT NUMBER
                |--------------------------------------------------------------------------
                |
                | HANYA untuk baris receipt.
                |
                */
    
                DB::raw(
                    "COALESCE(r.receipt_number, '') as receipt_number"
                ),
    
    
                /*
                |--------------------------------------------------------------------------
                | REFF
                |--------------------------------------------------------------------------
                |
                | Kolom J.
                |
                */
    
                DB::raw(
                    "COALESCE(r.reff, '') as reff"
                ),
    
    
                /*
                |--------------------------------------------------------------------------
                | SORTING
                |--------------------------------------------------------------------------
                */
    
                'r.id as sort_id',
            ]);
    
    
        /*
        |--------------------------------------------------------------------------
        | GABUNG MUTASI + RECEIPT
        |--------------------------------------------------------------------------
        */
    
        $query = $mutasiQuery
            ->unionAll(
                $receiptQuery
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | QUERY FINAL
        |--------------------------------------------------------------------------
        |
        | Sorting:
        |
        | 1. No Rek
        | 2. Tanggal
        | 3. Mutasi / Receipt
        | 4. ID
        |
        */
    
        $rows = DB::query()
            ->fromSub(
                $query,
                'data'
            )
    
            ->orderBy(
                'no_rek',
                'asc'
            )
    
            ->orderBy(
                'tgl',
                'asc'
            )
    
            /*
            |--------------------------------------------------------------------------
            | MUTASI DULU, RECEIPT SETELAHNYA
            |--------------------------------------------------------------------------
            */
    
            ->orderByRaw("
                CASE
                    WHEN row_type = 'MUTASI' THEN 1
                    WHEN row_type = 'RECEIPT' THEN 2
                    ELSE 3
                END
            ")
    
            ->orderBy(
                'sort_id',
                'asc'
            )
    
            ->get();
    
    
        /*
        |--------------------------------------------------------------------------
        | TULIS DATA KE EXCEL
        |--------------------------------------------------------------------------
        */
    
        $row = $headerRow + 1;
    
    
        foreach ($rows as $item) {
    
            /*
            |--------------------------------------------------------------------------
            | DATA MUTASI
            |--------------------------------------------------------------------------
            |
            | I = Receipt Number -> KOSONG
            | J = Reff -> m.reff
            |
            */
    
            if ($item->row_type === 'MUTASI') {
    
                $data = [
                    $item->kd_toko,
                    null,
                    null,
                    $item->remark1,
                    $item->cr,
                    '',
                    '',
                    '',
                    '',
                    $item->reff,
                ];
    
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | DATA RECEIPT
            |--------------------------------------------------------------------------
            |
            | I = Receipt Number
            | J = Reff
            |
            */
    
            else {
    
                $data = [
                    $item->kd_toko,
                    null,
                    null,
                    $item->remark1,
                    $item->cr,
                    $item->receipt_kd,
                    $item->activity,
                    $item->comments,
                    $item->receipt_number,
                    $item->reff,
                ];
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | TULIS DATA
            |--------------------------------------------------------------------------
            */
    
            $sheet->fromArray(
                $data,
                null,
                "A{$row}"
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | NO REKENING
            |--------------------------------------------------------------------------
            |
            | Dipaksa menjadi STRING.
            |
            | Ini mencegah:
            |
            | 0300842950
            |
            | menjadi:
            |
            | 3.00843E+08
            |
            */
    
            $sheet->setCellValueExplicit(
                "B{$row}",
                (string) $item->no_rek,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | TANGGAL
            |--------------------------------------------------------------------------
            |
            | Simpan sebagai tanggal Excel.
            |
            */
    
            try {
    
                $excelDate =
                    \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
                        \Carbon\Carbon::parse(
                            $item->tgl
                        )
                    );
    
                $sheet->setCellValue(
                    "C{$row}",
                    $excelDate
                );
    
            } catch (\Throwable $e) {
    
                $sheet->setCellValue(
                    "C{$row}",
                    $item->tgl
                );
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | WARNA BARIS
            |--------------------------------------------------------------------------
            |
            | MUTASI  = HITAM
            | RECEIPT = MERAH
            |
            */
    
            if (
                $item->row_type === 'RECEIPT'
            ) {
    
                $sheet
                    ->getStyle(
                        "A{$row}:J{$row}"
                    )
                    ->getFont()
                    ->getColor()
                    ->setARGB(
                        'FFFF0000'
                    );
    
            } else {
    
                $sheet
                    ->getStyle(
                        "A{$row}:J{$row}"
                    )
                    ->getFont()
                    ->getColor()
                    ->setARGB(
                        'FF000000'
                    );
            }
    
    
            $row++;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | DATA TERAKHIR
        |--------------------------------------------------------------------------
        */
    
        $lastDataRow =
            $row - 1;
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT DATA
        |--------------------------------------------------------------------------
        */
    
        if (
            $lastDataRow >=
            $headerRow + 1
        ) {
    
            /*
            |--------------------------------------------------------------------------
            | FORMAT TANGGAL
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "C{$headerRow}:C{$lastDataRow}"
                )
                ->getNumberFormat()
                ->setFormatCode(
                    'dd-mmm-yy'
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | NO REKENING
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "B{$headerRow}:B{$lastDataRow}"
                )
                ->getNumberFormat()
                ->setFormatCode(
                    'General'
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | FORMAT CR
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "E{$headerRow}:E{$lastDataRow}"
                )
                ->getNumberFormat()
                ->setFormatCode(
                    '#,##0'
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | ALIGNMENT VERTICAL
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "A{$headerRow}:J{$lastDataRow}"
                )
                ->getAlignment()
                ->setVertical(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | ALIGNMENT A-C
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "A{$headerRow}:C{$lastDataRow}"
                )
                ->getAlignment()
                ->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | ALIGNMENT CR
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "E{$headerRow}:E{$lastDataRow}"
                )
                ->getAlignment()
                ->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | BORDER
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "A{$headerRow}:J{$lastDataRow}"
                )
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | WRAP TEXT
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "A{$headerRow}:J{$lastDataRow}"
                )
                ->getAlignment()
                ->setWrapText(true);
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | COLUMN WIDTH
        |--------------------------------------------------------------------------
        */
    
        $widths = [
    
            'A' => 12,
    
            /*
            | No Rek dibuat lebar agar
            | nomor rekening terlihat utuh.
            */
    
            'B' => 24,
    
            'C' => 15,
    
            'D' => 55,
    
            'E' => 18,
    
            'F' => 18,
    
            'G' => 35,
    
            'H' => 45,
    
            'I' => 30,
    
            'J' => 25,
        ];
    
    
        foreach (
            $widths as $column => $width
        ) {
    
            $sheet
                ->getColumnDimension($column)
                ->setWidth($width);
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | FREEZE HEADER
        |--------------------------------------------------------------------------
        */
    
        $sheet->freezePane(
            'A8'
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | AUTOFILTER
        |--------------------------------------------------------------------------
        */
    
        $sheet->setAutoFilter(
            "A7:J{$lastDataRow}"
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | PAGE SETUP
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getPageSetup()
            ->setOrientation(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
            );
    
        $sheet
            ->getPageSetup()
            ->setPaperSize(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4
            );
    
        $sheet
            ->getPageSetup()
            ->setFitToWidth(1);
    
        $sheet
            ->getPageSetup()
            ->setFitToHeight(0);
    
    
        /*
        |--------------------------------------------------------------------------
        | MARGIN
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getPageMargins()
            ->setTop(0.25);
    
        $sheet
            ->getPageMargins()
            ->setBottom(0.25);
    
        $sheet
            ->getPageMargins()
            ->setLeft(0.25);
    
        $sheet
            ->getPageMargins()
            ->setRight(0.25);
    
    
        /*
        |--------------------------------------------------------------------------
        | PRINT AREA
        |--------------------------------------------------------------------------
        */
    
        $sheet
            ->getPageSetup()
            ->setPrintArea(
                "A1:J" .
                max(
                    $lastDataRow,
                    $headerRow
                )
            );
    }

    private function styleHeader(
        $sheet,
        string $range
    ) {
    
        $sheet->getStyle($range)->applyFromArray([
    
            'font' => [
                'bold' => true,
                'color' => [
                    'rgb' => 'FFFFFF'
                ],
            ],
    
            'fill' => [
                'fillType' =>
                    Fill::FILL_SOLID,
    
                'startColor' => [
                    'rgb' => '4472C4'
                ],
            ],
    
            'alignment' => [
                'horizontal' =>
                    Alignment::HORIZONTAL_CENTER,
    
                'vertical' =>
                    Alignment::VERTICAL_CENTER,
    
                'wrapText' => true,
            ],
    
            'borders' => [
    
                'allBorders' => [
    
                    'borderStyle' =>
                        Border::BORDER_THIN,
    
                    'color' => [
                        'rgb' => 'D9E1F2'
                    ],
                ],
            ],
        ]);
    }

    private function excelColumn(int $number): string
    {
        $column = '';

        while ($number > 0) {

            $mod =
                ($number - 1) % 26;

            $column =
                chr(65 + $mod) . $column;

            $number =
                intdiv(
                    $number - $mod,
                    26
                );
        }

        return $column;
    }

    private function makeSheetName(
        string $name,
        Spreadsheet $spreadsheet
    ): string {
    
        /*
        |--------------------------------------------------------------------------
        | HAPUS KARAKTER TERLARANG
        |--------------------------------------------------------------------------
        */
    
        $name = preg_replace(
            '/[\\\\\/\?\*\[\]\:]/',
            '',
            $name
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | MAKSIMUM 31 KARAKTER
        |--------------------------------------------------------------------------
        */
    
        $name =
            mb_substr(
                $name,
                0,
                31
            );
    
    
        if ($name === '') {
            $name = 'Sheet';
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | CEK DUPLIKAT
        |--------------------------------------------------------------------------
        */
    
        $original = $name;
    
        $counter = 1;
    
    
        while (
            $spreadsheet
                ->getSheetByName($name)
                !== null
        ) {
    
            $suffix =
                ' ' . $counter;
    
    
            $name =
                mb_substr(
                    $original,
                    0,
                    31 - mb_strlen($suffix)
                )
                . $suffix;
    
    
            $counter++;
        }
    
    
        return $name;
    }

    private function getPeriodById(
        string $cabang,
        int $periodId
    ) {
        return DB::table('periode')
            ->where('id', $periodId)
            ->where('Cabang', $cabang)
            ->where('kategori', 'Mutasi')
            ->first();
    }

    public function getRekeningRekonsiliasi(Request $request)
    {
        $request->validate([
            'cabang' => 'required|string|max:50',
            'type'   => 'required|in:FRC,REG',
        ]);

        $cabang = trim($request->input('cabang'));
        $type = strtoupper(trim($request->input('type')));

        /*
        |--------------------------------------------------------------------------
        | FRANCHISE
        |--------------------------------------------------------------------------
        */

        if ($type === 'FRC') {

            $accounts = DB::table('bank')
                ->where('cabang', $cabang)
                ->whereNotNull('jns_bank')
                ->where('site', '<>', 'REG')
                ->whereRaw("TRIM(jns_bank) <> ''")
                ->select('jns_bank')
                ->distinct()
                ->orderBy('jns_bank')
                ->pluck('jns_bank');

        }

        /*
        |--------------------------------------------------------------------------
        | REGULER
        |--------------------------------------------------------------------------
        */

        else {

            $accounts = DB::table('bank')
                ->where('cabang', $cabang)
                ->where('site', 'REG')
                ->whereNotNull('no_rek')
                ->whereRaw("TRIM(no_rek) <> ''")
                ->select('jns_bank', 'no_rek', 'akun')
                ->distinct()
                ->orderBy('jns_bank')
                ->orderBy('no_rek')
                ->get()
                ->map(function ($item) {
                    return [
                        'jns_bank' => trim(
                            (string) ($item->jns_bank ?? '')
                        ),
                        'no_rek' => trim(
                            (string) $item->no_rek
                        ),
                        'akun' => trim(
                            (string) $item->akun
                        ),
                    ];
                })
                ->values();

        }

        return response()->json([
            'success' => true,
            'type'    => $type,
            'cabang'  => $cabang,
            'count'   => $accounts->count(),
            'data'    => $accounts,
        ]);
    }
    
    public function reconcileReceipt(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. VALIDASI REQUEST
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'cabang'    => 'required|string|max:50',
            'period_id' => 'required|integer',
            'type'      => 'required|in:FRC,REG',
            'account'   => 'nullable|string|max:255',
        ]);

        $cabang   = trim($request->input('cabang'));
        $periodId = (int) $request->input('period_id');
        $type     = strtoupper(trim($request->input('type')));
        $account  = trim((string) $request->input('account', ''));

        /*
        |--------------------------------------------------------------------------
        | 2. JALUR FRC
        |--------------------------------------------------------------------------
        |
        | Pertahankan algoritma FRC yang lama.
        | Jangan mengubah reconcileReceiptFranchise().
        */

        if ($type === 'FRC') {
            try {
                return $this->reconcileReceiptFranchise(
                    $cabang,
                    $periodId,
                    $account
                );
            } catch (\Throwable $e) {
                Log::error('REKONSILIASI FRC ERROR', [
                    'cabang'    => $cabang,
                    'period_id' => $periodId,
                    'type'      => $type,
                    'account'   => $account,
                    'message'   => $e->getMessage(),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                    'trace'     => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data'    => [],
                    'summary' => $this->emptyReconciliationSummary(),
                ], 500);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. VALIDASI REKENING REG
        |--------------------------------------------------------------------------
        */

        if ($account === '' || strtoupper($account) === 'ALL') {
            return response()->json([
                'success' => false,
                'message' => 'Pilih satu rekening REG terlebih dahulu.',
                'data'    => [],
                'summary' => $this->emptyReconciliationSummary(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. VALIDASI INPUT METHODS
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'methods' => 'required|json',
        ]);

        $requestedMethods = json_decode(
            $request->input('methods'),
            true
        );

        if (
            !is_array($requestedMethods) ||
            empty($requestedMethods)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu metode rekonsiliasi REG.',
                'data'    => [],
                'summary' => $this->emptyReconciliationSummary(),
            ], 422);
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | 5. VALIDASI REKENING REG DAN SETTING
            |--------------------------------------------------------------------------
            */

            $bank = DB::table('bank')
                ->where('cabang', $cabang)
                ->where('no_rek', $account)
                ->where('site', 'REG')
                ->whereNotNull('jns_bank')
                ->first();

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rekening REG tidak ditemukan atau tidak valid.',
                    'data'    => [],
                    'summary' => $this->emptyReconciliationSummary(),
                ], 404);
            }

            $availableMethods = DB::table('setting')
                ->where('cabang', $cabang)
                ->where('category', 'rekon_methode')
                ->where('sub_category', $bank->jns_bank)
                ->get()
                ->keyBy('id');

            /*
            |--------------------------------------------------------------------------
            | 6. NORMALISASI DAN URUTKAN METODE
            |--------------------------------------------------------------------------
            */

            $methods = collect($requestedMethods)
                ->map(function ($item) {
                    if (!is_array($item)) {
                        return [
                            'id'    => 0,
                            'value' => '',
                            'order' => 0,
                        ];
                    }

                    return [
                        'id'    => (int) ($item['id'] ?? 0),
                        'value' => trim((string) ($item['value'] ?? '')),
                        'order' => (int) ($item['order'] ?? 0),
                    ];
                })
                ->sortBy('order')
                ->values();

            /*
            |--------------------------------------------------------------------------
            | 7. VALIDASI METODE YANG DIDUKUNG
            |--------------------------------------------------------------------------
            */

            $supportedMethods = [
                'date_amount',
                'sls_date_site_amount',
                'sls_sum_date_site_amount',
                'sls_sum_date_site_amount_reverse',
                'sls_sum_date_site_amount_many',
                'ats_date_amount',
                'virtual_transactions',
                'sls_sum_date_activity_amount_reverse',
                'date_amount_remark',
                'sum_date_amount_remark_reverse',
                'fas_date_site_amount',
                'fas_sum_date_site_amount_reverse',
            ];

            $seenIds = [];
            $seenOrders = [];

            foreach ($methods as $method) {
                $id    = $method['id'];
                $value = $method['value'];
                $order = $method['order'];

                if (
                    $id <= 0 ||
                    isset($seenIds[$id]) ||
                    !$availableMethods->has($id)
                ) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Metode tidak valid atau tidak tersedia pada setting.',
                        'data'    => [],
                        'summary' => $this->emptyReconciliationSummary(),
                    ], 422);
                }

                if ($order < 0 || isset($seenOrders[$order])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Urutan metode tidak valid atau terdapat urutan ganda.',
                        'data'    => [],
                        'summary' => $this->emptyReconciliationSummary(),
                    ], 422);
                }

                $seenIds[$id] = true;
                $seenOrders[$order] = true;

                $configuredValue = trim(
                    (string) $availableMethods->get($id)->value
                );

                if ($configuredValue !== $value) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nama metode tidak sesuai dengan konfigurasi database.',
                        'data'    => [],
                        'summary' => $this->emptyReconciliationSummary(),
                    ], 422);
                }

                if (!in_array($value, $supportedMethods, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Metode rekonsiliasi REG '{$value}' belum didukung.",
                        'data'    => [],
                        'summary' => $this->emptyReconciliationSummary(),
                    ], 422);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 8. JALANKAN SERVICE REG BERDASARKAN URUTAN METODE
            |--------------------------------------------------------------------------
            |
            | Catatan:
            | Metode berikutnya akan memproses data yang masih belum memiliki reff,
            | sesuai filter yang terdapat di RegularReconciliationService.
            */

            $service = app(RegularReconciliationService::class);

            $results = [];

            foreach ($methods as $method) {
                switch ($method['value']) {
                    case 'date_amount':
                        $result = $service->reconcileDateAmount(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;

                    case 'sls_date_site_amount':
                        $result = $service->reconcileSlsDateSiteAmount(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;

                    case 'sls_sum_date_site_amount':
                        $result = $service->reconcileSlsSumDateSiteAmount(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;

                    case 'sls_sum_date_site_amount_reverse':
                        $result = $service->reconcileSlsSumDateSiteAmountReverse(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;

                    case 'sls_sum_date_site_amount_many':
                        $result = $service->reconcileSlsSumDateSiteAmountMany(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;
                        
                    case 'ats_date_amount':
                        $result = $service->reconcileAtsDateAmount(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;
                    
                    case 'virtual_transactions':
                        $result = $service->reconcileVirtualTransactions(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;
                    
                    case 'sls_sum_date_activity_amount_reverse':
                        $result = $service->reconcileSlsSumDateActivityAmountReverse(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;
                    
                    case 'date_amount_remark':
                        $result = $service->reconcileDateAmountRemark(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;
                    
                    case 'sum_date_amount_remark_reverse':
                        $result = $service->reconcileSumDateAmountRemarkReverse(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;

                    case 'fas_date_site_amount':
                        $result = $service->reconcileFasDateSiteAmount(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;

                    case 'fas_sum_date_site_amount_reverse':
                        $result = $service->reconcileFasSumDateSiteAmountReverse(
                            $cabang,
                            $periodId,
                            $account
                        );
                        break;
                        
                    default:
                        // Pengaman tambahan apabila metode tidak dikenal.
                        throw new \RuntimeException(
                            'Metode rekonsiliasi REG tidak didukung: '
                            . $method['value']
                        );
                }

                $results[] = [
                    'method' => $method['value'],
                    'order'  => $method['order'],
                    'result' => $result,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | 9. GABUNGKAN HASIL SELURUH METODE
            |--------------------------------------------------------------------------
            |
            | Hasil masing-masing metode tetap tersedia agar detail dan summary
            | per metode dapat diperiksa secara terpisah.
            */

            $totalMatches = collect($results)
                ->sum(fn ($item) =>
                    (int) ($item['result']['summary']['match'] ?? 0)
                );

            $totalAmbiguous = collect($results)
                ->sum(fn ($item) =>
                    (int) ($item['result']['summary']['ambiguous'] ?? 0)
                );

            $allMatches = collect($results)
                ->flatMap(fn ($item) =>
                    $item['result']['data']['matches'] ?? []
                )
                ->values();

            $allAmbiguous = collect($results)
                ->flatMap(fn ($item) =>
                    $item['result']['data']['ambiguous'] ?? []
                )
                ->values();

            /*
            * Ringkasan receipt/mutasi yang belum cocok diambil dari hasil metode
            * terakhir, sebab metode tersebut dijalankan setelah metode sebelumnya.
            */
            $lastResult = collect($results)->last()['result'] ?? null;

            $lastSummary = $lastResult['summary'] ?? [];

            return response()->json([
                'success' => true,
                'message' => 'Rekonsiliasi REG selesai.',
                'rekening' => $account,
                'periode' => $lastResult['periode'] ?? [],
                'method' => $methods->pluck('value')->all(),

                'data' => [
                    'matches' => $allMatches,
                    'ambiguous' => $allAmbiguous,
                    'unmatched_receipts' =>
                        $lastResult['data']['unmatched_receipts'] ?? [],
                    'unmatched_mutasi' =>
                        $lastResult['data']['unmatched_mutasi'] ?? [],
                    'method_results' => $results,
                ],

                'summary' => [
                    'total_receipt' =>
                        $lastSummary['total_receipt'] ?? 0,
                    'total_mutasi' =>
                        $lastSummary['total_mutasi'] ?? 0,
                    'match' => $totalMatches,
                    'ambiguous' => $totalAmbiguous,
                    'receipt_only' =>
                        $lastSummary['receipt_only'] ?? 0,
                    'mutasi_only' =>
                        $lastSummary['mutasi_only'] ?? 0,
                    'receipt_amount' =>
                        $lastSummary['receipt_amount'] ?? 0,
                    'mutasi_amount' =>
                        $lastSummary['mutasi_amount'] ?? 0,
                    'difference_amount' =>
                        $lastSummary['difference_amount'] ?? 0,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('REKONSILIASI REG ERROR', [
                'cabang'    => $cabang,
                'period_id' => $periodId,
                'account'   => $account,
                'methods'   => $requestedMethods ?? [],
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Rekonsiliasi REG gagal: ' . $e->getMessage(),
                'data'    => [],
                'summary' => $this->emptyReconciliationSummary(),
            ], 500);
        }
    }

    private function reconcileReceiptFranchise(
        string $cabang,
        int $periodId,
        string $account = ''
    ) {
        /*
        |--------------------------------------------------------------------------
        | 1. AMBIL PERIODE
        |--------------------------------------------------------------------------
        */
    
        $period = DB::table('periode')
            ->where('id', $periodId)
            ->first();
    
        if (!$period) {
    
            return response()->json([
                'success' => false,
                'message' => 'Periode tidak ditemukan.',
                'data' => [],
                'summary' => $this->emptyReconciliationSummary(),
            ], 404);
        }
    
    
        $tanggalAwal =
            $period->start_date;
    
        $tanggalAkhir =
            $period->end_date;
    
    
        $bankQuery = DB::table('bank')
    
            ->where(
                'cabang',
                $cabang
            )
    
            ->whereNotNull(
                'no_rek'
            )
    
            ->whereRaw(
                "TRIM(no_rek) <> ''"
            )
    
            ->whereNotNull(
                'jns_bank'
            )
    
            ->whereRaw(
                "TRIM(jns_bank) <> ''"
            )
    
            ->where(
                'jns_bank',
                '<>',
                'REG'
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | FILTER JENIS BANK
        |--------------------------------------------------------------------------
        */
    
        if (
            $account !== ''
            &&
            strtoupper(
                trim($account)
            ) !== 'ALL'
        ) {
    
            $bankQuery->where(
                'jns_bank',
                trim($account)
            );
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | AMBIL NO REKENING
        |--------------------------------------------------------------------------
        */
    
        $rekening = $bankQuery
    
            ->pluck(
                'no_rek'
            )
    
            ->map(
                function ($value) {
    
                    return trim(
                        (string) $value
                    );
                }
            )
    
            ->filter()
    
            ->unique()
    
            ->values()
    
            ->toArray();
    
    
        /*
        |--------------------------------------------------------------------------
        | TIDAK ADA REKENING
        |--------------------------------------------------------------------------
        */
    
        if (
            empty($rekening)
        ) {
    
            return response()->json([
    
                'success' =>
                    true,
    
                'message' =>
                    'Tidak ditemukan rekening untuk jenis bank tersebut.',
    
                'data' =>
                    [],
    
                'summary' =>
                    $this->emptyReconciliationSummary(),
    
            ]);
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | 2B. MAPPING REKENING -> JENIS BANK
        |--------------------------------------------------------------------------
        */
    
        $bankTypes = DB::table('bank')
    
            ->where(
                'cabang',
                $cabang
            )
    
            ->whereIn(
                'no_rek',
                $rekening
            )
    
            ->whereNotNull(
                'jns_bank'
            )
    
            ->whereRaw(
                "TRIM(jns_bank) <> ''"
            )
    
            ->where(
                'jns_bank',
                '<>',
                'REG'
            )
    
            ->select([
                'no_rek',
                'jns_bank',
            ])
    
            ->get()
    
            ->groupBy(
                function ($row) {
    
                    return trim(
                        (string)
                        $row->no_rek
                    );
                }
            )
    
            ->map(
                function ($rows) {
    
                    return $rows
    
                        ->pluck(
                            'jns_bank'
                        )
    
                        ->map(
                            function ($value) {
    
                                return trim(
                                    (string)
                                    $value
                                );
                            }
                        )
    
                        ->filter()
    
                        ->unique()
    
                        ->first();
                }
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | 3. AMBIL DATA RECEIPT
        |--------------------------------------------------------------------------
        |
        | Kriteria:
        |
        | - rekening termasuk rekening FRC
        | - gl_date berada dalam periode
        | - reff NULL / kosong
        |
        | STATUS DAN STATE IKUT DIAMBIL.
        |--------------------------------------------------------------------------
        */
    
        $receipts = DB::table('receipt')
    
            ->whereIn(
                'remittance_bank_account',
                $rekening
            )
    
            ->whereBetween(
                'gl_date',
                [
                    $tanggalAwal,
                    $tanggalAkhir
                ]
            )
    
            ->where(
                function ($query) {
    
                    $query
                        ->whereNull(
                            'reff'
                        )
                        ->orWhere(
                            'reff',
                            ''
                        );
                }
            )
            ->where(
                'receipt_status',
                '<>',
                'Reversed'
            )
            ->whereNull('note')
            ->select([
                'id',
                'remittance_bank_account',
                'receipt_number',
                'receipt_amount',
                'receipt_date',
                'gl_date',
                'receipt_type',
                'receipt_status',
                'receipt_state',
                'comments',
                'activity',
                'paid_by',
                'reff',
            ])
    
            ->orderBy(
                'gl_date'
            )
    
            ->orderBy(
                'remittance_bank_account'
            )
    
            ->orderBy(
                'id'
            )
    
            ->get()
    
            ->map(
                function ($row) {
    
                    /*
                    |--------------------------------------------------------------------------
                    | Rekening internal
                    |--------------------------------------------------------------------------
                    */
    
                    $row->no_rek =
                        trim(
                            (string)
                            (
                                $row->remittance_bank_account
                                ?? ''
                            )
                        );
    
    
                    /*
                    |--------------------------------------------------------------------------
                    | Tanggal internal
                    |--------------------------------------------------------------------------
                    */
    
                    $row->gldate =
                        $row->gl_date;
    
    
                    /*
                    |--------------------------------------------------------------------------
                    | Nominal internal
                    |--------------------------------------------------------------------------
                    |
                    | PENTING:
                    | Nilai negatif Receipt TIDAK diubah menjadi positif.
                    |
                    | Contoh:
                    |
                    | 50.125
                    | -125
                    |
                    | harus tetap:
                    |
                    | 50125
                    | -125
                    |--------------------------------------------------------------------------
                    */
    
                    $row->nominal =
                        (float)
                        (
                            $row->receipt_amount
                            ?? 0
                        );
    
    
                    /*
                    |--------------------------------------------------------------------------
                    | REFF
                    |--------------------------------------------------------------------------
                    */
    
                    $row->reff =
                        $row->reff
                        ??
                        null;
    
    
                    /*
                    |--------------------------------------------------------------------------
                    | STATUS
                    |--------------------------------------------------------------------------
                    */
    
                    $row->receipt_status =
                        $row->receipt_status
                        !== null
                            ? trim(
                                (string)
                                $row->receipt_status
                            )
                            : null;
    
    
                    /*
                    |--------------------------------------------------------------------------
                    | STATE
                    |--------------------------------------------------------------------------
                    */
    
                    $row->receipt_state =
                        $row->receipt_state
                        !== null
                            ? trim(
                                (string)
                                $row->receipt_state
                            )
                            : null;
    
    
                    return $row;
                }
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | TIDAK ADA RECEIPT
        |--------------------------------------------------------------------------
        */
    
        if (
            $receipts->isEmpty()
        ) {
    
            return response()->json([
    
                'success' =>
                    true,
    
                'message' =>
                    'Tidak ada data receipt yang belum direkonsiliasi.',
    
                'data' =>
                    [],
    
                'summary' =>
                    $this->emptyReconciliationSummary(),
    
            ]);
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | 4. KELOMPOKKAN RECEIPT
        |--------------------------------------------------------------------------
        |
        | GROUP:
        |
        | no_rek + gl_date
        |
        */
    
        $receiptGroups =
            $receipts->groupBy(
                function ($row) {
    
                    return trim(
                        (string)
                        $row->no_rek
                    )
                    . '|'
                    .
                    $row->gldate;
                }
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | 5. AMBIL MUTASI FRC
        |--------------------------------------------------------------------------
        |
        | HANYA KREDIT.
        |
        | DB TIDAK IKUT.
        |--------------------------------------------------------------------------
        */
    
        $mutasi = DB::table(
            'mutasi_detail_frc'
        )
    
            ->where(
                'cabang',
                $cabang
            )
    
            ->whereIn(
                'no_rek',
                $rekening
            )
    
            ->whereBetween(
                'tgl',
                [
                    $tanggalAwal,
                    $tanggalAkhir
                ]
            )
    
            /*
            |--------------------------------------------------------------------------
            | HANYA CR
            |--------------------------------------------------------------------------
            */
    
            ->where(
                'cr',
                '>',
                0
            )
    
            /*
            |--------------------------------------------------------------------------
            | HANYA BELUM DIREKONSILIASI
            |--------------------------------------------------------------------------
            */
    
            ->where(
                function ($query) {
    
                    $query
                        ->whereNull(
                            'reff'
                        )
                        ->orWhere(
                            'reff',
                            ''
                        );
                }
            )
    
            ->select([
                'id',
                'cabang',
                'no_rek',
                'tgl',
                'cr',
                'reff',
                'trx_code',
                'remark',
                'remark1',
            ])
    
            ->orderBy(
                'tgl'
            )
    
            ->orderBy(
                'no_rek'
            )
    
            ->orderBy(
                'id'
            )
    
            ->get()
    
            ->map(
                function ($row)
                use (
                    $bankTypes
                ) {
    
                    $row->no_rek =
                        trim(
                            (string)
                            (
                                $row->no_rek
                                ?? ''
                            )
                        );
    
    
                    $row->cr =
                        (float)
                        (
                            $row->cr
                            ?? 0
                        );
    
    
                    $row->reff =
                        $row->reff
                        ??
                        null;
    
    
                    /*
                    |--------------------------------------------------------------------------
                    | JENIS BANK
                    |--------------------------------------------------------------------------
                    */
    
                    $row->jns_bank =
                        $bankTypes->get(
                            $row->no_rek
                        );
    
    
                    return $row;
                }
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | 6. KELOMPOKKAN MUTASI
        |--------------------------------------------------------------------------
        */
    
        $mutasiGroups =
            $mutasi->groupBy(
                function ($row) {
    
                    return trim(
                        (string)
                        $row->no_rek
                    )
                    . '|'
                    .
                    $row->tgl;
                }
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | HASIL
        |--------------------------------------------------------------------------
        */
    
        $hasil = [];
    
        $matchedCount = 0;
    
        $receiptOnlyCount = 0;
    
        $mutasiOnlyCount = 0;
    
        $nominalDifferentCount = 0;
    
        $totalReceiptAmount = 0;
    
        $totalMutasiAmount = 0;
    
    
        /*
        |--------------------------------------------------------------------------
        | 7. PROSES PER REKENING + TANGGAL
        |--------------------------------------------------------------------------
        */
    
        foreach (
            $receiptGroups
            as $groupKey => $receiptGroup
        ) {
    
            [
                $groupNoRek,
                $groupDate
            ] = explode(
                '|',
                $groupKey,
                2
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | Ambil mutasi rekening + tanggal
            |--------------------------------------------------------------------------
            */
    
            $mutasiGroup =
                $mutasiGroups->get(
                    $groupKey,
                    collect()
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | TIDAK ADA MUTASI
            |--------------------------------------------------------------------------
            */
    
            if (
                $mutasiGroup->isEmpty()
            ) {
    
                foreach (
                    $receiptGroup
                    as $receipt
                ) {
    
                    $amount =
                        (float)
                        $receipt->nominal;
    
    
                    $receiptOnlyCount++;
    
    
                    $totalReceiptAmount +=
                        $amount;
    
    
                    $hasil[] = [
    
                        'status' =>
                            'RECEIPT_ONLY',
    
                        'receipt_id' =>
                            $receipt->id,
    
                        'mutasi_id' =>
                            null,
    
                        'no_rek' =>
                            $groupNoRek,
    
                        'tanggal' =>
                            $groupDate,
    
                        'jns_bank' =>
                            $bankTypes->get(
                                $groupNoRek
                            ),
    
                        'receipt_number' =>
                            $receipt->receipt_number,
    
                        'receipt_date' =>
                            $receipt->receipt_date,
    
                        'gl_date' =>
                            $receipt->gl_date,
    
                        'remittance_bank_account' =>
                            $receipt->remittance_bank_account,
    
                        'receipt_amount' =>
                            $amount,
    
                        'receipt_status' =>
                            $receipt->receipt_status,
    
                        'receipt_state' =>
                            $receipt->receipt_state,
    
                        'receipt_type' =>
                            $receipt->receipt_type
                            ?? null,
    
                        'comments' =>
                            $receipt->comments
                            ?? null,
    
                        'activity' =>
                            $receipt->activity
                            ?? null,
    
                        'paid_by' =>
                            $receipt->paid_by
                            ?? null,
    
                        'mutasi_amount' =>
                            0,
    
                        'difference' =>
                            $amount,
    
                        'amount_match' =>
                            false,
    
                        'account_match' =>
                            true,
    
                        'receipt_reff' =>
                            $receipt->reff,
    
                        'mutation_reff' =>
                            null,
    
                        'reff' =>
                            $receipt->reff,
                    ];
                }
    
                continue;
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | COLLECTION KERJA
            |--------------------------------------------------------------------------
            */
    
            $remainingReceipts =
                $receiptGroup
                    ->values()
                    ->map(
                        function ($item) {
    
                            $item->nominal =
                                (float)
                                $item->nominal;
    
                            return $item;
                        }
                    );
    
    
            $remainingMutasi =
                $mutasiGroup
                    ->values()
                    ->map(
                        function ($item) {
    
                            $item->cr =
                                (float)
                                $item->cr;
    
                            return $item;
                        }
                    );
    
    
            /*
            |--------------------------------------------------------------------------
            | TAHAP 1
            |--------------------------------------------------------------------------
            | 1 RECEIPT VS 1 MUTASI
            |--------------------------------------------------------------------------
            */
    
            foreach (
                $remainingReceipts->values()
                as $receipt
            ) {
    
                if (
                    !empty(
                        $receipt->reff
                    )
                ) {
                    continue;
                }
    
    
                $foundMutasiIndex =
                    null;
    
    
                foreach (
                    $remainingMutasi->values()
                    as $mutasiIndex => $mutasiRow
                ) {
    
                    if (
                        !empty(
                            $mutasiRow->reff
                        )
                    ) {
                        continue;
                    }
    
    
                    if (
                        $this->moneyEquals(
                            $receipt->nominal,
                            $mutasiRow->cr
                        )
                    ) {
    
                        $foundMutasiIndex =
                            $mutasiIndex;
    
                        break;
                    }
                }
    
    
                if (
                    $foundMutasiIndex === null
                ) {
                    continue;
                }
    
    
                $mutasiRow =
                    $remainingMutasi[
                        $foundMutasiIndex
                    ];
    
    
                $ref =
                    'FRC-' .
                    $mutasiRow->id;
    
    
                DB::transaction(
                    function () use (
                        $receipt,
                        $mutasiRow,
                        $ref
                    ) {
    
                        DB::table(
                            'mutasi_detail_frc'
                        )
                            ->where(
                                'id',
                                $mutasiRow->id
                            )
                            ->where(
                                'cabang',
                                $mutasiRow->cabang
                            )
                            ->where(
                                function ($query) {
    
                                    $query
                                        ->whereNull(
                                            'reff'
                                        )
                                        ->orWhere(
                                            'reff',
                                            ''
                                        );
                                }
                            )
                            ->update([
                                'reff' =>
                                    $ref,
                            ]);
    
    
                        DB::table(
                            'receipt'
                        )
                            ->where(
                                'id',
                                $receipt->id
                            )
                            ->where(
                                function ($query) {
    
                                    $query
                                        ->whereNull(
                                            'reff'
                                        )
                                        ->orWhere(
                                            'reff',
                                            ''
                                        );
                                }
                            )
                            ->update([
                                'reff' =>
                                    $ref,
                            ]);
                    }
                );
    
    
                $receipt->reff =
                    $ref;
    
                $mutasiRow->reff =
                    $ref;
    
    
                $remainingReceipts =
                    $remainingReceipts
                        ->reject(
                            function ($item)
                            use ($receipt) {
    
                                return
                                    $item->id ==
                                    $receipt->id;
                            }
                        )
                        ->values();
    
    
                $remainingMutasi =
                    $remainingMutasi
                        ->reject(
                            function ($item)
                            use ($mutasiRow) {
    
                                return
                                    $item->id ==
                                    $mutasiRow->id;
                            }
                        )
                        ->values();
    
    
                $matchedCount++;
    
    
                $receiptAmount =
                    (float)
                    $receipt->nominal;
    
    
                $mutasiAmount =
                    (float)
                    $mutasiRow->cr;
    
    
                $totalReceiptAmount +=
                    $receiptAmount;
    
    
                $totalMutasiAmount +=
                    $mutasiAmount;
    
    
                $hasil[] = [
    
                    'status' =>
                        'MATCH',
    
                    'receipt_id' =>
                        $receipt->id,
    
                    'mutasi_id' =>
                        $mutasiRow->id,
    
                    'no_rek' =>
                        $groupNoRek,
    
                    'tanggal' =>
                        $groupDate,
    
                    'jns_bank' =>
                        $mutasiRow->jns_bank
                        ??
                        $bankTypes->get(
                            $groupNoRek
                        ),
    
                    'receipt_number' =>
                        $receipt->receipt_number,
    
                    'receipt_date' =>
                        $receipt->receipt_date,
    
                    'gl_date' =>
                        $receipt->gl_date,
    
                    'remittance_bank_account' =>
                        $receipt->remittance_bank_account,
    
                    'receipt_amount' =>
                        $receiptAmount,
    
                    'receipt_status' =>
                        $receipt->receipt_status,
    
                    'receipt_state' =>
                        $receipt->receipt_state,
    
                    'receipt_type' =>
                        $receipt->receipt_type
                        ?? null,
    
                    'comments' =>
                        $receipt->comments
                        ?? null,
    
                    'activity' =>
                        $receipt->activity
                        ?? null,
    
                    'paid_by' =>
                        $receipt->paid_by
                        ?? null,
    
                    'mutation_number' =>
                        $mutasiRow->trx_code
                        ?? null,
    
                    'mutasi_date' =>
                        $mutasiRow->tgl,
    
                    'mutasi_amount' =>
                        $mutasiAmount,
    
                    'difference' =>
                        0,
    
                    'amount_match' =>
                        true,
    
                    'account_match' =>
                        true,
    
                    'description' =>
                        $mutasiRow->remark
                        ?? null,
    
                    'receipt_reff' =>
                        $ref,
    
                    'mutation_reff' =>
                        $ref,
    
                    'reff' =>
                        $ref,
                ];
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | TAHAP 2
            |--------------------------------------------------------------------------
            | 1 RECEIPT VS BANYAK MUTASI
            |--------------------------------------------------------------------------
            |
            | Receipt harus positif pada tahap ini karena mutasi CR
            | seluruhnya positif.
            |--------------------------------------------------------------------------
            */
    
            foreach (
                $remainingReceipts->values()
                as $receipt
            ) {
    
                $target =
                    (float)
                    $receipt->nominal;
    
    
                /*
                |--------------------------------------------------------------------------
                | Receipt negatif tidak diproses pada tahap 2.
                |
                | Receipt negatif akan diproses pada tahap 3
                | sebagai bagian dari kombinasi Receipt.
                |--------------------------------------------------------------------------
                */
    
                if (
                    $target <= 0
                ) {
                    continue;
                }
    
    
                $combination =
                    $this->findSubsetBySum(
                        $remainingMutasi
                            ->values()
                            ->all(),
                        $target,
                        'cr'
                    );
    
    
                if (
                    !$combination
                ) {
                    continue;
                }
    
    
                $selectedMutasi =
                    collect(
                        $combination
                    );
    
    
                $mutasiIds =
                    $selectedMutasi
                        ->pluck('id')
                        ->values()
                        ->toArray();
    
    
                if (
                    empty($mutasiIds)
                ) {
                    continue;
                }
    
    
                $ref =
                    'FRC-' .
                    $selectedMutasi
                        ->first()
                        ->id;
    
    
                DB::transaction(
                    function () use (
                        $receipt,
                        $selectedMutasi,
                        $ref
                    ) {
    
                        foreach (
                            $selectedMutasi
                            as $mutasiRow
                        ) {
    
                            DB::table(
                                'mutasi_detail_frc'
                            )
                                ->where(
                                    'id',
                                    $mutasiRow->id
                                )
                                ->where(
                                    'cabang',
                                    $mutasiRow->cabang
                                )
                                ->where(
                                    function ($query) {
    
                                        $query
                                            ->whereNull(
                                                'reff'
                                            )
                                            ->orWhere(
                                                'reff',
                                                ''
                                            );
                                    }
                                )
                                ->update([
                                    'reff' =>
                                        $ref,
                                ]);
                        }
    
    
                        DB::table(
                            'receipt'
                        )
                            ->where(
                                'id',
                                $receipt->id
                            )
                            ->where(
                                function ($query) {
    
                                    $query
                                        ->whereNull(
                                            'reff'
                                        )
                                        ->orWhere(
                                            'reff',
                                            ''
                                        );
                                }
                            )
                            ->update([
                                'reff' =>
                                    $ref,
                            ]);
                    }
                );
    
    
                $remainingReceipts =
                    $remainingReceipts
                        ->reject(
                            function ($item)
                            use ($receipt) {
    
                                return
                                    $item->id ==
                                    $receipt->id;
                            }
                        )
                        ->values();
    
    
                $remainingMutasi =
                    $remainingMutasi
                        ->reject(
                            function ($item)
                            use ($mutasiIds) {
    
                                return in_array(
                                    $item->id,
                                    $mutasiIds
                                );
                            }
                        )
                        ->values();
    
    
                $mutasiAmount =
                    $selectedMutasi
                        ->sum('cr');
    
    
                $matchedCount++;
    
    
                $totalReceiptAmount +=
                    $target;
    
    
                $totalMutasiAmount +=
                    $mutasiAmount;
    
    
                $hasil[] = [
    
                    'status' =>
                        'MATCH',
    
                    'receipt_id' =>
                        $receipt->id,
    
                    'mutasi_id' =>
                        $mutasiIds,
    
                    'no_rek' =>
                        $groupNoRek,
    
                    'tanggal' =>
                        $groupDate,
    
                    'jns_bank' =>
                        optional(
                            $selectedMutasi->first()
                        )->jns_bank
                        ??
                        $bankTypes->get(
                            $groupNoRek
                        ),
    
                    'receipt_number' =>
                        $receipt->receipt_number,
    
                    'receipt_date' =>
                        $receipt->receipt_date,
    
                    'gl_date' =>
                        $receipt->gl_date,
    
                    'remittance_bank_account' =>
                        $receipt->remittance_bank_account,
    
                    'receipt_amount' =>
                        $target,
    
                    'receipt_status' =>
                        $receipt->receipt_status,
    
                    'receipt_state' =>
                        $receipt->receipt_state,
    
                    'receipt_type' =>
                        $receipt->receipt_type
                        ?? null,
    
                    'comments' =>
                        $receipt->comments
                        ?? null,
    
                    'activity' =>
                        $receipt->activity
                        ?? null,
    
                    'paid_by' =>
                        $receipt->paid_by
                        ?? null,
    
                    'mutasi_amount' =>
                        $mutasiAmount,
    
                    'difference' =>
                        0,
    
                    'amount_match' =>
                        true,
    
                    'account_match' =>
                        true,
    
                    'receipt_reff' =>
                        $ref,
    
                    'mutation_reff' =>
                        $ref,
    
                    'reff' =>
                        $ref,
                ];
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | TAHAP 3
            |--------------------------------------------------------------------------
            | BANYAK RECEIPT VS 1 MUTASI
            |--------------------------------------------------------------------------
            |
            | INI BAGIAN YANG DIPERBAIKI.
            |
            | Contoh:
            |
            | Mutasi = 50.000
            |
            | Receipt:
            | +50.125
            | -125
            |
            | 50.125 + (-125) = 50.000
            |
            | Receipt negatif TIDAK DIBUANG.
            |--------------------------------------------------------------------------
            */
    
            foreach (
                $remainingMutasi->values()
                as $mutasiRow
            ) {
    
                $target =
                    (float)
                    $mutasiRow->cr;
    
    
                /*
                |--------------------------------------------------------------------------
                | Kandidat Receipt
                |--------------------------------------------------------------------------
                |
                | Semua Receipt selain 0 boleh menjadi kandidat:
                |
                | positif  -> boleh
                | negatif  -> boleh
                |
                |--------------------------------------------------------------------------
                */
    
                $candidates = [];
    
    
                foreach (
                    $remainingReceipts->values()
                    as $receiptCandidate
                ) {
    
                    if (
                        !empty(
                            $receiptCandidate->reff
                        )
                    ) {
                        continue;
                    }
    
    
                    $value =
                        (float)
                        (
                            $receiptCandidate->nominal
                            ?? 0
                        );
    
    
                    /*
                    |--------------------------------------------------------------------------
                    | Abaikan hanya nominal 0
                    |--------------------------------------------------------------------------
                    */
    
                    if (
                        abs($value) < 0.000001
                    ) {
                        continue;
                    }
    
    
                    $candidates[] = [
    
                        'item' =>
                            $receiptCandidate,
    
                        'value' =>
                            (int) round(
                                $value * 100
                            ),
                    ];
                }
    
    
                if (
                    count($candidates) < 2
                ) {
                    continue;
                }
    
    
                /*
                |--------------------------------------------------------------------------
                | Target dalam cent
                |--------------------------------------------------------------------------
                */
    
                $targetCents =
                    (int) round(
                        $target * 100
                    );
    
    
                /*
                |--------------------------------------------------------------------------
                | Urutkan kandidat
                |--------------------------------------------------------------------------
                |
                | Receipt positif didahulukan.
                |
                | Contoh:
                |
                | +50.125
                | -125
                |--------------------------------------------------------------------------
                */
    
                usort(
                    $candidates,
                    function (
                        $a,
                        $b
                    ) {
    
                        $aPositive =
                            $a['value'] > 0;
    
                        $bPositive =
                            $b['value'] > 0;
    
    
                        if (
                            $aPositive !==
                            $bPositive
                        ) {
    
                            return
                                $aPositive
                                ? -1
                                : 1;
                        }
    
    
                        return
                            abs($b['value'])
                            <=>
                            abs($a['value']);
                    }
                );
    
    
                /*
                |--------------------------------------------------------------------------
                | Cari kombinasi Receipt.
                |--------------------------------------------------------------------------
                |
                | PENTING:
                |
                | Tidak menggunakan findSubsetBySum()
                | pada tahap ini.
                |
                | Karena tahap ini membutuhkan Receipt negatif.
                |--------------------------------------------------------------------------
                */
    
                $selectedCandidateIndexes = [];
    
                $foundCombination = null;
    
                $candidateCount =
                    count($candidates);
    
                $memo = [];
    
    
                /*
                |--------------------------------------------------------------------------
                | Recursive search
                |--------------------------------------------------------------------------
                */
    
                $searchReceiptCombination =
                    function (
                        int $index,
                        int $remaining
                    ) use (
                        &$searchReceiptCombination,
                        &$candidates,
                        &$selectedCandidateIndexes,
                        &$foundCombination,
                        &$memo,
                        $candidateCount
                    ): bool {

                        /*
                        |--------------------------------------------------------------------------
                        | TARGET TERCAPAI DENGAN MONEY TOLERANCE
                        |--------------------------------------------------------------------------
                        */

                        if (
                            count(
                                $selectedCandidateIndexes
                            ) >= 2
                        ) {

                            $toleranceCents =
                                (int) round(
                                    self::MONEY_TOLERANCE * 100
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | STRICT:
                            |
                            |     -1 < selisih < 1
                            |
                            | bukan:
                            |
                            |     <= 1
                            |     >= -1
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $remaining < $toleranceCents
                                &&
                                $remaining > -$toleranceCents
                            ) {

                                $foundCombination =
                                    array_map(
                                        function (
                                            $candidateIndex
                                        )
                                        use (
                                            &$candidates
                                        ) {

                                            return
                                                $candidates[
                                                    $candidateIndex
                                                ]['item'];

                                        },
                                        $selectedCandidateIndexes
                                    );

                                return true;
                            }
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | SEMUA KANDIDAT SUDAH DIPERIKSA
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $index >= $candidateCount
                        ) {

                            return false;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | MEMO KEY
                        |--------------------------------------------------------------------------
                        */

                        $memoKey =
                            $index .
                            ':' .
                            $remaining;


                        if (
                            isset(
                                $memo[$memoKey]
                            )
                        ) {

                            return false;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | NILAI KANDIDAT
                        |--------------------------------------------------------------------------
                        */

                        $value =
                            $candidates[
                                $index
                            ]['value'];


                        /*
                        |--------------------------------------------------------------------------
                        | PILIH KANDIDAT
                        |--------------------------------------------------------------------------
                        */

                        $selectedCandidateIndexes[] =
                            $index;


                        if (
                            $searchReceiptCombination(
                                $index + 1,
                                $remaining - $value
                            )
                        ) {

                            return true;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | BATALKAN PILIHAN
                        |--------------------------------------------------------------------------
                        */

                        array_pop(
                            $selectedCandidateIndexes
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | LEWATI KANDIDAT
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $searchReceiptCombination(
                                $index + 1,
                                $remaining
                            )
                        ) {

                            return true;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | SIMPAN GAGAL
                        |--------------------------------------------------------------------------
                        */

                        $memo[
                            $memoKey
                        ] =
                            true;


                        return false;
                    };
    
    
                /*
                |--------------------------------------------------------------------------
                | Jalankan pencarian
                |--------------------------------------------------------------------------
                */
    
                $found =
                    $searchReceiptCombination(
                        0,
                        $targetCents
                    );
    
    
                if (
                    !$found
                    ||
                    empty(
                        $foundCombination
                    )
                    ||
                    count(
                        $foundCombination
                    ) < 2
                ) {
                    continue;
                }
    
    
                /*
                |--------------------------------------------------------------------------
                | Receipt yang ditemukan
                |--------------------------------------------------------------------------
                */
    
                $selectedReceipts =
                    collect(
                        $foundCombination
                    );
    
    
                $receiptIds =
                    $selectedReceipts
                        ->pluck('id')
                        ->values()
                        ->toArray();
    
    
                if (
                    empty($receiptIds)
                ) {
                    continue;
                }
    
    
                /*
                |--------------------------------------------------------------------------
                | REFF
                |--------------------------------------------------------------------------
                */
    
                $ref =
                    'FRC-' .
                    $mutasiRow->id;
    
    
                /*
                |--------------------------------------------------------------------------
                | UPDATE DATABASE
                |--------------------------------------------------------------------------
                */
    
                DB::transaction(
                    function () use (
                        $selectedReceipts,
                        $mutasiRow,
                        $ref
                    ) {
    
                        /*
                        |--------------------------------------------------------------------------
                        | Update Mutasi
                        |--------------------------------------------------------------------------
                        */
    
                        DB::table(
                            'mutasi_detail_frc'
                        )
                            ->where(
                                'id',
                                $mutasiRow->id
                            )
                            ->where(
                                'cabang',
                                $mutasiRow->cabang
                            )
                            ->where(
                                function ($query) {
    
                                    $query
                                        ->whereNull(
                                            'reff'
                                        )
                                        ->orWhere(
                                            'reff',
                                            ''
                                        );
                                }
                            )
                            ->update([
                                'reff' =>
                                    $ref,
                            ]);
    
    
                        /*
                        |--------------------------------------------------------------------------
                        | Update SEMUA Receipt
                        |--------------------------------------------------------------------------
                        */
    
                        foreach (
                            $selectedReceipts
                            as $receipt
                        ) {
    
                            DB::table(
                                'receipt'
                            )
                                ->where(
                                    'id',
                                    $receipt->id
                                )
                                ->where(
                                    function ($query) {
    
                                        $query
                                            ->whereNull(
                                                'reff'
                                            )
                                            ->orWhere(
                                                'reff',
                                                ''
                                            );
                                    }
                                )
                                ->update([
                                    'reff' =>
                                        $ref,
                                ]);
                        }
                    }
                );
    
    
                /*
                |--------------------------------------------------------------------------
                | Hapus Receipt yang sudah match
                |--------------------------------------------------------------------------
                */
    
                $remainingReceipts =
                    $remainingReceipts
                        ->reject(
                            function ($item)
                            use ($receiptIds) {
    
                                return in_array(
                                    $item->id,
                                    $receiptIds
                                );
                            }
                        )
                        ->values();
    
    
                /*
                |--------------------------------------------------------------------------
                | Hapus Mutasi yang sudah match
                |--------------------------------------------------------------------------
                */
    
                $remainingMutasi =
                    $remainingMutasi
                        ->reject(
                            function ($item)
                            use ($mutasiRow) {
    
                                return
                                    $item->id ==
                                    $mutasiRow->id;
                            }
                        )
                        ->values();
    
    
                /*
                |--------------------------------------------------------------------------
                | Hitung nominal Receipt
                |--------------------------------------------------------------------------
                |
                | Receipt negatif tetap ikut.
                |
                | Contoh:
                |
                | 50.125 + (-125)
                |
                | hasil:
                |
                | 50.000
                |--------------------------------------------------------------------------
                */
    
                $receiptAmount =
                    $selectedReceipts
                        ->sum('nominal');
    
    
                $mutasiAmount =
                    (float)
                    $mutasiRow->cr;
    
    
                /*
                |--------------------------------------------------------------------------
                | MATCH
                |--------------------------------------------------------------------------
                */
    
                $matchedCount++;
    
    
                $totalReceiptAmount +=
                    $receiptAmount;
    
    
                $totalMutasiAmount +=
                    $mutasiAmount;
    
    
                /*
                |--------------------------------------------------------------------------
                | Gabungkan status Receipt
                |--------------------------------------------------------------------------
                */
    
                $receiptStatus =
                    $selectedReceipts
                        ->pluck(
                            'receipt_status'
                        )
                        ->filter(
                            function ($value) {
    
                                return
                                    trim(
                                        (string)
                                        $value
                                    ) !== '';
                            }
                        )
                        ->unique()
                        ->values()
                        ->implode(
                            ', '
                        );
    
    
                /*
                |--------------------------------------------------------------------------
                | Gabungkan state Receipt
                |--------------------------------------------------------------------------
                */
    
                $receiptState =
                    $selectedReceipts
                        ->pluck(
                            'receipt_state'
                        )
                        ->filter(
                            function ($value) {
    
                                return
                                    trim(
                                        (string)
                                        $value
                                    ) !== '';
                            }
                        )
                        ->unique()
                        ->values()
                        ->implode(
                            ', '
                        );
    
    
                /*
                |--------------------------------------------------------------------------
                | Hasil Rekonsiliasi
                |--------------------------------------------------------------------------
                */
    
                $hasil[] = [
    
                    'status' =>
                        'MATCH',
    
                    'receipt_id' =>
                        $receiptIds,
    
                    'mutasi_id' =>
                        $mutasiRow->id,
    
                    'no_rek' =>
                        $groupNoRek,
    
                    'tanggal' =>
                        $groupDate,
    
                    'jns_bank' =>
                        $mutasiRow->jns_bank
                        ??
                        $bankTypes->get(
                            $groupNoRek
                        ),
    
                    /*
                    |--------------------------------------------------------------------------
                    | Receipt
                    |--------------------------------------------------------------------------
                    */
    
                    'receipt_number' =>
                        $selectedReceipts
                            ->pluck(
                                'receipt_number'
                            )
                            ->filter()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'receipt_date' =>
                        $selectedReceipts
                            ->pluck(
                                'receipt_date'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'gl_date' =>
                        $selectedReceipts
                            ->pluck(
                                'gl_date'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'remittance_bank_account' =>
                        $groupNoRek,
    
                    'receipt_amount' =>
                        $receiptAmount,
    
                    /*
                    |--------------------------------------------------------------------------
                    | STATUS
                    |--------------------------------------------------------------------------
                    */
    
                    'receipt_status' =>
                        $receiptStatus,
    
                    /*
                    |--------------------------------------------------------------------------
                    | STATE
                    |--------------------------------------------------------------------------
                    */
    
                    'receipt_state' =>
                        $receiptState,
    
                    'receipt_type' =>
                        $selectedReceipts
                            ->pluck(
                                'receipt_type'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'comments' =>
                        $selectedReceipts
                            ->pluck(
                                'comments'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'activity' =>
                        $selectedReceipts
                            ->pluck(
                                'activity'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'paid_by' =>
                        $selectedReceipts
                            ->pluck(
                                'paid_by'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    /*
                    |--------------------------------------------------------------------------
                    | Mutasi
                    |--------------------------------------------------------------------------
                    */
    
                    'mutation_number' =>
                        $mutasiRow->trx_code
                        ?? null,
    
                    'mutasi_date' =>
                        $mutasiRow->tgl,
    
                    'mutasi_amount' =>
                        $mutasiAmount,
    
                    'description' =>
                        $mutasiRow->remark
                        ?? null,
    
                    /*
                    |--------------------------------------------------------------------------
                    | Selisih
                    |--------------------------------------------------------------------------
                    */
    
                    'difference' =>
                        $receiptAmount -
                        $mutasiAmount,
    
                    'amount_match' =>
                        $this->moneyEquals(
                            $receiptAmount,
                            $mutasiAmount
                        ),
    
                    'account_match' =>
                        true,
    
                    /*
                    |--------------------------------------------------------------------------
                    | REFF
                    |--------------------------------------------------------------------------
                    */
    
                    'receipt_reff' =>
                        $ref,
    
                    'mutation_reff' =>
                        $ref,
    
                    'reff' =>
                        $ref,
                ];
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | TAHAP 4
            |--------------------------------------------------------------------------
            | BANYAK RECEIPT VS BANYAK MUTASI
            |--------------------------------------------------------------------------
            |
            | Tetap menggunakan helper existing.
            |
            */
    
            while (
                $remainingReceipts->isNotEmpty()
                &&
                $remainingMutasi->isNotEmpty()
            ) {
    
                $found =
                    $this->findMatchingManyToMany(
                        $remainingReceipts
                            ->values()
                            ->all(),
    
                        $remainingMutasi
                            ->values()
                            ->all()
                    );
    
    
                if (
                    !$found
                ) {
                    break;
                }
    
    
                $selectedReceipts =
                    collect(
                        $found['receipts']
                    );
    
    
                $selectedMutasi =
                    collect(
                        $found['mutasi']
                    );
    
    
                $receiptIds =
                    $selectedReceipts
                        ->pluck('id')
                        ->values()
                        ->toArray();
    
    
                $mutasiIds =
                    $selectedMutasi
                        ->pluck('id')
                        ->values()
                        ->toArray();
    
    
                if (
                    empty($receiptIds)
                    ||
                    empty($mutasiIds)
                ) {
                    break;
                }
    
    
                $ref =
                    'FRC-' .
                    $selectedMutasi
                        ->first()
                        ->id;
    
    
                DB::transaction(
                    function () use (
                        $selectedReceipts,
                        $selectedMutasi,
                        $ref
                    ) {
    
                        foreach (
                            $selectedMutasi
                            as $mutasiRow
                        ) {
    
                            DB::table(
                                'mutasi_detail_frc'
                            )
                                ->where(
                                    'id',
                                    $mutasiRow->id
                                )
                                ->where(
                                    'cabang',
                                    $mutasiRow->cabang
                                )
                                ->where(
                                    function ($query) {
    
                                        $query
                                            ->whereNull(
                                                'reff'
                                            )
                                            ->orWhere(
                                                'reff',
                                                ''
                                            );
                                    }
                                )
                                ->update([
                                    'reff' =>
                                        $ref,
                                ]);
                        }
    
    
                        foreach (
                            $selectedReceipts
                            as $receipt
                        ) {
    
                            DB::table(
                                'receipt'
                            )
                                ->where(
                                    'id',
                                    $receipt->id
                                )
                                ->where(
                                    function ($query) {
    
                                        $query
                                            ->whereNull(
                                                'reff'
                                            )
                                            ->orWhere(
                                                'reff',
                                                ''
                                            );
                                    }
                                )
                                ->update([
                                    'reff' =>
                                        $ref,
                                ]);
                        }
                    }
                );
    
    
                $remainingReceipts =
                    $remainingReceipts
                        ->reject(
                            function ($item)
                            use ($receiptIds) {
    
                                return in_array(
                                    $item->id,
                                    $receiptIds
                                );
                            }
                        )
                        ->values();
    
    
                $remainingMutasi =
                    $remainingMutasi
                        ->reject(
                            function ($item)
                            use ($mutasiIds) {
    
                                return in_array(
                                    $item->id,
                                    $mutasiIds
                                );
                            }
                        )
                        ->values();
    
    
                $receiptAmount =
                    $selectedReceipts
                        ->sum('nominal');
    
    
                $mutasiAmount =
                    $selectedMutasi
                        ->sum('cr');
    
    
                $matchedCount++;
    
    
                $totalReceiptAmount +=
                    $receiptAmount;
    
    
                $totalMutasiAmount +=
                    $mutasiAmount;
    
    
                $hasil[] = [
    
                    'status' =>
                        'MATCH',
    
                    'receipt_id' =>
                        $receiptIds,
    
                    'mutasi_id' =>
                        $mutasiIds,
    
                    'no_rek' =>
                        $groupNoRek,
    
                    'tanggal' =>
                        $groupDate,
    
                    'jns_bank' =>
                        optional(
                            $selectedMutasi->first()
                        )->jns_bank
                        ??
                        $bankTypes->get(
                            $groupNoRek
                        ),
    
                    'receipt_number' =>
                        $selectedReceipts
                            ->pluck(
                                'receipt_number'
                            )
                            ->filter()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'receipt_date' =>
                        $selectedReceipts
                            ->pluck(
                                'receipt_date'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'gl_date' =>
                        $selectedReceipts
                            ->pluck(
                                'gl_date'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'remittance_bank_account' =>
                        $groupNoRek,
    
                    'receipt_amount' =>
                        $receiptAmount,
    
                    'receipt_status' =>
                        $selectedReceipts
                            ->pluck(
                                'receipt_status'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'receipt_state' =>
                        $selectedReceipts
                            ->pluck(
                                'receipt_state'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'receipt_type' =>
                        $selectedReceipts
                            ->pluck(
                                'receipt_type'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'comments' =>
                        $selectedReceipts
                            ->pluck(
                                'comments'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'activity' =>
                        $selectedReceipts
                            ->pluck(
                                'activity'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'paid_by' =>
                        $selectedReceipts
                            ->pluck(
                                'paid_by'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(
                                ', '
                            ),
    
                    'mutasi_amount' =>
                        $mutasiAmount,
    
                    'difference' =>
                        0,
    
                    'amount_match' =>
                        true,
    
                    'account_match' =>
                        true,
    
                    'receipt_reff' =>
                        $ref,
    
                    'mutation_reff' =>
                        $ref,
    
                    'reff' =>
                        $ref,
                ];
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | 8. SISA RECEIPT
            |--------------------------------------------------------------------------
            */
    
            foreach (
                $remainingReceipts
                as $receipt
            ) {
    
                $amount =
                    (float)
                    $receipt->nominal;
    
    
                $receiptOnlyCount++;
    
    
                $totalReceiptAmount +=
                    $amount;
    
    
                $hasil[] = [
    
                    'status' =>
                        'RECEIPT_ONLY',
    
                    'receipt_id' =>
                        $receipt->id,
    
                    'mutasi_id' =>
                        null,
    
                    'no_rek' =>
                        $groupNoRek,
    
                    'tanggal' =>
                        $groupDate,
    
                    'jns_bank' =>
                        $bankTypes->get(
                            $groupNoRek
                        ),
    
                    'receipt_number' =>
                        $receipt->receipt_number,
    
                    'receipt_date' =>
                        $receipt->receipt_date,
    
                    'gl_date' =>
                        $receipt->gl_date,
    
                    'remittance_bank_account' =>
                        $receipt->remittance_bank_account,
    
                    'receipt_amount' =>
                        $amount,
    
                    'receipt_status' =>
                        $receipt->receipt_status,
    
                    'receipt_state' =>
                        $receipt->receipt_state,
    
                    'receipt_type' =>
                        $receipt->receipt_type
                        ?? null,
    
                    'comments' =>
                        $receipt->comments
                        ?? null,
    
                    'activity' =>
                        $receipt->activity
                        ?? null,
    
                    'paid_by' =>
                        $receipt->paid_by
                        ?? null,
    
                    'mutasi_amount' =>
                        0,
    
                    'difference' =>
                        $amount,
    
                    'amount_match' =>
                        false,
    
                    'account_match' =>
                        true,
    
                    'receipt_reff' =>
                        $receipt->reff,
    
                    'mutation_reff' =>
                        null,
    
                    'reff' =>
                        $receipt->reff,
                ];
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | 9. SISA MUTASI
            |--------------------------------------------------------------------------
            */
    
            foreach (
                $remainingMutasi
                as $mutasiRow
            ) {
    
                $amount =
                    (float)
                    $mutasiRow->cr;
    
    
                $mutasiOnlyCount++;
    
    
                $totalMutasiAmount +=
                    $amount;
    
    
                $hasil[] = [
    
                    'status' =>
                        'MUTASI_ONLY',
    
                    'receipt_id' =>
                        null,
    
                    'mutasi_id' =>
                        $mutasiRow->id,
    
                    'no_rek' =>
                        $groupNoRek,
    
                    'tanggal' =>
                        $groupDate,
    
                    'jns_bank' =>
                        $mutasiRow->jns_bank
                        ??
                        $bankTypes->get(
                            $groupNoRek
                        ),
    
                    'receipt_number' =>
                        null,
    
                    'receipt_date' =>
                        null,
    
                    'gl_date' =>
                        null,
    
                    'remittance_bank_account' =>
                        null,
    
                    'receipt_amount' =>
                        0,
    
                    'receipt_status' =>
                        null,
    
                    'receipt_state' =>
                        null,
    
                    'mutasi_amount' =>
                        $amount,
    
                    'difference' =>
                        $amount,
    
                    'amount_match' =>
                        false,
    
                    'account_match' =>
                        true,
    
                    'mutation_number' =>
                        $mutasiRow->trx_code
                        ?? null,
    
                    'mutasi_date' =>
                        $mutasiRow->tgl,
    
                    'description' =>
                        $mutasiRow->remark
                        ?? null,
    
                    'receipt_reff' =>
                        null,
    
                    'mutation_reff' =>
                        $mutasiRow->reff,
    
                    'reff' =>
                        $mutasiRow->reff,
                ];
            }
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | 10. SUMMARY
        |--------------------------------------------------------------------------
        */
    
        $difference =
            $totalReceiptAmount -
            $totalMutasiAmount;
    
    
        return response()->json([
    
            'success' =>
                true,
    
            'message' =>
                'Rekonsiliasi Franchise berhasil diproses.',
    
            'data' =>
                $hasil,
    
            'summary' => [
    
                'total' =>
                    count($hasil),
    
                'match' =>
                    $matchedCount,
    
                'mutasi_only' =>
                    $mutasiOnlyCount,
    
                'receipt_only' =>
                    $receiptOnlyCount,
    
                'nominal_different' =>
                    $nominalDifferentCount,
    
                'account_different' =>
                    0,
    
                'mutasi_amount' =>
                    $totalMutasiAmount,
    
                'receipt_amount' =>
                    $totalReceiptAmount,
    
                'difference_amount' =>
                    $difference,
            ],
        ]);
    }

    private function findSubsetBySum(
        array $items,
        $target,
        string $field
    ): ?array {
    
        /*
        |--------------------------------------------------------------------------
        | TARGET DALAM SATUAN CENT
        |--------------------------------------------------------------------------
        |
        | Semua perhitungan dilakukan menggunakan integer cent
        | untuk menghindari masalah floating point.
        |
        | Contoh:
        |
        | Rp 50.000,00
        | = 5.000.000 cent
        |
        */
    
        $targetCents =
            (int) round(
                ((float) $target) * 100
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | TOLERANSI DALAM SATUAN CENT
        |--------------------------------------------------------------------------
        |
        | MONEY_TOLERANCE = 1.00
        |
        | Berarti:
        |
        |     -1 < selisih < 1
        |
        | Dalam cent:
        |
        |     -100 < selisih < 100
        |
        | Perhatikan bahwa kita menggunakan STRICT < dan >.
        |
        | Jadi:
        |
        |     selisih  0,99  => MATCH
        |     selisih -0,99  => MATCH
        |     selisih  1,00  => TIDAK MATCH
        |     selisih -1,00  => TIDAK MATCH
        |
        */
    
        $toleranceCents =
            (int) round(
                self::MONEY_TOLERANCE * 100
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | VALIDASI TOLERANSI
        |--------------------------------------------------------------------------
        */
    
        if (
            $toleranceCents <= 0
        ) {
    
            $toleranceCents = 1;
    
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | VALIDASI TARGET
        |--------------------------------------------------------------------------
        */
    
        if (
            $targetCents <= 0
            ||
            empty($items)
        ) {
    
            return null;
    
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | BUAT KANDIDAT
        |--------------------------------------------------------------------------
        |
        | Nominal POSITIF maupun NEGATIF diperbolehkan.
        |
        | Contoh:
        |
        | Receipt A = 50.125
        | Receipt B =   -125
        |
        | Total = 50.000
        |
        */
    
        $candidates = [];
    
    
        foreach (
            $items
            as $item
        ) {
    
            $valueCents =
                (int) round(
                    (
                        (float)
                        (
                            $item->{$field}
                            ?? 0
                        )
                    ) * 100
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | ABAIKAN NOMINAL 0
            |--------------------------------------------------------------------------
            |
            | Nominal 0 tidak membantu pencapaian target.
            |
            */
    
            if (
                $valueCents === 0
            ) {
    
                continue;
    
            }
    
    
            $candidates[] = [
    
                'item' =>
                    $item,
    
                'value' =>
                    $valueCents,
    
            ];
    
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | TIDAK ADA KANDIDAT
        |--------------------------------------------------------------------------
        */
    
        if (
            empty($candidates)
        ) {
    
            return null;
    
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | URUTKAN KANDIDAT
        |--------------------------------------------------------------------------
        |
        | POSITIF diprioritaskan terlebih dahulu.
        |
        | Nominal terbesar berdasarkan absolute value
        | diprioritaskan dalam kelompok yang sama.
        |
        */
    
        usort(
            $candidates,
            function (
                $a,
                $b
            ) {
    
                $aPositive =
                    $a['value'] > 0;
    
                $bPositive =
                    $b['value'] > 0;
    
    
                /*
                |--------------------------------------------------------------------------
                | POSITIF LEBIH DAHULU
                |--------------------------------------------------------------------------
                */
    
                if (
                    $aPositive !==
                    $bPositive
                ) {
    
                    return
                        $aPositive
                        ? -1
                        : 1;
    
                }
    
    
                /*
                |--------------------------------------------------------------------------
                | NOMINAL ABSOLUT TERBESAR DIDAHULUKAN
                |--------------------------------------------------------------------------
                */
    
                return
                    abs($b['value'])
                    <=>
                    abs($a['value']);
    
            }
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | JUMLAH KANDIDAT
        |--------------------------------------------------------------------------
        */
    
        $count =
            count($candidates);
    
    
        /*
        |--------------------------------------------------------------------------
        | MEMOIZATION
        |--------------------------------------------------------------------------
        |
        | Menyimpan kombinasi:
        |
        |     index : remaining
        |
        | yang sudah terbukti tidak menghasilkan solusi.
        |
        */
    
        $memo = [];
    
    
        /*
        |--------------------------------------------------------------------------
        | HASIL YANG SEDANG DIPILIH
        |--------------------------------------------------------------------------
        */
    
        $selected = [];
    
    
        /*
        |--------------------------------------------------------------------------
        | RECURSIVE SEARCH
        |--------------------------------------------------------------------------
        */
    
        $search =
            function (
                int $index,
                int $remaining
            ) use (
                &$search,
                &$candidates,
                &$memo,
                &$selected,
                $count,
                $toleranceCents
            ): bool {
    
    
                /*
                |--------------------------------------------------------------------------
                | TARGET TERCAPAI DENGAN TOLERANSI
                |--------------------------------------------------------------------------
                |
                | INI ADALAH PERUBAHAN UTAMA.
                |
                | Sebelumnya:
                |
                |     $remaining === 0
                |
                | Sekarang:
                |
                |     -tolerance < remaining < tolerance
                |
                | Karena:
                |
                |     remaining = target - total receipt
                |
                | maka:
                |
                |     -1 < remaining < 1
                |
                | berarti:
                |
                |     -1 < total receipt - target < 1
                |
                | secara bisnis tetap MATCH.
                |
                */
    
                if (
                    !empty($selected)
                    &&
                    $remaining < $toleranceCents
                    &&
                    $remaining > -$toleranceCents
                ) {
    
                    return true;
    
                }
    
    
                /*
                |--------------------------------------------------------------------------
                | SEMUA KANDIDAT SUDAH DIPERIKSA
                |--------------------------------------------------------------------------
                */
    
                if (
                    $index >= $count
                ) {
    
                    return false;
    
                }
    
    
                /*
                |--------------------------------------------------------------------------
                | KEY MEMO
                |--------------------------------------------------------------------------
                */
    
                $key =
                    $index .
                    ':' .
                    $remaining;
    
    
                /*
                |--------------------------------------------------------------------------
                | JIKA KOMBINASI INI SUDAH PERNAH GAGAL
                |--------------------------------------------------------------------------
                */
    
                if (
                    isset(
                        $memo[$key]
                    )
                ) {
    
                    return false;
    
                }
    
    
                /*
                |--------------------------------------------------------------------------
                | NILAI KANDIDAT SAAT INI
                |--------------------------------------------------------------------------
                */
    
                $value =
                    $candidates[
                        $index
                    ]['value'];
    
    
                /*
                |--------------------------------------------------------------------------
                | PILIH ITEM
                |--------------------------------------------------------------------------
                |
                | TIDAK menggunakan:
                |
                |     if ($value <= $remaining)
                |
                | karena nominal NEGATIF diperbolehkan.
                |
                */
    
                $selected[] =
                    $candidates[
                        $index
                    ]['item'];
    
    
                if (
                    $search(
                        $index + 1,
                        $remaining - $value
                    )
                ) {
    
                    return true;
    
                }
    
    
                /*
                |--------------------------------------------------------------------------
                | JIKA MEMILIH ITEM TIDAK MENGHASILKAN SOLUSI
                |--------------------------------------------------------------------------
                */
    
                array_pop(
                    $selected
                );
    
    
                /*
                |--------------------------------------------------------------------------
                | LEWATI ITEM
                |--------------------------------------------------------------------------
                */
    
                if (
                    $search(
                        $index + 1,
                        $remaining
                    )
                ) {
    
                    return true;
    
                }
    
    
                /*
                |--------------------------------------------------------------------------
                | SIMPAN BAHWA KOMBINASI INI GAGAL
                |--------------------------------------------------------------------------
                */
    
                $memo[$key] =
                    true;
    
    
                return false;
    
            };
    
    
        /*
        |--------------------------------------------------------------------------
        | JALANKAN SEARCH
        |--------------------------------------------------------------------------
        */
    
        if (
            $search(
                0,
                $targetCents
            )
        ) {
    
            return $selected;
    
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | TIDAK DITEMUKAN
        |--------------------------------------------------------------------------
        */
    
        return null;
    
    }

    private function moneyEquals(
        $value1,
        $value2
    ): bool {

        $value1Cents = (int) round(
            ((float) $value1) * 100
        );

        $value2Cents = (int) round(
            ((float) $value2) * 100
        );

        return abs(
            $value1Cents - $value2Cents
        ) < (self::MONEY_TOLERANCE * 100);
    }

    private function findMatchingManyToMany(
        array $receipts,
        array $mutasi
    ): ?array {
    
        if (
            count($receipts) < 2 ||
            count($mutasi) < 2
        ) {
            return null;
        }
    
        $receiptCount =
            count($receipts);
    
        for (
            $size = 2;
            $size <= $receiptCount;
            $size++
        ) {
    
            $receiptCombinations =
                $this->generateCombinations(
                    $receipts,
                    $size
                );
    
            foreach (
                $receiptCombinations
                as $receiptCombination
            ) {
    
                $receiptTotal = 0;
    
                foreach (
                    $receiptCombination
                    as $receipt
                ) {
    
                    $receiptTotal +=
                        (float)
                        $receipt->nominal;
                }
    
    
                $mutasiCombination =
                    $this->findSubsetBySum(
                        $mutasi,
                        $receiptTotal,
                        'cr'
                    );
    
    
                if (
                    !$mutasiCombination
                ) {
                    continue;
                }
    
    
                if (
                    count(
                        $mutasiCombination
                    ) < 2
                ) {
                    continue;
                }
    
    
                return [
                    'receipts' =>
                        $receiptCombination,
    
                    'mutasi' =>
                        $mutasiCombination,
                ];
            }
        }
    
    
        return null;
    }

    private function generateCombinations(
        array $items,
        int $size
    ): array {
    
        $result = [];
    
        $this->generateCombinationRecursive(
            $items,
            $size,
            0,
            [],
            $result
        );
    
        return $result;
    }

    private function generateCombinationRecursive(
        array $items,
        int $size,
        int $start,
        array $current,
        array &$result
    ): void {
    
        if (count($current) === $size) {
    
            $result[] = $current;
    
            return;
        }
    
        $remainingNeeded =
            $size - count($current);
    
        $maxStart =
            count($items) - $remainingNeeded;
    
        for (
            $i = $start;
            $i <= $maxStart;
            $i++
        ) {
    
            $next = $current;
    
            $next[] = $items[$i];
    
            $this->generateCombinationRecursive(
                $items,
                $size,
                $i + 1,
                $next,
                $result
            );
        }
    }

    private function emptyReconciliationSummary(): array
    {
        return [
            'total' => 0,
            'match' => 0,
            'mutasi_only' => 0,
            'receipt_only' => 0,
            'nominal_different' => 0,
            'account_different' => 0,
            'mutasi_amount' => 0,
            'receipt_amount' => 0,
            'difference_amount' => 0,
        ];
    }

    public function getHistoryFilters(Request $request)
    {
        $request->validate([
            'cabang' => 'required|string|max:50',
            'type'   => 'required|in:FRC,REG',
        ]);

        $cabang = trim($request->input('cabang'));
        $type   = strtoupper(trim($request->input('type')));

        try {

            /*
            |--------------------------------------------------------------------------
            | BANK
            |--------------------------------------------------------------------------
            |
            | FRC:
            |   jns_bank diambil dari tabel bank
            |
            | REG:
            |   kita tetap ambil rekening REG dari tabel bank
            |
            */

            if ($type === 'FRC') {

                $banks = DB::table('bank')
                    ->where('cabang', $cabang)
                    ->whereNotNull('jns_bank')
                    ->whereRaw("TRIM(jns_bank) <> ''")
                    ->where('jns_bank', '<>', 'REG')
                    ->distinct()
                    ->orderBy('jns_bank')
                    ->pluck('jns_bank')
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values();

                $accounts = DB::table('bank')
                    ->where('cabang', $cabang)
                    ->where('site', '<>', 'REG')
                    ->whereNotNull('no_rek')
                    ->whereRaw("TRIM(no_rek) <> ''")
                    ->distinct()
                    ->orderBy('no_rek')
                    ->pluck('no_rek')
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values();

            } else {

                $banks = collect([
                    'REG'
                ]);

                $accounts = DB::table('bank')
                    ->where('cabang', $cabang)
                    ->where('site', 'REG')
                    ->whereNotNull('no_rek')
                    ->whereRaw("TRIM(no_rek) <> ''")
                    ->distinct()
                    ->orderBy('no_rek')
                    ->pluck('no_rek')
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values();
            }

            return response()->json([
                'success'  => true,
                'type'     => $type,
                'cabang'   => $cabang,
                'banks'    => $banks,
                'accounts' => $accounts,
                'categories' => [
                    [
                        'value' => 'ALL',
                        'label' => 'SEMUA'
                    ],
                    [
                        'value' => 'MUTASI_ONLY',
                        'label' => 'MUTASI ONLY'
                    ],
                    [
                        'value' => 'RECEIPT_ONLY',
                        'label' => 'RECEIPT ONLY'
                    ],
                    [
                        'value' => 'MATCH_SELISIH',
                        'label' => 'MATCH TAPI SELISIH'
                    ],
                    [
                        'value' => 'MATCH',
                        'label' => 'MATCH'
                    ],
                ],
            ]);

        } catch (\Throwable $e) {

            Log::error(
                'GET HISTORY FILTER ERROR',
                [
                    'cabang' => $cabang,
                    'type'   => $type,
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'banks' => [],
                'accounts' => [],
                'categories' => [],
            ], 500);
        }
    }

    public function getReconciliationHistory(Request $request)
    {
        $request->validate([
            'cabang'              => 'required|string|max:50',
            'type'                => 'required|in:FRC,REG',
            'bank'                => 'nullable|string|max:255',
            'account'             => 'nullable|string|max:255',
            'date_start'          => 'nullable|date',
            'date_end'            => 'nullable|date',
            'category'            => 'nullable|in:ALL,MUTASI_ONLY,RECEIPT_ONLY,MATCH_SELISIH,MATCH',
            'search'              => 'nullable|string|max:255',
            'include_unreconciled' => 'nullable|boolean',
        ]);

        $cabang = trim($request->input('cabang'));
        $type = strtoupper(trim($request->input('type')));

        $bank = trim((string) $request->input('bank', 'ALL'));
        $account = trim((string) $request->input('account', 'ALL'));

        $dateStart = $request->input('date_start');
        $dateEnd = $request->input('date_end');

        $category = strtoupper(
            trim(
                (string) $request->input(
                    'category',
                    'ALL'
                )
            )
        );

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $includeUnreconciled = $request->boolean(
            'include_unreconciled',
            true
        );

        /*
        |--------------------------------------------------------------------------
        | DEFAULT DATE
        |--------------------------------------------------------------------------
        |
        | Kalau tanggal tidak diberikan, jangan membatasi tanggal.
        |
        */

        try {

            /*
            |--------------------------------------------------------------------------
            | REKENING YANG VALID UNTUK CABANG
            |--------------------------------------------------------------------------
            */

            $bankRows = DB::table('bank')
                ->where('cabang', $cabang)
                ->whereNotNull('no_rek')
                ->whereRaw("TRIM(no_rek) <> ''")
                ->select([
                    'no_rek',
                    'jns_bank',
                    'site',
                ])
                ->get();

            /*
            |--------------------------------------------------------------------------
            | MAPPING REKENING -> JENIS BANK
            |--------------------------------------------------------------------------
            */

            $bankMap = [];

            foreach ($bankRows as $row) {

                $noRek = trim(
                    (string) $row->no_rek
                );

                if ($noRek === '') {
                    continue;
                }

                if (!isset($bankMap[$noRek])) {
                    $bankMap[$noRek] = [];
                }

                $bankMap[$noRek][] = [
                    'jns_bank' => trim(
                        (string) ($row->jns_bank ?? '')
                    ),
                    'site' => trim(
                        (string) ($row->site ?? '')
                    ),
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | REKENING SESUAI TYPE
            |--------------------------------------------------------------------------
            */

            $rekening = collect(
                array_keys($bankMap)
            );

            if ($type === 'FRC') {

                $rekening = $rekening
                    ->filter(function ($noRek) use (
                        $bankMap
                    ) {

                        foreach (
                            $bankMap[$noRek]
                            ?? []
                            as $bankInfo
                        ) {

                            if (
                                strtoupper(
                                    $bankInfo['site']
                                ) !== 'REG'
                            ) {
                                return true;
                            }
                        }

                        return false;
                    })
                    ->values();

            } else {

                $rekening = $rekening
                    ->filter(function ($noRek) use (
                        $bankMap
                    ) {

                        foreach (
                            $bankMap[$noRek]
                            ?? []
                            as $bankInfo
                        ) {

                            if (
                                strtoupper(
                                    $bankInfo['site']
                                ) === 'REG'
                            ) {
                                return true;
                            }
                        }

                        return false;
                    })
                    ->values();
            }

            /*
            |--------------------------------------------------------------------------
            | FILTER ACCOUNT
            |--------------------------------------------------------------------------
            */

            if (
                $account !== ''
                && strtoupper($account) !== 'ALL'
            ) {

                $rekening = $rekening
                    ->filter(
                        fn ($item) =>
                            trim((string) $item)
                            === $account
                    )
                    ->values();
            }

            /*
            |--------------------------------------------------------------------------
            | TIDAK ADA REKENING
            |--------------------------------------------------------------------------
            */

            if ($rekening->isEmpty()) {

                return response()->json([
                    'success' => true,
                    'data' => [],
                    'summary' => $this->emptyHistorySummary(),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | RECEIPT
            |--------------------------------------------------------------------------
            */

            $receiptQuery = DB::table('receipt')
                ->whereIn(
                    'remittance_bank_account',
                    $rekening->toArray()
                )
                ->where('receipt_status', '<>', 'Reversed')
                ->whereNull('note')
                ->select([
                    'id',
                    'receipt_number',
                    'receipt_amount',
                    'receipt_date',
                    'gl_date',
                    'remittance_bank_account',
                    'receipt_type',
                    'receipt_status',
                    'receipt_state',
                    'comments',
                    'activity',
                    'paid_by',
                    'reff',
                ]);

            /*
            |--------------------------------------------------------------------------
            | DATE RECEIPT
            |--------------------------------------------------------------------------
            |
            | Untuk rekonsiliasi existing digunakan gl_date.
            |
            */

            if ($dateStart) {

                $receiptQuery->whereDate(
                    'gl_date',
                    '>=',
                    $dateStart
                );
            }

            if ($dateEnd) {

                $receiptQuery->whereDate(
                    'gl_date',
                    '<=',
                    $dateEnd
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SEARCH RECEIPT
            |--------------------------------------------------------------------------
            */

            if ($search !== '') {

                $receiptQuery->where(
                    function ($q) use (
                        $search
                    ) {

                        $q->where(
                            'receipt_number',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'remittance_bank_account',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'reff',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'comments',
                            'like',
                            '%' . $search . '%'
                        );
                    }
                );
            }

            $receipts = $receiptQuery
                ->orderBy('gl_date')
                ->orderBy('id')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | MUTASI
            |--------------------------------------------------------------------------
            */

            if ($type === 'FRC') {

                $mutasiQuery = DB::table(
                    'mutasi_detail_frc'
                )
                    ->where(
                        'cabang',
                        $cabang
                    )
                    ->whereIn(
                        'no_rek',
                        $rekening->toArray()
                    )
                    ->select([
                        'id',
                        'cabang',
                        'no_rek',
                        'tgl',
                        'cr',
                        'trx_code',
                        'remark',
                        'remark1',
                        'reff',
                    ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | REG
                |--------------------------------------------------------------------------
                |
                | Diasumsikan tabel REG = mutasi.
                |
                | Struktur yang digunakan:
                | id
                | cabang
                | no_rek
                | tgl
                | cr
                | trx_code
                | remark
                | remark1
                | reff
                |
                */

                $mutasiQuery = DB::table('mutasi')
                    ->where(
                        'cabang',
                        $cabang
                    )
                    ->whereIn(
                        'no_rek',
                        $rekening->toArray()
                    )
                    ->select([
                        'id',
                        'cabang',
                        'no_rek',
                        'tgl',
                        'cr',
                        'trx_code',
                        'remark',
                        'remark1',
                        'reff',
                    ]);
            }

            /*
            |--------------------------------------------------------------------------
            | DATE MUTASI
            |--------------------------------------------------------------------------
            */

            if ($dateStart) {

                $mutasiQuery->whereDate(
                    'tgl',
                    '>=',
                    $dateStart
                );
            }

            if ($dateEnd) {

                $mutasiQuery->whereDate(
                    'tgl',
                    '<=',
                    $dateEnd
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SEARCH MUTASI
            |--------------------------------------------------------------------------
            */

            if ($search !== '') {

                $mutasiQuery->where(
                    function ($q) use (
                        $search
                    ) {

                        $q->where(
                            'no_rek',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'trx_code',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'remark',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'remark1',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'reff',
                            'like',
                            '%' . $search . '%'
                        );
                    }
                );
            }

            /*
            |--------------------------------------------------------------------------
            | MUTASI HANYA CREDIT
            |--------------------------------------------------------------------------
            |
            | Sama dengan proses FRC yang sekarang.
            |
            */

            $mutasiQuery->where(
                'cr',
                '>',
                0
            );

            $mutasi = $mutasiQuery
                ->orderBy('tgl')
                ->orderBy('id')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | INDEX MUTASI
            |--------------------------------------------------------------------------
            */

            $mutasiByGroup = $mutasi->groupBy(
                function ($row) {

                    $reff = trim(
                        (string) (
                            $row->reff
                            ?? ''
                        )
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Kalau belum mempunyai reff,
                    | setiap row menjadi candidate sendiri.
                    |--------------------------------------------------------------------------
                    */

                    if ($reff === '') {

                        return '__MUTASI__' .
                            $row->id;
                    }

                    return '__REFF__' . $reff;
                }
            );

            /*
            |--------------------------------------------------------------------------
            | INDEX RECEIPT
            |--------------------------------------------------------------------------
            */

            $receiptByGroup = $receipts->groupBy(
                function ($row) {

                    $reff = trim(
                        (string) (
                            $row->reff
                            ?? ''
                        )
                    );

                    if ($reff === '') {

                        return '__RECEIPT__' .
                            $row->id;
                    }

                    return '__REFF__' . $reff;
                }
            );

            /*
            |--------------------------------------------------------------------------
            | GABUNGKAN GROUP
            |--------------------------------------------------------------------------
            */

            $groupKeys = $receiptByGroup
                ->keys()
                ->merge(
                    $mutasiByGroup->keys()
                )
                ->unique()
                ->values();

            $hasil = [];

            /*
            |--------------------------------------------------------------------------
            | PROSES GROUP
            |--------------------------------------------------------------------------
            */

            foreach ($groupKeys as $groupKey) {

                $receiptGroup =
                    $receiptByGroup->get(
                        $groupKey,
                        collect()
                    );

                $mutasiGroup =
                    $mutasiByGroup->get(
                        $groupKey,
                        collect()
                    );

                /*
                |--------------------------------------------------------------------------
                | KHUSUS GROUP REFF
                |--------------------------------------------------------------------------
                */

                $reff = null;

                if (
                    str_starts_with(
                        $groupKey,
                        '__REFF__'
                    )
                ) {

                    $reff = substr(
                        $groupKey,
                        strlen('__REFF__')
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | ACCOUNT
                |--------------------------------------------------------------------------
                */

                $noRek =
                    $receiptGroup
                        ->first()
                        ->remittance_bank_account
                    ?? $mutasiGroup
                        ->first()
                        ->no_rek
                    ?? null;

                $noRek = trim(
                    (string) $noRek
                );

                /*
                |--------------------------------------------------------------------------
                | JENIS BANK
                |--------------------------------------------------------------------------
                */

                $jnsBank = null;

                foreach (
                    $bankMap[$noRek]
                    ?? []
                    as $bankInfo
                ) {

                    if ($type === 'FRC') {

                        if (
                            strtoupper(
                                $bankInfo['site']
                            ) !== 'REG'
                        ) {

                            $jnsBank =
                                $bankInfo['jns_bank'];

                            break;
                        }

                    } else {

                        if (
                            strtoupper(
                                $bankInfo['site']
                            ) === 'REG'
                        ) {

                            $jnsBank =
                                'REG';

                            break;
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | FILTER BANK
                |--------------------------------------------------------------------------
                */

                if (
                    $bank !== ''
                    && strtoupper($bank) !== 'ALL'
                ) {

                    if (
                        strtoupper(
                            (string) $jnsBank
                        ) !== strtoupper($bank)
                    ) {
                        continue;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | TOTAL RECEIPT
                |--------------------------------------------------------------------------
                */

                $receiptAmount =
                    $receiptGroup->sum(
                        function ($row) {

                            return (float) (
                                $row->receipt_amount
                                ?? 0
                            );
                        }
                    );

                /*
                |--------------------------------------------------------------------------
                | TOTAL MUTASI
                |--------------------------------------------------------------------------
                */

                $mutasiAmount =
                    $mutasiGroup->sum(
                        function ($row) {

                            return (float) (
                                $row->cr
                                ?? 0
                            );
                        }
                    );

                /*
                |--------------------------------------------------------------------------
                | JUMLAH DATA
                |--------------------------------------------------------------------------
                */

                $receiptCount =
                    $receiptGroup->count();

                $mutasiCount =
                    $mutasiGroup->count();

                /*
                |--------------------------------------------------------------------------
                | STATUS
                |--------------------------------------------------------------------------
                */

                if (
                    $receiptCount > 0
                    && $mutasiCount === 0
                ) {

                    $status = 'RECEIPT_ONLY';

                } elseif (
                    $receiptCount === 0
                    && $mutasiCount > 0
                ) {

                    $status = 'MUTASI_ONLY';

                } elseif (
                    $this->moneyEquals(
                        $receiptAmount,
                        $mutasiAmount
                    )
                ) {

                    $status = 'MATCH';

                } else {

                    $status = 'MATCH_SELISIH';
                }

                /*
                |--------------------------------------------------------------------------
                | FILTER CATEGORY
                |--------------------------------------------------------------------------
                */

                if (
                    $category !== ''
                    && $category !== 'ALL'
                    && $status !== $category
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | TANGGAL
                |--------------------------------------------------------------------------
                */

                $tanggal =
                    $receiptGroup
                        ->pluck('gl_date')
                        ->filter()
                        ->sort()
                        ->first()
                    ??
                    $mutasiGroup
                        ->pluck('tgl')
                        ->filter()
                        ->sort()
                        ->first();

                /*
                |--------------------------------------------------------------------------
                | DETAIL RECEIPT
                |--------------------------------------------------------------------------
                */

                $receiptData =
                    $receiptGroup
                        ->map(
                            function ($row) {

                                return [
                                    'id' =>
                                        $row->id,

                                    'receipt_number' =>
                                        $row->receipt_number,

                                    'receipt_amount' =>
                                        (float) (
                                            $row->receipt_amount
                                            ?? 0
                                        ),

                                    'receipt_date' =>
                                        $row->receipt_date,

                                    'gl_date' =>
                                        $row->gl_date,

                                    'no_rek' =>
                                        $row->remittance_bank_account,

                                    'receipt_type' =>
                                        $row->receipt_type,

                                    'receipt_status' =>
                                        $row->receipt_status,

                                    'receipt_state' =>
                                        $row->receipt_state,

                                    'comments' =>
                                        $row->comments,

                                    'activity' =>
                                        $row->activity,

                                    'paid_by' =>
                                        $row->paid_by,

                                    'reff' =>
                                        $row->reff,
                                ];
                            }
                        )
                        ->values()
                        ->toArray();

                /*
                |--------------------------------------------------------------------------
                | DETAIL MUTASI
                |--------------------------------------------------------------------------
                */

                $mutasiData =
                    $mutasiGroup
                        ->map(
                            function ($row) {

                                return [
                                    'id' =>
                                        $row->id,

                                    'cabang' =>
                                        $row->cabang,

                                    'no_rek' =>
                                        $row->no_rek,

                                    'tgl' =>
                                        $row->tgl,

                                    'cr' =>
                                        (float) (
                                            $row->cr
                                            ?? 0
                                        ),

                                    'trx_code' =>
                                        $row->trx_code,

                                    'remark' =>
                                        $row->remark,

                                    'remark1' =>
                                        $row->remark1,

                                    'reff' =>
                                        $row->reff,
                                ];
                            }
                        )
                        ->values()
                        ->toArray();

                /*
                |--------------------------------------------------------------------------
                | DIFFERENCE
                |--------------------------------------------------------------------------
                |
                | Frontend menggunakan:
                |
                | mutasi - receipt
                |
                */

                $difference =
                    $mutasiAmount
                    - $receiptAmount;

                /*
                |--------------------------------------------------------------------------
                | DATA HISTORY
                |--------------------------------------------------------------------------
                */

                $hasil[] = [

                    'id' =>
                        $reff
                        ?? $groupKey,

                    'reff' =>
                        $reff,

                    'status' =>
                        $status,

                    'category' =>
                        $status,

                    'type' =>
                        $type,

                    'jns_bank' =>
                        $jnsBank,

                    'bank' =>
                        $jnsBank,

                    'no_rek' =>
                        $noRek,

                    'receipt_number' =>
                        $receiptGroup
                            ->pluck('receipt_number')
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(', '),                    
                    'tanggal' =>
                        $tanggal,

                    'receipt_date' =>
                        $receiptGroup
                            ->pluck('receipt_date')
                            ->filter()
                            ->sort()
                            ->first(),

                    'mutasi_date' =>
                        $mutasiGroup
                            ->pluck('tgl')
                            ->filter()
                            ->sort()
                            ->first(),

                    'receipt_amount' =>
                        $receiptAmount,

                    'mutasi_amount' =>
                        $mutasiAmount,

                    'difference' =>
                        $difference,

                    'difference_amount' =>
                        $difference,

                    'receipt_count' =>
                        $receiptCount,

                    'mutasi_count' =>
                        $mutasiCount,

                    'receipt_ids' =>
                        $receiptGroup
                            ->pluck('id')
                            ->values()
                            ->toArray(),

                    'mutasi_ids' =>
                        $mutasiGroup
                            ->pluck('id')
                            ->values()
                            ->toArray(),

                    'receipt_reff' =>
                        $receiptGroup
                            ->pluck('reff')
                            ->filter()
                            ->unique()
                            ->values()
                            ->first(),

                    'mutation_reff' =>
                        $mutasiGroup
                            ->pluck('reff')
                            ->filter()
                            ->unique()
                            ->values()
                            ->first(),

                    'receipt_status' =>
                        $receiptGroup
                            ->pluck('receipt_status')
                            ->filter()
                            ->unique()
                            ->values()
                            ->first(),

                    'receipt_state' =>
                        $receiptGroup
                            ->pluck('receipt_state')
                            ->filter()
                            ->unique()
                            ->values()
                            ->first(),

                    'receipts' =>
                        $receiptData,

                    'mutasi' =>
                        $mutasiData,

                    /*
                    |--------------------------------------------------------------------------
                    | Flag untuk frontend
                    |--------------------------------------------------------------------------
                    */

                    'can_manual_reconcile' =>
                        (
                            $receiptCount > 0
                            || $mutasiCount > 0
                        ),

                ];
            }

            /*
            |--------------------------------------------------------------------------
            | SORT
            |--------------------------------------------------------------------------
            */

            $hasil = collect($hasil)
                ->sortBy([
                    ['tanggal', 'asc'],
                    ['no_rek', 'asc'],
                ])
                ->values();

            /*
            |--------------------------------------------------------------------------
            | SUMMARY
            |--------------------------------------------------------------------------
            */

            $summary = [
                'total' =>
                    $hasil->count(),

                'match' =>
                    $hasil
                        ->where(
                            'status',
                            'MATCH'
                        )
                        ->count(),

                'match_selisih' =>
                    $hasil
                        ->where(
                            'status',
                            'MATCH_SELISIH'
                        )
                        ->count(),

                'receipt_only' =>
                    $hasil
                        ->where(
                            'status',
                            'RECEIPT_ONLY'
                        )
                        ->count(),

                'mutasi_only' =>
                    $hasil
                        ->where(
                            'status',
                            'MUTASI_ONLY'
                        )
                        ->count(),

                'receipt_amount' =>
                    $hasil->sum(
                        'receipt_amount'
                    ),

                'mutasi_amount' =>
                    $hasil->sum(
                        'mutasi_amount'
                    ),

                'difference_amount' =>
                    $hasil->sum(
                        'difference_amount'
                    ),
            ];

            return response()->json([
                'success' => true,
                'type' => $type,
                'cabang' => $cabang,
                'data' => $hasil,
                'summary' => $summary,
            ]);

        } catch (\Throwable $e) {

            Log::error(
                'RECONCILIATION HISTORY ERROR',
                [
                    'cabang' => $cabang,
                    'type' => $type,
                    'bank' => $bank,
                    'account' => $account,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal mengambil riwayat rekonsiliasi: '
                    . $e->getMessage(),
                'data' => [],
                'summary' =>
                    $this->emptyHistorySummary(),
            ], 500);
        }
    }

    public function manualReconciliation(Request $request)
    {
        $request->validate([
            'cabang' => 'required|string|max:50',

            'type' => [
                'required',
                'in:FRC,REG',
            ],

            'receipt_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'receipt_ids.*' => [
                'integer',
            ],

            'mutasi_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'mutasi_ids.*' => [
                'integer',
            ],
        ]);

        $cabang = trim(
            $request->input('cabang')
        );

        $type = strtoupper(
            trim(
                $request->input('type')
            )
        );

        $receiptIds = collect(
            $request->input(
                'receipt_ids',
                []
            )
        )
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $mutasiIds = collect(
            $request->input(
                'mutasi_ids',
                []
            )
        )
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($receiptIds->isEmpty()) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Minimal pilih satu Receipt.',
            ], 422);
        }

        if ($mutasiIds->isEmpty()) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Minimal pilih satu Mutasi.',
            ], 422);
        }

        try {

            $result = DB::transaction(
                function () use (
                    $cabang,
                    $type,
                    $receiptIds,
                    $mutasiIds
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | REKENING BANK CABANG
                    |--------------------------------------------------------------------------
                    */

                    $bankRows = DB::table('bank')
                        ->where(
                            'cabang',
                            $cabang
                        )
                        ->whereNotNull('no_rek')
                        ->whereRaw(
                            "TRIM(no_rek) <> ''"
                        )
                        ->get([
                            'no_rek',
                            'jns_bank',
                            'site',
                        ]);

                    $validAccounts =
                        $bankRows
                            ->filter(
                                function ($row) use (
                                    $type
                                ) {

                                    if (
                                        $type === 'REG'
                                    ) {

                                        return strtoupper(
                                            trim(
                                                (string)
                                                $row->site
                                            )
                                        ) === 'REG';
                                    }

                                    return strtoupper(
                                        trim(
                                            (string)
                                            $row->site
                                        )
                                    ) !== 'REG';
                                }
                            )
                            ->pluck('no_rek')
                            ->map(
                                fn ($value) =>
                                    trim(
                                        (string) $value
                                    )
                            )
                            ->filter()
                            ->unique()
                            ->values();

                    /*
                    |--------------------------------------------------------------------------
                    | RECEIPT
                    |--------------------------------------------------------------------------
                    */

                    $receipts =
                        DB::table('receipt')
                            ->whereIn(
                                'id',
                                $receiptIds
                                    ->toArray()
                            )
                            ->lockForUpdate()
                            ->get([
                                'id',
                                'receipt_number',
                                'receipt_amount',
                                'receipt_date',
                                'gl_date',
                                'remittance_bank_account',
                                'receipt_status',
                                'receipt_state',
                                'reff',
                            ]);

                    if (
                        $receipts->count()
                        !== $receiptIds->count()
                    ) {

                        throw new \Exception(
                            'Ada Receipt yang tidak ditemukan.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CEK RECEIPT SUDAH DIREKON
                    |--------------------------------------------------------------------------
                    */

                    $alreadyReceipt =
                        $receipts->filter(
                            function ($row) {

                                return trim(
                                    (string) (
                                        $row->reff
                                        ?? ''
                                    )
                                ) !== '';
                            }
                        );

                    if (
                        $alreadyReceipt->isNotEmpty()
                    ) {

                        $numbers =
                            $alreadyReceipt
                                ->pluck(
                                    'receipt_number'
                                )
                                ->implode(', ');

                        throw new \Exception(
                            'Receipt sudah memiliki reff: '
                            . $numbers
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CEK REKENING RECEIPT
                    |--------------------------------------------------------------------------
                    */

                    $receiptAccounts =
                        $receipts
                            ->pluck(
                                'remittance_bank_account'
                            )
                            ->map(
                                fn ($value) =>
                                    trim(
                                        (string) $value
                                    )
                            )
                            ->unique()
                            ->values();

                    foreach (
                        $receiptAccounts
                        as $receiptAccount
                    ) {

                        if (
                            !$validAccounts
                                ->contains(
                                    $receiptAccount
                                )
                        ) {

                            throw new \Exception(
                                "Rekening Receipt "
                                . $receiptAccount
                                . " tidak valid untuk "
                                . $type
                                . " cabang "
                                . $cabang
                            );
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | HARUS SATU REKENING
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $receiptAccounts->count()
                        > 1
                    ) {

                        throw new \Exception(
                            'Receipt yang dipilih '
                            . 'berasal dari rekening berbeda.'
                        );
                    }

                    $receiptAccount =
                        $receiptAccounts
                            ->first();

                    /*
                    |--------------------------------------------------------------------------
                    | MUTASI
                    |--------------------------------------------------------------------------
                    */

                    if ($type === 'FRC') {

                        $mutasi =
                            DB::table(
                                'mutasi_detail_frc'
                            )
                                ->whereIn(
                                    'id',
                                    $mutasiIds
                                        ->toArray()
                                )
                                ->where(
                                    'cabang',
                                    $cabang
                                )
                                ->lockForUpdate()
                                ->get([
                                    'id',
                                    'cabang',
                                    'no_rek',
                                    'tgl',
                                    'cr',
                                    'trx_code',
                                    'remark',
                                    'remark1',
                                    'reff',
                                ]);

                    } else {

                        $mutasi =
                            DB::table('mutasi')
                                ->whereIn(
                                    'id',
                                    $mutasiIds
                                        ->toArray()
                                )
                                ->where(
                                    'cabang',
                                    $cabang
                                )
                                ->lockForUpdate()
                                ->get([
                                    'id',
                                    'cabang',
                                    'no_rek',
                                    'tgl',
                                    'cr',
                                    'trx_code',
                                    'remark',
                                    'remark1',
                                    'reff',
                                ]);
                    }

                    if (
                        $mutasi->count()
                        !== $mutasiIds->count()
                    ) {

                        throw new \Exception(
                            'Ada Mutasi yang tidak ditemukan '
                            . 'atau bukan milik cabang '
                            . $cabang
                            . '.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CEK MUTASI SUDAH DIREKON
                    |--------------------------------------------------------------------------
                    */

                    $alreadyMutasi =
                        $mutasi->filter(
                            function ($row) {

                                return trim(
                                    (string) (
                                        $row->reff
                                        ?? ''
                                    )
                                ) !== '';
                            }
                        );

                    if (
                        $alreadyMutasi->isNotEmpty()
                    ) {

                        throw new \Exception(
                            'Ada Mutasi yang sudah memiliki reff.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CEK REKENING MUTASI
                    |--------------------------------------------------------------------------
                    */

                    $mutasiAccounts =
                        $mutasi
                            ->pluck('no_rek')
                            ->map(
                                fn ($value) =>
                                    trim(
                                        (string) $value
                                    )
                            )
                            ->unique()
                            ->values();

                    if (
                        $mutasiAccounts->count()
                        > 1
                    ) {

                        throw new \Exception(
                            'Mutasi yang dipilih '
                            . 'berasal dari rekening berbeda.'
                        );
                    }

                    $mutasiAccount =
                        $mutasiAccounts
                            ->first();

                    /*
                    |--------------------------------------------------------------------------
                    | RECEIPT VS MUTASI REKENING
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $receiptAccount
                        !== $mutasiAccount
                    ) {

                        throw new \Exception(
                            'Rekening Receipt dan Mutasi berbeda.'
                            . ' Receipt: '
                            . $receiptAccount
                            . ', Mutasi: '
                            . $mutasiAccount
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | TOTAL
                    |--------------------------------------------------------------------------
                    */

                    $receiptAmount =
                        $receipts->sum(
                            function ($row) {

                                return (float) (
                                    $row->receipt_amount
                                    ?? 0
                                );
                            }
                        );

                    $mutasiAmount =
                        $mutasi->sum(
                            function ($row) {

                                return (float) (
                                    $row->cr
                                    ?? 0
                                );
                            }
                        );

                    $difference =
                        $mutasiAmount
                        - $receiptAmount;

                    /*
                    |--------------------------------------------------------------------------
                    | GENERATE REFF
                    |--------------------------------------------------------------------------
                    |
                    | Tidak memakai ID mutasi saja karena:
                    |
                    | - manual
                    | - bisa many-to-many
                    | - harus mudah dibedakan dari otomatis
                    |
                    */

                    $reff =
                        $type
                        . '-MANUAL-'
                        . now()->format(
                            'YmdHis'
                        )
                        . '-'
                        . strtoupper(
                            Str::random(6)
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE RECEIPT
                    |--------------------------------------------------------------------------
                    */

                    $updatedReceipt =
                        DB::table('receipt')
                            ->whereIn(
                                'id',
                                $receiptIds
                                    ->toArray()
                            )
                            ->where(
                                function ($query) {

                                    $query
                                        ->whereNull(
                                            'reff'
                                        )
                                        ->orWhere(
                                            'reff',
                                            ''
                                        );
                                }
                            )
                            ->update([
                                'reff' => $reff,
                            ]);

                    if (
                        $updatedReceipt
                        !== $receiptIds->count()
                    ) {

                        throw new \Exception(
                            'Gagal update Receipt. '
                            . 'Kemungkinan data sudah '
                            . 'direkonsiliasi oleh proses lain.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE MUTASI
                    |--------------------------------------------------------------------------
                    */

                    if ($type === 'FRC') {

                        $updatedMutasi =
                            DB::table(
                                'mutasi_detail_frc'
                            )
                                ->whereIn(
                                    'id',
                                    $mutasiIds
                                        ->toArray()
                                )
                                ->where(
                                    'cabang',
                                    $cabang
                                )
                                ->where(
                                    function ($query) {

                                        $query
                                            ->whereNull(
                                                'reff'
                                            )
                                            ->orWhere(
                                                'reff',
                                                ''
                                            );
                                    }
                                )
                                ->update([
                                    'reff' => $reff,
                                ]);

                    } else {

                        $updatedMutasi =
                            DB::table('mutasi')
                                ->whereIn(
                                    'id',
                                    $mutasiIds
                                        ->toArray()
                                )
                                ->where(
                                    'cabang',
                                    $cabang
                                )
                                ->where(
                                    function ($query) {

                                        $query
                                            ->whereNull(
                                                'reff'
                                            )
                                            ->orWhere(
                                                'reff',
                                                ''
                                            );
                                    }
                                )
                                ->update([
                                    'reff' => $reff,
                                ]);
                    }

                    if (
                        $updatedMutasi
                        !== $mutasiIds->count()
                    ) {

                        throw new \Exception(
                            'Gagal update Mutasi. '
                            . 'Kemungkinan data sudah '
                            . 'direkonsiliasi oleh proses lain.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | STATUS HASIL MANUAL
                    |--------------------------------------------------------------------------
                    */

                    $status =
                        $this->moneyEquals(
                            $receiptAmount,
                            $mutasiAmount
                        )
                        ? 'MATCH'
                        : 'MATCH_SELISIH';

                    return [
                        'reff' =>
                            $reff,

                        'status' =>
                            $status,

                        'type' =>
                            $type,

                        'cabang' =>
                            $cabang,

                        'no_rek' =>
                            $receiptAccount,

                        'receipt_ids' =>
                            $receiptIds
                                ->toArray(),

                        'mutasi_ids' =>
                            $mutasiIds
                                ->toArray(),

                        'receipt_count' =>
                            $receipts->count(),

                        'mutasi_count' =>
                            $mutasi->count(),

                        'receipt_amount' =>
                            $receiptAmount,

                        'mutasi_amount' =>
                            $mutasiAmount,

                        'difference' =>
                            $difference,

                        'difference_amount' =>
                            $difference,
                    ];
                }
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Rekonsiliasi manual berhasil.',
                'data' =>
                    $result,
            ]);

        } catch (\Throwable $e) {

            Log::error(
                'MANUAL RECONCILIATION ERROR',
                [
                    'cabang' => $cabang,
                    'type' => $type,
                    'receipt_ids' =>
                        $receiptIds->toArray(),
                    'mutasi_ids' =>
                        $mutasiIds->toArray(),
                    'message' =>
                        $e->getMessage(),
                    'file' =>
                        $e->getFile(),
                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    $e->getMessage(),
            ], 422);
        }
    }

    private function emptyHistorySummary()
    {
        return [
            'total' => 0,

            'match' => 0,

            'match_selisih' => 0,

            'receipt_only' => 0,

            'mutasi_only' => 0,

            'receipt_amount' => 0,

            'mutasi_amount' => 0,

            'difference_amount' => 0,
        ];
    }

    public function getReconciliationHistoryFilters(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDASI
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'cabang' => 'required|string|max:50',
            'type'   => 'required|in:FRC,REG',
        ]);


        /*
        |--------------------------------------------------------------------------
        | PARAMETER
        |--------------------------------------------------------------------------
        */

        $cabang = trim(
            $request->input('cabang')
        );

        $type = strtoupper(
            trim(
                $request->input('type')
            )
        );


        /*
        |--------------------------------------------------------------------------
        | BASE QUERY BANK
        |--------------------------------------------------------------------------
        |
        | Tabel bank digunakan sebagai master:
        |
        | FRC
        | - site <> REG
        | - no_rek
        | - jns_bank
        |
        | REG
        | - site = REG
        | - no_rek
        | - jns_bank
        |
        |--------------------------------------------------------------------------
        */

        $bankQuery = DB::table('bank')
            ->where('cabang', $cabang);


        /*
        |--------------------------------------------------------------------------
        | FILTER TYPE
        |--------------------------------------------------------------------------
        */

        if ($type === 'FRC') {

            $bankQuery
                ->where(function ($query) {

                    $query
                        ->whereNull('site')
                        ->orWhere('site', '<>', 'REG');

                });

        } else {

            $bankQuery
                ->where('site', 'REG');

        }


        /*
        |--------------------------------------------------------------------------
        | AMBIL DATA BANK
        |--------------------------------------------------------------------------
        */

        $bankRows = $bankQuery
            ->select([
                'no_rek',
                'jns_bank',
                'bank',
                'site',
            ])
            ->get();


        /*
        |--------------------------------------------------------------------------
        | DAFTAR JENIS BANK
        |--------------------------------------------------------------------------
        |
        | Frontend membutuhkan:
        |
        | result.banks
        |
        |--------------------------------------------------------------------------
        */

        $banks = $bankRows
            ->map(function ($row) {

                /*
                Jika jns_bank tersedia gunakan jns_bank.
                Jika kosong gunakan nama bank.
                */

                $value = trim(
                    (string) (
                        $row->jns_bank
                        ?: $row->bank
                        ?: ''
                    )
                );

                return $value;

            })
            ->filter(function ($value) {

                return $value !== '';

            })
            ->unique()
            ->sort()
            ->values();


        /*
        |--------------------------------------------------------------------------
        | DAFTAR NOMOR REKENING
        |--------------------------------------------------------------------------
        |
        | Frontend membutuhkan:
        |
        | result.accounts
        |
        |--------------------------------------------------------------------------
        */

        $accounts = $bankRows
            ->map(function ($row) {

                return trim(
                    (string) (
                        $row->no_rek
                        ?? ''
                    )
                );

            })
            ->filter(function ($value) {

                return $value !== '';

            })
            ->unique()
            ->sort()
            ->values();


        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'type' => $type,

            'cabang' => $cabang,

            'banks' => $banks,

            'accounts' => $accounts,

            'categories' => [
                'MATCH',
                'RECEIPT_ONLY',
                'MUTASI_ONLY',
                'MATCH_SELISIH',
            ],

        ]);
    }

    public function getReconciliationMethods(Request $request)
    {
        $validated = $request->validate([
            'cabang' => ['required', 'string', 'max:50'],
            'no_rek' => ['required', 'string', 'max:100'],
        ]);

        $cabang = trim($validated['cabang']);
        $noRek = trim($validated['no_rek']);

        // 1. Pastikan rekening terdaftar untuk cabang
        //    dan merupakan rekening Reguler.
        $bank = DB::table('bank')
            ->where('cabang', $cabang)
            ->where('no_rek', $noRek)
            ->where('site', 'REG')
            ->select('id', 'no_rek', 'jns_bank', 'site')
            ->first();

        if (!$bank) {
            return response()->json([
                'success' => false,
                'message' => 'Rekening Reguler tidak ditemukan untuk cabang tersebut.',
                'data' => [],
            ], 404);
        }
        
        // 2. Ambil metode berdasarkan cabang dan jns_bank.
        $methods = DB::table('setting')
            ->whereRaw('TRIM(cabang) = ?', [$cabang])
            ->whereRaw('TRIM(category) = ?', ['rekon_methode'])
            ->whereRaw(
                'TRIM(sub_category) = ?',
                [trim((string) $bank->jns_bank)]
            )
            ->select(
                'id',
                'cabang',
                'category',
                'sub_category',
                'value'
            )
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Metode rekonsiliasi berhasil dimuat.',
            'rekening' => [
                'no_rek' => $bank->no_rek,
                'jns_bank' => $bank->jns_bank,
            ],
            'data' => $methods,
        ]);
    }
    
    public function updateMutasi(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. VALIDASI PAYLOAD
        |--------------------------------------------------------------------------
        */

        $validator = Validator::make($request->all(), [
            'cabang'            => ['required', 'string', 'max:50'],
            'records'           => ['required', 'array', 'min:1'],
            'records.*.no_rek'  => ['required', 'string', 'max:100'],
            'records.*.tgl'     => ['required', 'string', 'max:50'],
            'records.*.remark'  => ['required', 'string'],
            'records.*.remark1' => ['sometimes', 'nullable', 'string'],
            'records.*.cr'      => ['required'],
            'records.*.dept'    => ['required', 'string', 'max:50'],
            'records.*.site'    => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi data gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $cabang  = trim($request->input('cabang'));
        $records = $request->input('records');

        $total   = count($records);
        $updated = 0;
        $skipped = 0;
        $failed  = 0;
        $errors  = [];

        /*
        |--------------------------------------------------------------------------
        | 2. FORMAT TANGGAL YANG DIDUKUNG
        |--------------------------------------------------------------------------
        */

        $dateFormats = [
            'd-M-y',
            'd-M-Y',
            'd/m/Y',
            'd/m/y',
            'Y-m-d',
            'd-m-Y',
            'd-m-y',
        ];

        /*
        |--------------------------------------------------------------------------
        | 3. PROSES SETIAP BARIS EXCEL
        |--------------------------------------------------------------------------
        */

        foreach ($records as $index => $record) {
            $rowNumber = $index + 2;

            try {
                /*
                |--------------------------------------------------------------------------
                | NORMALISASI TANGGAL
                |--------------------------------------------------------------------------
                */

                $rawDate = trim((string) $record['tgl']);
                $tgl = null;

                foreach ($dateFormats as $format) {
                    try {
                        $parsed = \Carbon\Carbon::createFromFormat(
                            '!' . $format,
                            $rawDate,
                            config('app.timezone')
                        );

                        if ($parsed !== false) {
                            $dateErrors = \Carbon\Carbon::getLastErrors();

                            if (
                                $dateErrors === false ||
                                (
                                    $dateErrors['warning_count'] === 0 &&
                                    $dateErrors['error_count'] === 0
                                )
                            ) {
                                $tgl = $parsed->format('Y-m-d');
                                break;
                            }
                        }
                    } catch (\Throwable $e) {
                        // Lanjutkan ke format tanggal berikutnya.
                    }
                }

                if (!$tgl) {
                    $failed++;

                    $errors[] = [
                        'row'     => $rowNumber,
                        'message' => "Format tanggal tidak valid: {$rawDate}",
                    ];

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | NORMALISASI CR
                |--------------------------------------------------------------------------
                | 48,000.00 -> 48000.00
                */

                $rawCr = trim((string) $record['cr']);
                $normalizedCr = str_replace(',', '', $rawCr);

                if (
                    $normalizedCr === '' ||
                    !is_numeric($normalizedCr) ||
                    (float) $normalizedCr < 0
                ) {
                    $failed++;

                    $errors[] = [
                        'row'     => $rowNumber,
                        'message' => "Nominal CR tidak valid: {$rawCr}",
                    ];

                    continue;
                }

                $cr = number_format(
                    (float) $normalizedCr,
                    2,
                    '.',
                    ''
                );

                /*
                |--------------------------------------------------------------------------
                | NORMALISASI DATA TRANSAKSI
                |--------------------------------------------------------------------------
                */

                $noRek = trim((string) $record['no_rek']);
                $remark = (string) $record['remark'];
                $dept = trim((string) ($record['dept'] ?? ''));
                $site = trim((string) ($record['site'] ?? ''));
                $updateSite = $site !== '';

                if (
                    $noRek === '' ||
                    trim($remark) === '' ||
                    $dept === ''
                ) {
                    $failed++;

                    $errors[] = [
                        'row'     => $rowNumber,
                        'message' => 'NO REK, REMARK, dan DEPT tidak boleh kosong. SITE bersifat opsional.',
                    ];

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | REMARK1 OPSIONAL
                |--------------------------------------------------------------------------
                | Jika properti remark1 dikirim:
                | - nilai berisi teks: cocokkan dengan nilai tersebut
                | - nilai kosong/null: cocokkan dengan remark1 NULL atau ''
                |
                | Jika properti tidak dikirim: jangan gunakan remark1 sebagai filter.
                */

                $hasRemark1 = array_key_exists('remark1', $record);

                $remark1 = $hasRemark1 && $record['remark1'] !== null
                    ? (string) $record['remark1']
                    : null;

                /*
                |--------------------------------------------------------------------------
                | 4. CARI DAN UPDATE SATU RECORD
                |--------------------------------------------------------------------------
                | Pemilihan ID dan update dilakukan dalam transaksi.
                | lockForUpdate mencegah record yang sama dipilih oleh
                | proses bersamaan sebelum transaksi selesai.
                */

                $result = DB::transaction(function () use (
                    $cabang,
                    $noRek,
                    $tgl,
                    $remark,
                    $remark1,
                    $hasRemark1,
                    $cr,
                    $dept,
                    $site,
                    $updateSite
                ) {
                    $query = DB::table('mutasi_detail')
                        ->select('id')
                        ->where('cabang', $cabang)
                        ->where('no_rek', $noRek)
                        ->where('tgl', $tgl)
                        ->where('remark', $remark)
                        ->where('cr', $cr)
                        ->whereNull('dept')
                        ->orderBy('id')
                        ->lockForUpdate();

                    // Jika SITE akan diisi, pertahankan aturan lama:
                    // record harus memiliki dept dan site yang masih NULL.
                    // Jika SITE kosong, cukup pastikan dept masih NULL.
                    if ($updateSite) {
                        $query->whereNull('site');
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | FILTER REMARK1 JIKA DIKIRIM
                    |--------------------------------------------------------------------------
                    */

                    if ($hasRemark1) {
                        if ($remark1 === null || trim($remark1) === '') {
                            $query->where(function ($q) {
                                $q->whereNull('remark1')
                                ->orWhere('remark1', '');
                            });
                        } else {
                            $query->where('remark1', $remark1);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | AMBIL SATU RECORD SAJA
                    |--------------------------------------------------------------------------
                    */

                    $mutasi = $query->first();

                    if (!$mutasi) {
                        return 'skipped';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE BERDASARKAN PRIMARY KEY
                    |--------------------------------------------------------------------------
                    | Syarat dept NULL diperiksa kembali untuk keamanan.
                    */

                    $updateData = [
                        'dept' => $dept,
                    ];

                    // SITE hanya ikut diperbarui jika nilainya diisi.
                    if ($updateSite) {
                        $updateData['site'] = $site;
                    }

                    $updateQuery = DB::table('mutasi_detail')
                        ->where('id', $mutasi->id)
                        ->where('cabang', $cabang);

                    $affected = $updateQuery->update($updateData);

                    return $affected === 1 ? 'updated' : 'skipped';
                }, 3);

                if ($result === 'updated') {
                    $updated++;
                } else {
                    $skipped++;
                }

            } catch (\Throwable $e) {
                report($e);

                $failed++;

                $errors[] = [
                    'row'     => $rowNumber,
                    'message' => $e->getMessage(),
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 5. RESPONSE KE FRONTEND
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => $failed === 0,
            'message' => 'Proses update mutasi selesai.',
            'total'   => $total,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed'  => $failed,
            'errors'  => $errors,
        ]);
    }
    
    public function updateReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cabang' => ['required', 'string', 'max:50'],
            'records' => ['required', 'array', 'min:1'],

            'records.*.remittance_bank_account' => [
                'required',
                'string',
                'max:100',
            ],
            'records.*.receipt_number' => [
                'required',
                'string',
                'max:100',
            ],
            'records.*.receipt_date' => [
                'required',
                'date',
            ],

            // Field opsional: hanya divalidasi jika dikirim dan berisi nilai.
            'records.*.category' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],
            'records.*.site' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],
            'records.*.note' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi data receipt gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cabang = trim((string) $request->input('cabang'));
        $records = $request->input('records', []);

        $total = count($records);
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        try {
            DB::transaction(function () use (
                $records,
                &$updated,
                &$skipped,
                &$failed,
                &$errors
            ) {
                foreach ($records as $index => $record) {
                    $rowNumber = $index + 1;

                    try {
                        $account = trim(
                            (string) ($record['remittance_bank_account'] ?? '')
                        );

                        $receiptNumber = trim(
                            (string) ($record['receipt_number'] ?? '')
                        );

                        $receiptDate = trim(
                            (string) ($record['receipt_date'] ?? '')
                        );

                        /*
                        * Cari berdasarkan rekening dan nomor receipt.
                        * receipt_date tetap divalidasi, tetapi bukan
                        * kunci pencarian.
                        */
                        $query = DB::table('receipt')
                            ->where(
                                'remittance_bank_account',
                                $account
                            )
                            ->where(
                                'receipt_number',
                                $receiptNumber
                            );

                        $matches = (clone $query)
                            ->lockForUpdate()
                            ->limit(2)
                            ->get(['id']);

                        if ($matches->isEmpty()) {
                            $skipped++;

                            $errors[] = [
                                'row' => $rowNumber,
                                'message' =>
                                    "Receipt tidak ditemukan: rekening "
                                    . "{$account}, nomor {$receiptNumber}.",
                            ];

                            continue;
                        }

                        if ($matches->count() > 1) {
                            $skipped++;

                            $errors[] = [
                                'row' => $rowNumber,
                                'message' =>
                                    "Ditemukan lebih dari satu receipt "
                                    . "untuk rekening {$account} dan "
                                    . "nomor {$receiptNumber}. "
                                    . "Data tidak diperbarui.",
                            ];

                            continue;
                        }

                        $receiptId = $matches->first()->id;

                        /*
                        * Hanya field yang memiliki nilai yang akan
                        * dimasukkan ke updateData.
                        *
                        * category, site, dan note:
                        * - Tidak dikirim       -> tidak diupdate.
                        * - Nilai null/kosong  -> tidak diupdate.
                        * - Berisi nilai       -> diupdate.
                        */
                        $updateData = [];

                        foreach (['category', 'site', 'note'] as $field) {
                            if (!array_key_exists($field, $record)) {
                                continue;
                            }

                            $value = $record[$field];

                            if ($value === null) {
                                continue;
                            }

                            if (is_string($value)) {
                                $value = trim($value);
                            }

                            if ($value === '') {
                                continue;
                            }

                            $updateData[$field] = $value;
                        }

                        /*
                        * Tidak ada field opsional yang berisi nilai.
                        * Receipt tetap dihitung sebagai dilewati karena
                        * tidak ada perubahan yang perlu dilakukan.
                        */
                        if (empty($updateData)) {
                            $skipped++;

                            $errors[] = [
                                'row' => $rowNumber,
                                'message' =>
                                    "Tidak ada field category, site, atau "
                                    . "note yang berisi nilai untuk diupdate.",
                            ];

                            continue;
                        }

                        DB::table('receipt')
                            ->where('id', $receiptId)
                            ->where(
                                'remittance_bank_account',
                                $account
                            )
                            ->where(
                                'receipt_number',
                                $receiptNumber
                            )
                            ->update($updateData);

                        $updated++;

                    } catch (\Throwable $e) {
                        $failed++;

                        $errors[] = [
                            'row' => $rowNumber,
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            });

            return response()->json([
                'success' => $failed === 0 && $skipped === 0,
                'message' => 'Proses update receipt selesai.',
                'total' => $total,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $failed,
                'errors' => $errors,
            ]);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses update receipt.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Terjadi kesalahan pada server.',
            ], 500);
        }
    }

}