<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ReconciliationService;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
                'remittance_bank_account'
            )
            ->get();
    }

    private function getReceiptScope(
        string $cabang,
        string $remittanceBankAccount
    ) {
        $bank = DB::table('bank')
            ->where('cabang', $cabang)
            ->where(
                'remittance_bank_account',
                $remittanceBankAccount
            )
            ->first();

        if (!$bank) {
            return null;
        }

        $jnsBank = strtoupper(trim($bank->jns_bank ?? ''));

        if ($jnsBank === 'REG') {
            return [
                'type' => 'REG',
                'account' => trim($bank->no_rek),
                'jns_bank' => 'REG'
            ];
        }

        return [
            'type' => 'FRC',
            'account' => null,
            'jns_bank' => trim($bank->jns_bank)
        ];
    }

    public function importReceipt(Request $request)
    {
        $request->validate([
            'files'   => 'required',
            'files.*' => 'required|file|mimes:csv,txt',
            'cabang'  => 'required|string|max:50'
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
            | Mapping Remittance Bank Account
            |--------------------------------------------------------------------------
            */

            $validAccounts = [];

            foreach ($bankConfigs as $bank) {

                $account = trim(
                    $bank->remittance_bank_account ?? ''
                );

                if ($account !== '') {

                    $validAccounts[$account] = $bank;
                }
            }

            $existingReceipt = DB::table('receipt')
                ->select(
                    'id',
                    'receipt_number',
                    'remittance_bank_account',
                    'receipt_amount',
                    'receipt_date',
                    'receipt_status',
                    'receipt_state'
                )
                ->whereBetween('receipt_date', [
                    $activePeriod->start_date,
                    $activePeriod->end_date
                ])
                ->get();

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
            | LOOP FILE
            |--------------------------------------------------------------------------
            */

            foreach ($request->file('files') as $file) {

                $handle = fopen(
                    $file->getRealPath(),
                    'r'
                );

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
                | Header
                |--------------------------------------------------------------------------
                */

                $header = fgetcsv(
                    $handle,
                    0,
                    "|"
                );

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

                $header = array_map(
                    'trim',
                    $header
                );


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


                if ($header !== $expectedHeader) {

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
                | Loop Data
                |--------------------------------------------------------------------------
                */

                $line = 1;

                while (
                    ($row = fgetcsv(
                        $handle,
                        0,
                        "|"
                    )) !== false
                ) {

                    $line++;


                    if (count($row) !== 12) {

                        $errors[] = [
                            'file' => $file->getClientOriginalName(),
                            'issues' => [
                                "Baris {$line} jumlah kolom tidak sesuai."
                            ]
                        ];

                        continue;
                    }


                    $receiptMethod =
                        trim($row[0]);

                    $remittanceBankAccount =
                        trim($row[1]);

                    $receiptNumber =
                        trim($row[2]);

                    $receiptAmount =
                        str_replace(
                            ",",
                            "",
                            trim($row[3])
                        );

                    $receiptDate =
                        trim($row[4]);

                    $glDate =
                        trim($row[5]);

                    $receiptType =
                        trim($row[6]);

                    $receiptStatus =
                        trim($row[7]);

                    $receiptState =
                        trim($row[8]);

                    $comments =
                        trim($row[9]);

                    $activity =
                        trim($row[10]);

                    $paidBy =
                        trim($row[11]);


                    /*
                    |--------------------------------------------------------------------------
                    | Validasi Remittance Bank Account
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !isset(
                            $validAccounts[
                                $remittanceBankAccount
                            ]
                        )
                    ) {

                        $errors[] = [
                            'file' => $file->getClientOriginalName(),
                            'issues' => [
                                "Baris {$line}: Remittance Bank Account '{$remittanceBankAccount}' tidak terdaftar pada cabang {$cabang}."
                            ]
                        ];

                        continue;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Parse tanggal
                    |--------------------------------------------------------------------------
                    */

                    try {

                        $receiptDate =
                            Carbon::createFromFormat(
                                'd/m/y',
                                $receiptDate
                            )->format('Y-m-d');

                        $glDate =
                            Carbon::createFromFormat(
                                'd/m/y',
                                $glDate
                            )->format('Y-m-d');


                        /*
                        |--------------------------------------------------------------------------
                        | Receipt Date harus berada dalam periode aktif
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $receiptDate <
                                $activePeriod->start_date
                            ||
                            $receiptDate >
                                $activePeriod->end_date
                        ) {

                            $errors[] = [
                                'file' => $file->getClientOriginalName(),
                                'issues' => [
                                    "Baris {$line}: Receipt Date {$receiptDate} berada di luar periode aktif Mutasi."
                                ]
                            ];

                            continue;
                        }

                    } catch (\Throwable $e) {

                        $errors[] = [
                            'file' => $file->getClientOriginalName(),
                            'issues' => [
                                "Baris {$line}: format tanggal salah."
                            ]
                        ];

                        continue;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Composite Key
                    |--------------------------------------------------------------------------
                    */

                    $key =
                        $remittanceBankAccount
                        . '|'
                        . $receiptNumber;


                    /*
                    |--------------------------------------------------------------------------
                    | Receipt sudah ada
                    |--------------------------------------------------------------------------
                    */

                    if (
                        isset(
                            $existingMap[$key]
                        )
                    ) {

                        $old =
                            $existingMap[$key];


                        /*
                        |--------------------------------------------------------------------------
                        | Reversed tidak diproses ulang
                        |--------------------------------------------------------------------------
                        */

                        if (
                            strcasecmp(
                                $old->receipt_status,
                                'Reversed'
                            ) === 0
                        ) {

                            $skipped++;

                            continue;
                        }


                        $statusChanged =
                            strcasecmp(
                                $old->receipt_status,
                                $receiptStatus
                            ) !== 0;

                        $stateChanged =
                            strcasecmp(
                                $old->receipt_state,
                                $receiptState
                            ) !== 0;


                        if (
                            !$statusChanged
                            &&
                            !$stateChanged
                        ) {

                            $skipped++;

                            continue;
                        }


                        $updateData[] = [

                            'id' =>
                                $old->id,

                            'receipt_status' =>
                                $receiptStatus,

                            'receipt_state' =>
                                $receiptState,

                            'clear_trx' =>
                                strcasecmp(
                                    $receiptStatus,
                                    'Reversed'
                                ) === 0
                        ];

                        $updated++;

                        continue;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | INSERT
                    |--------------------------------------------------------------------------
                    */

                    $insertData[] = [

                        'receipt_method' =>
                            $receiptMethod,

                        'remittance_bank_account' =>
                            $remittanceBankAccount,

                        'receipt_number' =>
                            $receiptNumber,

                        'receipt_amount' =>
                            $receiptAmount,

                        'receipt_date' =>
                            $receiptDate,

                        'gl_date' =>
                            $glDate,

                        'receipt_type' =>
                            $receiptType,

                        'receipt_status' =>
                            $receiptStatus,

                        'receipt_state' =>
                            $receiptState,

                        'comments' =>
                            $comments,

                        'activity' =>
                            $activity,

                        'paid_by' =>
                            $paidBy,

                        'trx_id' =>
                            ''
                    ];


                    /*
                    |--------------------------------------------------------------------------
                    | Masukkan ke map agar duplikat antar file
                    | pada upload yang sama tidak terjadi
                    |--------------------------------------------------------------------------
                    */

                    $existingMap[$key] =
                        (object) [

                            'id' => 0,

                            'receipt_number' =>
                                $receiptNumber,

                            'remittance_bank_account' =>
                                $remittanceBankAccount,

                            'receipt_amount' =>
                                $receiptAmount,

                            'receipt_date' =>
                                $receiptDate,

                            'receipt_status' =>
                                $receiptStatus,

                            'receipt_state' =>
                                $receiptState
                        ];

                    $inserted++;
                }

                fclose($handle);
            }


            /*
            |--------------------------------------------------------------------------
            | Bulk INSERT
            |--------------------------------------------------------------------------
            */

            if (!empty($insertData)) {

                foreach (
                    array_chunk(
                        $insertData,
                        1000
                    ) as $chunk
                ) {

                    DB::table('receipt')
                        ->insert($chunk);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            foreach ($updateData as $row) {

                $update = [

                    'receipt_status' =>
                        $row['receipt_status'],

                    'receipt_state' =>
                        $row['receipt_state']
                ];


                if ($row['clear_trx']) {

                    $update['trx_id'] = '';
                }


                DB::table('receipt')
                    ->where(
                        'id',
                        $row['id']
                    )
                    ->update($update);
            }


            DB::commit();


            return response()->json([

                'success' => true,

                'message' =>
                    "Import Receipt selesai.",

                'inserted' =>
                    $inserted,

                'updated' =>
                    $updated,

                'skipped' =>
                    $skipped,

                'errors' =>
                    $errors,

                'period' => [

                    'periode' =>
                        $activePeriod->periode ?? null,

                    'start_date' =>
                        $activePeriod->start_date,

                    'end_date' =>
                        $activePeriod->end_date
                ]
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Import Receipt Error',
                [
                    'cabang' => $cabang,
                    'error' =>
                        $e->getMessage()
                ]
            );


            return response()->json([

                'success' => false,

                'message' =>
                    $e->getMessage()

            ], 500);
        }
    }

    private function buildMatchRow(
        $mutation,
        $receipt
    ) {

        $mutasiAmount =
            (float)
            $mutation->mutasi_amount;

        $receiptAmount =
            (float)
            $receipt->receipt_amount;

        $difference =
            $mutasiAmount
            -
            $receiptAmount;


        $accountMatch =
            trim(
                $mutation->no_rek ?? ''
            )
            ===
            trim(
                $this->resolveReceiptAccount(
                    $receipt
                )
            );


        return [

            'id' =>
                'M-' . $mutation->id
                . '-R-' . $receipt->id,

            'status' =>
                abs($difference) < 0.01
                    ? 'MATCH'
                    : 'NOMINAL_DIFFERENT',

            'mutation_id' =>
                $mutation->id,

            'receipt_id' =>
                $receipt->id,

            'mutation_number' =>
                $mutation->mutation_number,

            'receipt_number' =>
                $receipt->receipt_number,

            'mutasi_date' =>
                $mutation->mutasi_date,

            'receipt_date' =>
                $receipt->receipt_date,

            'no_rek' =>
                $mutation->no_rek,

            'jns_bank' =>
                $mutation->jns_bank,

            'remittance_bank_account' =>
                $receipt->remittance_bank_account,

            'mutasi_amount' =>
                $mutasiAmount,

            'receipt_amount' =>
                $receiptAmount,

            'difference_amount' =>
                $difference,

            'description' =>
                $mutation->description,

            'comments' =>
                $receipt->comments,

            'receipt_status' =>
                $receipt->receipt_status,

            'receipt_state' =>
                $receipt->receipt_state,

            'amount_match' =>
                abs($difference) < 0.01,

            'account_match' =>
                $accountMatch
        ];
    }

    private function buildMutasiOnlyRow(
        $mutation
    ) {

        $amount =
            (float)
            $mutation->mutasi_amount;


        return [

            'id' =>
                'M-' . $mutation->id,

            'status' =>
                'MUTASI_ONLY',

            'mutation_id' =>
                $mutation->id,

            'receipt_id' =>
                null,

            'mutation_number' =>
                $mutation->mutation_number,

            'receipt_number' =>
                null,

            'mutasi_date' =>
                $mutation->mutasi_date,

            'receipt_date' =>
                null,

            'no_rek' =>
                $mutation->no_rek,

            'jns_bank' =>
                $mutation->jns_bank,

            'remittance_bank_account' =>
                null,

            'mutasi_amount' =>
                $amount,

            'receipt_amount' =>
                0,

            'difference_amount' =>
                $amount,

            'description' =>
                $mutation->description,

            'comments' =>
                null,

            'receipt_status' =>
                null,

            'receipt_state' =>
                null,

            'amount_match' =>
                false,

            'account_match' =>
                false
        ];
    }

    private function buildReceiptOnlyRow(
        $receipt
    ) {

        $amount =
            (float)
            $receipt->receipt_amount;


        return [

            'id' =>
                'R-' . $receipt->id,

            'status' =>
                'RECEIPT_ONLY',

            'mutation_id' =>
                null,

            'receipt_id' =>
                $receipt->id,

            'mutation_number' =>
                null,

            'receipt_number' =>
                $receipt->receipt_number,

            'mutasi_date' =>
                null,

            'receipt_date' =>
                $receipt->receipt_date,

            'no_rek' =>
                null,

            'jns_bank' =>
                null,

            'remittance_bank_account' =>
                $receipt->remittance_bank_account,

            'mutasi_amount' =>
                0,

            'receipt_amount' =>
                $amount,

            'difference_amount' =>
                -$amount,

            'description' =>
                null,

            'comments' =>
                $receipt->comments,

            'receipt_status' =>
                $receipt->receipt_status,

            'receipt_state' =>
                $receipt->receipt_state,

            'amount_match' =>
                false,

            'account_match' =>
                false
        ];
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

            /*
            |--------------------------------------------------------------------------
            | UNTUK SEMENTARA EXPORT INI KHUSUS FRC
            |--------------------------------------------------------------------------
            */

            if ($type !== 'FRC') {
                return response()->json([
                    'success' => false,
                    'message' => 'Export saat ini hanya tersedia untuk Rekonsiliasi Franchise.'
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | AMBIL PERIODE
            |--------------------------------------------------------------------------
            |
            | Struktur periode yang kita gunakan:
            |
            | id
            | periode
            | start_date
            | end_date
            |
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

            if (empty($period->start_date) || empty($period->end_date)) {

                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal awal atau tanggal akhir periode tidak tersedia.'
                ], 422);

            }


            $startDate = $period->start_date;
            $endDate   = $period->end_date;


            /*
            |--------------------------------------------------------------------------
            | AMBIL DAFTAR JENIS BANK FRC
            |--------------------------------------------------------------------------
            |
            | Contoh:
            |
            | BCA Frc
            | BRI Frc
            | MDR Frc
            | BNI Frc
            |
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
            | HAPUS SHEET DEFAULT
            |--------------------------------------------------------------------------
            */

            $defaultSheet = $spreadsheet->getActiveSheet();

            $defaultSheet->setTitle('REPORT');
            $defaultSheet->setShowGridlines(false);


            /*
            |--------------------------------------------------------------------------
            | BUAT REPORT
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

                $mutasiSheetName =
                    $this->makeSheetName(
                        $jnsBank,
                        $spreadsheet
                    );

                $mutasiSheet->setTitle($mutasiSheetName);


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

                $klopSheetName =
                    $this->makeSheetName(
                        $jnsBank . ' KLOP',
                        $spreadsheet
                    );

                $klopSheet->setTitle($klopSheetName);


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
            | AKTIFKAN REPORT
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


            return response()->json([
                'success' => false,
                'message' => 'File Excel gagal dibuat.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    private function buildFranchiseReportSheet(
        $sheet,
        string $cabang,
        $period,
        $jenisBanks,
        string $startDate,
        string $endDate
    ) {
        /*
        |--------------------------------------------------------------------------
        | TENTUKAN TANGGAL AGING
        |--------------------------------------------------------------------------
        */
    
        $start = new \DateTime($startDate);
        $end   = new \DateTime($endDate);
    
        $year  = (int) $start->format('Y');
        $month = (int) $start->format('m');
    
        /*
        |--------------------------------------------------------------------------
        | TANGGAL 1-8
        |--------------------------------------------------------------------------
        */
    
        $aging1Start = new \DateTime(
            sprintf('%04d-%02d-01', $year, $month)
        );
    
        $aging1End = new \DateTime(
            sprintf('%04d-%02d-08', $year, $month)
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | TANGGAL 9-16
        |--------------------------------------------------------------------------
        */
    
        $aging2Start = new \DateTime(
            sprintf('%04d-%02d-09', $year, $month)
        );
    
        $aging2End = new \DateTime(
            sprintf('%04d-%02d-16', $year, $month)
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | TANGGAL 17-24
        |--------------------------------------------------------------------------
        */
    
        $aging3Start = new \DateTime(
            sprintf('%04d-%02d-17', $year, $month)
        );
    
        $aging3End = new \DateTime(
            sprintf('%04d-%02d-24', $year, $month)
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | TANGGAL 25 - AKHIR BULAN
        |--------------------------------------------------------------------------
        */
    
        $lastDay = (int) $end->format('d');
    
        $aging4Start = new \DateTime(
            sprintf('%04d-%02d-25', $year, $month)
        );
    
        $aging4End = new \DateTime(
            sprintf('%04d-%02d-%02d', $year, $month, $lastDay)
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | BATASI DENGAN PERIODE AKTIF
        |--------------------------------------------------------------------------
        |
        | Ini penting apabila suatu saat periode tidak dimulai tanggal 1
        | atau tidak berakhir pada akhir bulan.
        |
        */
    
        $periodStart = new \DateTime($startDate);
        $periodEnd   = new \DateTime($endDate);
    
    
        /*
        |--------------------------------------------------------------------------
        | HELPER UNTUK MENYESUAIKAN RANGE DENGAN PERIODE
        |--------------------------------------------------------------------------
        */
    
        $normalizeRange = function (
            \DateTime $rangeStart,
            \DateTime $rangeEnd
        ) use ($periodStart, $periodEnd) {
    
            if ($rangeStart < $periodStart) {
                $rangeStart = clone $periodStart;
            }
    
            if ($rangeEnd > $periodEnd) {
                $rangeEnd = clone $periodEnd;
            }
    
            return [
                $rangeStart->format('Y-m-d'),
                $rangeEnd->format('Y-m-d')
            ];
        };
    
    
        /*
        |--------------------------------------------------------------------------
        | RANGE FINAL
        |--------------------------------------------------------------------------
        */
    
        [$aging1StartDate, $aging1EndDate] =
            $normalizeRange(
                $aging1Start,
                $aging1End
            );
    
    
        [$aging2StartDate, $aging2EndDate] =
            $normalizeRange(
                $aging2Start,
                $aging2End
            );
    
    
        [$aging3StartDate, $aging3EndDate] =
            $normalizeRange(
                $aging3Start,
                $aging3End
            );
    
    
        [$aging4StartDate, $aging4EndDate] =
            $normalizeRange(
                $aging4Start,
                $aging4End
            );
    
    
        /*
        |--------------------------------------------------------------------------
        | JUDUL
        |--------------------------------------------------------------------------
        */
    
        $sheet->mergeCells('A1:I1');
    
        $sheet->setCellValue(
            'A1',
            'REPORT REKONSILIASI BANK FRANCHISE'
        );
    
        $sheet->getStyle('A1')->applyFromArray([
    
            'font' => [
                'bold' => true,
                'size' => 15,
            ],
    
            'alignment' => [
                'horizontal' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    
                'vertical' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
    
        ]);
    
        $sheet->getRowDimension(1)->setRowHeight(25);
    
    
        /*
        |--------------------------------------------------------------------------
        | INFORMASI CABANG
        |--------------------------------------------------------------------------
        */
    
        $sheet->setCellValue(
            'A3',
            'CABANG : '
        );
    
        $sheet->setCellValue(
            'B3',
            strtoupper($cabang)
        );
    
        $sheet->setCellValue(
            'H3',
            'PERIODE :'
        );
        
        $maxMutasiDate = DB::table('mutasi_detail_frc as m')
            ->where('m.cabang', $cabang)
            ->whereBetween('m.tgl', [
                $startDate,
                $endDate
            ])
            ->max('m.tgl');
        
        $startDateFormatted = \Carbon\Carbon::parse($startDate)
            ->format('d M y');

        $reportEndDate = $maxMutasiDate
            ? \Carbon\Carbon::parse($maxMutasiDate)->format('d M y')
            : \Carbon\Carbon::parse($endDate)->format('d M y');

        $sheet->setCellValue(
            'I3',
            $startDateFormatted . ' s/d ' . $reportEndDate
        );
    
        $sheet->getStyle('A3:I3')->applyFromArray([
    
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
        | HEADER REPORT
        |--------------------------------------------------------------------------
        */
    
        /*
        | A = No
        | B = Cabang
        | C = Nomor Rekening
        | D:G = Aging
        | H = Mutasi Outstanding
        | I = Keterangan
        */
    
        $sheet->mergeCells('A5:A6');
        $sheet->mergeCells('B5:B6');
        $sheet->mergeCells('C5:C6');
    
        $sheet->mergeCells('D5:G5');
    
        $sheet->mergeCells('H5:H6');
        $sheet->mergeCells('I5:I6');
    
    
        $sheet->setCellValue(
            'A5',
            'No'
        );
    
        $sheet->setCellValue(
            'B5',
            'JENIS BANK'
        );
    
        $sheet->setCellValue(
            'C5',
            'NO REKENING'
        );
    
        $sheet->setCellValue(
            'D5',
            'PENERIMAAN BELUM DIBUKUKAN'
        );
    
        $sheet->setCellValue(
            'D6',
            '01-' . $aging1End->format('d')
        );
    
        $sheet->setCellValue(
            'E6',
            '09-' . $aging2End->format('d')
        );
    
        $sheet->setCellValue(
            'F6',
            '17-' . $aging3End->format('d')
        );
    
        $sheet->setCellValue(
            'G6',
            '25-' . $aging4End->format('d')
        );
    
        $sheet->setCellValue(
            'H5',
            'MUTASI OUTSTANDING'
        );
    
        $sheet->setCellValue(
            'I5',
            'KETERANGAN'
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | STYLE HEADER
        |--------------------------------------------------------------------------
        */
    
        $sheet->getStyle('A5:I6')->applyFromArray([
    
            'font' => [
                'bold' => true,
            ],
    
            'alignment' => [
                'horizontal' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    
                'vertical' =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    
                'wrapText' => true,
            ],
    
            'fill' => [
                'fillType' =>
                    \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
    
                'startColor' => [
                    'rgb' => 'D9EAD3',
                ],
            ],
    
            'borders' => [
                'allBorders' => [
                    'borderStyle' =>
                        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
    
        ]);
    
    
        $sheet->getRowDimension(5)->setRowHeight(35);
        $sheet->getRowDimension(6)->setRowHeight(25);
    
    
        /*
        |--------------------------------------------------------------------------
        | DATA
        |--------------------------------------------------------------------------
        */
    
        $row = 7;
    
        $no = 1;
    
    
        /*
        |--------------------------------------------------------------------------
        | GRAND TOTAL
        |--------------------------------------------------------------------------
        */
    
        $grandAging1 = 0;
        $grandAging2 = 0;
        $grandAging3 = 0;
        $grandAging4 = 0;
        $grandOutstanding = 0;
    
    
        /*
        |--------------------------------------------------------------------------
        | LOOP JENIS BANK
        |--------------------------------------------------------------------------
        */
    
        foreach ($jenisBanks as $jnsBank) {
    
            $baseQuery = function () use (
                $cabang,
                $jnsBank,
                $startDate,
                $endDate
            ) {
    
                return DB::table('mutasi_detail_frc as m')
                    ->whereBetween(
                        'm.tgl',
                        [
                            $startDate,
                            $endDate
                        ]
                    )
                    ->where('m.cr', '!=', 0)
                    ->where(function ($query) {
    
                        $query
                            ->whereNull('m.reff')
                            ->orWhereRaw(
                                "TRIM(m.reff) = ''"
                            );
    
                    })
                    ->whereExists(function ($query) use (
                        $cabang,
                        $jnsBank
                    ) {
    
                        $query->select(
                            DB::raw(1)
                        )
    
                        ->from('bank as b')
    
                        ->whereColumn(
                            'b.no_rek',
                            'm.no_rek'
                        )
    
                        ->where(
                            'b.cabang',
                            $cabang
                        )
    
                        ->where(
                            'b.jns_bank',
                            $jnsBank
                        )
    
                        /*
                        |--------------------------------------------------------------------------
                        | KHUSUS FRANCHISE
                        |--------------------------------------------------------------------------
                        */
    
                        ->where(
                            'b.site',
                            '<>',
                            'REG'
                        );
    
                    });
    
            };
    
    
            /*
            |--------------------------------------------------------------------------
            | AGING 01-08
            |--------------------------------------------------------------------------
            */
    
            $aging1 = (clone $baseQuery())
                ->whereBetween(
                    'm.tgl',
                    [
                        $aging1StartDate,
                        $aging1EndDate
                    ]
                )
                ->count();
    
    
            /*
            |--------------------------------------------------------------------------
            | AGING 09-16
            |--------------------------------------------------------------------------
            */
    
            $aging2 = (clone $baseQuery())
                ->whereBetween(
                    'm.tgl',
                    [
                        $aging2StartDate,
                        $aging2EndDate
                    ]
                )
                ->count();
    
    
            /*
            |--------------------------------------------------------------------------
            | AGING 17-24
            |--------------------------------------------------------------------------
            */
    
            $aging3 = (clone $baseQuery())
                ->whereBetween(
                    'm.tgl',
                    [
                        $aging3StartDate,
                        $aging3EndDate
                    ]
                )
                ->count();
    
    
            /*
            |--------------------------------------------------------------------------
            | AGING 25-AKHIR BULAN
            |--------------------------------------------------------------------------
            */
    
            $aging4 = (clone $baseQuery())
                ->whereBetween(
                    'm.tgl',
                    [
                        $aging4StartDate,
                        $aging4EndDate
                    ]
                )
                ->count();
    
    
            /*
            |--------------------------------------------------------------------------
            | TOTAL OUTSTANDING
            |--------------------------------------------------------------------------
            */
    
            $outstanding =
                $aging1
                + $aging2
                + $aging3
                + $aging4;
    
    
            /*
            |--------------------------------------------------------------------------
            | KETERANGAN
            |--------------------------------------------------------------------------
            */
    
            $keterangan =
                $outstanding == 0
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
    
            /*
            | Sesuai format report yang Anda inginkan:
            | CABANG = JNS_BANK
            */
    
            $sheet->setCellValue(
                "B{$row}",
                strtoupper($jnsBank)
            );
    
    
            /*
            | Semua rekening
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
    
            $grandOutstanding += $outstanding;
    
    
            $row++;
            $no++;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | TOTAL
        |--------------------------------------------------------------------------
        */
    
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
    
        $sheet->setCellValue(
            "I{$row}",
            $grandOutstanding == 0
                ? 'Clear'
                : 'Cabang Belum Pengajuan'
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | STYLE DATA
        |--------------------------------------------------------------------------
        */
    
        if ($row >= 7) {
    
            $sheet->getStyle(
                "A7:I{$row}"
            )->applyFromArray([
    
                'borders' => [
                    'allBorders' => [
                        'borderStyle' =>
                            \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
    
                'alignment' => [
                    'vertical' =>
                        \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
    
            ]);
    
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | STYLE TOTAL
        |--------------------------------------------------------------------------
        */
    
        $sheet->getStyle(
            "A{$row}:I{$row}"
        )->applyFromArray([
    
            'font' => [
                'bold' => true,
            ],
    
            'fill' => [
                'fillType' =>
                    \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
    
                'startColor' => [
                    'rgb' => 'D9EAF7',
                ],
            ],
    
            'borders' => [
                'allBorders' => [
                    'borderStyle' =>
                        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
    
        ]);
    
    
        /*
        |--------------------------------------------------------------------------
        | ALIGNMENT
        |--------------------------------------------------------------------------
        */
    
        $sheet->getStyle(
            "A7:A{$row}"
        )->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        );
    
    
        $sheet->getStyle(
            "C7:H{$row}"
        )->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | WIDTH
        |--------------------------------------------------------------------------
        */
    
        $sheet->getColumnDimension('A')->setWidth(8);
    
        $sheet->getColumnDimension('B')->setWidth(25);
    
        $sheet->getColumnDimension('C')->setWidth(18);
    
        $sheet->getColumnDimension('D')->setWidth(12);
    
        $sheet->getColumnDimension('E')->setWidth(12);
    
        $sheet->getColumnDimension('F')->setWidth(12);
    
        $sheet->getColumnDimension('G')->setWidth(15);
    
        $sheet->getColumnDimension('H')->setWidth(20);
    
        $sheet->getColumnDimension('I')->setWidth(30);
    
    
        /*
        |--------------------------------------------------------------------------
        | FREEZE PANE
        |--------------------------------------------------------------------------
        */
    
        $sheet->freezePane('A7');
    
    
        /*
        |--------------------------------------------------------------------------
        | PRINT SETTING
        |--------------------------------------------------------------------------
        */
    
        $sheet->getPageSetup()->setOrientation(
            \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
        );
    
        $sheet->getPageSetup()->setPaperSize(
            \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4
        );
    
        $sheet->getPageSetup()->setFitToWidth(1);
    
        $sheet->getPageSetup()->setFitToHeight(0);
    
        $sheet->getPageMargins()->setTop(0.25);
        $sheet->getPageMargins()->setBottom(0.25);
        $sheet->getPageMargins()->setLeft(0.25);
        $sheet->getPageMargins()->setRight(0.25);
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
        | JUDUL
        |--------------------------------------------------------------------------
        */
    
        $sheet->mergeCells('A1:N1');
    
        $sheet->setCellValue(
            'A1',
            "REKONSILIASI FRANCHISE - {$jnsBank}"
        );
    
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
    
            'alignment' => [
                'horizontal' =>
                    Alignment::HORIZONTAL_CENTER,
    
                'vertical' =>
                    Alignment::VERTICAL_CENTER,
            ],
        ]);
    
    
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
            ': '.$cabang
        );
    
    
        $sheet->setCellValue(
            'A4',
            'Jenis Bank'
        );
    
        $sheet->setCellValue(
            'B4',
            ': '.$jnsBank
        );
    
    
        $sheet->setCellValue(
            'A5',
            'Periode'
        );
    
        $sheet->setCellValue(
            'B5',
            ': '.$startDate . ' s/d ' . $endDate
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */
    
        $headerRow = 7;
    
    
        $headers = [
            'No Rekening',
            'Tanggal',
            'Remark',
            'Remark',
            'Kredit',
        ];
    
    
        foreach ($headers as $index => $header) {
    
            $column =
                $this->excelColumn(
                    $index + 1
                );
    
            $sheet->setCellValue(
                "{$column}{$headerRow}",
                $header
            );
        }
    
    
        $this->styleHeader(
            $sheet,
            "A{$headerRow}:N{$headerRow}"
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | QUERY MUTASI
        |--------------------------------------------------------------------------
        */
    
        $query = DB::table(
            'mutasi_detail_frc as m'
        )
    
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
                }
            )
            ->where('m.cr', '!=', 0)
            ->whereBetween(
                'm.tgl',
                [
                    $startDate,
                    $endDate
                ]
            )
    
            /*
            |--------------------------------------------------------------------------
            | HANYA YANG BELUM KLOP
            |--------------------------------------------------------------------------
            */
    
            ->where(function ($query) {
    
                $query
                    ->whereNull('m.reff')
                    ->orWhereRaw(
                        "TRIM(m.reff) = ''"
                    );
            })
    
            ->orderBy(
                'm.tgl'
            )
    
            ->orderBy(
                'm.id'
            )
    
            ->select([
                'm.no_rek',
                'm.tgl',
                'm.remark',
                'm.remark1',
                'm.cr',
            ]);
    
    
        /*
        |--------------------------------------------------------------------------
        | TULIS DATA
        |--------------------------------------------------------------------------
        */
    
        $row = $headerRow + 1;
    
    
        $query->chunk(
            1000,
            function ($items) use (
                &$sheet,
                &$row
            ) {
    
                foreach ($items as $item) {
    
                    $values = [
                        $item->no_rek,
                        $item->tgl,
                        $item->remark,
                        $item->remark1,
                        $item->cr,
                    ];
    
    
                    foreach (
                        $values
                        as $index => $value
                    ) {
    
                        $column =
                            $this->excelColumn(
                                $index + 1
                            );
    
    
                        $sheet->setCellValue(
                            "{$column}{$row}",
                            $value
                        );
                    }
    
    
                    $row++;
                }
            }
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT ANGKA
        |--------------------------------------------------------------------------
        */
    
        if ($row > $headerRow + 1) {
    
            $sheet->getStyle(
                "G" . ($headerRow + 1) . ":I" . ($row - 1)
            )->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | WIDTH
        |--------------------------------------------------------------------------
        */
    
        $widths = [
            'A' => 10,
            'B' => 18,
            'C' => 14,
            'D' => 18,
            'E' => 45,
            'F' => 35,
            'G' => 18,
            'H' => 18,
            'I' => 18,
            'J' => 35,
            'K' => 14,
            'L' => 15,
            'M' => 15,
            'N' => 25,
        ];
    
    
        foreach ($widths as $column => $width) {
    
            $sheet
                ->getColumnDimension($column)
                ->setWidth($width);
        }
    
    
        $sheet->freezePane(
            'A8'
        );
    
        $sheet->setAutoFilter(
            "A7:N7"
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
        | JUDUL
        |--------------------------------------------------------------------------
        */
    
        $sheet->mergeCells('A1:W1');
    
        $sheet->setCellValue(
            'A1',
            "REKONSILIASI KLOP - {$jnsBank}"
        );
    
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
    
            'alignment' => [
                'horizontal' =>
                    Alignment::HORIZONTAL_CENTER,
    
                'vertical' =>
                    Alignment::VERTICAL_CENTER,
            ],
        ]);
    
    
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
            $cabang
        );
    
    
        $sheet->setCellValue(
            'A4',
            'Jenis Bank'
        );
    
        $sheet->setCellValue(
            'B4',
            $jnsBank
        );
    
    
        $sheet->setCellValue(
            'A5',
            'Periode'
        );
    
        $sheet->setCellValue(
            'B5',
            $startDate . ' s/d ' . $endDate
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */
    
        $headerRow = 7;
    
    
        $headers = [
    
            // MUTASI
            'Mutasi ID',
            'No Rekening',
            'Tanggal Mutasi',
            'Kode Transaksi',
            'Remark',
            'Remark 1',
            'Debit',
            'Kredit',
            'Saldo',
            'Source',
            'Reconciled',
            'Dept',
            'Inv',
            'Reff',
    
            // RECEIPT
            'Receipt ID',
            'Receipt Number',
            'Receipt Date',
            'Receipt Amount',
            'Receipt Bank Account',
            'Receipt Type',
            'Receipt Status',
            'Paid By',
            'Activity',
            'Comments',
            'Receipt Reff',
        ];
    
    
        foreach ($headers as $index => $header) {
    
            $column =
                $this->excelColumn(
                    $index + 1
                );
    
            $sheet->setCellValue(
                "{$column}{$headerRow}",
                $header
            );
        }
    
    
        $lastColumn =
            $this->excelColumn(
                count($headers)
            );
    
    
        $this->styleHeader(
            $sheet,
            "A{$headerRow}:{$lastColumn}{$headerRow}"
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | QUERY PASANGAN MUTASI + RECEIPT
        |--------------------------------------------------------------------------
        */
    
        $query = DB::table(
            'mutasi_detail_frc as m'
        )
    
            /*
            |--------------------------------------------------------------------------
            | VALIDASI REKENING + CABANG + JENIS BANK
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
                }
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | PASANGAN RECEIPT
            |--------------------------------------------------------------------------
            */
    
            ->join(
                'receipt as r',
                function ($join) {
    
                    $join->on(
                        'r.reff',
                        '=',
                        'm.reff'
                    );
    
                    $join->on(
                        'r.remittance_bank_account',
                        '=',
                        'm.no_rek'
                    );
                }
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | PERIODE MUTASI
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
            | PERIODE RECEIPT
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
            | REFF HARUS TERISI
            |--------------------------------------------------------------------------
            */
    
            ->whereNotNull(
                'm.reff'
            )
    
            ->whereRaw(
                "TRIM(m.reff) <> ''"
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | URUTKAN
            |--------------------------------------------------------------------------
            */
    
            ->orderBy(
                'm.tgl'
            )
    
            ->orderBy(
                'm.id'
            )
    
    
            /*
            |--------------------------------------------------------------------------
            | FIELD
            |--------------------------------------------------------------------------
            */
    
            ->select([
    
                /*
                |------------------------------------------------------------------
                | MUTASI
                |------------------------------------------------------------------
                */
    
                'm.id as mutasi_id',
    
                'm.no_rek',
    
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
    
                'm.reff',
    
    
                /*
                |------------------------------------------------------------------
                | RECEIPT
                |------------------------------------------------------------------
                */
    
                'r.id as receipt_id',
    
                'r.receipt_number',
    
                'r.receipt_date',
    
                'r.receipt_amount',
    
                'r.remittance_bank_account',
    
                'r.receipt_type',
    
                'r.receipt_status',
    
                'r.paid_by',
    
                'r.activity',
    
                'r.comments',
    
                'r.reff as receipt_reff',
    
            ]);
    
    
        /*
        |--------------------------------------------------------------------------
        | TULIS DATA
        |--------------------------------------------------------------------------
        */
    
        $row = $headerRow + 1;
    
    
        $query->chunk(
            1000,
            function ($items) use (
                &$sheet,
                &$row
            ) {
    
                foreach ($items as $item) {
    
                    $values = [
    
                        // MUTASI
                        $item->mutasi_id,
                        $item->no_rek,
                        $item->tgl,
                        $item->trx_code,
                        $item->remark,
                        $item->remark1,
                        $item->db,
                        $item->cr,
                        $item->saldo,
                        $item->src,
                        $item->reconciled,
                        $item->dept,
                        $item->inv,
                        $item->reff,
    
                        // RECEIPT
                        $item->receipt_id,
                        $item->receipt_number,
                        $item->receipt_date,
                        $item->receipt_amount,
                        $item->remittance_bank_account,
                        $item->receipt_type,
                        $item->receipt_status,
                        $item->paid_by,
                        $item->activity,
                        $item->comments,
                        $item->receipt_reff,
                    ];
    
    
                    foreach (
                        $values
                        as $index => $value
                    ) {
    
                        $column =
                            $this->excelColumn(
                                $index + 1
                            );
    
    
                        $sheet->setCellValue(
                            "{$column}{$row}",
                            $value
                        );
                    }
    
    
                    $row++;
                }
            }
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMAT NOMINAL
        |--------------------------------------------------------------------------
        */
    
        if ($row > $headerRow + 1) {
    
            $sheet
                ->getStyle(
                    "G" .
                    ($headerRow + 1) .
                    ":I" .
                    ($row - 1)
                )
                ->getNumberFormat()
                ->setFormatCode(
                    '#,##0.00'
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | RECEIPT AMOUNT
            |--------------------------------------------------------------------------
            */
    
            $sheet
                ->getStyle(
                    "R" .
                    ($headerRow + 1) .
                    ":R" .
                    ($row - 1)
                )
                ->getNumberFormat()
                ->setFormatCode(
                    '#,##0.00'
                );
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | WIDTH
        |--------------------------------------------------------------------------
        */
    
        $widths = [
    
            'A' => 12,
            'B' => 18,
            'C' => 14,
            'D' => 18,
            'E' => 40,
            'F' => 35,
            'G' => 18,
            'H' => 18,
            'I' => 18,
            'J' => 30,
            'K' => 14,
            'L' => 15,
            'M' => 15,
            'N' => 25,
    
            'O' => 12,
            'P' => 22,
            'Q' => 15,
            'R' => 20,
            'S' => 22,
            'T' => 18,
            'U' => 18,
            'V' => 25,
            'W' => 35,
            'X' => 25,
        ];
    
    
        foreach ($widths as $column => $width) {
    
            $sheet
                ->getColumnDimension($column)
                ->setWidth($width);
        }
    
    
        $sheet->freezePane('A8');
    
    
        $sheet->setAutoFilter(
            "A7:X7"
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
                ->select('no_rek')
                ->distinct()
                ->orderBy('no_rek')
                ->pluck('no_rek');

        }


        return response()->json([

            'success' => true,

            'type' => $type,

            'cabang' => $cabang,

            'count' => $accounts->count(),

            'data' => $accounts->values(),

        ]);
    }

    public function reconcileReceipt(Request $request)
    {
        $request->validate([
            'cabang'    => 'required|string|max:50',
            'period_id' => 'required|integer',
            'type'      => 'required|in:FRC,REG',
            'account'   => 'nullable|string|max:255',
        ]);

        $cabang = trim($request->input('cabang'));
        $periodId = (int) $request->input('period_id');
        $type = strtoupper(trim($request->input('type')));
        $account = trim((string) $request->input('account', ''));

        /*
        |--------------------------------------------------------------------------
        | FRC
        |--------------------------------------------------------------------------
        */

        if ($type === 'FRC') {

            try {

                return $this->reconcileReceiptFranchise(
                    $cabang,
                    $periodId,
                    $account
                );

            } catch (\Throwable $e) {

                Log::error(
                    'REKONSILIASI FRC ERROR',
                    [
                        'cabang'    => $cabang,
                        'period_id' => $periodId,
                        'type'      => $type,
                        'account'   => $account,
                        'message'   => $e->getMessage(),
                        'file'      => $e->getFile(),
                        'line'      => $e->getLine(),
                        'trace'     => $e->getTraceAsString(),
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data' => [],
                    'summary' => $this->emptyReconciliationSummary(),
                ], 500);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | REG
        |--------------------------------------------------------------------------
        |
        | Untuk sementara tetap dikembalikan sebagai belum tersedia.
        | Jangan menggunakan tabel mutasi REG yang belum kita pastikan
        | strukturnya.
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => false,
            'message' =>
                'Proses rekonsiliasi REG belum diimplementasikan pada endpoint ini.',
            'data' => [],
            'summary' => $this->emptyReconciliationSummary(),
        ], 422);
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
                'trx_id',
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
    
                        'trx_id' =>
                            $receipt->trx_id
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
    
                    'trx_id' =>
                        $receipt->trx_id
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
    
                    'trx_id' =>
                        $receipt->trx_id
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
    
                    'trx_id' =>
                        $selectedReceipts
                            ->pluck(
                                'trx_id'
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
    
                    'trx_id' =>
                        $selectedReceipts
                            ->pluck(
                                'trx_id'
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
    
                    'trx_id' =>
                        $receipt->trx_id
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
                    'trx_id',
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

                                    'trx_id' =>
                                        $row->trx_id,

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
}