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
}
