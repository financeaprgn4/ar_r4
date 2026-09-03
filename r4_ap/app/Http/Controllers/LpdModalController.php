<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LpdModalController extends Controller
{
    public function modal(Request $request)
    {
        $no_rab = $request->query('no_rab');

        if (!$no_rab) {
            return response()->json([
                'message' => 'Parameter no_rab wajib diisi.'
            ], 400);
        }

        $data = DB::table('lpd_realisasi_detail as d')
            ->join('lpd as l', 'l.no_rab', '=', 'd.rab')
            ->leftJoin('berkas_lpd as b', 'b.rab', '=', 'd.rab')
            ->where('d.rab', $no_rab)
            ->select(
                'd.*',
                'l.kd_toko',
                'l.nama_toko',
                'l.jns_toko',
                'l.tgl_wrlb',
                'l.tgl_jt',
                'l.status',
                'b.rab_rekap',
                'b.rab_detail',
                'b.termin_invest',
                'b.proposal'
            )
            ->orderBy('d.id')
            ->get();

        if (!$data) {
            return response()->json([
                'message' => 'Data tidak ditemukan untuk no_rab tersebut.'
            ], 404);
        }

        $modal_detail = DB::table('modal_detail')
            ->where('rab', $no_rab)
            ->get();
        
        $modal = DB::table('modal')
            ->where('rab', $no_rab)
            ->get();

        return response()->json([
            'data' => $data,
            'modal' => $modal,
            'modal_detail' => $modal_detail,
        ]);
    }
}
