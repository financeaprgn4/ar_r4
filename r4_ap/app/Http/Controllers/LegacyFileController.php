<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;

class LegacyFileController extends Controller
{
    public function showFile($folder, $filename)
    {
        $allowedFolders = [
            'lpd' => 'C:/xampp/htdocs/AP_R4/File/lpd/',
            'proposal' => 'C:/xampp/htdocs/AP_R4/File/proposal/',
            'rab_rekap' => 'C:/xampp/htdocs/AP_R4/File/rab_rekap/',
            'rab_detail' => 'C:/xampp/htdocs/AP_R4/File/rab_detail/',
            'termin_invest' => 'C:/xampp/htdocs/AP_R4/File/termin_invest/',
            'draft_cs' => 'C:/xampp/htdocs/AP_R4/File/clearencesheet/',
            'ba_rab' => 'C:/xampp/htdocs/AP_R4/File/BA_RAB/',
            'clearencesheet' => 'C:/xampp/htdocs/AP_R4/File/clearencesheet/',
            'lpd_prj' => 'C:/xampp/htdocs/AP_R4/File/lpd_prj/',
            'item_tdk_realisasi' => 'C:/xampp/htdocs/AP_R4/File/BA_Tidak_Realisasi/',
            'pot_surkas' => 'C:/xampp/htdocs/AP_R4/File/ba_pot_surkas/',
        ];

        if (!array_key_exists($folder, $allowedFolders)) {
            abort(403, 'Unauthorized folder access');
        }

        $allowedExtensions = ['pdf', 'xls', 'xlsx', 'csv'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            abort(403, 'Invalid file type');
        }

        $path = $allowedFolders[$folder] . $filename;

        if (!File::exists($path)) {
            abort(404, 'File not found');
        }

        $contentTypes = [
            'pdf' => 'application/pdf',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
        ];

        return Response::file($path, [
            'Content-Type' => $contentTypes[$extension] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function statement($cabang, $filename)
    {
        $path = "C:/xampp/htdocs/AP_R4/File/mutasi/{$cabang}/{$filename}";

        if (!file_exists($path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return response()->download($path);
    }
    
}
