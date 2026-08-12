<?php

namespace App\Http\Controllers;

use App\Services\FtpService;
use App\Services\GLImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FTPController extends Controller
{
    public function listFile(Request $request): JsonResponse
    {
        $ftp = null;

        try {

            $ftp = new FtpService(FtpService::GL);

            $ftp->connect();

            $cabang = $request->query('cabang');

            if (!$cabang) {
                throw new \Exception('Parameter cabang wajib diisi.');
            }

            // Tentukan root berdasarkan cabang
            $ftp->setCabang($cabang);

            $path = $request->query('path', $ftp->getRootPath());

            $files = $ftp->listFiles($path);

            return response()->json([
                'success' => true,
                'path'    => $path,
                'data'    => $files
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        } finally {

            $ftp?->close();

        }
    }

    public function GLimport(Request $request)
    {
        $request->validate([
            'cabang' => 'required|string',
            'file'   => 'required|string',
            'path'   => 'required|string',
        ]);

        $service = new GLImportService();

        return $service->import(
            $request->cabang,
            $request->file,
            $request->path
        );
    }
}