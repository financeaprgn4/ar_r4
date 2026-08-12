<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function getTables(string $jenisBank): array
    {
        $isReg = $jenisBank == 'REG';

        return [

            'is_reg' => $isReg,

            'mutasi' => $isReg
                ? 'mutasi_rekap'
                : 'mutasi_rekap_frc',

            'mutasi_detail' => $isReg
                ? 'mutasi_detail'
                : 'mutasi_detail_frc',

            'gl' => $isReg
                ? 'gl_rekap'
                : 'gl_rekap_frc',

            'unrec' => $isReg
                ? 'mutasi_rekap_unrec'
                : 'mutasi_rekap_unrec_frc',

        ];
    }

    public function getPeriode(string $cabang)
    {
        return DB::table('periode')
            ->where('cabang', $cabang)
            ->where('kategori', 'Mutasi')
            ->where('status', 'Aktif')
            ->first();
    }

    public function getBankInfo(
        string $cabang,
        string $noRek
    ) {
        return DB::table('bank')
            ->select(
                'akun',
                'bank',
                'jns_bank',
                'site',
                'no_rek',
                'cabang'
            )
            ->where('cabang', $cabang)
            ->where('no_rek', $noRek)
            ->first();
    }

    public function getRekeningList(
        string $cabang,
        string $jenisBank,
        ?string $typeBank = null
    )
    {
        $query = DB::table('bank')
            ->select(
                'cabang',
                'akun',
                'bank',
                'jns_bank',
                'site',
                'no_rek'
            )
            ->where('cabang', $cabang);
    
        if ($jenisBank == 'REG') {
    
            $query
                ->where('site', 'REG')
                ->where('bank', '<>', 'Titipan')
                ->orderBy('no_rek');
    
        } else {
    
            $query
                ->where('site', '<>', 'REG')
                ->where('jns_bank', $typeBank)
                ->orderBy('site')
                ->orderBy('no_rek');
    
        }
    
        return $query->get();
    }

    public function formatPeriode(object $periode): array
    {
        return [

            'start_date' => $periode->start_date,

            'end_date' => $periode->end_date,

            'display_start' => date(
                'd-M-Y',
                strtotime($periode->start_date)
            ),

            'display_end' => date(
                'd-M-Y',
                strtotime($periode->end_date)
            )

        ];
    }

    private function getSummaryMap(
        string $table,
        string $cabang,
        string $start,
        string $end
    ): \Illuminate\Support\Collection
    {
        return DB::table($table)
            ->selectRaw("
                no_rek,
                SUM(db) AS total_db,
                SUM(cr) AS total_cr
            ")
            ->where('cabang', $cabang)
            ->whereBetween('tgl', [
                $start,
                $end
            ])
            ->groupBy('no_rek')
            ->get()
            ->mapWithKeys(function ($row) {
    
                return [
    
                    $row->no_rek => [
    
                        'db' => (float) $row->total_db,
    
                        'cr' => (float) $row->total_cr
    
                    ]
    
                ];
    
            });
    }

    public function getMutasiMap(
        string $table,
        string $cabang,
        string $start,
        string $end
    ): \Illuminate\Support\Collection
    {
        return $this->getSummaryMap(
            $table,
            $cabang,
            $start,
            $end
        );
    }

    public function getUnrecMap(
        string $table,
        string $cabang,
        string $start,
        string $end
    ): \Illuminate\Support\Collection
    {
        return $this->getSummaryMap(
            $table,
            $cabang,
            $start,
            $end
        );
    }

    public function getGLMap(
        string $table,
        string $cabang,
        string $start,
        string $end,
        bool $isReg
    ): \Illuminate\Support\Collection
    {
    
        $query = DB::table($table)
            ->where('cabang', $cabang)
            ->whereBetween(
                'tgl',
                [
                    $start,
                    $end
                ]
            );
    
        if ($isReg) {
    
            $query->selectRaw("
                akun,
                SUM(pay) AS total_pay,
                SUM(rec) AS total_rec
            ");
    
            $query->groupBy('akun');
    
        } else {
    
            $query->selectRaw("
                akun,
                segment5,
                SUM(pay) AS total_pay,
                SUM(rec) AS total_rec
            ");
    
            $query->groupBy(
                'akun',
                'segment5'
            );
    
        }
    
        return $query
            ->get()
            ->mapWithKeys(function ($row) use ($isReg) {
    
                $key = $isReg
                    ? $row->akun
                    : $row->akun.'|'.$row->segment5;
    
                return [
    
                    $key => [
    
                        'pay' => (float) $row->total_pay,
    
                        'rec' => (float) $row->total_rec
    
                    ]
    
                ];
    
            });
    
    }

    public function summary(
        string $cabang,
        string $jenisBank,
        ?string $typeBank = null
    ): array
    {
        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */
    
        $table = $this->getTables($jenisBank);
    
        /*
        |--------------------------------------------------------------------------
        | PERIODE
        |--------------------------------------------------------------------------
        */
    
        $periode = $this->getPeriode($cabang);
    
        if (!$periode) {
    
            throw new \Exception(
                'Periode Mutasi Aktif tidak ditemukan'
            );
    
        }
    
        $periodeFormat = $this->formatPeriode($periode);
    
        /*
        |--------------------------------------------------------------------------
        | REKENING
        |--------------------------------------------------------------------------
        */
    
        $rekening = $this->getRekeningList(
            $cabang,
            $jenisBank,
            $typeBank
        );
    
        /*
        |--------------------------------------------------------------------------
        | MAP
        |--------------------------------------------------------------------------
        */
    
        $mutasiMap = $this->getMutasiMap(
            $table['mutasi'],
            $cabang,
            $periode->start_date,
            $periode->end_date
        );
    
        $unrecMap = $this->getUnrecMap(
            $table['unrec'],
            $cabang,
            $periode->start_date,
            $periode->end_date
        );
    
        $glMap = $this->getGLMap(
            $table['gl'],
            $cabang,
            $periode->start_date,
            $periode->end_date,
            $table['is_reg']
        );
    
        /*
        |--------------------------------------------------------------------------
        | HASIL
        |--------------------------------------------------------------------------
        */
    
        $result = [];
    
        foreach ($rekening as $row) {

            $result[] = $this->buildSummaryRow(
                $row,
                $table,
                $mutasiMap,
                $glMap,
                $unrecMap,
                $periodeFormat
            );
        
        }
    
        return [
    
            'periode' => [
    
                'start_date' => $periode->start_date,
    
                'end_date' => $periode->end_date
    
            ],
    
            'data' => $result
    
        ];
    }

    private function getDateSummary(
        string $table,
        string $cabang,
        string $noRek,
        string $start,
        string $end,
        string $debetKey,
        string $kreditKey
    ): \Illuminate\Support\Collection
    {
        return DB::table($table)
            ->selectRaw("
                tgl,
                SUM(db) AS total_db,
                SUM(cr) AS total_cr
            ")
            ->where('cabang', $cabang)
            ->where('no_rek', $noRek)
            ->whereBetween('tgl', [
                $start,
                $end
            ])
            ->groupBy('tgl')
            ->get()
            ->mapWithKeys(function ($row) use (
                $debetKey,
                $kreditKey
            ) {
    
                return [
    
                    $row->tgl => [
    
                        $debetKey => (float) $row->total_db,
    
                        $kreditKey => (float) $row->total_cr
    
                    ]
    
                ];
    
            });
    }

    public function getMutasiByDate(
        string $table,
        string $cabang,
        string $noRek,
        string $start,
        string $end
    ): \Illuminate\Support\Collection
    {
        return $this->getDateSummary(
            $table,
            $cabang,
            $noRek,
            $start,
            $end,
            'debet',
            'kredit'
        );
    }
    
    public function getGLByDate(
        string $table,
        string $cabang,
        string $akun,
        ?string $site,
        bool $isReg,
        string $start,
        string $end
    )
    {
    
        $query = DB::table($table)
            ->selectRaw("
                tgl,
                SUM(pay) AS gl_payables,
                SUM(rec) AS gl_receivables
            ")
            ->where('cabang', $cabang)
            ->where('akun', $akun)
            ->whereBetween('tgl', [
                $start,
                $end
            ]);
    
        if (!$isReg) {
    
            $query->where(
                'segment5',
                $site
            );
    
        }
    
        return $query
            ->groupBy('tgl')
            ->get()
            ->mapWithKeys(function ($row) {
    
                return [
    
                    $row->tgl => [
    
                        'gl_payables' => abs($row->gl_payables),
    
                        'gl_receivables' => abs($row->gl_receivables)
    
                    ]
    
                ];
    
            });
    
    }

    public function getUnrecByDate(
        string $table,
        string $cabang,
        string $noRek,
        string $start,
        string $end
    ): \Illuminate\Support\Collection
    {
        return $this->getDateSummary(
            $table,
            $cabang,
            $noRek,
            $start,
            $end,
            'unrec_debet',
            'unrec_kredit'
        );
    }

    private function buildDetailRow(
        string $tanggal,
        array $mutasi,
        array $gl,
        array $unrec
    ): array
    {
        $cashIn = $mutasi['debet'] - $gl['gl_payables'];
    
        $cashOut = $mutasi['kredit'] - $gl['gl_receivables'];
    
        return [
    
            'tanggal' => $tanggal,
    
            'debet' => $mutasi['debet'],
    
            'kredit' => $mutasi['kredit'],
    
            'gl_payables' => $gl['gl_payables'],
    
            'gl_receivables' => $gl['gl_receivables'],
    
            'cash_in' => $cashIn,
    
            'cash_out' => $cashOut,
    
            'unrec_debet' => $unrec['unrec_debet'],
    
            'unrec_kredit' => $unrec['unrec_kredit'],
    
            'selisih_in' => $cashIn - $unrec['unrec_debet'],
    
            'selisih_out' => $cashOut - $unrec['unrec_kredit']
    
        ];
    }

    public function detail(
        string $cabang,
        string $jenisBank,
        string $noRek
    ): array
    {
        $table = $this->getTables($jenisBank);

        $periode = $this->getPeriode($cabang);

        if (!$periode) {

            throw new \Exception(
                'Periode aktif tidak ditemukan'
            );

        }

        $bank = $this->getBankInfo(
            $cabang,
            $noRek
        );

        if (!$bank) {

            throw new \Exception(
                'Data rekening tidak ditemukan'
            );

        }

        $mutasi = $this->getMutasiByDate(
            $table['mutasi'],
            $cabang,
            $noRek,
            $periode->start_date,
            $periode->end_date
        );

        $gl = $this->getGLByDate(
            $table['gl'],
            $cabang,
            $bank->akun,
            $bank->site,
            $table['is_reg'],
            $periode->start_date,
            $periode->end_date
        );

        $unrec = $this->getUnrecByDate(
            $table['unrec'],
            $cabang,
            $noRek,
            $periode->start_date,
            $periode->end_date
        );

        $result = [];

        $current = strtotime($periode->start_date);

        $end = strtotime($periode->end_date);

        while ($current <= $end) {

            $tgl = date('Y-m-d', $current);

            $result[] = $this->buildDetailRow(

                $tgl,

                $mutasi->get($tgl, [
                    'debet' => 0,
                    'kredit' => 0
                ]),

                $gl->get($tgl, [
                    'gl_payables' => 0,
                    'gl_receivables' => 0
                ]),

                $unrec->get($tgl, [
                    'unrec_debet' => 0,
                    'unrec_kredit' => 0
                ])

            );

            $current = strtotime('+1 day', $current);

        }

        return [

            'periode' => [

                'start_date' => $periode->start_date,

                'end_date' => $periode->end_date

            ],

            'data' => $result

        ];
    }

    private function buildSummaryRow(
        object $rekening,
        array $table,
        \Illuminate\Support\Collection $mutasiMap,
        \Illuminate\Support\Collection $glMap,
        \Illuminate\Support\Collection $unrecMap,
        array $periode
    ): array
    {
        $mutasi = $mutasiMap->get($rekening->no_rek, [
            'db' => 0,
            'cr' => 0
        ]);
    
        $unrec = $unrecMap->get($rekening->no_rek, [
            'db' => 0,
            'cr' => 0
        ]);
    
        $glKey = $table['is_reg']
            ? $rekening->akun
            : $rekening->akun.'|'.$rekening->site;
    
        $gl = $glMap->get($glKey, [
            'pay' => 0,
            'rec' => 0
        ]);
    
        return [
    
            'cabang' => $rekening->cabang,
    
            'akun' => $rekening->akun,
    
            'jns_bank' => $rekening->jns_bank,
    
            'site' => $rekening->site ?? null,
    
            'no_rek' => $rekening->no_rek,
    
            'display_rek' => $table['is_reg']
                ? $rekening->no_rek
                : $rekening->site.' - '.$rekening->no_rek,
    
            'tgl_awal' => $periode['display_start'],

            'tgl_akhir' => $periode['display_end'],
    
            'debet' => $mutasi['db'],
    
            'kredit' => $mutasi['cr'],
    
            'gl_payables' => $gl['pay'],
    
            'gl_receivables' => $gl['rec'],
    
            'unrec_debet' => $unrec['db'],
    
            'unrec_kredit' => $unrec['cr']
    
        ];
    }

    public function updateReconcile(
        string $detailTable,
        string $action,
        string $noRek,
        string $tgl,
        string $jenis,
        array $ids = []
    ): void
    {
    
        switch ($action) {
    
            case 'RECON_ALL':
    
                $query = DB::table($detailTable)
                    ->where('no_rek', $noRek)
                    ->where('tgl', $tgl);
    
                if ($jenis == 'db') {
    
                    $query->where('db', '<>', 0);
    
                } else {
    
                    $query->where('cr', '<>', 0);
    
                }
    
                $query->update([
                    'reconciled' => 'Y'
                ]);
    
                break;
    
            case 'RECON_SELECTED':
    
            case 'UNRECON_SELECTED':
    
                DB::table($detailTable)
                    ->whereIn('id', $ids)
                    ->update([
                        'reconciled' => $action == 'RECON_SELECTED'
                            ? 'Y'
                            : null
                    ]);
    
                break;
    
            default:
    
                throw new \Exception('Bulk action tidak dikenali.');
    
        }
    
    }

    public function recalculateUnrec(
        string $detailTable,
        string $unrecTable,
        string $noRek,
        string $tgl
    ): void
    {
    
        $recalc = DB::table($detailTable)
            ->selectRaw("
                COALESCE(SUM(db),0) AS db,
                COALESCE(SUM(cr),0) AS cr
            ")
            ->where('no_rek', $noRek)
            ->where('tgl', $tgl)
            ->whereNull('reconciled')
            ->first();
    
        DB::table($unrecTable)
            ->where('no_rek', $noRek)
            ->where('tgl', $tgl)
            ->update([
    
                'db' => $recalc->db ?? 0,
    
                'cr' => $recalc->cr ?? 0
    
            ]);
    
    }

    public function unrecDetail(
        string $jenisBank,
        string $noRek,
        string $tgl,
        string $jenis
    ): array
    {
        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */
    
        $table = $this->getTables($jenisBank);
    
        /*
        |--------------------------------------------------------------------------
        | QUERY
        |--------------------------------------------------------------------------
        */
    
        $query = DB::table($table['mutasi_detail'])
            ->select(
                'id',
                'tgl',
                'no_rek',
                'remark',
                'remark1',
                'db',
                'cr',
                'reconciled'
            )
            ->where('no_rek', $noRek)
            ->where('tgl', $tgl);
    
        /*
        |--------------------------------------------------------------------------
        | FILTER DEBET / KREDIT
        |--------------------------------------------------------------------------
        */
    
        if ($jenis == 'db') {
    
            $query->where(
                'db',
                '<>',
                0
            );
    
        } else {
    
            $query->where(
                'cr',
                '<>',
                0
            );
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | RESULT
        |--------------------------------------------------------------------------
        */
    
        $rows = $query
            ->orderBy('id')
            ->get();
    
        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */
    
        $totalUnrec = $rows
            ->filter(function ($row) {
    
                return empty($row->reconciled);
    
            })
            ->sum(
                $jenis == 'db'
                    ? 'db'
                    : 'cr'
            );
    
        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */
    
        return [
    
            'summary' => [
    
                'total_unrec' => (float) $totalUnrec
    
            ],
    
            'data' => $rows
    
        ];
    }

    public function getDetailRow(
        string $cabang,
        string $jenisBank,
        string $noRek,
        string $tgl
    ): array
    {
        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */
    
        $table = $this->getTables($jenisBank);
    
        /*
        |--------------------------------------------------------------------------
        | PERIODE
        |--------------------------------------------------------------------------
        */
    
        $periode = $this->getPeriode($cabang);
    
        if (!$periode) {
    
            throw new \Exception(
                'Periode aktif tidak ditemukan'
            );
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | REKENING
        |--------------------------------------------------------------------------
        */
    
        $bank = $this->getBankInfo(
            $cabang,
            $noRek
        );
    
        if (!$bank) {
    
            throw new \Exception(
                'Data rekening tidak ditemukan'
            );
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | MUTASI
        |--------------------------------------------------------------------------
        */
    
        $mutasi = $this->getMutasiByDate(
            $table['mutasi'],
            $cabang,
            $noRek,
            $periode->start_date,
            $periode->end_date
        );
    
        /*
        |--------------------------------------------------------------------------
        | GL
        |--------------------------------------------------------------------------
        */
    
        $gl = $this->getGLByDate(
            $table['gl'],
            $cabang,
            $bank->akun,
            $bank->site,
            $table['is_reg'],
            $periode->start_date,
            $periode->end_date
        );
    
        /*
        |--------------------------------------------------------------------------
        | UNREC
        |--------------------------------------------------------------------------
        */
    
        $unrec = $this->getUnrecByDate(
            $table['unrec'],
            $cabang,
            $noRek,
            $periode->start_date,
            $periode->end_date
        );
    
        /*
        |--------------------------------------------------------------------------
        | RETURN 1 ROW
        |--------------------------------------------------------------------------
        */
    
        return $this->buildDetailRow(
    
            $tgl,
    
            $mutasi->get($tgl, [
                'debet' => 0,
                'kredit' => 0
            ]),
    
            $gl->get($tgl, [
                'gl_payables' => 0,
                'gl_receivables' => 0
            ]),
    
            $unrec->get($tgl, [
                'unrec_debet' => 0,
                'unrec_kredit' => 0
            ])
    
        );
    }
}