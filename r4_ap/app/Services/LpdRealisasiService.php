<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LpdRealisasiService
{
    public static function updateDetail($group)
    {
        $kdToko = $group['kd_toko'];
        $rab = $group['rab'];
        $kdGroup = $group['kd_group'];
        $plu = $group['plu'];

        if (!DB::getSchemaBuilder()->hasTable($kdToko)) return;

        // Mapping group normal
        $groupMapping = [
            '030001' => 'realisasi_frc_fee',
            '030002' => 'realisasi_promo',
            '030003' => 'realisasi_rekrut_train',
            '030004' => 'realisasi_sw_pph',
            '030005' => 'realisasi_jasa_pihak3',
            '030007' => 'realisasi_prasarana',
            // CATATAN:
            // realisasi_peralatan SEKARANG di-handle terpisah (030008 + 030009), jadi tidak disini.
        ];

        // Mapping PLU untuk 030006
        $pluMapping030006 = [
            '0216030012' => 'realisasi_fg',
            'PJ00448' => 'realisasi_kanopi',
            '0216030016' => 'realisasi_ins_ac',
            '0216030014' => 'realisasi_teralis',
            '0216030010' => 'realisasi_halaman',
            '0216030013' => 'realisasi_policarbonate',
            '0216030015' => 'realisasi_listrik',
            '0216030011' => 'realisasi_aluminium_kaca',
            '0211040090' => 'realisasi_signage',
            '0216030008' => 'realisasi_sipil',
            'PJ00089' => 'realisasi_interior',
            'PJ01741' => 'realisasi_lift',
        ];

        // Query dasar
        $query = DB::table($kdToko)->where('rab', $rab)->where('kd_group', $kdGroup);

        // ================================
        // 1. KHUSUS GROUP 030008 + 030009
        // ================================
        if ($kdGroup === '030008' || $kdGroup === '030009') {

            // Hitung total untuk kedua grup
            $totalPeralatan = DB::table($kdToko)
                ->where('rab', $rab)
                ->whereIn('kd_group', ['030008', '030009'])
                ->sum('total');

            // Update ke tabel detail realisasi
            DB::table('lpd_realisasi_detail')
                ->where('rab', $rab)
                ->update(['realisasi_peralatan' => $totalPeralatan]);

            return; // selesai
        }

        // ================================
        // 2. KHUSUS GROUP 030006 (by PLU)
        // ================================
        if ($kdGroup === '030006') {
            if (isset($pluMapping030006[$plu])) {
                $field = $pluMapping030006[$plu];

                // Hitung total item yang cocok
                $total = $query->where('plu', $plu)->sum('total');

                DB::table('lpd_realisasi_detail')
                    ->where('rab', $rab)
                    ->update([$field => $total]);
            }

            return;
        }

        // ================================
        // 3. GROUP NORMAL (sesuai mapping)
        // ================================
        if (isset($groupMapping[$kdGroup])) {
            $field = $groupMapping[$kdGroup];
            $total = $query->sum('total');

            DB::table('lpd_realisasi_detail')
                ->where('rab', $rab)
                ->update([$field => $total]);
        }

        return;
    }

}
