<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LpdRealisasiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LpdDeleteId extends Controller
{
    public function destroy(Request $request)
    {
        $id  = $request->input('id');
        $rab = $request->input('rab');

        if (!$id || !$rab) {
            return response()->json(['message' => 'ID dan RAB wajib diisi'], 422);
        }

        $kdToko = DB::table('lpd')->where('no_rab', $rab)->value('kd_toko');

        if (!$kdToko) {
            return response()->json(['message' => 'kd_toko tidak ditemukan dari RAB'], 404);
        }

        $namaTabel = $kdToko;

        if (!Schema::hasTable($namaTabel)) {
            return response()->json(['message' => "Tabel {$namaTabel} tidak ditemukan"], 404);
        }

        $data = DB::table($namaTabel)->where('id', $id)->first();

        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if (!empty($data->flag_sarana) || !empty($data->flag_dat_pr)) {
            return response()->json([
                'message' => 'Data tidak dapat dihapus karena flag_sarana atau flag_dat_pr sudah terisi'
            ], 403);
        }

        try {
            // Simpan data yang diperlukan untuk update realisasi detail
            $realisasiData = [
                'rab'      => $data->rab,
                'kd_group' => $data->kd_group,
                'plu'      => $data->plu,
                'kd_toko'  => $kdToko
            ];

            // Hapus data
            DB::table($namaTabel)->where('id', $id)->delete();

            // Update tabel lpd_realisasi_detail
            LpdRealisasiService::updateDetail($realisasiData);

            return response()->json(['message' => 'Data berhasil dihapus dan realisasi diperbarui']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
