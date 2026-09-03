<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class LpdModalAddController extends Controller
{
    public function modalAdd(Request $request)
    {
        $data = $request->all();

        if (!is_array($data) || empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid atau kosong.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            foreach ($data as $item) {
                // Validasi data
                if (
                    empty($item['rab']) || empty($item['bbt']) ||
                    empty($item['tgl_bbt']) || !isset($item['nilai']) || empty($item['keterangan'])
                ) {
                    throw new Exception("Data tidak lengkap pada salah satu baris.");
                }

                // Insert ke modal_detail
                DB::table('modal_detail')->insert([
                    'rab' => $item['rab'],
                    'bbt' => $item['bbt'],
                    'tgl_bbt' => $item['tgl_bbt'],
                    'nilai' => $item['nilai'],
                    'keterangan' => $item['keterangan'],
                ]);
            }

            // Ambil nomor RAB dari data pertama
            $rab = $data[0]['rab'];

            // Hitung total nilai berdasarkan keterangan
            $totalSetor = DB::table('modal_detail')
                ->where('rab', $rab)
                ->where('keterangan', 'Setor')
                ->sum('nilai');

            $totalCadangan = DB::table('modal_detail')
                ->where('rab', $rab)
                ->where('keterangan', 'Cadangan')
                ->sum('nilai');

            // Cek apakah RAB sudah ada di tabel modal
            $modalExists = DB::table('modal')->where('rab', $rab)->exists();

            if ($modalExists) {
                // Update
                DB::table('modal')
                    ->where('rab', $rab)
                    ->update([
                        'setor' => $totalSetor,
                        'cad_dana' => $totalCadangan,
                    ]);
            } else {
                // Insert baru
                DB::table('modal')->insert([
                    'rab' => $rab,
                    'setor' => $totalSetor,
                    'cad_dana' => $totalCadangan,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disimpan dan modal diperbarui.'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteBBT(Request $request)
    {
        $bbt = $request->input('bbt');
        $rab = $request->input('rab');

        if (empty($bbt)) {
            return response()->json(['success' => false, 'message' => 'Parameter BBT tidak ditemukan.'], 400);
        }

        try {
            DB::beginTransaction();   // ← tambahkan transaksi

            $deleted = DB::table('modal_detail')->where('bbt', $bbt)->delete();

            if ($deleted) {
                $totalSetor = DB::table('modal_detail')->where('rab', $rab)->where('keterangan', 'Setor')->sum('nilai');
                $totalCadangan = DB::table('modal_detail')->where('rab', $rab)->where('keterangan', 'Cadangan')->sum('nilai');

                DB::table('modal')->updateOrInsert(
                    ['rab' => $rab],
                    ['setor' => $totalSetor, 'cad_dana' => $totalCadangan]
                );

                DB::commit();  // ← baru commit di sini

                return response()->json(['success' => true, 'message' => 'Data berhasil dihapus dan modal diperbarui.']);
            }

            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan atau sudah dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack(); // ← rollback bila error
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
        }
    }


    public function modalEdit(Request $request)
    {
        $data = $request->all();

        if (!is_array($data) || empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid atau kosong.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $rab = null;

            foreach ($data as $item) {
                if (
                    empty($item['id']) || empty($item['rab']) || empty($item['bbt']) ||
                    empty($item['tgl_bbt']) || !isset($item['nilai']) || empty($item['keterangan'])
                ) {
                    throw new Exception("Data tidak lengkap pada salah satu baris.");
                }

                $rab = $item['rab']; // Simpan nilai rab untuk nanti update/insert tabel modal

                DB::table('modal_detail')
                    ->where('id', $item['id'])
                    ->update([
                        'rab' => $item['rab'],
                        'bbt' => $item['bbt'],
                        'tgl_bbt' => $item['tgl_bbt'],
                        'nilai' => $item['nilai'],
                        'keterangan' => $item['keterangan'],
                    ]);
            }

            // Setelah update modal_detail, hitung dan update tabel 'modal'
            $totalSetor = DB::table('modal_detail')
                ->where('rab', $rab)
                ->where('keterangan', 'Setor')
                ->sum('nilai');

            $totalCadangan = DB::table('modal_detail')
                ->where('rab', $rab)
                ->where('keterangan', 'Cadangan')
                ->sum('nilai');

            // Cek apakah data sudah ada di tabel 'modal'
            $modalExists = DB::table('modal')->where('rab', $rab)->exists();

            if ($modalExists) {
                DB::table('modal')
                    ->where('rab', $rab)
                    ->update([
                        'setor' => $totalSetor,
                        'cad_dana' => $totalCadangan,
                    ]);
            } else {
                DB::table('modal')->insert([
                    'rab' => $rab,
                    'setor' => $totalSetor,
                    'cad_dana' => $totalCadangan,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diperbarui.'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }
}
