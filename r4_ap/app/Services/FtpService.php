<?php

namespace App\Services;

use App\Models\FtpRoot;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class FtpService
{
    public const GL = 'ftpreg4share';
    public const AP = 'ftpreg4ap';

    /**
     * Root path FTP aktif
     */
    protected string $rootPath = '';

    /**
     * FTP Connection
     */
    protected $conn;

    /**
     * Konfigurasi FTP
     */
    protected $config;

    public function __construct(string $user)
    {
        $this->config = DB::table('ftp')
            ->where('user', $user)
            ->first();

        if (!$this->config) {
            throw new Exception("FTP {$user} tidak aktif.");
        }
    }

    /**
     * Menentukan root folder berdasarkan cabang
     */
    public function setCabang(string $cabang): void
    {
        $ftpRoot = FtpRoot::where('cabang', $cabang)->first();

        if (!$ftpRoot) {
            throw new Exception("Root FTP cabang '{$cabang}' tidak ditemukan.");
        }

        $this->rootPath = rtrim($ftpRoot->root, '/');
    }

    /**
     * Mendapatkan root folder
     */
    public function getRootPath(): string
    {
        if (empty($this->rootPath)) {
            throw new Exception("Root FTP belum ditentukan.");
        }

        return $this->rootPath;
    }

    /**
     * Connect FTP
     */
    public function connect(): bool
    {
        $this->conn = ftp_connect($this->config->server);

        if (!$this->conn) {
            throw new Exception("Tidak dapat connect FTP.");
        }

        if (!ftp_login(
            $this->conn,
            $this->config->user,
            $this->config->pass
        )) {

            ftp_close($this->conn);

            throw new Exception("Login FTP gagal.");
        }

        ftp_pasv($this->conn, true);

        return true;
    }

    /**
     * List isi folder
     */
    public function listFiles(string $path): array
    {
        $rows = ftp_rawlist($this->conn, $path);

        if ($rows === false) {
            throw new Exception("Tidak dapat membaca folder {$path}");
        }

        $result = [];

        foreach ($rows as $row) {

            $parts = preg_split('/\s+/', trim($row), 9);

            if (count($parts) < 9) {
                continue;
            }

            $permission = $parts[0];
            $size       = $parts[4];
            $month      = $parts[5];
            $day        = $parts[6];
            $timeOrYear = $parts[7];
            $name       = $parts[8];

            if ($name === '.' || $name === '..') {
                continue;
            }

            $isFolder = str_starts_with($permission, 'd');

            $result[] = [

                'name' => $name,

                'path' => rtrim($path, '/') . '/' . $name,

                'type' => $isFolder
                    ? 'folder'
                    : 'file',

                'size' => $isFolder
                    ? null
                    : (int) $size,

                'modified' => "{$month} {$day} {$timeOrYear}"

            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Folder di atas, kemudian urut nama
        |--------------------------------------------------------------------------
        */

        usort($result, function ($a, $b) {

            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'folder' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    /**
     * Download file FTP
     */
    public function download(string $remoteFile, string $localFile): bool
    {
        return ftp_get(
            $this->conn,
            $localFile,
            $remoteFile,
            FTP_BINARY
        );
    }

    /**
     * Tutup koneksi FTP
     */
    public function close(): void
    {
        if ($this->conn) {
            ftp_close($this->conn);
        }
    }
}