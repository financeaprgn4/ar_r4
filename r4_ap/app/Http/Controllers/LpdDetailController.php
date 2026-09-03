<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lpd;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LpdDetailController extends Controller
{
    public function detail(Request $request)
    {
        $no_rab = $request->query('no_rab');$no_rab = $request->query('no_rab');

        $data = DB::table('lpd')
            ->select([
                'lpd.kd_toko',
                'lpd.nama_toko',
                'lpd.jns_toko',
                'lpd.no_rab',
                'lpd.tgl_proposal',
                'lpd.tgl_wrlb',
                'lpd.tgl_jt',
                'lpd.badan',
                'lpd.status',
                'lpd.report',
                'lpd.pdf',
                'lpd.excel',
                'lpd.keterangan',
                'lpd.catatan_final',
                'lpd.rab_final',
                'berkas_lpd.rab_rekap',
                'berkas_lpd.rab_detail',
                'berkas_lpd.termin_invest',
                'berkas_lpd.proposal',
                'berkas_lpd.draft_cs',
                'berkas_lpd.pot_surkas',
                'berkas_lpd.item_tdk_realisasi',
                'lrd.rab_frc_fee',
                'lrd.realisasi_frc_fee',
                'lrd.rab_promo',
                'lrd.realisasi_promo',
                'lrd.rab_rekrut_train',
                'lrd.realisasi_rekrut_train',
                'lrd.rab_sw_pph',
                'lrd.realisasi_sw_pph',
                'lrd.rab_jasa_pihak3',
                'lrd.realisasi_jasa_pihak3',
                'lrd.rab_fg',
                'lrd.realisasi_fg',
                'lrd.rab_kanopi',
                'lrd.realisasi_kanopi',
                'lrd.rab_ins_ac',
                'lrd.realisasi_ins_ac',
                'lrd.rab_teralis',
                'lrd.realisasi_teralis',
                'lrd.rab_halaman',
                'lrd.realisasi_halaman',
                'lrd.rab_policarbonate',
                'lrd.realisasi_policarbonate',
                'lrd.rab_listrik',
                'lrd.realisasi_listrik',
                'lrd.rab_aluminium_kaca',
                'lrd.realisasi_aluminium_kaca',
                'lrd.rab_signage',
                'lrd.realisasi_signage',
                'lrd.rab_sipil',
                'lrd.realisasi_sipil',
                'lrd.rab_urugan',
                'lrd.realisasi_urugan',
                'lrd.rab_lift',
                'lrd.realisasi_lift',
                'lrd.rab_prasarana',
                'lrd.realisasi_prasarana',
                'lrd.rab_peralatan',
                'lrd.realisasi_peralatan',
                'lrd.rab_interior',
                'lrd.realisasi_interior',
                'lrd.flag_realisasi_fg',
                'lrd.flag_realisasi_kanopi',
                'lrd.flag_realisasi_ins_ac',
                'lrd.flag_realisasi_teralis',
                'lrd.flag_realisasi_halaman',
                'lrd.flag_realisasi_policarbonate',
                'lrd.flag_realisasi_listrik',
                'lrd.flag_realisasi_aluminium_kaca',
                'lrd.flag_realisasi_signage',
                'lrd.flag_realisasi_interior',
                'lrd.flag_realisasi_sipil',
                'lrd.flag_realisasi_urugan',
                'lrd.flag_realisasi_lift',

                DB::raw('(
                    SELECT IFNULL(SUM(setor), 0)
                    FROM modal
                    WHERE modal.rab = lpd.no_rab
                ) AS total_setor'),
                DB::raw('(
                    SELECT IFNULL(SUM(cad_dana), 0)
                    FROM modal
                    WHERE modal.rab = lpd.no_rab
                ) AS total_cad_dana'),
                DB::raw('(
                    SELECT IFNULL(SUM(setor), 0) + IFNULL(SUM(cad_dana), 0)
                    FROM modal
                    WHERE modal.rab = lpd.no_rab
                ) AS total_investasi'),
                DB::raw('(
                    SELECT IFNULL(SUM(pek_by_frcsee), 0)
                    FROM modal
                    WHERE modal.rab = lpd.no_rab
                ) AS pek_frc'),
                DB::raw('(
                    SELECT IFNULL(SUM(sewa_by_frcsee), 0)
                    FROM modal
                    WHERE modal.rab = lpd.no_rab
                ) AS sewa_frc'),
                DB::raw('(
                    SELECT IFNULL(SUM(sewa_at), 0)
                    FROM modal
                    WHERE modal.rab = lpd.no_rab
                ) AS sewa_at'),
            ])
            ->leftJoin('berkas_lpd', 'berkas_lpd.rab', '=', 'lpd.no_rab')
            ->leftJoin('lpd_realisasi_detail as lrd', 'lrd.rab', '=', 'lpd.no_rab')
            ->where('lpd.no_rab', $no_rab)
            ->get();


        $modal_detail = DB::table('modal_detail')
            ->where('rab', $no_rab)
            ->get();
        
        $modal = DB::table('modal')
            ->where('rab', $no_rab)
            ->get();

        $sarana = DB::table('sarana_toko')
            ->where('rab', $no_rab)
            ->get();
        
        $datpr = DB::table('dat_pr')
            ->where('rab', $no_rab)
            ->get();

        $allTokoData = collect();

        if ($data->isNotEmpty()) {
            $kdToko = strtolower($data->first()->kd_toko);

            if (Schema::hasTable($kdToko)) {
                $allTokoData = DB::table($kdToko)
                    ->where('rab', $no_rab)
                    ->get()
                    ->groupBy('kd_group');
            }
        }

        $result = $data->map(function ($item) use ($allTokoData) {
            $flattened = $allTokoData->flatten();

            $item->all_data = [
                'rows'       => $flattened,
                'total_dpp'  => $flattened->sum('dpp'),
                'total_ppn'  => $flattened->sum('ppn'),
                'total_sum'  => $flattened->sum('total'),
            ];

            // Group utama
            $item->frc_fee   = $allTokoData->get('030001') ?? collect();
            $item->promo     = $allTokoData->get('030002') ?? collect();
            $item->rekrut    = $allTokoData->get('030003') ?? collect();
            $item->sewa      = $allTokoData->get('030004') ?? collect();
            $item->js_phk    = $allTokoData->get('030005') ?? collect();
            $item->renov     = $allTokoData->get('030006') ?? collect();
            $item->prasarana = $allTokoData->get('030007') ?? collect();
            $item->peralatan = collect()
                                ->merge($allTokoData->get('030008') ?? collect())
                                ->merge($allTokoData->get('030009') ?? collect())
                                ->values();

            // Data spesifik dalam grup 030006
            $renov = $allTokoData->get('030006') ?? collect();
            $item->folding   = $renov->where('plu', '0216030012')->values();
            $item->kanopi    = $renov->where('plu', 'PJ00448')->values();
            $item->ins_ac    = $renov->where('plu', '0216030016')->values();
            $item->teralis   = $renov->where('plu', '0216030014')->values();
            $item->halaman   = $renov->where('plu', '0216030010')->values();
            $item->poly      = $renov->where('plu', '0216030013')->values();
            $item->listrik   = $renov->where('plu', '0216030015')->values();
            $item->kaca      = $renov->where('plu', '0216030011')->values();
            $item->signage   = $renov->where('plu', '0211040090')->values();
            $item->interior  = $renov->where('plu', 'PJ00089')->values();
            $item->urugan    = $renov->where('plu', '0216030009')->values();
            $item->sipil     = $renov->where('plu', '0216030008')->values();

            return $item;
        });

        $filteredRenovBAPJ = collect();
        if ($data->isNotEmpty()) {
            $kdToko = strtolower($data->first()->kd_toko);

            if (Schema::hasTable($kdToko)) {
                // Ambil data grup 030006 dan inv_num mengandung BAPJ
                $filteredRenovBAPJ = DB::table($kdToko)
                    ->where('rab', $no_rab)
                    ->where('kd_group', '030006')
                    ->where('inv_num', 'LIKE', '%BAPJ%')
                    ->get();
            }
        }

        return response()->json([
            'data' => $result,
            'modal' => $modal,
            'sarana' => $sarana,
            'datpr' => $datpr,
            'modal_detail' => $modal_detail,
            'bapj_renov' => $filteredRenovBAPJ,
        ]);
    }
}