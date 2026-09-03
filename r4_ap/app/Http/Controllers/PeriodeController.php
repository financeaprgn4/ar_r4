<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Periode;

class PeriodeController extends Controller
{
    public function periodeList(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json([
                'message' => 'Cabang tidak ditemukan dalam request.'
            ], 400);
        }

        $data = Periode::where('Cabang', $cabang)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($data);
    }

    public function update(Request $request, $id)
    {
        try {

            $result = DB::transaction(function () use ($request, $id) {

                /*
                |--------------------------------------------------------------------------
                | 1. Ambil periode
                |--------------------------------------------------------------------------
                */
                $periode = DB::table('periode')
                    ->where('id', $id)
                    ->first();

                if (!$periode) {
                    throw new \Exception('Data periode tidak ditemukan.');
                }


                /*
                |--------------------------------------------------------------------------
                | 2. Ambil parameter
                |--------------------------------------------------------------------------
                */
                $cabang   = $request->input('cabang');
                $kategori = $request->input('kategori', $periode->kategori);
                $status   = $request->input('status');

                if (!in_array($status, ['Aktif', 'Close'])) {
                    throw new \Exception('Status periode tidak valid.');
                }


                /*
                |--------------------------------------------------------------------------
                | 3. HD / LPD
                |--------------------------------------------------------------------------
                |
                | Sama seperti sistem lama:
                | hanya update status.
                |
                |--------------------------------------------------------------------------
                */
                if (in_array($kategori, ['HD', 'LPD'])) {

                    DB::table('periode')
                        ->where('id', $id)
                        ->update([
                            'status' => $status
                        ]);

                    return [
                        'message' => "Status periode {$kategori} berhasil diubah menjadi {$status}.",
                        'data' => DB::table('periode')
                            ->where('id', $id)
                            ->first()
                    ];
                }


                /*
                |--------------------------------------------------------------------------
                | 4. MUTASI
                |--------------------------------------------------------------------------
                */
                $awal  = $periode->start_date;
                $akhir = $periode->end_date;

                /*
                * Saldo awal = satu hari sebelum tanggal awal periode
                */
                $tglSaldo = date(
                    'Y-m-d',
                    strtotime('-1 day', strtotime($awal))
                );


                /*
                |--------------------------------------------------------------------------
                | 5. Ambil seluruh rekening cabang
                |--------------------------------------------------------------------------
                */
                $banks = DB::table('bank')
                    ->select('no_rek', 'site')
                    ->where('cabang', $cabang)
                    ->where('bank', '!=', 'Titipan')
                    ->get();

                if ($banks->isEmpty()) {

                    DB::table('periode')
                        ->where('id', $id)
                        ->update([
                            'status' => $status
                        ]);

                    return [
                        'message' => "Periode Mutasi berhasil diubah menjadi {$status}.",
                        'data' => DB::table('periode')
                            ->where('id', $id)
                            ->first()
                    ];
                }


                /*
                |--------------------------------------------------------------------------
                | 6. Pisahkan rekening REG dan FRC
                |--------------------------------------------------------------------------
                */
                $regAccounts = $banks
                    ->where('site', 'REG')
                    ->pluck('no_rek')
                    ->values();

                $frcAccounts = $banks
                    ->where('site', '!=', 'REG')
                    ->pluck('no_rek')
                    ->values();


                /*
                |--------------------------------------------------------------------------
                | 7. Ambil total debit & kredit SEKALI
                |--------------------------------------------------------------------------
                |
                | Sebelumnya:
                |
                | 1 rekening = SUM debit + SUM kredit
                |
                | Sekarang:
                |
                | 1 query = seluruh rekening
                |
                |--------------------------------------------------------------------------
                */
                $mutasiMap = collect();


                /*
                |--------------------------------------------------------------------------
                | REG
                |--------------------------------------------------------------------------
                */
                if ($regAccounts->isNotEmpty()) {

                    $regData = DB::table('mutasi_rekap')
                        ->select(
                            'no_rek',
                            DB::raw('COALESCE(SUM(db), 0) AS tot_db'),
                            DB::raw('COALESCE(SUM(cr), 0) AS tot_cr')
                        )
                        ->whereIn('no_rek', $regAccounts)
                        ->whereBetween('tgl', [$awal, $akhir])
                        ->groupBy('no_rek')
                        ->get();

                    foreach ($regData as $row) {

                        $mutasiMap->put($row->no_rek, [
                            'db' => (float) $row->tot_db,
                            'cr' => (float) $row->tot_cr,
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | FRC
                |--------------------------------------------------------------------------
                */
                if ($frcAccounts->isNotEmpty()) {

                    $frcData = DB::table('mutasi_rekap_frc')
                        ->select(
                            'no_rek',
                            DB::raw('COALESCE(SUM(db), 0) AS tot_db'),
                            DB::raw('COALESCE(SUM(cr), 0) AS tot_cr')
                        )
                        ->whereIn('no_rek', $frcAccounts)
                        ->whereBetween('tgl', [$awal, $akhir])
                        ->groupBy('no_rek')
                        ->get();

                    foreach ($frcData as $row) {

                        $mutasiMap->put($row->no_rek, [
                            'db' => (float) $row->tot_db,
                            'cr' => (float) $row->tot_cr,
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 8. Jika CLOSE
                |--------------------------------------------------------------------------
                */
                if ($status === 'Close') {

                    /*
                    |--------------------------------------------------------------------------
                    | Ambil seluruh saldo awal SEKALI
                    |--------------------------------------------------------------------------
                    */
                    $saldoAwalMap = DB::table('saldo_akhir_bank')
                        ->where('per', $tglSaldo)
                        ->whereIn('no_rek', $banks->pluck('no_rek'))
                        ->pluck('saldo', 'no_rek');


                    /*
                    |--------------------------------------------------------------------------
                    | Hitung saldo akhir
                    |--------------------------------------------------------------------------
                    */
                    $saldoRows = [];

                    foreach ($banks as $bank) {

                        $noRek = $bank->no_rek;

                        $saldoAwal = (float) (
                            $saldoAwalMap[$noRek] ?? 0
                        );

                        $mutasi = $mutasiMap[$noRek] ?? [
                            'db' => 0,
                            'cr' => 0
                        ];

                        $totDb = (float) $mutasi['db'];
                        $totCr = (float) $mutasi['cr'];

                        /*
                        * Rumus sama dengan sistem lama:
                        *
                        * saldo akhir =
                        * saldo awal + kredit - debit
                        */
                        $saldoAkhir =
                            $saldoAwal
                            + $totCr
                            - $totDb;


                        $saldoRows[] = [
                            'no_rek' => $noRek,
                            'saldo'  => $saldoAkhir,
                            'per'    => $akhir,
                        ];
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | INSERT / UPDATE SEKALIGUS
                    |--------------------------------------------------------------------------
                    |
                    | Membutuhkan unique index:
                    |
                    | UNIQUE(no_rek, per)
                    |
                    |--------------------------------------------------------------------------
                    */
                    if (!empty($saldoRows)) {

                        DB::table('saldo_akhir_bank')
                            ->upsert(
                                $saldoRows,
                                ['no_rek', 'per'],
                                ['saldo']
                            );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 9. Jika AKTIF
                |--------------------------------------------------------------------------
                |
                | Hapus saldo akhir periode secara BULK.
                |
                | Tidak perlu loop rekening.
                |--------------------------------------------------------------------------
                */
                else {

                    DB::table('saldo_akhir_bank')
                        ->where('per', $akhir)
                        ->whereIn(
                            'no_rek',
                            $banks->pluck('no_rek')
                        )
                        ->delete();
                }


                /*
                |--------------------------------------------------------------------------
                | 10. Update status periode
                |--------------------------------------------------------------------------
                */
                DB::table('periode')
                    ->where('id', $id)
                    ->update([
                        'status' => $status
                    ]);


                /*
                |--------------------------------------------------------------------------
                | 11. Ambil data terbaru
                |--------------------------------------------------------------------------
                */
                return [
                    'message' =>
                        "Status periode Mutasi berhasil diubah menjadi {$status}.",

                    'data' => DB::table('periode')
                        ->where('id', $id)
                        ->first()
                ];
            });


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ], 200);


        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | LOG
            |--------------------------------------------------------------------------
            */
            Log::error('PERIODE UPDATE ERROR', [
                'id'       => $id,
                'cabang'   => $request->input('cabang'),
                'kategori' => $request->input('kategori'),
                'status'   => $request->input('status'),
                'message'  => $e->getMessage(),
                'line'     => $e->getLine(),
                'file'     => $e->getFile(),
            ]);


            /*
            |--------------------------------------------------------------------------
            | ERROR RESPONSE
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | Ambil input
            |--------------------------------------------------------------------------
            */
            $cabang   = $request->input('cabang');
            $kategori = $request->input('kategori');
            $periode  = $request->input('periode');

            $tglAwal  = $request->input('start_date');
            $tglAkhir = $request->input('end_date');


            /*
            |--------------------------------------------------------------------------
            | Validasi
            |--------------------------------------------------------------------------
            */
            if (!$cabang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang wajib diisi.'
                ], 422);
            }

            if (!$kategori) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori wajib diisi.'
                ], 422);
            }

            if (!in_array($kategori, ['Mutasi', 'HD', 'LPD'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori periode tidak valid.'
                ], 422);
            }

            if (!$periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode wajib diisi.'
                ], 422);
            }

            if (!$tglAwal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal awal wajib diisi.'
                ], 422);
            }

            if (!$tglAkhir) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal akhir wajib diisi.'
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | Validasi tanggal
            |--------------------------------------------------------------------------
            */
            if ($tglAwal > $tglAkhir) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.'
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | Cek apakah periode sudah ada
            |--------------------------------------------------------------------------
            |
            | Sama dengan project lama:
            |
            | SELECT *
            | FROM periode
            | WHERE Cabang = '$cabang'
            | AND kategori = '$kategori'
            | AND periode = '$per'
            |
            |--------------------------------------------------------------------------
            */
            $existing = DB::table('periode')
                ->where('Cabang', $cabang)
                ->where('kategori', $kategori)
                ->where('periode', $periode)
                ->first();


            /*
            |--------------------------------------------------------------------------
            | Jika sudah ada
            |--------------------------------------------------------------------------
            */
            if ($existing) {

                return response()->json([
                    'success' => false,
                    'duplicate' => true,
                    'message' =>
                        "Periode {$periode} untuk kategori {$kategori} di cabang {$cabang} sudah tersedia.",
                    'data' => $existing
                ], 409);
            }


            /*
            |--------------------------------------------------------------------------
            | INSERT
            |--------------------------------------------------------------------------
            |
            | Project lama:
            |
            | INSERT INTO periode VALUES(
            |     null,
            |     '$cabang',
            |     '$kategori',
            |     '$per',
            |     '$tgl_awal',
            |     '$tgl_akhir',
            |     'Aktif'
            | )
            |
            |--------------------------------------------------------------------------
            */
            $id = DB::table('periode')->insertGetId([
                'Cabang'     => $cabang,
                'kategori'   => $kategori,
                'periode'    => $periode,
                'start_date' => $tglAwal,
                'end_date'   => $tglAkhir,
                'status'     => 'Aktif',
            ]);


            /*
            |--------------------------------------------------------------------------
            | Ambil data yang baru dibuat
            |--------------------------------------------------------------------------
            */
            $data = DB::table('periode')
                ->where('id', $id)
                ->first();


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => 'Periode berhasil ditambahkan.',
                'data' => $data
            ], 201);


        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | LOG ERROR
            |--------------------------------------------------------------------------
            */
            Log::error('PERIODE STORE ERROR', [
                'cabang'   => $request->input('cabang'),
                'kategori' => $request->input('kategori'),
                'periode'  => $request->input('periode'),
                'message'  => $e->getMessage(),
                'line'     => $e->getLine(),
                'file'     => $e->getFile(),
            ]);


            /*
            |--------------------------------------------------------------------------
            | ERROR RESPONSE
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan periode.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
