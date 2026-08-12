<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lpd;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use App\Services\LpdRealisasiService;
use Carbon\Carbon;
use COM;

class LpdController extends Controller
{
    public function lpdAll(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json(['error' => 'Cabang tidak ditemukan'], 400);
        }

        $lpdList = DB::table('lpd')
            ->select([
                'lpd.kd_toko',
                'lpd.nama_toko',
                'lpd.jns_toko',
                'lpd.no_rab',
                'lpd.tgl_wrlb',
                'berkas_lpd.rab_rekap',
                'berkas_lpd.rab_detail',
                'berkas_lpd.termin_invest',
                'berkas_lpd.proposal'
            ])
            ->leftJoin('berkas_lpd', 'berkas_lpd.rab', '=', 'lpd.no_rab')
            ->where('lpd.cabang', '=', $cabang)
            ->orderBy('lpd.tgl_wrlb', 'desc')
            ->get();

        return response()->json([
            'data' => $lpdList,
        ]);
    }

    public function lpdModal(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json(['error' => 'Cabang tidak ditemukan'], 400);
        }

        $lpdList = DB::table('lpd')
            ->select([
                'lpd.kd_toko',
                'lpd.nama_toko',
                'lpd.jns_toko',
                'lpd.no_rab',
                'lpd.tgl_wrlb',
                'lpd.rab_final',
                'modal.id',
                'modal.setor',
                'modal.cad_dana',
                'modal.pek_by_frcsee',
                'modal.sewa_by_frcsee',
                'modal.sewa_at',
                'modal.keterangan',
                'lpd.pdf',
                'lpd.excel',
                'berkas_lpd.rab_rekap',
                'berkas_lpd.rab_detail',
                'berkas_lpd.termin_invest',
                'berkas_lpd.proposal',
                'berkas_lpd.draft_cs',

                // Tambahkan total dari semua kolom rab_ di tabel lpd_realisasi_detail
                DB::raw('
                    (
                        IFNULL(lrd.rab_frc_fee, 0) + 
                        IFNULL(lrd.rab_promo, 0) + 
                        IFNULL(lrd.rab_rekrut_train, 0) +
                        IFNULL(lrd.rab_sw_pph, 0) +
                        IFNULL(lrd.rab_jasa_pihak3, 0) +
                        IFNULL(lrd.rab_fg, 0) +
                        IFNULL(lrd.rab_kanopi, 0) +
                        IFNULL(lrd.rab_ins_ac, 0) +
                        IFNULL(lrd.rab_teralis, 0) +
                        IFNULL(lrd.rab_halaman, 0) +
                        IFNULL(lrd.rab_policarbonate, 0) +
                        IFNULL(lrd.rab_listrik, 0) +
                        IFNULL(lrd.rab_aluminium_kaca, 0) +
                        IFNULL(lrd.rab_signage, 0) +
                        IFNULL(lrd.rab_sipil, 0) +
                        IFNULL(lrd.rab_prasarana, 0) +
                        IFNULL(lrd.rab_peralatan, 0) +
                        IFNULL(lrd.rab_interior, 0)
                    ) AS total_rab_detail
                ')
            ])
            ->leftJoin('modal', 'modal.rab', '=', 'lpd.no_rab')
            ->leftJoin('berkas_lpd', 'berkas_lpd.rab', '=', 'lpd.no_rab')
            ->leftJoin('lpd_realisasi_detail as lrd', 'lrd.rab', '=', 'lpd.no_rab')
            ->where('lpd.cabang', '=', $cabang)
            ->orderBy('lpd.tgl_wrlb', 'desc')
            ->get();

        return response()->json([
            'data' => $lpdList,
        ]);
    }

    public function lpdRab(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json(['error' => 'Cabang tidak ditemukan'], 400);
        }

        $lpdList = DB::table('lpd')
            ->select([
                'lpd.kd_toko',
                'lpd.nama_toko',
                'lpd.jns_toko',
                'lpd.no_rab',
                'lpd.tgl_wrlb',
                'lpd.rab_final',
                'lpd.pdf',
                'lpd.excel',
                'berkas_lpd.rab_rekap',
                'berkas_lpd.rab_detail',
                'berkas_lpd.termin_invest',
                'berkas_lpd.proposal',
                'berkas_lpd.draft_cs',
                'lrd.id',
                'lrd.lampiran',
                'lrd.keterangan',

                // Tambahkan total dari semua kolom rab_ di tabel lpd_realisasi_detail
                DB::raw('
                    (
                        IFNULL(lrd.rab_frc_fee, 0) + 
                        IFNULL(lrd.rab_promo, 0) + 
                        IFNULL(lrd.rab_rekrut_train, 0) +
                        IFNULL(lrd.rab_sw_pph, 0) +
                        IFNULL(lrd.rab_jasa_pihak3, 0) +
                        IFNULL(lrd.rab_fg, 0) +
                        IFNULL(lrd.rab_kanopi, 0) +
                        IFNULL(lrd.rab_ins_ac, 0) +
                        IFNULL(lrd.rab_teralis, 0) +
                        IFNULL(lrd.rab_halaman, 0) +
                        IFNULL(lrd.rab_policarbonate, 0) +
                        IFNULL(lrd.rab_listrik, 0) +
                        IFNULL(lrd.rab_aluminium_kaca, 0) +
                        IFNULL(lrd.rab_signage, 0) +
                        IFNULL(lrd.rab_sipil, 0) +
                        IFNULL(lrd.rab_urugan, 0) +
                        IFNULL(lrd.rab_lift, 0) +
                        IFNULL(lrd.rab_prasarana, 0) +
                        IFNULL(lrd.rab_peralatan, 0) +
                        IFNULL(lrd.rab_interior, 0)
                    ) AS total_rab_detail
                ')
            ])
            ->leftJoin('berkas_lpd', 'berkas_lpd.rab', '=', 'lpd.no_rab')
            ->leftJoin('lpd_realisasi_detail as lrd', 'lrd.rab', '=', 'lpd.no_rab')
            ->where('lpd.cabang', '=', $cabang)
            ->orderBy('lpd.tgl_wrlb', 'desc')
            ->get();

        return response()->json([
            'data' => $lpdList,
        ]);
    }

    public function getOuts(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json(['error' => 'Cabang tidak ditemukan'], 400);
        }

        $periode_lpd = DB::table('periode')
            ->where('periode.kategori', '=', 'LPD')
            ->where('periode.Cabang', '=', $cabang)
            ->where('periode.status', '=', 'Aktif')
            ->get();

        $lpdList = DB::table('lpd')
            ->select([
                'lpd.kd_toko',
                'lpd.nama_toko',
                'lpd.jns_toko',
                'lpd.no_rab',
                'lpd.tgl_wrlb',
                'lpd.tgl_jt',
                'lpd.badan',
                'lpd.status',
                'lpd.pdf',
                'lpd.excel',
                'lpd.keterangan',
                'berkas_lpd.rab_rekap',
                'berkas_lpd.rab_detail',
                'berkas_lpd.termin_invest',
                'berkas_lpd.proposal',
                'berkas_lpd.draft_cs',
                // Detail dari lpd_realisasi_detail
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
                'lrd.rab_prasarana',
                'lrd.realisasi_prasarana',
                'lrd.rab_peralatan',
                'lrd.realisasi_peralatan',
                'lrd.rab_interior',
                'lrd.realisasi_interior',
                
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
            ])
            ->leftJoin('berkas_lpd', 'berkas_lpd.rab', '=', 'lpd.no_rab')
            ->leftJoin('lpd_realisasi_detail as lrd', 'lrd.rab', '=', 'lpd.no_rab')
            ->where('lpd.status', '!=', 'final')
            ->where('lpd.cabang', '=', $cabang)
            ->orderBy('lpd.tgl_wrlb', 'asc')
            ->get();

        foreach ($lpdList as $lpd) {
            $tableName = $lpd->kd_toko;

            if (Schema::hasTable($tableName)) {
                $totalRealisasi = DB::table($tableName)
                    ->where('rab', $lpd->no_rab)
                    ->sum('total');
            } else {
                $totalRealisasi = 0;
            }

            $lpd->total_realisasi = floatval($totalRealisasi);
            $lpd->sisa_lpd = ($lpd->total_investasi ?? 0) - $totalRealisasi;
        }

        return response()->json([
            'periode' => $periode_lpd,
            'data' => $lpdList,
        ]);
    }

    public function getFinal(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json(['error' => 'Cabang tidak ditemukan'], 400);
        }

        $periode_lpd = DB::table('periode')
            ->where('periode.kategori', '=', 'LPD')
            ->where('periode.Cabang', '=', $cabang)
            ->where('periode.status', '=', 'Aktif')
            ->get();

        $lpdList = DB::table('lpd')
            ->select([
                'lpd.kd_toko',
                'lpd.nama_toko',
                'lpd.jns_toko',
                'lpd.no_rab',
                'lpd.tgl_wrlb',
                'lpd.tgl_jt',
                'lpd.badan',
                'lpd.status',
                'lpd.pdf',
                'lpd.excel',
                'lpd.catatan_final',
                'berkas_lpd.rab_rekap',
                'berkas_lpd.rab_detail',
                'berkas_lpd.termin_invest',
                'berkas_lpd.proposal',
                'berkas_lpd.cs_final',
                'berkas_lpd.pot_surkas',
                // Detail dari lpd_realisasi_detail
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
                'lrd.rab_prasarana',
                'lrd.realisasi_prasarana',
                'lrd.rab_peralatan',
                'lrd.realisasi_peralatan',
                'lrd.rab_interior',
                'lrd.realisasi_interior',
                
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
            ])
            ->leftJoin('berkas_lpd', 'berkas_lpd.rab', '=', 'lpd.no_rab')
            ->leftJoin('lpd_realisasi_detail as lrd', 'lrd.rab', '=', 'lpd.no_rab')
            ->where('lpd.status', '=', 'final')
            ->where('lpd.cabang', '=', $cabang)
            ->orderBy('lpd.tgl_wrlb', 'asc')
            ->get();

        foreach ($lpdList as $lpd) {
            $tableName = $lpd->kd_toko;

            if (Schema::hasTable($tableName)) {
                $totalRealisasi = DB::table($tableName)
                    ->where('rab', $lpd->no_rab)
                    ->sum('total');
            } else {
                $totalRealisasi = 0;
            }

            $lpd->total_realisasi = floatval($totalRealisasi);
            $lpd->sisa_lpd = ($lpd->total_investasi ?? 0) - $totalRealisasi;
        }

        return response()->json([
            'periode' => $periode_lpd,
            'data' => $lpdList,
        ]);
    }

    public function getCS(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json(['error' => 'Cabang tidak ditemukan'], 400);
        }

        $periode_lpd = DB::table('periode')
            ->where('periode.kategori', '=', 'LPD')
            ->where('periode.Cabang', '=', $cabang)
            ->where('periode.status', '=', 'Aktif')
            ->get();

        $lpdList = DB::table('lpd')
            ->select([
                'lpd.kd_toko',
                'lpd.nama_toko',
                'lpd.jns_toko',
                'lpd.no_rab',
                'lpd.tgl_wrlb',
                'lpd.tgl_jt',
                'lpd.badan',
                'lpd.status',
                'lpd.pdf',
                'lpd.excel',
                'lpd.keterangan',
                'berkas_lpd.rab_rekap',
                'berkas_lpd.rab_detail',
                'berkas_lpd.termin_invest',
                'berkas_lpd.proposal',
                'berkas_lpd.draft_cs',
                'berkas_lpd.cs_final',
                // Detail dari lpd_realisasi_detail
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
                'lrd.rab_prasarana',
                'lrd.realisasi_prasarana',
                'lrd.rab_peralatan',
                'lrd.realisasi_peralatan',
                'lrd.rab_interior',
                'lrd.realisasi_interior',
                
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
            ])
            ->leftJoin('berkas_lpd', 'berkas_lpd.rab', '=', 'lpd.no_rab')
            ->leftJoin('lpd_realisasi_detail as lrd', 'lrd.rab', '=', 'lpd.no_rab')
            ->where('lpd.status', '=', 'CS')
            ->where('lpd.cabang', '=', $cabang)
            ->orderBy('lpd.tgl_wrlb', 'asc')
            ->get();

        foreach ($lpdList as $lpd) {
            $tableName = $lpd->kd_toko;

            if (Schema::hasTable($tableName)) {
                $totalRealisasi = DB::table($tableName)
                    ->where('rab', $lpd->no_rab)
                    ->sum('total');
            } else {
                $totalRealisasi = 0;
            }

            $lpd->total_realisasi = floatval($totalRealisasi);
            $lpd->sisa_lpd = ($lpd->total_investasi ?? 0) - $totalRealisasi;
        }

        return response()->json([
            'periode' => $periode_lpd,
            'data' => $lpdList,
        ]);
    }

    public function importDetail(Request $request)
    {
        $data = $request->input('data');

        /**
         * =========================
         * VALIDASI FORMAT DATA
         * =========================
         */
        if (!is_array($data) || empty($data) || !is_array($data[0])) {
            return response()->json([
                'success' => false,
                'message' => 'Format data tidak valid!',
            ], 422);
        }

        $inserted     = 0;
        $skipped      = 0;
        $updateGroups = [];
        $lpdCache     = [];

        DB::beginTransaction();

        try {

            foreach ($data as $row) {

                /**
                 * =========================
                 * VALIDASI FIELD WAJIB
                 * =========================
                 */
                $noRab = $row['NO_RAB'] ?? null;
                if (!$noRab) {
                    $skipped++;
                    continue;
                }

                /**
                 * =========================
                 * CACHE LPD PER RAB
                 * =========================
                 */
                if (!array_key_exists($noRab, $lpdCache)) {
                    $lpdCache[$noRab] = DB::table('lpd')
                        ->where('no_rab', $noRab)
                        ->where('status', 'NEW')
                        ->first();
                }

                $lpd = $lpdCache[$noRab];
                if (!$lpd) {
                    $skipped++;
                    continue;
                }

                /**
                 * =========================
                 * DATA DASAR
                 * =========================
                 */
                $toko       = $lpd->kd_toko;
                $kdGroup    = $row['NO_GROUP'] ?? null;
                $no_sjf     = $row['NO_SJF'] ?? "";
                $line_num   = $row['LINE_NUM'] ?? "";
                $plu        = $row['PLU'] ?? "";

                $invNum     = $row['INVOICE_NUM'] ?? null;
                $keterangan = $row['DESKRIPSI'] ?? null;
                $dpp        = $row['AMOUNT'] ?? null;

                if (!$invNum || !$keterangan) {
                    $skipped++;
                    continue;
                }

                /**
                 * =========================
                 * CEK DUPLIKASI DATA
                 * =========================
                 */
                $exists = DB::table($toko)
                    ->where('rab', $noRab)
                    ->where('plu', $plu)
                    ->where('inv_num', $invNum)
                    ->where('no_sjf', $no_sjf)
                    ->where('line_num', $line_num)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                /**
                 * =========================
                 * INSERT DATA
                 * =========================
                 */
                DB::table($toko)->insert([
                    'rab'         => $noRab,
                    'no_sjf'      => $no_sjf,
                    'line_num'    => $line_num,
                    'kd_group'    => $kdGroup,
                    'plu'         => $plu,
                    'keterangan'  => $keterangan,
                    'dpp'         => $row['AMOUNT'] ?? 0,
                    'ppn'         => $row['PPn'] ?? 0,
                    'total'       => $row['Total'] ?? 0,
                    'inv_num'     => $invNum,
                    'flag_sarana' => $row['Flag_Sarana'] ?? '',
                    'flag_renov'  => '',
                    'flag_dat_pr' => '',
                ]);

                /**
                 * =========================
                 * GROUP UNTUK UPDATE REALISASI
                 * =========================
                 */
                $key = "{$toko}|{$noRab}|{$kdGroup}|{$plu}";
                if (!isset($updateGroups[$key])) {
                    $updateGroups[$key] = [
                        'kd_toko'  => $toko,
                        'rab'      => $noRab,
                        'kd_group' => $kdGroup,
                        'plu'      => $plu,
                    ];
                }

                $inserted++;
            }

            /**
             * =========================
             * UPDATE REALISASI
             * =========================
             */
            foreach ($updateGroups as $group) {
                LpdRealisasiService::updateDetail($group);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import selesai. Berhasil: {$inserted}, Dilewati: {$skipped}",
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            \Log::error('IMPORT DETAIL ERROR', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import data',
            ], 500);
        }
    }

    public function storeAdd(Request $request)
    {
        try {
            $data = $request->all();
            $kd_toko = $data['kd_toko'];
            $no_rab = $data['no_rab'];
            $log = [];

            $exists = DB::table('lpd')
                ->where('kd_toko', $kd_toko)
                ->where('no_rab', $no_rab)
                ->exists();

            if (!$exists) {
                $tgl_jt = Carbon::parse($data['tgl_wrlb'])->addMonths(3)->subDay();
                DB::table('lpd')->insert([
                    'cabang'       => $data['cabang'],
                    'kd_toko'      => $kd_toko,
                    'nama_toko'    => $data['nama_toko'],
                    'jns_toko'     => $data['jns_toko'],
                    'tgl_proposal' => $data['tgl_proposal'],
                    'tgl_wrlb'     => $data['tgl_wrlb'],
                    'tgl_jt'       => $tgl_jt,
                    'report'       => $data['report'],
                    'no_rab'       => $no_rab,
                    'rab_final'    => $data['rab_final'],
                    'badan'        => $data['badan'],
                    'status'       => 'NEW',
                    'last_update'  => now(),
                ]);
                $log[] = "✅ Insert tabel `lpd` berhasil.";
            } else {
                $log[] = "⏩ Data pada tabel `lpd` sudah ada. Dilewati.";
            }

            // Insert ke tabel lpd_realisasi_detail
            $exists_realisasi = DB::table('lpd_realisasi_detail')->where('rab', $no_rab)->exists();
            if (!$exists_realisasi) {
                DB::table('lpd_realisasi_detail')->insert([
                    'rab' => $no_rab
                ]);
                $log[] = "✅ Insert tabel `lpd_realisasi_detail` berhasil.";
            } else {
                $log[] = "⏩ Data pada tabel `lpd_realisasi_detail` sudah ada. Dilewati.";
            }

            // Insert ke tabel modal
            $exists_modal = DB::table('modal')->where('rab', $no_rab)->exists();
            if (!$exists_modal) {
                DB::table('modal')->insert([
                    'rab'            => $no_rab,
                    'pek_by_frcsee'  => $data['pek_frc'],
                    'sewa_by_frcsee' => $data['sewa_frc'],
                    'sewa_at'        => $data['sewa_at']
                ]);
                $log[] = "✅ Insert tabel `modal` berhasil.";
            } else {
                $log[] = "⏩ Data pada tabel `modal` sudah ada. Dilewati.";
            }

            // Insert ke tabel berkas_lpd
            $exists_berkas = DB::table('berkas_lpd')->where('rab', $no_rab)->exists();
            if (!$exists_berkas) {
                DB::table('berkas_lpd')->insert([
                    'rab' => $no_rab
                ]);
                $log[] = "✅ Insert tabel `berkas_lpd` berhasil.";
            } else {
                $log[] = "⏩ Data pada tabel `berkas_lpd` sudah ada. Dilewati.";
            }

            // Cek dan buat tabel kd_toko jika belum ada
            if (!Schema::hasTable($kd_toko)) {
                DB::statement("
                    CREATE TABLE `$kd_toko` (
                        `id` int(5) NOT NULL AUTO_INCREMENT,
                        `rab` varchar(25) NOT NULL,
                        `kd_group` varchar(6) NOT NULL,
                        `no_sjf` text NOT NULL,
                        `line_num` text NOT NULL,
                        `plu` varchar(25) NOT NULL,
                        `keterangan` text NOT NULL,
                        `dpp` int(10) NOT NULL,
                        `ppn` int(10) NOT NULL,
                        `total` int(10) NOT NULL,
                        `inv_num` varchar(50) NOT NULL,
                        `flag_sarana` varchar(50) NOT NULL,
                        `flag_renov` text NOT NULL,
                        `flag_dat_pr` text NOT NULL,
                        PRIMARY KEY (`id`) USING BTREE
                    )
                ");
                $log[] = "✅ Tabel `$kd_toko` berhasil dibuat.";
            } else {
                $log[] = "⏩ Tabel `$kd_toko` sudah ada. Dilewati.";
            }

            return response()->json([
                'message' => implode("\n", $log),
                'success' => true
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Terjadi kesalahan: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function updateSite(Request $request)
    {
        try {
            $no_rab = $request->no_rab;
            $changes = $request->changes ?? [];

            if (!$no_rab || empty($changes)) {
                return response()->json(['message' => 'Data tidak lengkap.'], 422);
            }

            $existing = DB::table('lpd')->where('no_rab', $no_rab)->first();
            if (!$existing) {
                return response()->json(['message' => 'Data tidak ditemukan.'], 404);
            }

            $kd_toko_lama = $existing->kd_toko;
            $kd_toko_baru = $changes['kd_toko']['after'] ?? $kd_toko_lama;

            $no_rab_lama = $existing->no_rab;
            $no_rab_baru = $changes['no_rab']['after'] ?? $no_rab_lama;

            $log = [];

            // ambil tgl_wrlb baru (kalau ada perubahan)
            $tgl_wrlb_baru = $changes['tgl_wrlb']['after'] ?? $existing->tgl_wrlb;

            // hitung tgl_jt = tgl_wrlb + 3 bulan
            $tgl_jt = null;
            if ($tgl_wrlb_baru) {
                $tgl_jt = \Carbon\Carbon::parse($tgl_wrlb_baru)->addMonths(3)->toDateString();
            }

            // Update tabel lpd
            $lpdUpdateData = [
                'kd_toko'      => $kd_toko_baru,
                'nama_toko'    => $changes['nama_toko']['after'] ?? $existing->nama_toko,
                'jns_toko'     => $changes['jns_toko']['after'] ?? $existing->jns_toko,
                'tgl_proposal' => $changes['tgl_proposal']['after'] ?? $existing->tgl_proposal,
                'tgl_wrlb'     => $tgl_wrlb_baru,
                'tgl_jt'       => $tgl_jt,
                'report'       => $changes['report']['after'] ?? $existing->report,
                'no_rab'       => $no_rab_baru,
                'rab_final'    => $changes['rab_final']['after'] ?? $existing->rab_final,
                'badan'        => $changes['badan']['after'] ?? $existing->badan,
                'last_update'  => now(),
            ];

            DB::table('lpd')->where('no_rab', $no_rab)->update($lpdUpdateData);

            // Update modal
            DB::table('modal')->where('rab', $no_rab)->update([
                'pek_by_frcsee' => $changes['pek_frc']['after'] ?? DB::table('modal')->where('rab', $no_rab)->value('pek_by_frcsee'),
                'sewa_by_frcsee' => $changes['sewa_frc']['after'] ?? DB::table('modal')->where('rab', $no_rab)->value('sewa_by_frcsee'),
                'sewa_at' => $changes['sewa_at']['after'] ?? DB::table('modal')->where('rab', $no_rab)->value('sewa_at'),
            ]);

            // Jika no_rab berubah → update juga semua tabel terkait
            if ($no_rab_lama !== $no_rab_baru) {
                $tablesToUpdate = ['modal', 'berkas_lpd', 'dat_pr', 'lpd_realisasi_detail', 'modal_detail'];
                foreach ($tablesToUpdate as $table) {
                    DB::table($table)->where('rab', $no_rab_lama)->update(['rab' => $no_rab_baru]);
                }
                $log[] = "📝 RAB diperbarui dari `$no_rab_lama` ke `$no_rab_baru` di semua tabel terkait.";
            }

            // Jika kd_toko berubah → buat tabel baru jika belum ada
            if ($kd_toko_lama !== $kd_toko_baru) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $kd_toko_baru)) {
                    return response()->json(['message' => 'Nama tabel kd_toko tidak valid.'], 400);
                }

                if (!Schema::hasTable($kd_toko_baru)) {
                    DB::statement("
                        CREATE TABLE `$kd_toko_baru` (
                            `id` int(5) NOT NULL AUTO_INCREMENT,
                            `rab` varchar(25) NOT NULL,
                            `kd_group` varchar(6) NOT NULL,
                            `plu` varchar(25) NOT NULL,
                            `keterangan` text NOT NULL,
                            `dpp` int(10) NOT NULL,
                            `ppn` int(10) NOT NULL,
                            `total` int(10) NOT NULL,
                            `inv_num` varchar(50) NOT NULL,
                            `flag_sarana` varchar(50) NOT NULL,
                            `flag_renov` text NOT NULL,
                            `flag_dat_pr` text NOT NULL,
                            PRIMARY KEY (`id`) USING BTREE
                        )
                    ");
                    $log[] = "✅ Tabel `$kd_toko_baru` berhasil dibuat.";
                } else {
                    $log[] = "⏩ Tabel `$kd_toko_baru` sudah ada. Dilewati.";
                }
            }

            return response()->json([
                'message' => implode("\n", $log),
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            Log::error('Gagal update site: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menyimpan data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function upload(Request $request)
    {
        $request->validate([
            'pdf_files' => 'required|array',
            'pdf_files.*' => 'file|mimes:pdf|max:5120' // maksimal 5MB per file
        ]);

        $uploadedFiles = [];

        foreach ($request->file('pdf_files') as $file) {
            $originalName = $file->getClientOriginalName();
            $filenameOnly = pathinfo($originalName, PATHINFO_FILENAME);
            
            $data = DB::table('lpd')
                ->where('kd_toko', $filenameOnly)
                ->where('status', '!=', 'Final')
                ->first();

            if (!$data) {
                continue;
            }

            $tgl = date('Y-m-d', strtotime($data->tgl_wrlb));
            $newFilename = $filenameOnly . '_' . $tgl . '.' . $file->getClientOriginalExtension();

            $savePath = 'C:/xampp/htdocs/AP_R4/File/lpd/' . $newFilename;
            $file->move('C:/xampp/htdocs/AP_R4/File/lpd', $newFilename);

            // Update kolom `pdf` pada tabel lpd
            DB::table('lpd')
                ->where('id', $data->id)
                ->update(['pdf' => $newFilename]);

            $uploadedFiles[] = $newFilename;
        }

        if (count($uploadedFiles) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada file yang berhasil diunggah. Pastikan file cocok dengan kd_toko dan status bukan Final.'
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => count($uploadedFiles) . ' file berhasil diunggah.',
            'files' => $uploadedFiles
        ]);
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
            'no_rab' => 'required|string',
            'label' => 'required|string',
            'kd_toko' => 'required|string',
            'tgl_wrlb' => 'required|date',
        ]);

        try {
            $file = $request->file('file');
            $noRab = $request->input('no_rab');
            $label = $request->input('label');
            $kdToko = $request->input('kd_toko');
            $tglWrlb = $request->input('tgl_wrlb');

            $labelMap = [
                'Proposal' => 'proposal',
                'RAB Rekap' => 'rab_rekap',
                'RAB Detail' => 'rab_detail',
                'Termin Investasi' => 'termin_invest',
            ];

            if (!array_key_exists($label, $labelMap)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Label tidak valid.',
                ], 400);
            }

            $folder = $labelMap[$label];
            $columnToUpdate = $labelMap[$label];

            $bulan = \Carbon\Carbon::parse($tglWrlb)->format('M');
            $tahun = \Carbon\Carbon::parse($tglWrlb)->format('Y');
            $prefix = Str::of($label)->replace(' ', '_')->title();
            $newFilename = "{$prefix}_{$kdToko}_{$bulan}_{$tahun}.pdf";

            // Simpan ke folder lokal tertentu
            $destinationPath = "C:/xampp/htdocs/AP_R4/File/{$folder}";
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0775, true);
            }

            $file->move($destinationPath, $newFilename);

            // Update kolom berdasarkan no_rab
            DB::table('berkas_lpd')
                ->updateOrInsert(
                    ['rab' => $noRab],
                    [$columnToUpdate => $newFilename]
                );

            return response()->json([
                'success' => true,
                'message' => 'File berhasil diunggah dan disimpan.',
                'filename' => $newFilename
            ]);
        } catch (\Exception $e) {
            \Log::error('Upload file error', [
                'error' => $e->getMessage(),
                'file_mime' => $request->file('file')?->getMimeType(),
                'file_size' => $request->file('file')?->getSize(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function uploadCSFinal(Request $request)
    {
        $request->validate([
            'no_rab' => 'required|string',
            'kd_toko' => 'required|string',
            'tgl_wrlb' => 'required|date',
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $file = $request->file('file');
        $kd_toko = $request->kd_toko;
        $tglWrlb  = \Carbon\Carbon::parse($request->tgl_wrlb)->format('M_Y');
        
        $filename     = 'CS_Final_' . $kd_toko . '_' . $tglWrlb . '.pdf';
        $destination = 'C:/xampp/htdocs/AP_R4/File/clearencesheet/';

        try {
            $file->move($destination, $filename);

            DB::table('berkas_lpd')
                ->where('rab', $request->no_rab)
                ->update(['cs_final' => $filename]);

            return response()->json([
                'success' => true,
                'message' => 'File berhasil diupload dan disimpan.',
                'filename' => $filename
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal upload: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteFile(Request $request)
    {
        $request->validate([
            'no_rab' => 'required|string',
            'label' => 'required|string',
        ]);

        $labelMap = [
            'Proposal' => 'proposal',
            'RAB Rekap' => 'rab_rekap',
            'RAB Detail' => 'rab_detail',
            'Termin Investasi' => 'termin_invest',
        ];

        $label = $request->input('label');
        $noRab = $request->input('no_rab');

        if (!array_key_exists($label, $labelMap)) {
            return response()->json([
                'success' => false,
                'message' => 'Label tidak valid.',
            ], 400);
        }

        $column = $labelMap[$label];
        $folder = $labelMap[$label];

        try {
            $existing = DB::table('berkas_lpd')
                ->where('rab', $noRab)
                ->select($column)
                ->first();

            if (!$existing || !$existing->$column) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan di database.',
                ], 404);
            }

            $filename = $existing->$column;
            $filepath = "C:/xampp/htdocs/AP_R4/File/{$folder}/{$filename}";

            DB::table('berkas_lpd')
                ->where('rab', $noRab)
                ->update([
                    $column => null
                ]);

            if (file_exists($filepath)) {
                unlink($filepath);
            }

            return response()->json([
                'success' => true,
                'message' => "File berhasil dihapus: {$filename}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function GenerateClearenceSheet(Request $request)
    {
        $request->validate([
            'no_rab' => 'required|string',
            'kd_toko' => 'required|string',
            'tgl_wrlb' => 'required|date',
        ]);

        try {
            $noRab = $request->input('no_rab');
            $kdToko = $request->input('kd_toko');
            $tglWrlb = $request->input('tgl_wrlb');

            $bulanTahun = \Carbon\Carbon::parse($tglWrlb)->format('m_Y');
            $filename = "Clearencesheet_{$kdToko}_{$bulanTahun}.pdf";

            $destinationPath = "C:/xampp/htdocs/AP_R4/File/clearencesheet";
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0775, true);
            }

            $lpd = DB::table('lpd')
                ->leftJoin('modal', 'modal.rab', '=', 'lpd.no_rab')
                ->where('lpd.no_rab', $noRab)
                ->select('lpd.*', 'modal.*')
                ->first();


            $data = [
                'no_rab' => $lpd->no_rab,
                'kd_toko' => $kdToko,
                'tgl_wrlb' => $tglWrlb,
                'cabang'     => $lpd->cabang,
                'badan'     => $lpd->badan,
                'jns_toko'  => $lpd->jns_toko,
                'nama_toko'  => $lpd->nama_toko,
                'setor'  => $lpd->setor,
                'cad_dana'  => $lpd->cad_dana
            ];

            $pdf = Pdf::loadView('pdf.clearencesheet', compact('data'));
            $pdf->save($destinationPath . '/' . $filename);

            DB::table('lpd')
                ->where('no_rab', $noRab)
                ->update([
                    'status' => 'CS',
                    'keterangan' => 'Proses Clearencesheet Cabang (Email tgl ' . Carbon::now()->format('d/m/y') . ')',
                ]);
            DB::table('berkas_lpd')->updateOrInsert(
                ['rab' => $noRab],
                ['draft_cs' => $filename]
            );

            return response()->json([
                'success' => true,
                'message' => 'Clearencesheet berhasil dibuat.',
                'filename' => $filename,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat Clearencesheet: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function discardCS(Request $request)
    {
        $request->validate([
            'no_rab' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            $updated = DB::table('lpd')
                ->where('no_rab', $request->no_rab)
                ->update(['status' => 'NEW']);

            DB::table('berkas_lpd')
                ->where('rab', $request->no_rab)
                ->update(['draft_cs' => null]);

            DB::commit();

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Clearencesheet berhasil dibatalkan.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan atau sudah dalam status yang sama.'
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function finalisasi(Request $request)
    {
        $request->validate([
            'no_rab'       => 'required|string',
            'penyelesaian' => 'required|string',
            'tgl_final'    => 'required|date',
            'keterangan'   => 'required|string',
            'file'         => 'nullable|file|mimes:pdf|max:5120',
        ]);

        DB::beginTransaction();

        try {

            /*
            =========================================
            1. Ambil data LPD
            =========================================
            */
            $lpd = DB::table('lpd')
                ->where('no_rab', $request->no_rab)
                ->first();

            if (!$lpd) {
                throw new \Exception('Data LPD tidak ditemukan');
            }

            $fileName = null;

            /*
            =========================================
            2. HANDLE UPLOAD POT SURKAS
            =========================================
            */
            if (
                $request->penyelesaian === 'Pot Surkas' &&
                $request->hasFile('file')
            ) {
                $targetDir = 'C:/xampp/htdocs/AP_R4/File/ba_pot_surkas';

                if (!File::exists($targetDir)) {
                    File::makeDirectory($targetDir, 0755, true);
                }

                $tglWrlb = Carbon::parse($lpd->tgl_wrlb)->format('Ymd');

                $fileName = 'BA_Pot_Surkas_'
                    . $lpd->kd_toko
                    . '_'
                    . $tglWrlb
                    . '.pdf';

                $request->file('file')->move($targetDir, $fileName);
            }

            /*
            =========================================
            3. UPDATE TABEL LPD (TETAP)
            =========================================
            */
            $updated = DB::table('lpd')
                ->where('no_rab', $request->no_rab)
                ->update([
                    'status'        => 'Final',
                    'keterangan'    => 'Final',
                    'catatan_final' => $request->keterangan,
                    'tgl_final'     => Carbon::parse($request->tgl_final)->format('Y-m-d'),
                    'penyelesaian'  => $request->penyelesaian,
                ]);

            /*
            =========================================
            4. UPDATE TABEL BERKAS_LPD
            =========================================
            */
            if ($fileName) {
                DB::table('berkas_lpd')
                    ->updateOrInsert(
                        ['rab' => $request->no_rab],
                        ['pot_surkas' => $fileName]
                    );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'LPD berhasil difinalisasi.',
                'file'    => $fileName,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            \Log::error('FINALISASI LPD ERROR', [
                'no_rab'  => $request->no_rab,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat finalisasi LPD',
            ], 500);
        }
    }

    public function unfinalisasi(Request $request)
    {
        $request->validate([
            'no_rab' => 'required|string'
        ]);

        try {
            $updated = DB::table('lpd')
                ->where('no_rab', $request->no_rab)
                ->update(['status' => 'CS']);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'LPD berhasil di-unfinalisasi.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan atau sudah dalam status yang sama.'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function sendLPD(Request $request)
    {
        $rab = $request->input('rab');

        $lpd = DB::table('lpd')->where('no_rab', $rab)->first();
        
        if (!$lpd) {
            return response()->json(['message' => 'Data LPD tidak ditemukan'], 404);
        }
        
        $cabang = $lpd->cabang ?? '';

        try {
            $toRecipients = DB::table('mail')
                ->where('untuk', 'LPD')
                ->where('sub', 'TO')
                ->where('cabang', $cabang)
                ->pluck('mail')
                ->toArray();

            $ccRecipients = DB::table('mail')
                ->where('untuk', 'LPD')
                ->where('sub', 'CC')
                ->where('cabang', $cabang)
                ->pluck('mail')
                ->toArray();

            $emailData = [
                'to' => $toRecipients,
                'cc' => $ccRecipients,
                'subject' => "Perhitungan LPD " . $lpd->nama_toko . " " . $lpd->kd_toko . " (RAB No: " . $rab . ")",
                'body' => "
                    Yth - Tim Finance & Mkt Frc Cabang {$cabang} <br><br>
                    Dh,<br>
                    Berikut saya kirimkan Perhitungan LPD " . $lpd->nama_toko . " " . $lpd->kd_toko . " RAB No: " . $rab . " (See Attach).<br><br>
                    Atas perhatian dan kerjasama baiknya saya ucapkan terima kasih.<br>
                    Hs,<br><br><br>
                    <b>Nur Syahid</b><br>
                    Finance AP RGN4",
                'attachments' => []
            ];

            $basePath = "C:/xampp/htdocs/AP_R4/File/LPD/";
            $lampiran1 = $basePath . $lpd->pdf;
            $lampiran2 = $basePath . $lpd->excel;

            if (file_exists($lampiran1)) $emailData['attachments'][] = $lampiran1;
            if (file_exists($lampiran2)) $emailData['attachments'][] = $lampiran2;

            $queueFile = storage_path("app/email_queue/lpd_" . uniqid() . ".json");
            file_put_contents($queueFile, json_encode($emailData, JSON_PRETTY_PRINT));

            $scriptPath = base_path('app/email/send_outlook.php');

            $output = [];
            $returnCode = 0;

            $phpPath = 'C:\\xampp\\php\\php.exe';
            exec(
                "\"$phpPath\" \"$scriptPath\" \"$queueFile\"",
                $output,
                $returnCode
            );

            return response()->json(['message' => 'LPD ' . $lpd->nama_toko . ' ' . $lpd->kd_toko . ' Berhasil dikirimkan']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menyiapkan email: ' . $e->getMessage()], 500);
        }
    }

    public function sendLPDFAD(Request $request)
    {
        $rab = $request->input('rab');

        $lpd = DB::table('lpd')->where('no_rab', $rab)->first();
        if (!$lpd) {
            return response()->json(['message' => 'Data LPD tidak ditemukan'], 404);
        }

        $cabang = $lpd->cabang ?? '';

        try {
            $berkas = DB::table('berkas_lpd')->where('rab', $rab)->first();
            $site = strtolower($lpd->kd_toko);
            $lampiranRenovasi = [];

            if (Schema::hasTable($site)) {
                $lampiranRenovasi = DB::table($site)
                    ->select('flag_renov')
                    ->where('rab', $rab)
                    ->where('kd_group', '030006')
                    ->where('inv_num', 'LIKE', '%BAPJ%')
                    ->distinct()
                    ->pluck('flag_renov')
                    ->filter()
                    ->toArray();
            }

            $toRecipients = DB::table('mail')
                ->where('untuk', 'CS')
                ->where('sub', 'TO')
                ->where('cabang', $cabang)
                ->pluck('mail')
                ->toArray();

            $ccRecipients = DB::table('mail')
                ->where('untuk', 'CS')
                ->where('sub', 'CC')
                ->where('cabang', $cabang)
                ->pluck('mail')
                ->toArray();

            $basePath = "C:/xampp/htdocs/AP_R4/File/";
            $attachments = [];

            // Lampiran utama
            if (!empty($lpd->pdf) && file_exists($basePath . "LPD/" . $lpd->pdf)) {
                $attachments[] = $basePath . "LPD/" . $lpd->pdf;
            }

            if (!empty($lpd->excel) && file_exists($basePath . "LPD/" . $lpd->excel)) {
                $attachments[] = $basePath . "LPD/" . $lpd->excel;
            }

            // Lampiran dari berkas_lpd
            if ($berkas) {
                $fields = [
                    'rab_rekap'        => 'rab_rekap',
                    'rab_detail'       => 'rab_detail',
                    'termin_invest'    => 'termin_invest',
                    'proposal'         => 'proposal',
                    'item_tdk_realisasi' => 'BA_Tidak_Realisasi',
                ];

                foreach ($fields as $field => $folder) {
                    if (!empty($berkas->$field) && file_exists($basePath . $folder . '/' . $berkas->$field)) {
                        $attachments[] = $basePath . $folder . '/' . $berkas->$field;
                    }
                }
            }

            // Lampiran renovasi jika ada
            foreach ($lampiranRenovasi as $renov) {
                $fileRenov = $basePath . "lpd_prj/" . $renov;
                if (file_exists($fileRenov)) {
                    $attachments[] = $fileRenov;
                }
            }

            // Siapkan emailData
            $emailData = [
                'to' => $toRecipients,
                'cc' => $ccRecipients,
                'subject' => "Perhitungan LPD {$lpd->nama_toko} {$lpd->kd_toko} (RAB No: {$rab})",
                'body' => "
                    Yth - Tim FAD Region 4 Cabang {$cabang} <br><br>
                    Dh,<br>
                    Berikut saya kirimkan Perhitungan LPD {$lpd->nama_toko} {$lpd->kd_toko} RAB No: {$rab} (See Attach). Mohon bantuannya untuk kroscek.<br><br>
                    Atas perhatian dan kerjasama baiknya saya ucapkan terima kasih.<br>
                    Hs,<br><br><br>
                    <b>Nur Syahid</b><br>
                    Finance AP RGN4",
                'attachments' => $attachments
            ];

            // Simpan ke queue
            $queueFile = storage_path("app/email_queue/lpd_" . uniqid() . ".json");
            file_put_contents($queueFile, json_encode($emailData, JSON_PRETTY_PRINT));

            $scriptPath = base_path('app/email/send_outlook.php');

            $output = [];
            $returnCode = 0;

            $phpPath = 'C:\\xampp\\php\\php.exe';
            exec(
                "\"$phpPath\" \"$scriptPath\" \"$queueFile\"",
                $output,
                $returnCode
            );

            // Update keterangan
            DB::table('lpd')
                ->where('no_rab', $rab)
                ->update([
                    'keterangan' => DB::raw("CONCAT('Proses Clearencesheet Region (Email ', DATE_FORMAT(NOW(), '%d/%m/%y'), ')')")
                ]);

            return response()->json(['message' => 'LPD ' . $lpd->nama_toko . ' ' . $lpd->kd_toko . ' berhasil dikirim']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menyiapkan email: ' . $e->getMessage()], 500);
        }
    }

    public function confRenov(Request $request)
    {
        $rab = $request->input('rab');

        $lpd = DB::table('lpd')
            ->join('berkas_lpd', 'berkas_lpd.rab', '=', 'lpd.no_rab')
            ->leftJoin('lpd_realisasi_detail as lrd', 'lrd.rab', '=', 'lpd.no_rab')
            ->where('lpd.no_rab', $rab)
            ->select(
                'lpd.cabang',
                'lpd.kd_toko',
                'lpd.nama_toko',
                'lpd.jns_toko',
                'berkas_lpd.rab_detail',
                'berkas_lpd.rab_rekap',
                'lrd.rab_fg',
                'lrd.rab_kanopi',
                'lrd.rab_ins_ac',
                'lrd.rab_teralis',
                'lrd.rab_halaman',
                'lrd.rab_policarbonate',
                'lrd.rab_listrik',
                'lrd.rab_aluminium_kaca',
                'lrd.rab_signage',
                'lrd.rab_sipil',
                'lrd.rab_interior'
            )
            ->first();

        if (!$lpd) {
            return response()->json(['message' => 'Data LPD tidak ditemukan'], 404);
        }

        try {
            $toRecipients = DB::table('mail')->where('untuk', 'Confirm_Renov')->where('sub', 'TO')->pluck('mail')->toArray();
            $ccRecipients = DB::table('mail')->where('untuk', 'Confirm_Renov')->where('sub', 'CC')->pluck('mail')->toArray();

            // definisi baris tabel
            $rows = [
                ["Pekerjaan Folding Gate (0216030012)", $lpd->rab_fg ?? 0],
                ["Pekerjaan Kanopi (PJ00448)", $lpd->rab_kanopi ?? 0],
                ["Pekerjaan Instalasi AC (0216030016)", $lpd->rab_ins_ac ?? 0],
                ["Pekerjaan Teralis (0216030014)", $lpd->rab_teralis ?? 0],
                ["Pekerjaan Halaman (0216030010)", $lpd->rab_halaman ?? 0],
                ["Pekerjaan Polycarbonat (0216030013)", $lpd->rab_policarbonate ?? 0],
                ["Pekerjaan Listrik (0216030015)", $lpd->rab_listrik ?? 0],
                ["Pekerjaan Aluminium & Kaca (0216030011)", $lpd->rab_aluminium_kaca ?? 0],
                ["Pekerjaan Signage (0211040090)", $lpd->rab_signage ?? 0],
                ["Pekerjaan Interior & Eksterior (PJ00089)", $lpd->rab_interior ?? 0],
                ["Pekerjaan Sipil (0216030008)", $lpd->rab_sipil ?? 0],
            ];

            $tableHtml = '<table border="1" cellspacing="0" cellpadding="6" style="border-collapse: collapse; width:100%; font-family: Arial, sans-serif; font-size: 12px;">
                <thead style="background:#cfe2ff; color:#084298;">
                    <tr>
                        <th align="left">Keterangan</th>
                        <th align="center">Estimasi</th>
                        <th align="center">Rencana Realisasi</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($rows as $row) {
                $tableHtml .= '<tr>
                    <td>'.$row[0].'</td>
                    <td align="right">'.number_format($row[1],0,',','.').'</td>
                    <td align="center"></td>
                </tr>';
            }

            $tableHtml .= '</tbody></table>';

            $basePath1 = "C:/xampp/htdocs/AP_R4/File/rab_detail/";
            $basePath2 = "C:/xampp/htdocs/AP_R4/File/rab_rekap/";
            $lampiran1 = $basePath1 . $lpd->rab_detail;
            $lampiran2 = $basePath2 . $lpd->rab_rekap;

            if (!$lpd->rab_detail || !$lpd->rab_rekap) {
                return response()->json([
                    'message' => 'Lampiran belum diunggah. Mohon unggah rab_detail dan rab_rekap.'
                ], 422);
            }

            // ✅ cek keberadaan file
            if (!file_exists($lampiran1) || !file_exists($lampiran2)) {
                return response()->json([
                    'message' => 'Lampiran RAB tidak ditemukan. Pastikan file rab_detail dan rab_rekap tersedia.'
                ], 422);
            }

            $emailData = [
                'to' => $toRecipients,
                'cc' => $ccRecipients,
                'subject' => "Konfirmasi Rencana Realisasi Renovasi Fisik " . $lpd->nama_toko . " " . $lpd->kd_toko . " (RAB No : " . $rab . ")",
                'body' => "
                    Yth - Tim Finance Cabang ". $lpd->cabang ."<br><br>
                    Dh,<br>
                    Mohon bantuannya untuk dikonfirmasikan dengan tim project/ACL atas rencana renovasi fisik yang akan dilakukan pada toko " . $lpd->jns_toko . " ". $lpd->nama_toko . " " . $lpd->kd_toko . " (RAB No : " . $rab . "). Dari estimasi berikut mana saja yang akan dilakukan pekerjaan.<br>
                    $tableHtml
                    <i style='font-size:10px;'>Note : Isi Rencana Realisasi dengan 'Y' jika akan ada pekerjaan dan 'N' jika tidak ada pekerjaan.</i>
                    <br><br>Atas perhatian dan kerjasama baiknya saya ucapkan terima kasih.<br>
                    Hs,<br><br><br>
                    <b>Nur Syahid</b><br>
                    Finance AP RGN4",
                'attachments' => [$lampiran1, $lampiran2]
            ];

            $queueFile = storage_path("app/email_queue/lpd_" . uniqid() . ".json");
            file_put_contents($queueFile, json_encode($emailData, JSON_PRETTY_PRINT));

            $scriptPath = base_path('app/email/send_outlook.php');

            $output = [];
            $returnCode = 0;

            $phpPath = 'C:\\xampp\\php\\php.exe';
            exec(
                "\"$phpPath\" \"$scriptPath\" \"$queueFile\"",
                $output,
                $returnCode
            );

            return response()->json(['message' => 'Konfirmasi Renovasi Fisik ' . $lpd->jns_toko . ' ' . $lpd->nama_toko . ' ' . $lpd->kd_toko . ' Berhasil dikirimkan']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menyiapkan email: ' . $e->getMessage()], 500);
        }
    }

    public function invunmatch(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json(['message' => 'Cabang is required'], 400);
        }

        // Ambil data dari tabel lpd berdasarkan cabang dan status != 'Final'
        $lpds = DB::table('lpd')
            ->select('kd_toko', 'no_rab', 'nama_toko', 'jns_toko', 'tgl_wrlb')
            ->where('cabang', $cabang)
            ->where('status', '!=', 'Final')
            ->get();

        $results = [];

        foreach ($lpds as $lpd) {
            // Ambil data dari tabel kd_toko (nama tabel dinamis) berdasarkan no_rab
            $kdTokoTable = $lpd->kd_toko;

            if (!Schema::hasTable($kdTokoTable)) {
                continue;
            }

            $data = DB::table($kdTokoTable)
                ->where('rab', $lpd->no_rab)
                ->where(function ($query) {
                    $query->whereNull('flag_dat_pr')
                        ->orWhere('flag_dat_pr', '');
                })
                ->get();

            foreach ($data as $item) {
                $results[] = [
                    ...get_object_vars($item),
                    'kd_toko'    => $lpd->kd_toko,
                    'no_rab'     => $lpd->no_rab,
                    'nama_toko'  => $lpd->nama_toko,
                    'jns_toko'   => $lpd->jns_toko,
                    'tgl_wrlb'   => $lpd->tgl_wrlb,
                ];
            }
        }

        return response()->json(['data' => $results]);
    }

    public function invunmatchsarana(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json(['message' => 'Cabang is required'], 400);
        }

        $lpds = DB::table('lpd')
            ->select('kd_toko', 'no_rab', 'nama_toko', 'status', 'jns_toko', 'tgl_wrlb')
            ->where('cabang', $cabang)
            ->where('status', '!=', 'Final')
            ->get();

        $results = [];

        foreach ($lpds as $lpd) {
            // Ambil data dari tabel kd_toko (nama tabel dinamis) berdasarkan no_rab
            $kdTokoTable = $lpd->kd_toko;

            if (!Schema::hasTable($kdTokoTable)) {
                continue;
            }

            $data = DB::table($kdTokoTable)
                ->where('rab', $lpd->no_rab)
                ->where('kd_group', '030008')
                ->where(function ($query) {
                    $query->whereNull('flag_sarana')
                        ->orWhere('flag_sarana', '');
                })
                ->get();

            foreach ($data as $item) {
                $results[] = [
                    ...get_object_vars($item),
                    'kd_toko'    => $lpd->kd_toko,
                    'no_rab'     => $lpd->no_rab,
                    'nama_toko'  => $lpd->nama_toko,
                    'jns_toko'   => $lpd->jns_toko,
                    'status'   => $lpd->status,
                    'tgl_wrlb'   => $lpd->tgl_wrlb,
                ];
            }
        }

        return response()->json(['data' => $results]);
    }

    public function lpdModalSync(Request $request)
    {
        $rows = $request->all();

        if (!is_array($rows) || empty($rows)) {
            return response()->json(['error' => 'Data kosong'], 400);
        }

        $noRAB = $rows[0]['noRAB'] ?? null;
        if (!$noRAB) {
            return response()->json(['error' => 'No RAB tidak ditemukan di data'], 400);
        }

        $lpd = DB::table('lpd')->where('no_rab', $noRAB)->first();
        if (!$lpd) {
            return response()->json([
                'error' => "No RAB {$noRAB} tidak ditemukan di tabel LPD"
            ], 404);
        }

        if (strtoupper($lpd->status) !== "NEW") {
            return response()->json([
                'error' => 'Modal sudah tidak bisa diupdate karena status LPD CS/Final!'
            ], 400);
        }
        
        foreach ($rows as $row) {
            $noBBT   = $row['noBBT'] ?? null;
            $tglBBT  = $row['tglBBT'] ?? null;
            $nilai   = $row['nilaiNum'] ?? 0;

            if (!$noBBT) {
                continue;
            }

            $exists = DB::table('modal_detail')
                ->where('rab', $noRAB)
                ->where('bbt', $noBBT)
                ->exists();

            if (!$exists) {
                $keterangan = 'Setor';
                if (in_array($lpd->jns_toko, ['NS', 'UP', 'RE'])) {
                    $keterangan = 'Setor';
                } elseif ($lpd->jns_toko === 'PPJ') {
                    if (in_array($nilai, [3000000, 30000000, 33000000, 36000000])) {
                        $keterangan = 'Cadangan';
                    } else {
                        $keterangan = 'Setor';
                    }
                }

                DB::table('modal_detail')->insert([
                    'rab'        => $noRAB,
                    'bbt'        => $noBBT,
                    'tgl_bbt'    => $tglBBT,
                    'nilai'      => $nilai,
                    'keterangan' => $keterangan,
                ]);
            }
        }

        if ($lpd->jns_toko !== 'PPJ') {
            $totalSetor = DB::table('modal_detail')
                ->where('rab', $noRAB)
                ->sum('nilai');

            DB::table('modal')
                ->where('rab', $noRAB)
                ->update(['setor' => $totalSetor]);
        } else {
            $totalSetor = DB::table('modal_detail')
                ->where('rab', $noRAB)
                ->where('keterangan', 'Setor')
                ->sum('nilai');

            $totalCadangan = DB::table('modal_detail')
                ->where('rab', $noRAB)
                ->where('keterangan', 'Cadangan')
                ->sum('nilai');

            DB::table('modal')
                ->where('rab', $noRAB)
                ->update([
                    'setor'     => $totalSetor,
                    'cad_dana'  => $totalCadangan,
                ]);
        }

        $modal = DB::table('modal')->where('rab', $noRAB)->first();
        if ($modal) {
            if (strtoupper($lpd->jns_toko) !== 'PPJ') {
                // --- Untuk toko selain PPJ ---
                $selisih = ($lpd->rab_final ?? 0) - (($modal->setor ?? 0) + ($modal->pek_by_frcsee ?? 0) + ($modal->sewa_by_frcsee ?? 0) + ($modal->sewa_at ?? 0));

                if ($selisih > 100) {
                    $keterangan = "Modal Kurang Rp " . number_format($selisih, 0, ',', '.');
                } else {
                    $keterangan = "Clear";
                }
            } else {
                // --- Untuk toko jenis PPJ ---
                $cadDana = $modal->cad_dana ?? 0;

                if ($cadDana != 36000000) {
                    $selisih = 36000000 - $cadDana;
                    if ($selisih > 0) {
                        $keterangan = "Cadangan Dana Kurang Rp " . number_format($selisih, 0, ',', '.');
                    } else {
                        $keterangan = "Clear";
                    }
                } else {
                    // Jika cad_dana sudah 36.000.000, terapkan aturan sama seperti non-PPJ
                    $selisih = ($lpd->rab_final ?? 0) - (($modal->setor ?? 0) + ($modal->pek_by_frcsee ?? 0) + ($modal->sewa_by_frcsee ?? 0));
                    if ($selisih > 100) {
                        $keterangan = "Modal Kurang Rp " . number_format($selisih, 0, ',', '.');
                    } else {
                        $keterangan = "Clear";
                    }
                }
            }

            DB::table('modal')
                ->where('rab', $noRAB)
                ->update(['keterangan' => $keterangan]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil disinkronkan'
        ]);
    }

    public function updateKeterangan(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer',
                'keterangan' => 'required|string',
            ]);

            $updated = DB::table('modal')
                ->where('id', $validated['id'])
                ->update([
                    'keterangan' => $validated['keterangan']
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Keterangan berhasil diupdate'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan atau tidak ada perubahan'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateKeteranganRab(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer',
                'keterangan' => 'nullable|string',
            ]);

            $updated = DB::table('lpd_realisasi_detail')
                ->where('id', $validated['id'])
                ->update([
                    'keterangan' => $validated['keterangan']
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Keterangan berhasil diupdate'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan atau tidak ada perubahan'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateFlagReal(Request $request)
    {
        try {
            $request->validate([
                'no_rab' => 'required|string',
                'flags'  => 'required|array', // menerima banyak field
            ]);

            $no_rab = $request->input('no_rab');
            $flags  = $request->input('flags');

            // Daftar field yang diizinkan diupdate
            $allowedFields = [
                'flag_realisasi_fg',
                'flag_realisasi_kanopi',
                'flag_realisasi_ins_ac',
                'flag_realisasi_teralis',
                'flag_realisasi_halaman',
                'flag_realisasi_policarbonate',
                'flag_realisasi_listrik',
                'flag_realisasi_aluminium_kaca',
                'flag_realisasi_signage',
                'flag_realisasi_interior',
                'flag_realisasi_sipil',
                'flag_realisasi_urugan',
                'flag_realisasi_lift',
            ];

            // Filter hanya field yang valid
            $filteredFlags = [];
            foreach ($flags as $field => $value) {
                $field = trim(strtolower($field));
                if (in_array($field, $allowedFields)) {
                    // pastikan nilai hanya Y, N, atau null
                    if (in_array($value, ['Y', 'N', null, ''])) {
                        $filteredFlags[$field] = $value ?: null;
                    }
                }
            }

            if (empty($filteredFlags)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada field valid untuk diupdate.',
                ], 400);
            }

            // Update ke database
            $updated = DB::table('lpd_realisasi_detail')
                ->where('rab', $no_rab)
                ->update($filteredFlags);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data realisasi berhasil diperbarui.',
                    'updated_fields' => $filteredFlags,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada perubahan atau data tidak ditemukan.',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function lpdEstSync(Request $request)
    {
        $noRab = $request->input('no_rab');
        $estimasi = $request->input('estimasi', []);

        if (!$noRab) {
            return response()->json(['message' => 'No RAB tidak boleh kosong'], 400);
        }

        // 🔍 Cek status LPD
        $lpd = DB::table('lpd')->where('no_rab', $noRab)->first();

        if (!$lpd) {
            return response()->json(['message' => 'Data LPD tidak ditemukan'], 404);
        }

        if ($lpd->status !== "NEW") {
            return response()->json([
                'message' => 'Estimasi LPD sudah tidak bisa diedit, Status LPD CS/Final'
            ], 400);
        }

        // 🔑 Mapping kategori ke field
        $map = [
            "FRANCHISE FEE" => "rab_frc_fee",
            "BIAYA PROMOSI PEMBUKAAN" => "rab_promo",
            "BIAYA REKRUITMENT KARYAWAN" => "rab_rekrut_train",
            "BIAYA SEWA DAN PPH" => "rab_sw_pph",
            "BIAYA JASA PIHAK KE 3" => "rab_jasa_pihak3",
            "PEKERJAAN HALAMAN" => "rab_halaman",
            "PEKERJAAN TERALIS" => "rab_teralis",
            "PEKERJAAN FOLDING GATE" => "rab_fg",
            "PEKERJAAN LISTRIK" => "rab_listrik",
            "PEKERJAAN ALUMUNIUM + KACA" => "rab_aluminium_kaca",
            "PEKERJAAN KANOPI" => "rab_kanopi",
            "PEKERJAAN INSTALASI UTK AC" => "rab_ins_ac",
            "PEKERJAAN SIPIL" => "rab_sipil",
            "PEKERJAAN URUGAN DAN" => "rab_urugan",
            "PEKERJAAN SIGNAGE" => "rab_signage",
            "PEKERJAAN INTERIOR DAN" => "rab_interior",
            "PEKERJAAN LIFT" => "rab_lift",
            "AC SPLIT" => "rab_prasarana",
            "SARANA UTILITAS" => "rab_prasarana",
            "TAMBAH DAYA DARIVA KEVA" => "rab_prasarana",
            "PASANG DAYA LISTRIK BARU" => "rab_prasarana",
            "PERALATAN ELEKTRONIK DAN" => "rab_peralatan",
        ];

        // 🔄 Susun data update & hitung total estimasi
        $updateData = [];
        $totalEstimasi = 0;

        foreach ($estimasi as $item) {
            $kategori = strtoupper($item['kategori'] ?? '');
            $nilai = (float) ($item['estimasi'] ?? 0);

            // ➕ total estimasi
            $totalEstimasi += $nilai;

            if (isset($map[$kategori])) {
                if (isset($updateData[$map[$kategori]])) {
                    $updateData[$map[$kategori]] += $nilai;
                } else {
                    $updateData[$map[$kategori]] = $nilai;
                }
            }
        }

        if (empty($updateData)) {
            return response()->json([
                'message' => 'Tidak ada data estimasi yang sesuai untuk diupdate'
            ], 400);
        }

        // 🔄 Update estimasi ke lpd_realisasi_detail
        DB::table('lpd_realisasi_detail')
            ->where('rab', $noRab)
            ->update($updateData);

        // ===============================
        // 🔍 CEK SELISIH RAB
        // ===============================
        $rabFinal = (float) ($lpd->rab_final ?? 0);
        $selisih = $rabFinal - $totalEstimasi;

        if ($selisih < -100 || $selisih > 100) {
            DB::table('lpd_realisasi_detail')
                ->where('rab', $noRab)
                ->update([
                    'keterangan' => 'RAB Selisih'
                ]);
        }

        return response()->json([
            'message' => 'Estimasi LPD berhasil disimpan',
            'total_estimasi' => $totalEstimasi,
            'rab_final' => $rabFinal,
            'selisih' => $selisih,
            'updated_fields' => $updateData
        ]);
    }

    public function uploadLPDPrj(Request $request)
    {
        try {
            \Log::info('UploadLPDPrj request:', $request->all());

            // validasi input
            $request->validate([
                'no_rab'   => 'required|string',
                'kd_toko'  => 'required|string',
                'wrlb'     => 'required|date',
                'ids'      => 'required',
                'file'     => 'required|file|mimes:pdf|max:10240',
            ]);

            $kd_toko = $request->kd_toko;
            $tgl_wrlb = \Carbon\Carbon::parse($request->wrlb)->format('Ymd');
            $datetime = now()->format('YmdHis');

            $filename = "{$kd_toko}_{$tgl_wrlb}_{$datetime}.pdf";

            // lokasi folder tujuan
            $destinationPath = "C:/xampp/htdocs/AP_R4/File/lpd_prj";

            // buat folder jika belum ada
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            // pindahkan file ke folder tujuan
            $request->file('file')->move($destinationPath, $filename);

            $ids = json_decode($request->ids, true);

            if (empty($ids) || !is_array($ids)) {
                \Log::error('UploadLPDPrj: IDS tidak valid', ['ids' => $ids]);
                return response()->json([
                    'success' => false,
                    'message' => 'IDs tidak valid',
                ], 400);
            }

            // cek apakah tabel kd_toko ada
            if (!Schema::hasTable($kd_toko)) {
                \Log::error("UploadLPDPrj: Tabel {$kd_toko} tidak ditemukan");
                return response()->json([
                    'success' => false,
                    'message' => "Tabel {$kd_toko} tidak ditemukan",
                ], 400);
            }

            // update tabel dinamis
            DB::table($kd_toko)
                ->whereIn('id', $ids)
                ->update([
                    'flag_renov' => $filename,
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Upload berhasil',
                'file'    => $filename,
                'path'    => $destinationPath . '/' . $filename,
            ]);
        } catch (\Exception $e) {
            \Log::error('UploadLPDPrj Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteClearanceSheet(Request $request)
    {
        $rab = $request->input('rab');

        if (!$rab) {
            return response()->json(['error' => 'No RAB tidak dikirim'], 400);
        }

        // Ambil record
        $berkas = DB::table('berkas_lpd')->where('rab', $rab)->first();
        if (!$berkas) {
            return response()->json(['error' => 'Data berkas tidak ditemukan'], 404);
        }

        // Ambil nama file cs_final
        $filename = $berkas->cs_final;

        if ($filename) {

            // Path lengkap folder tempat file disimpan
            $basePath = 'C:\\xampp\\htdocs\\AP_R4\\File\\clearencesheet\\';

            // Gabungkan folder + nama file
            $fullPath = $basePath . $filename;

            // Hapus file jika ada
            if (file_exists($fullPath)) {
                try {
                    unlink($fullPath);
                } catch (\Exception $e) {
                    // File gagal dihapus tetapi tetap lanjut proses database
                    return response()->json([
                        'error' => 'Gagal menghapus file dari server: ' . $e->getMessage()
                    ], 500);
                }
            }
        }

        // Kosongkan field cs_final
        DB::table('berkas_lpd')
            ->where('rab', $rab)
            ->update(['cs_final' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Clearancesheet Final berhasil dihapus'
        ]);
    }

}
