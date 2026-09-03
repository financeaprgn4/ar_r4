<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ReconciliationService;

class ReconciliationController extends Controller
{
    protected ReconciliationService $service;

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
}