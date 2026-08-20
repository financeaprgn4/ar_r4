<?php

namespace App\Http\Controllers;

use App\Services\FtpService;
use App\Services\GLImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class FTPController extends Controller
{
    public function testConnection($id)
    {
        $connection = null;

        try {

            /*
            |--------------------------------------------------------------------------
            | Ambil konfigurasi FTP
            |--------------------------------------------------------------------------
            */
            $ftp = DB::table('ftp')
                ->where('id', $id)
                ->first();

            if (!$ftp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data FTP tidak ditemukan.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | Validasi data
            |--------------------------------------------------------------------------
            */
            if (
                empty($ftp->server) ||
                empty($ftp->user) ||
                empty($ftp->pass)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Konfigurasi FTP belum lengkap.'
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | Port FTP
            |--------------------------------------------------------------------------
            */
            $port = 21;

            /*
            |--------------------------------------------------------------------------
            | Timeout koneksi
            |
            | 10 detik supaya halaman tidak menunggu terlalu lama
            |--------------------------------------------------------------------------
            */
            $timeout = 10;


            /*
            |--------------------------------------------------------------------------
            | Connect ke FTP server
            |--------------------------------------------------------------------------
            */
            $connection = @ftp_connect(
                $ftp->server,
                $port,
                $timeout
            );


            if (!$connection) {

                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat terhubung ke FTP server.',
                    'server' => $ftp->server,
                    'port' => $port
                ], 500);
            }


            /*
            |--------------------------------------------------------------------------
            | Login FTP
            |--------------------------------------------------------------------------
            */
            $login = @ftp_login(
                $connection,
                $ftp->user,
                $ftp->pass
            );


            if (!$login) {

                @ftp_close($connection);

                return response()->json([
                    'success' => false,
                    'message' => 'Server FTP dapat dihubungi, tetapi username atau password salah.',
                    'server' => $ftp->server,
                    'port' => $port
                ], 401);
            }


            /*
            |--------------------------------------------------------------------------
            | Aktifkan passive mode
            |--------------------------------------------------------------------------
            */
            @ftp_pasv($connection, true);


            /*
            |--------------------------------------------------------------------------
            | Cek direktori root
            |--------------------------------------------------------------------------
            */
            $currentDirectory = @ftp_pwd($connection);


            /*
            |--------------------------------------------------------------------------
            | Tutup koneksi
            |--------------------------------------------------------------------------
            */
            @ftp_close($connection);


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => 'Koneksi FTP berhasil.',
                'server' => $ftp->server,
                'port' => $port,
                'user' => $ftp->user,
                'directory' => $currentDirectory ?: '/'
            ], 200);


        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Pastikan koneksi ditutup
            |--------------------------------------------------------------------------
            */
            if ($connection) {
                @ftp_close($connection);
            }


            /*
            |--------------------------------------------------------------------------
            | Log error
            |--------------------------------------------------------------------------
            |
            | Password TIDAK dicatat ke log.
            |--------------------------------------------------------------------------
            */
            Log::error('FTP TEST CONNECTION ERROR', [
                'id' => $id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);


            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat melakukan koneksi FTP.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {

            $data = DB::table('ftp')
                ->select(
                    'id',
                    'server',
                    'user',
                    'pass'
                )
                ->orderBy('id', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data FTP berhasil diambil.',
                'data' => $data
            ], 200);

        } catch (\Throwable $e) {

            Log::error('FTP INDEX ERROR', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data FTP.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {

            $data = DB::table('ftp')
                ->where('id', $id)
                ->first();

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data FTP tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data FTP berhasil ditemukan.',
                'data' => $data
            ], 200);

        } catch (\Throwable $e) {

            Log::error('FTP SHOW ERROR', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail FTP.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {

            $server = trim((string) $request->input('server'));
            $user   = trim((string) $request->input('user'));
            $pass   = $request->input('pass');


            $validator = Validator::make(
                [
                    'server' => $server,
                    'user'   => $user,
                    'pass'   => $pass,
                ],
                [
                    'server' => [
                        'required',
                        'string',
                        'max:255'
                    ],

                    'user' => [
                        'required',
                        'string',
                        'max:255'
                    ],

                    'pass' => [
                        'required',
                        'string',
                        'max:255'
                    ],
                ],
                [
                    'server.required' => 'Server FTP wajib diisi.',
                    'user.required' => 'Username FTP wajib diisi.',
                    'pass.required' => 'Password FTP wajib diisi.',
                ]
            );


            if ($validator->fails()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | Cek apakah server + username sudah ada
            |--------------------------------------------------------------------------
            */
            $existing = DB::table('ftp')
                ->where('server', $server)
                ->where('user', $user)
                ->first();

            if ($existing) {

                return response()->json([
                    'success' => false,
                    'message' => 'FTP dengan server dan username tersebut sudah tersedia.'
                ], 409);
            }


            /*
            |--------------------------------------------------------------------------
            | Insert
            |--------------------------------------------------------------------------
            */
            $id = DB::table('ftp')->insertGetId([
                'server' => $server,
                'user'   => $user,
                'pass'   => $pass,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Ambil data baru
            |--------------------------------------------------------------------------
            */
            $data = DB::table('ftp')
                ->where('id', $id)
                ->first();


            return response()->json([
                'success' => true,
                'message' => 'Data FTP berhasil ditambahkan.',
                'data' => $data
            ], 201);

        } catch (\Throwable $e) {

            Log::error('FTP STORE ERROR', [
                'request' => $request->except('pass'),
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data FTP.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | Cari data berdasarkan ID
            |--------------------------------------------------------------------------
            */
            $existing = DB::table('ftp')
                ->where('id', $id)
                ->first();

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data FTP tidak ditemukan.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | Ambil input
            |--------------------------------------------------------------------------
            */
            $server = trim((string) $request->input('server'));
            $user   = trim((string) $request->input('user'));
            $pass   = $request->input('pass');


            /*
            |--------------------------------------------------------------------------
            | Validasi
            |--------------------------------------------------------------------------
            */
            $validator = Validator::make(
                [
                    'server' => $server,
                    'user'   => $user,
                    'pass'   => $pass,
                ],
                [
                    'server' => [
                        'required',
                        'string',
                        'max:255'
                    ],

                    'user' => [
                        'required',
                        'string',
                        'max:255'
                    ],

                    'pass' => [
                        'required',
                        'string',
                        'max:255'
                    ],
                ],
                [
                    'server.required' => 'Server FTP wajib diisi.',
                    'server.max' => 'Server FTP maksimal 255 karakter.',

                    'user.required' => 'Username FTP wajib diisi.',
                    'user.max' => 'Username FTP maksimal 255 karakter.',

                    'pass.required' => 'Password FTP wajib diisi.',
                    'pass.max' => 'Password FTP maksimal 255 karakter.',
                ]
            );


            if ($validator->fails()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | Cek duplikasi
            |--------------------------------------------------------------------------
            */
            $duplicate = DB::table('ftp')
                ->where('server', $server)
                ->where('user', $user)
                ->where('id', '!=', $id)
                ->first();

            if ($duplicate) {

                return response()->json([
                    'success' => false,
                    'message' => 'FTP dengan server dan username tersebut sudah tersedia.'
                ], 409);
            }


            /*
            |--------------------------------------------------------------------------
            | Update data
            |--------------------------------------------------------------------------
            */
            DB::table('ftp')
                ->where('id', $id)
                ->update([
                    'server' => $server,
                    'user'   => $user,
                    'pass'   => $pass,
                ]);


            /*
            |--------------------------------------------------------------------------
            | Ambil data terbaru
            |--------------------------------------------------------------------------
            */
            $data = DB::table('ftp')
                ->where('id', $id)
                ->first();


            return response()->json([
                'success' => true,
                'message' => 'Data FTP berhasil diperbarui.',
                'data' => $data
            ], 200);

        } catch (\Throwable $e) {

            Log::error('FTP UPDATE ERROR', [
                'id' => $id,

                /*
                * Jangan log password
                */
                'request' => $request->except('pass'),

                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data FTP.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {

            /*
             * Cari data
             */
            $existing = DB::table('ftp')
                ->where('id', $id)
                ->first();

            if (!$existing) {

                return response()->json([
                    'success' => false,
                    'message' => 'Data FTP tidak ditemukan.'
                ], 404);
            }


            /*
             * Hapus data
             */
            DB::table('ftp')
                ->where('id', $id)
                ->delete();


            return response()->json([
                'success' => true,
                'message' => 'Data FTP berhasil dihapus.'
            ], 200);

        } catch (\Throwable $e) {

            Log::error('FTP DELETE ERROR', [
                'id' => $id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data FTP.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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