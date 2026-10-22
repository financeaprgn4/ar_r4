<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Throwable;

class BankController extends Controller
{
    /**
     * Mengambil daftar rekening bank berdasarkan cabang.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cabang' => ['required', 'string', 'max:15'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $cabang = trim($request->input('cabang'));

            $data = DB::table('bank')
                ->where('cabang', $cabang)
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data rekening bank berhasil diambil.',
                'data'    => $data,
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data rekening bank.',
            ], 500);
        }
    }

    /**
     * Menambahkan rekening bank baru.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cabang'        => ['required', 'string', 'max:15'],
            'bank'          => ['required', 'string', 'max:100'],
            'jns_bank'       => ['required', 'string', 'max:15'],
            'no_rek'        => ['required', 'string', 'max:100'],
            'akun'          => ['required', 'string', 'max:6'],

            'ce'            => ['nullable', 'string', 'max:1'],
            'site'          => ['nullable', 'string', 'max:4'],
            'bank_name'     => ['nullable', 'string'],
            'acc_num'       => ['nullable', 'string'],
            'receipt_method'=> ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $payload = $this->prepareBankData(
                $validator->validated()
            );

            $id = DB::table('bank')->insertGetId($payload);

            $bank = DB::table('bank')
                ->where('id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Data rekening berhasil ditambahkan.',
                'data'    => $bank,
            ], 201);

        } catch (QueryException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan rekening ke database.',
            ], 500);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan rekening.',
            ], 500);
        }
    }

    /**
     * Memperbarui rekening bank berdasarkan ID.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'cabang'         => ['required', 'string', 'max:15'],
            'bank'           => ['required', 'string', 'max:100'],
            'jns_bank'       => ['required', 'string', 'max:15'],
            'no_rek'         => ['required', 'string', 'max:100'],
            'akun'           => ['required', 'string', 'max:6'],

            'ce'             => ['nullable', 'string', 'max:1'],
            'site'           => ['nullable', 'string', 'max:4'],
            'bank_name'      => ['nullable', 'string'],
            'acc_num'        => ['nullable', 'string'],
            'receipt_method' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $existing = DB::table('bank')
                ->where('id', $id)
                ->first();

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data rekening bank tidak ditemukan.',
                ], 404);
            }

            $payload = $this->prepareBankData(
                $validator->validated()
            );

            DB::table('bank')
                ->where('id', $id)
                ->update($payload);

            $bank = DB::table('bank')
                ->where('id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Data rekening berhasil diubah.',
                'data'    => $bank,
            ], 200);

        } catch (QueryException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui rekening di database.',
            ], 500);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui rekening.',
            ], 500);
        }
    }

    /**
     * Menghapus rekening bank berdasarkan ID.
     */
    public function destroy($id)
    {
        try {
            $deleted = DB::table('bank')
                ->where('id', $id)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data rekening bank tidak ditemukan.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data rekening berhasil dihapus.',
            ], 200);

        } catch (QueryException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus rekening dari database.',
            ], 500);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus rekening.',
            ], 500);
        }
    }

    /**
     * Menyiapkan data sesuai kolom tabel bank.
     *
     * Kolom opsional pada tabel menggunakan string kosong
     * jika nilainya tidak diberikan atau bernilai null.
     */
    private function prepareBankData(array $data): array
    {
        return [
            'cabang'         => trim($data['cabang']),
            'bank'           => trim($data['bank']),
            'jns_bank'       => trim($data['jns_bank']),
            'no_rek'         => trim($data['no_rek']),
            'akun'           => trim($data['akun']),
            'ce'             => trim($data['ce'] ?? ''),
            'site'           => trim($data['site'] ?? ''),
            'bank_name'      => trim($data['bank_name'] ?? ''),
            'acc_num'        => trim($data['acc_num'] ?? ''),
            'receipt_method' => trim($data['receipt_method'] ?? ''),
        ];
    }
}