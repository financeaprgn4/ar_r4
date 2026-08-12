<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class GLImportService
{
    protected FtpService $ftp;
    protected string $cabang;
    protected string $file;
    protected string $path;
    protected object $periode;
    protected string $type;
    protected string $tableGL;
    protected string $tableGLInt;
    protected string $tableRekap;
    protected string $segmentField;
    protected int $segmentIndex;

    protected function detectType(): void
    {
        $filename = strtoupper($this->file);

        if (str_contains($filename, '_FRC_')) {

            $this->type = 'FRC';

            $this->tableGL = 'gl_frc';

            $this->tableGLInt = 'gl_int_frc';

            $this->tableRekap = 'gl_rekap_frc';

            $this->segmentField = 'segment5';

            $this->segmentIndex = 8;
            return;
        }

        if (str_contains($filename, '_CBG_')) {

            $this->type = 'REG';

            $this->tableGL = 'gl';

            $this->tableGLInt = 'gl_int';

            $this->tableRekap = 'gl_rekap';

            $this->segmentField = 'segment6';

            $this->segmentIndex = 9;
            return;
        }

        throw new Exception("Jenis file tidak dikenali.");
    }

    public function import(
        string $cabang,
        string $file,
        string $path
    ): array
    {
        $this->cabang = $cabang;
        $this->file = $file;
        $this->path = $path;
        $this->detectType();

        $this->periode = DB::table('periode')
            ->where('kategori', 'Mutasi')
            ->where('cabang', $cabang)
            ->where('status', 'Aktif')
            ->first();
    
        if (!$this->periode) {
            throw new Exception("Periode aktif tidak ditemukan.");
        }
    
        $this->ftp = new FtpService(FtpService::GL);
    
        $this->ftp->connect();
    
        try {
    
            $txt = $this->downloadAndExtract();
    
            $result = $this->parse($txt);
    
            DB::transaction(function () use ($result) {
    
                $this->clearGLInt();
    
                $this->insertGLInt($result['rows']);
    
                $this->syncGL();
    
                $this->generateRekap();
    
                $this->clearGLInt();
    
            });
    
            return [
    
                'success' => true,
    
                'message' => count($result['rows']) . " transaksi berhasil diimport."
    
            ];
    
        } finally {
    
            $this->ftp->close();
    
        }
    }

    /**
     * Download ZIP kemudian extract TXT
     */
    protected function downloadAndExtract(): string
    {
        /*
        |--------------------------------------------------------------------------
        | Download ZIP ke file temporary
        |--------------------------------------------------------------------------
        */

        $tempZip = tempnam(sys_get_temp_dir(), 'gl_');

        if (!$this->ftp->download(
            $this->path,
            $tempZip
        )) {

            throw new Exception("Download FTP gagal.");

        }

        /*
        |--------------------------------------------------------------------------
        | Nama TXT di dalam ZIP
        |--------------------------------------------------------------------------
        */

        $txtName = preg_replace(
            '/\.[^.]+$/',
            '.TXT',
            $this->file
        );

        /*
        |--------------------------------------------------------------------------
        | Buka ZIP
        |--------------------------------------------------------------------------
        */

        $zip = new ZipArchive();

        if ($zip->open($tempZip) !== true) {

            unlink($tempZip);

            throw new Exception("ZIP tidak dapat dibuka.");

        }

        /*
        |--------------------------------------------------------------------------
        | Ambil isi TXT
        |--------------------------------------------------------------------------
        */

        $content = $zip->getFromName($txtName);

        $zip->close();

        unlink($tempZip);

        if ($content === false) {

            throw new Exception(
                "File {$txtName} tidak ditemukan di ZIP."
            );

        }

        return $content;
    }

    /**
     * Parsing TXT menjadi array
     */
    protected function parse(string $content): array
    {
        /*
        |--------------------------------------------------------------------------
        | Ambil daftar akun yang valid berdasarkan cabang & jenis GL
        |--------------------------------------------------------------------------
        */

        $validAccounts = DB::table('bank')
            ->where('cabang', $this->cabang)
            ->when(
                $this->type === 'REG',
                fn ($q) => $q->where('site', 'REG'),
                fn ($q) => $q->where('site', '<>', 'REG')
            )
            ->distinct()
            ->pluck('akun')
            ->mapWithKeys(fn ($akun) => [trim($akun) => true])
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Pecah file menjadi baris
        |--------------------------------------------------------------------------
        */

        $lines = preg_split("/\r\n|\n|\r/", $content);

        /*
        |--------------------------------------------------------------------------
        | Hapus header
        |--------------------------------------------------------------------------
        */

        array_shift($lines);

        $rows = [];

        foreach ($lines as $line) {

            if ($line === '') {
                continue;
            }

            $col = explode('|', $line);

            if (count($col) < 27) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Skip IDM-SJU
            |--------------------------------------------------------------------------
            */

            if (trim($col[3]) === 'IDM-SJU') {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Validasi akun
            |--------------------------------------------------------------------------
            */

            $segment4 = trim($col[7]);

            if (!isset($validAccounts[$segment4])) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Simpan transaksi
            |--------------------------------------------------------------------------
            */

            $rows[] = [

                'cabang'   => $this->cabang,

                'batchid'  => trim($col[0]),

                'src'      => trim($col[2]),

                'docseqvl' => trim($col[4]),

                'jelinenu' => trim($col[5]),

                'gldate'   => Carbon::createFromFormat(
                    'd-M-Y',
                    trim($col[6])
                )->format('Y-m-d'),

                'segment4' => $segment4,

                $this->segmentField => trim($col[$this->segmentIndex]),

                'gldesc'   => trim($col[13]),

                'acctdr'   => (float) $col[15],

                'acctcr'   => (float) $col[16],

                'nilai'    => (float) $col[15] - (float) $col[16],

                'tipedok'  => trim($col[26])

            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Return data transaksi
        |--------------------------------------------------------------------------
        */

        return [

            'rows' => $rows

        ];
    }

    protected function clearGLInt(): void
    {
        DB::table($this->tableGLInt)

            ->where('cabang', $this->cabang)

            ->whereBetween('gldate', [

                $this->periode->start_date,

                $this->periode->end_date

            ])

            ->delete();
    }

    protected function insertGLInt(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Insert per 1000 record
        |--------------------------------------------------------------------------
        */

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table($this->tableGLInt)->insert($chunk);
        }
    }

    protected function syncGL(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Hapus seluruh GL periode aktif
        |--------------------------------------------------------------------------
        */

        DB::table($this->tableGL)
            ->where('cabang', $this->cabang)
            ->whereBetween('gldate', [
                $this->periode->start_date,
                $this->periode->end_date
            ])
            ->whereIn(
                'segment4',
                DB::table($this->tableGLInt)
                    ->select('segment4')
                    ->where('cabang', $this->cabang)
                    ->distinct()
            )
            ->delete();

        /*
        |--------------------------------------------------------------------------
        | Copy seluruh gl_int -> gl
        |--------------------------------------------------------------------------
        */

        DB::table($this->tableGL)->insertUsing(
            [
                'cabang',
                'batchid',
                'src',
                'docseqvl',
                'jelinenu',
                'gldate',
                'segment4',
                $this->segmentField,
                'gldesc',
                'acctdr',
                'acctcr',
                'nilai',
                'tipedok'
            ],
            DB::table($this->tableGLInt)
                ->select(
                    'cabang',
                    'batchid',
                    'src',
                    'docseqvl',
                    'jelinenu',
                    'gldate',
                    'segment4',
                    $this->segmentField,
                    'gldesc',
                    'acctdr',
                    'acctcr',
                    'nilai',
                    'tipedok'
                )
                ->where('cabang', $this->cabang)
                ->whereBetween('gldate', [
                    $this->periode->start_date,
                    $this->periode->end_date
                ])
        );
    }

    protected function generateRekap(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Hapus rekap periode aktif
        |--------------------------------------------------------------------------
        */

        DB::table($this->tableRekap)
            ->where('cabang', $this->cabang)
            ->whereBetween('tgl', [
                $this->periode->start_date,
                $this->periode->end_date
            ])
            ->delete();

        /*
        |--------------------------------------------------------------------------
        | Ambil rekap dari GL
        |--------------------------------------------------------------------------
        */

        $rekap = DB::table($this->tableGL)

            ->selectRaw("
                cabang,
                segment4 as akun,
                {$this->segmentField},
                gldate as tgl,

                SUM(
                    CASE
                        WHEN src <> 'Receivables'
                        THEN nilai
                        ELSE 0
                    END
                ) as pay,

                SUM(
                    CASE
                        WHEN src = 'Receivables'
                        THEN nilai
                        ELSE 0
                    END
                ) as rec
            ")

            ->where('cabang', $this->cabang)

            ->whereBetween('gldate', [
                $this->periode->start_date,
                $this->periode->end_date
            ])

            ->groupBy(
                'cabang',
                'segment4',
                $this->segmentField,
                'gldate'
            )

            ->get();

        if ($rekap->isEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Siapkan data insert
        |--------------------------------------------------------------------------
        */

        $rows = [];

        foreach ($rekap as $row) {

            $rows[] = [

                'cabang' => $row->cabang,

                'akun' => $row->akun,

                $this->segmentField => $row->{$this->segmentField},

                'tgl' => $row->tgl,

                'pay' => $row->pay,

                'rec' => $row->rec

            ];

        }

        /*
        |--------------------------------------------------------------------------
        | Insert per 1000 record
        |--------------------------------------------------------------------------
        */

        foreach (array_chunk($rows, 1000) as $chunk) {

            DB::table($this->tableRekap)->insert($chunk);

        }
    }
}
