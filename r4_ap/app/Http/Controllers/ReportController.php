<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function generateCS(Request $request)
    {
        $data = [
            'no_rab' => '03G009/0123456/01/24',
            'nama_toko' => 'Contoh Toko'
        ];

        $pdf = Pdf::loadView('pdf.laporan', ['data' => (object) $data]);

        $folderPath = 'D:/xampp/htdocs/AP_R4/File/clearencesheet';

        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0775, true);
        }

        $filename = 'Laporan_' . now()->format('Ymd_His') . '.pdf';
        $fullPath = $folderPath . '/' . $filename;

        $pdf->save($fullPath);

        return response()->json([
            'success' => true,
            'message' => 'PDF berhasil dibuat',
            'filename' => $filename,
            'path' => $fullPath
        ]);
    }
}
