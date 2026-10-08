<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class BankController extends Controller
{
    /**
     * GET /api/bank?cabang=Yogyakarta
     */
    public function index(Request $request)
    {
        try {

            $cabang = trim(
                (string) $request->query('cabang', '')
            );

            if ($cabang === '') {

                return response()->json([
                    'success' => false,
                    'message' => 'Parameter cabang wajib diisi.',
                    'data' => [],
                ], 422);
            }


            $data = DB::table('bank')
                ->where('cabang', $cabang)
                ->orderBy('bank')
                ->orderBy('jns_bank')
                ->orderBy('no_rek')
                ->get([
                    'id',
                    'cabang',
                    'bank',
                    'jns_bank',
                    'no_rek',
                    'akun',
                    'ce',
                    'site',
                    'bank_name',
                    'acc_num',
                    'receipt_method',
                ]);


            return response()->json([
                'success' => true,
                'message' => 'Data rekening berhasil diambil.',
                'data' => $data,
            ]);

        } catch (Throwable $e) {

            \Log::error(
                'BANK INDEX ERROR',
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'cabang' => $request->query('cabang'),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data rekening bank.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * POST /api/bank
     */
    public function store(Request $request)
    {
        try {

            $validated = $request->validate([

                'cabang' => 'required|string|max:15',

                'bank' => 'required|string|max:100',

                'jns_bank' => 'required|string|max:100',

                'no_rek' => 'required|string|max:100',

                'akun' => 'required|string|max:6',

                'ce' => 'required|string|max:1',

                'site' => 'required|string|max:4',

                'bank_name' => 'required|string',

                'acc_num' => 'required|string',

                'receipt_method' => 'required|string',

            ]);


            $validated['jns_bank'] =
                strtoupper(trim($validated['jns_bank']));

            $validated['site'] =
                strtoupper(trim($validated['site']));


            $id = DB::table('bank')->insertGetId([
                'cabang' => trim($validated['cabang']),
                'bank' => trim($validated['bank']),
                'jns_bank' => $validated['jns_bank'],
                'no_rek' => trim($validated['no_rek']),
                'akun' => trim($validated['akun']),
                'ce' => trim($validated['ce']),
                'site' => $validated['site'],
                'bank_name' => trim($validated['bank_name']),
                'acc_num' => trim($validated['acc_num']),
                'receipt_method' => trim($validated['receipt_method']),
            ]);


            $data = DB::table('bank')
                ->where('id', $id)
                ->first();


            return response()->json([
                'success' => true,
                'message' => 'Rekening berhasil ditambahkan.',
                'data' => $data,
            ], 201);

        } catch (Throwable $e) {

            \Log::error(
                'BANK STORE ERROR',
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Rekening gagal ditambahkan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * GET /api/bank/{id}
     */
    public function show($id)
    {
        try {

            $data = DB::table('bank')
                ->where('id', $id)
                ->first();


            if (!$data) {

                return response()->json([
                    'success' => false,
                    'message' => 'Data rekening tidak ditemukan.',
                ], 404);
            }


            return response()->json([
                'success' => true,
                'message' => 'Data rekening ditemukan.',
                'data' => $data,
            ]);

        } catch (Throwable $e) {

            \Log::error(
                'BANK SHOW ERROR',
                [
                    'id' => $id,
                    'message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data rekening.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * PUT /api/bank/{id}
     */
    public function update(
        Request $request,
        $id
    ) {
        try {

            $validated = $request->validate([

                'cabang' => 'required|string|max:15',

                'bank' => 'required|string|max:100',

                'jns_bank' => 'required|string|max:100',

                'no_rek' => 'required|string|max:100',

                'akun' => 'required|string|max:6',

                'ce' => 'required|string|max:1',

                'site' => 'required|string|max:4',

                'bank_name' => 'required|string',

                'acc_num' => 'required|string',

                'receipt_method' => 'required|string',

            ]);


            $exists = DB::table('bank')
                ->where('id', $id)
                ->exists();


            if (!$exists) {

                return response()->json([
                    'success' => false,
                    'message' => 'Data rekening tidak ditemukan.',
                ], 404);
            }


            $validated['jns_bank'] =
                strtoupper(trim($validated['jns_bank']));

            $validated['site'] =
                strtoupper(trim($validated['site']));


            DB::table('bank')
                ->where('id', $id)
                ->update([

                    'cabang' =>
                        trim($validated['cabang']),

                    'bank' =>
                        trim($validated['bank']),

                    'jns_bank' =>
                        $validated['jns_bank'],

                    'no_rek' =>
                        trim($validated['no_rek']),

                    'akun' =>
                        trim($validated['akun']),

                    'ce' =>
                        trim($validated['ce']),

                    'site' =>
                        $validated['site'],

                    'bank_name' =>
                        trim($validated['bank_name']),

                    'acc_num' =>
                        trim($validated['acc_num']),

                    'receipt_method' =>
                        trim($validated['receipt_method']),
                ]);


            $data = DB::table('bank')
                ->where('id', $id)
                ->first();


            return response()->json([
                'success' => true,
                'message' => 'Rekening berhasil diubah.',
                'data' => $data,
            ]);

        } catch (Throwable $e) {

            \Log::error(
                'BANK UPDATE ERROR',
                [
                    'id' => $id,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Rekening gagal diubah.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * DELETE /api/bank/{id}
     */
    public function destroy($id)
    {
        try {

            $exists = DB::table('bank')
                ->where('id', $id)
                ->exists();


            if (!$exists) {

                return response()->json([
                    'success' => false,
                    'message' => 'Data rekening tidak ditemukan.',
                ], 404);
            }


            DB::table('bank')
                ->where('id', $id)
                ->delete();


            return response()->json([
                'success' => true,
                'message' => 'Rekening berhasil dihapus.',
            ]);

        } catch (Throwable $e) {

            \Log::error(
                'BANK DELETE ERROR',
                [
                    'id' => $id,
                    'message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Rekening gagal dihapus.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}