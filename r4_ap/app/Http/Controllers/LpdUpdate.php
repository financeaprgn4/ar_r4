<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lpd;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LpdUpdate extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'no_rab' => 'required|string|exists:lpd,no_rab',
            'estimasi' => 'required|array',
            'keterangan' => 'nullable|string',
            'catatan_final' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $lpd = Lpd::where('no_rab', $validated['no_rab'])->first();

            if (!$lpd) {
                return response()->json(['message' => 'LPD tidak ditemukan.'], 404);
            }

            if (isset($validated['catatan_final'])) {
                $lpd->catatan_final = trim($validated['catatan_final']);
            } else {
                $lpd->keterangan = trim($validated['keterangan'] ?? '') ?: 'Kekurangan :';
            }

            $lpd->save();

            DB::table('lpd_realisasi_detail')
                ->where('rab', $validated['no_rab'])
                ->update($validated['estimasi']);

            DB::commit();
            return response()->json([
                'message' => 'Berhasil update data LPD.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat update.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}