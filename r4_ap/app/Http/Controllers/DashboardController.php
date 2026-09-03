<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Lpd;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $cabang = $request->input('cabang');

        if (!$cabang) {
            return response()->json(['error' => 'Cabang tidak ditemukan'], 400);
        }

        $periodeAktif = DB::table('periode')
            ->where('kategori', 'LPD')
            ->where('status', 'Aktif')
            ->where('cabang', $cabang)
            ->first();

        if (!$periodeAktif || !isset($periodeAktif->end_date)) {
            return response()->json(['error' => 'Periode aktif tidak ditemukan'], 404);
        }

        $baseQuery = Lpd::where('status', '!=', 'final')->where('cabang', $cabang);
        $endDate = $periodeAktif->end_date;

        $total = $baseQuery->count();
        $ppj = (clone $baseQuery)->where('jns_toko', 'ppj')->count();
        $ns = (clone $baseQuery)->where('jns_toko', 'ns')->count();
        $up = (clone $baseQuery)->where('jns_toko', 'up')->count();

        $jatuhTempo = (clone $baseQuery)->whereDate('tgl_jt', '<=', $endDate)->count();
        $jatuhTempo_ns = (clone $baseQuery)->where('jns_toko', 'ns')->whereDate('tgl_jt', '<=', $endDate)->count();
        $jatuhTempo_ppj = (clone $baseQuery)->where('jns_toko', 'ppj')->whereDate('tgl_jt', '<=', $endDate)->count();
        $jatuhTempo_up = (clone $baseQuery)->where('jns_toko', 'up')->whereDate('tgl_jt', '<=', $endDate)->count();

        return response()->json([
            'total' => $total,
            'ppj' => $ppj,
            'ns' => $ns,
            'up' => $up,
            'jatuh_tempo' => $jatuhTempo,
            'jatuh_tempo_ns' => $jatuhTempo_ns,
            'jatuh_tempo_ppj' => $jatuhTempo_ppj,
            'jatuh_tempo_up' => $jatuhTempo_up,
        ]);
    }
}
