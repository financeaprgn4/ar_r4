<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegularReconciliationService
{
    /** Toleransi nominal: selisih harus kurang dari Rp1 (100 sen). */
    private const AMOUNT_TOLERANCE_CENTS = 100;

    /** Membandingkan dua nominal dengan toleransi kurang dari Rp1. */
    private function isAmountWithinTolerance($amount1, $amount2): bool
    {
        return abs(
            $this->toCents($amount1) - $this->toCents($amount2)
        ) < self::AMOUNT_TOLERANCE_CENTS;
    }

    /**
     * METODE 1: date_amount
     *
     * Pencocokan 1 receipt : 1 mutasi.
     * Kriteria: rekening, tanggal, dan nominal sama.
     *
     * Jika terdapat beberapa kandidat, gunakan kandidat pertama
     * yang masih belum memiliki reff.
     */
    public function reconcileDateAmount(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $context = $this->loadContext($cabang, $periodId, $account);

        $receipts = $context['receipts'];
        $mutasi = $context['mutasi'];

        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildMatchKey(
                $row->no_rek,
                $row->tgl,
                $row->cr
            )
        );

        $matches = [];
        $usedMutasiIds = [];
        $usedReceiptIds = [];

        foreach ($receipts as $receipt) {
            $key = $this->buildMatchKey(
                $receipt->remittance_bank_account,
                $receipt->receipt_date,
                $receipt->receipt_amount
            );

            $candidates = $mutasiGroups->get($key, collect());

            $mutasiRow = $candidates->first(
                fn ($row) =>
                    !in_array(
                        (string) $row->id,
                        $usedMutasiIds,
                        true
                    )
                    && $this->isAmountWithinTolerance(
                        $receipt->receipt_amount,
                        $row->cr
                    )
            );

            if (!$mutasiRow) {
                continue;
            }

            $matches[] = $this->makeMatch(
                $receipt,
                collect([$mutasiRow])
            );

            $usedReceiptIds[] = (string) $receipt->id;
            $usedMutasiIds[] = (string) $mutasiRow->id;
        }

        return $this->finishReconciliation(
            $context,
            'date_amount',
            $matches,
            []
        );
    }

    /**
     * METODE ATS: ats_date_amount
     *
     * Pencocokan 1 receipt : 1 mutasi.
     *
     * Kriteria:
     * - Rekening sama.
     * - Tanggal sama.
     * - Nominal sama.
     * - mutasi_detail.dept = ATS.
     * - receipt.activity = Interbank Transfer.
     * - Receipt dan mutasi belum memiliki reff.
     */
    public function reconcileAtsDateAmount(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        // Ambil receipt yang activity-nya khusus Interbank Transfer.
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account,
            ['Interbank Transfer']
        );

        $receipts = $context['receipts'];
        
        // Ambil hanya mutasi dengan dept ATS.
        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                strtoupper(trim((string) ($row->dept ?? ''))) === 'ATS'
            )
            ->values();

        // Kelompokkan mutasi berdasarkan rekening, tanggal, dan nominal.
        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildMatchKey(
                $row->no_rek,
                $row->tgl,
                $row->cr
            )
        );

        $matches = [];
        $usedMutasiIds = [];

        // Cocokkan satu receipt dengan satu mutasi.
        foreach ($receipts as $receipt) {
            $key = $this->buildMatchKey(
                $receipt->remittance_bank_account,
                $receipt->receipt_date,
                $receipt->receipt_amount
            );

            $candidates = $mutasiGroups->get($key, collect());

            // Gunakan kandidat pertama yang belum dipakai.
            $mutasiRow = $candidates->first(
                fn ($row) =>
                    !in_array(
                        (string) $row->id,
                        $usedMutasiIds,
                        true
                    )
                    && $this->isAmountWithinTolerance(
                        $receipt->receipt_amount,
                        $row->cr
                    )
            );

            if (!$mutasiRow) {
                continue;
            }

            $matches[] = $this->makeMatch(
                $receipt,
                collect([$mutasiRow])
            );

            $usedMutasiIds[] = (string) $mutasiRow->id;
        }

        // Gunakan mekanisme penyimpanan yang sudah tersedia.
        return $this->finishReconciliation(
            $context,
            'ats_date_amount',
            $matches,
            []
        );
    }
        
    /**
     * METODE: virtual_transactions
     *
     * Menandai transaksi virtual dengan reff REG-VIR-ddmmyy.
     *
     * Mutasi:
     * - dept = VIR
     * - cr > 0 (mengikuti filter loadContext)
     *
     * Receipt:
     * - activity termasuk daftar aktivitas virtual.
     *
     * Reff dibuat berdasarkan tanggal masing-masing transaksi.
     * Contoh: REG-VIR-151026 untuk tanggal 15 Oktober 2026.
     */
    public function reconcileVirtualTransactions(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $activities = [
            'Beban Bank (DDR) Frc',
            'PIUTANG BCA',
            'AYAT SILANG BCA TARTUN',
            'Beban Bank (DDR)',
            'TITIPAN PEMILIK KARTU FLAZZ',
            'Pendapatan Lain-lain (ICB)',
            'IDM BEBAN MDR BRI',
            'BRI ON US',
            'IDM BEBAN MDR BRI FRC',
            'BRI OFF US',
            'BIAYA KK BRI (Titipan)',
            'Fee Tarik Tunai BRI NEW',
            'Biaya KK Mandiri On Us Frc',
            'Debit Kartu IDM',
            'Kartu Debit Mandiri',
            'Kartu Kredit Mandiri On Us II',
            'Biaya KK Mandiri On Us',
        ];

        // Ambil data sesuai periode, cabang, rekening,
        // status receipt, dan transaksi yang belum memiliki reff.
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account,
            $activities
        );

        // Hanya mutasi dengan dept VIR.
        $mutasi = $context['mutasi']
            ->filter(function ($row) {
                return strtoupper(
                    trim((string) ($row->dept ?? ''))
                ) === 'VIR';
            })
            ->values();

        $receipts = $context['receipts']->values();

        $updatedMutasi = 0;
        $updatedReceipt = 0;

        DB::transaction(function () use (
            $cabang,
            $account,
            $mutasi,
            $receipts,
            &$updatedMutasi,
            &$updatedReceipt
        ) {
            /*
            |--------------------------------------------------------------------------
            | UPDATE MUTASI
            |--------------------------------------------------------------------------
            */

            foreach ($mutasi as $row) {

                // Pastikan tanggal tersedia.
                if (empty($row->tgl)) {
                    continue;
                }

                $reff = 'REG-VIR-' .
                    \Carbon\Carbon::parse($row->tgl)->format('dmy');

                $affected = DB::table('mutasi_detail')
                    ->where('id', $row->id)
                    ->where('cabang', $cabang)
                    ->where('no_rek', $account)
                    ->where('cr', '>', 0)
                    ->whereRaw(
                        "UPPER(TRIM(COALESCE(dept, ''))) = 'VIR'"
                    )
                    ->where(function ($query) {
                        $query->whereNull('reff')
                            ->orWhere('reff', '');
                    })
                    ->update([
                        'reff' => $reff,
                    ]);

                $updatedMutasi += $affected;
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE RECEIPT
            |--------------------------------------------------------------------------
            */

            foreach ($receipts as $row) {

                if (empty($row->receipt_date)) {
                    continue;
                }

                $reff = 'REG-VIR-' .
                    \Carbon\Carbon::parse($row->receipt_date)->format('dmy');

                $affected = DB::table('receipt')
                    ->where('id', $row->id)
                    ->whereIn('activity', [
                        'Beban Bank (DDR) Frc',
                        'PIUTANG BCA',
                        'AYAT SILANG BCA TARTUN',
                        'Beban Bank (DDR)',
                        'TITIPAN PEMILIK KARTU FLAZZ',
                        'Pendapatan Lain-lain (ICB)',
                        'IDM BEBAN MDR BRI',
                        'BRI ON US',
                        'IDM BEBAN MDR BRI FRC',
                        'BRI OFF US',
                        'BIAYA KK BRI (Titipan)',
                        'Fee Tarik Tunai BRI NEW',
                        'Biaya KK Mandiri On Us Frc',
                        'Debit Kartu IDM',
                        'Kartu Debit Mandiri',
                        'Kartu Kredit Mandiri On Us II',
                        'Biaya KK Mandiri On Us',
                    ])
                    ->where(function ($query) {
                        $query->whereNull('reff')
                            ->orWhere('reff', '');
                    })
                    ->update([
                        'reff' => $reff,
                    ]);

                $updatedReceipt += $affected;
            }
        }, 3);

        return [
            'success' => true,
            'message' => 'Penandaan transaksi virtual REG-VIR selesai.',
            'periode' => [
                'tanggal_awal' => $context['tanggal_awal'],
                'tanggal_akhir' => $context['tanggal_akhir'],
            ],
            'rekening' => $account,
            'method' => 'virtual_transactions',
            'data' => [
                'total_mutasi_vir' => $mutasi->count(),
                'total_receipt_virtual' => $receipts->count(),
                'updated_mutasi' => $updatedMutasi,
                'updated_receipt' => $updatedReceipt,
            ],
        ];
    }

    private function makeVirtualReff($date): string
    {
        return 'REG-VIR-' . \Carbon\Carbon::parse($date)->format('dmy');
    }

    /**
     * METODE BARU: date_amount_remark
     *
     * Pola: 1 receipt : 1 mutasi.
     *
     * Kriteria:
     * - Rekening sama.
     * - Tanggal sama.
     * - Nominal receipt sama dengan kredit mutasi.
     * - mutasi_detail.remark sama dengan receipt.comments.
     * - Receipt dan mutasi yang sudah memiliki reff tidak digunakan.
     */
    public function reconcileDateAmountRemark(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account
        );

        $receipts = $context['receipts']->values();
        $mutasi = $context['mutasi']->values();

        /*
        * Kelompok mutasi berdasarkan rekening + tanggal + nominal.
        */
        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildMatchKey(
                $row->no_rek,
                $row->tgl,
                $row->cr
            )
        );

        $matches = [];
        $ambiguous = [];
        $usedReceiptIds = [];
        $usedMutasiIds = [];

        /*
        * Proses receipt satu per satu.
        */
        foreach ($receipts as $receipt) {
            $key = $this->buildMatchKey(
                $receipt->remittance_bank_account,
                $receipt->receipt_date,
                $receipt->receipt_amount
            );

            $candidates = $mutasiGroups->get($key, collect());

            /*
            * Tambahkan syarat remark = comments.
            * Trim digunakan untuk mengabaikan spasi di awal/akhir.
            * Perbandingan tetap case-sensitive.
            */
            
            $mutasiRow = $candidates->first(
                fn ($row) =>
                    !in_array(
                        (string) $row->id,
                        $usedMutasiIds,
                        true
                    )
                    && $this->isAmountWithinTolerance(
                        $receipt->receipt_amount,
                        $row->cr
                    )
                    && str_replace(
                        [' ', '.', ',', "'"],
                        '',
                        (string) ($row->remark ?? '')
                    ) === str_replace(
                        [' ', '.', ',', "'"],
                        '',
                        (string) ($receipt->comments ?? '')
                    )
            );


            if (!$mutasiRow) {
                continue;
            }

            /*
            * Satu receipt dipasangkan dengan satu mutasi.
            */
            $matches[] = $this->makeMatch(
                $receipt,
                collect([$mutasiRow])
            );

            $usedReceiptIds[] = (string) $receipt->id;
            $usedMutasiIds[] = (string) $mutasiRow->id;
        }

        return $this->finishReconciliation(
            $context,
            'date_amount_remark',
            $matches,
            $ambiguous
        );
    }

    /**
     * METODE BARU: sum_date_amount_remark_reverse
     *
     * Pola: N receipt : 1 mutasi.
     *
     * Kriteria:
     * - Rekening sama.
     * - Tanggal sama.
     * - mutasi_detail.remark = receipt.comments setelah normalisasi.
     * - Total beberapa receipt harus sama dengan mutasi_detail.cr.
     * - Receipt dan mutasi yang sudah memiliki reff tidak digunakan.
     *
     * Normalisasi teks menghapus:
     * spasi, titik, koma, dan petik satu.
     */
    public function reconcileSumDateAmountRemarkReverse(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account
        );

        /*
        * Normalisasi remark dan comments.
        */
        $normalizeText = static fn ($value) => str_replace(
            [' ', '.', ',', "'"],
            '',
            trim((string) ($value ?? ''))
        );

        /*
        * Receipt harus memiliki comments agar dapat dicocokkan.
        * Nominal receipt harus positif.
        */
        $receipts = $context['receipts']
            ->filter(fn ($row) =>
                trim((string) ($row->comments ?? '')) !== ''
                && $this->toCents($row->receipt_amount) !== 0
            )
            ->values();

        /*
        * Mutasi hanya dengan kredit positif.
        * Remark tidak boleh kosong.
        */
        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                trim((string) ($row->remark ?? '')) !== ''
                && $this->toCents($row->cr) > 0
            )
            ->values();

        /*
        * Kelompok receipt berdasarkan:
        * rekening + tanggal + comments yang sudah dinormalisasi.
        */
        $receiptGroups = $receipts->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->remittance_bank_account,
                $row->receipt_date,
                $normalizeText($row->comments)
            )
        );

        /*
        * Kelompok mutasi berdasarkan:
        * rekening + tanggal + remark yang sudah dinormalisasi.
        */
        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->no_rek,
                $row->tgl,
                $normalizeText($row->remark)
            )
        );

        $matches = [];
        $usedReceiptIds = [];
        $usedMutasiIds = [];

        /*
        * Proses setiap kelompok mutasi.
        */
        foreach ($mutasiGroups as $key => $mutasiGroup) {
            $receiptGroup = $receiptGroups->get($key, collect());

            if ($receiptGroup->isEmpty()) {
                continue;
            }

            /*
            * Akumulasi nominal seluruh receipt dalam kelompok.
            * Semua receipt dengan rekening, tanggal, dan comments
            * yang sama dihitung bersama.
            */
            $availableReceipts = $receiptGroup
                ->reject(fn ($row) =>
                    in_array(
                        (string) $row->id,
                        $usedReceiptIds,
                        true
                    )
                )
                ->values();

            if ($availableReceipts->isEmpty()) {
                continue;
            }

            $receiptTotalCents = $availableReceipts->sum(
                fn ($row) => $this->toCents($row->receipt_amount)
            );

            /*
            * Cari mutasi yang nominal kreditnya tepat sama
            * dengan akumulasi receipt.
            */
            $mutasiRow = $mutasiGroup->first(
                fn ($row) =>
                    !in_array(
                        (string) $row->id,
                        $usedMutasiIds,
                        true
                    )
                    && abs(
                        $this->toCents($row->cr) - $receiptTotalCents
                    ) < self::AMOUNT_TOLERANCE_CENTS
            );

            if (!$mutasiRow) {
                continue;
            }

            /*
            * Validasi ulang total receipt dalam satuan sen.
            */
            $matchedTotalCents = $availableReceipts->sum(
                fn ($row) => $this->toCents($row->receipt_amount)
            );

            if (
                abs($matchedTotalCents - $this->toCents($mutasiRow->cr))
                >= self::AMOUNT_TOLERANCE_CENTS
            ) {
                continue;
            }

            /*
            * Semua receipt dan satu mutasi menggunakan reff yang sama.
            */
            $reff = 'REG-' . $mutasiRow->id;

            $matches[] = [
                'receipt_ids' => $availableReceipts
                    ->pluck('id')
                    ->values()
                    ->all(),

                'receipt_numbers' => $availableReceipts
                    ->pluck('receipt_number')
                    ->values()
                    ->all(),

                'receipt_dates' => $availableReceipts
                    ->pluck('receipt_date')
                    ->values()
                    ->all(),

                'receipt_amounts' => $availableReceipts
                    ->map(fn ($row) => (float) $row->receipt_amount)
                    ->values()
                    ->all(),

                'receipt_amount' => (float) $availableReceipts->sum(
                    fn ($row) => (float) $row->receipt_amount
                ),

                'comments' => $availableReceipts->first()->comments,

                'mutasi_ids' => [$mutasiRow->id],
                'mutasi_date' => $mutasiRow->tgl,
                'mutasi_amount' => (float) $mutasiRow->cr,
                'reff' => $reff,
            ];

            /*
            * Tandai semua receipt agar tidak digunakan lagi.
            */
            foreach ($availableReceipts as $receipt) {
                $usedReceiptIds[] = (string) $receipt->id;
            }

            $usedMutasiIds[] = (string) $mutasiRow->id;
        }

        /*
        * Simpan dengan helper N receipt : 1 mutasi yang tersedia.
        */
        return $this->finishReverseReconciliation(
            $context,
            'sum_date_amount_remark_reverse',
            $matches
        );
    }

    public function reconcileFasDateSiteAmount(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account,
        );

        $receipts = $context['receipts']
            ->filter(fn ($row) =>
                str_starts_with(
                    strtoupper(trim((string) ($row->comments ?? ''))),
                    'PELUNASAN KURSET'
                )
            )
            ->values();

        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                strtoupper(trim((string) ($row->dept ?? ''))) === 'FAS'
            )
            ->values();

        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSlsMatchKey(
                $row->no_rek,
                $row->tgl,
                $row->cr,
                $row->site
            )
        );

        $matches = [];
        $usedMutasiIds = [];

        foreach ($receipts as $receipt) {
            // Site FAS diambil dari 4 karakter paling kanan comments.
            $site = substr(
                trim((string) ($receipt->comments ?? '')),
                -4
            );

            $key = $this->buildSlsMatchKey(
                $receipt->remittance_bank_account,
                $receipt->receipt_date,
                $receipt->receipt_amount,
                $site
            );

            $candidates = $mutasiGroups->get($key, collect());

            $mutasiRow = $candidates->first(
                fn ($row) =>
                    !in_array(
                        (string) $row->id,
                        $usedMutasiIds,
                        true
                    )
                    && $this->isAmountWithinTolerance(
                        $receipt->receipt_amount,
                        $row->cr
                    )
            );

            if (!$mutasiRow) {
                continue;
            }

            $matches[] = $this->makeMatch(
                $receipt,
                collect([$mutasiRow])
            );

            $usedMutasiIds[] = (string) $mutasiRow->id;
        }

        return $this->finishReconciliation(
            $context,
            'fas_date_site_amount',
            $matches,
            []
        );
    }    
        
    public function reconcileFasSumDateSiteAmountReverse(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account,
        );

        /*
        |--------------------------------------------------------------------------
        | Receipt FAS
        |--------------------------------------------------------------------------
        | Receipt diproses jika:
        | 1. category = FAS; atau
        | 2. comments diawali PELUNASAN KURSET.
        |
        | Prioritas penentuan site:
        | - category = FAS: gunakan receipt.site.
        | - Selain itu: ambil 4 karakter terakhir comments.
        |--------------------------------------------------------------------------
        */

        $receipts = $context['receipts']
            ->filter(function ($row) {
                $category = strtoupper(
                    trim((string) ($row->category ?? ''))
                );

                $comments = strtoupper(
                    trim((string) ($row->comments ?? ''))
                );

                return $category === 'FAS'
                    || str_starts_with(
                        $comments,
                        'PELUNASAN KURSET'
                    );
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Mutasi khusus dept FAS
        |--------------------------------------------------------------------------
        */

        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                strtoupper(trim((string) ($row->dept ?? ''))) === 'FAS'
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Kelompokkan receipt berdasarkan rekening + tanggal + site.
        |--------------------------------------------------------------------------
        | Jika category = FAS, site berasal dari field receipt.site.
        | Jika bukan, site berasal dari 4 karakter terakhir comments.
        |--------------------------------------------------------------------------
        */

        $receiptGroups = $receipts->groupBy(function ($row) {
            $category = strtoupper(
                trim((string) ($row->category ?? ''))
            );

            if ($category === 'FAS') {
                $site = trim((string) ($row->site ?? ''));
            } else {
                $site = substr(
                    trim((string) ($row->comments ?? '')),
                    -4
                );
            }

            return $this->buildSiteDateKey(
                $row->remittance_bank_account,
                $row->receipt_date,
                $site
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Kelompokkan mutasi berdasarkan rekening + tanggal + site.
        |--------------------------------------------------------------------------
        */

        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->no_rek,
                $row->tgl,
                $row->site
            )
        );

        $matches = [];
        $usedReceiptIds = [];
        $usedMutasiIds = [];

        /*
        |--------------------------------------------------------------------------
        | Cari kombinasi beberapa receipt untuk satu mutasi.
        |--------------------------------------------------------------------------
        */

        foreach ($mutasiGroups as $key => $mutasiGroup) {
            $receiptGroup = $receiptGroups->get($key, collect());

            if ($receiptGroup->isEmpty()) {
                continue;
            }

            foreach ($mutasiGroup as $mutasiRow) {
                if (in_array(
                    (string) $mutasiRow->id,
                    $usedMutasiIds,
                    true
                )) {
                    continue;
                }

                $availableReceipts = $receiptGroup
                    ->reject(fn ($row) =>
                        in_array(
                            (string) $row->id,
                            $usedReceiptIds,
                            true
                        )
                    )
                    ->filter(fn ($row) =>
                        $this->toCents($row->receipt_amount) > 0
                    )
                    ->values();

                if ($availableReceipts->isEmpty()) {
                    continue;
                }

                $targetCents = $this->toCents($mutasiRow->cr);

                $solution = $this->findFirstReceiptSubset(
                    $availableReceipts,
                    $targetCents
                );

                if ($solution === null || empty($solution)) {
                    continue;
                }

                $matchedReceipts = collect($solution)
                    ->map(fn ($index) =>
                        $availableReceipts->get($index)
                    )
                    ->values();

                $matchedTotalCents = $matchedReceipts->sum(
                    fn ($row) =>
                        $this->toCents($row->receipt_amount)
                );

                /*
                |--------------------------------------------------------------------------
                | Validasi toleransi nominal
                |--------------------------------------------------------------------------
                */

                if (
                    abs($matchedTotalCents - $targetCents)
                    >= self::AMOUNT_TOLERANCE_CENTS
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Satu reff untuk satu mutasi dan semua receipt yang cocok.
                |--------------------------------------------------------------------------
                */

                $reff = 'REG-' . $mutasiRow->id;

                $matches[] = [
                    'receipt_ids' => $matchedReceipts
                        ->pluck('id')
                        ->values()
                        ->all(),

                    'receipt_numbers' => $matchedReceipts
                        ->pluck('receipt_number')
                        ->values()
                        ->all(),

                    'receipt_dates' => $matchedReceipts
                        ->pluck('receipt_date')
                        ->values()
                        ->all(),

                    'receipt_amounts' => $matchedReceipts
                        ->map(fn ($row) =>
                            (float) $row->receipt_amount
                        )
                        ->values()
                        ->all(),

                    'receipt_amount' => (float) $matchedReceipts->sum(
                        fn ($row) =>
                            (float) $row->receipt_amount
                    ),

                    'paid_by' => $matchedReceipts->first()->paid_by,

                    'mutasi_ids' => [$mutasiRow->id],
                    'mutasi_date' => $mutasiRow->tgl,
                    'mutasi_amount' => (float) $mutasiRow->cr,
                    'reff' => $reff,
                ];

                /*
                |--------------------------------------------------------------------------
                | Tandai receipt dan mutasi yang sudah digunakan.
                |--------------------------------------------------------------------------
                */

                foreach ($matchedReceipts as $receipt) {
                    $usedReceiptIds[] = (string) $receipt->id;
                }

                $usedMutasiIds[] = (string) $mutasiRow->id;
            }
        }

        return $this->finishReverseReconciliation(
            $context,
            'fas_sum_date_site_amount_reverse',
            $matches
        );
    }

    /**
     * METODE 2: sls_date_site_amount
     *
     * Pencocokan 1 receipt : 1 mutasi.
     *
     * Kriteria:
     * - Rekening sama.
     * - Tanggal sama.
     * - Nominal sama.
     * - mutasi_detail.dept = SLS.
     * - receipt.paid_by = mutasi_detail.site.
     *
     * Jika ada beberapa kandidat, gunakan kandidat pertama
     * yang masih tersedia.
     */
    public function reconcileSlsDateSiteAmount(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account,
            [
                'Setoran Sales per Shift',
                'Penerimaan Lain-Lain',
                'Hasil Penjualan Belum Diterima',
            ]
        );

        $receipts = $context['receipts'];

        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                strtoupper(trim((string) ($row->dept ?? ''))) === 'SLS'
            )
            ->values();

        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSlsMatchKey(
                $row->no_rek,
                $row->tgl,
                $row->cr,
                $row->site
            )
        );

        $matches = [];
        $usedMutasiIds = [];

        foreach ($receipts as $receipt) {
            $key = $this->buildSlsMatchKey(
                $receipt->remittance_bank_account,
                $receipt->receipt_date,
                $receipt->receipt_amount,
                $receipt->paid_by
            );

            $candidates = $mutasiGroups->get($key, collect());

            $mutasiRow = $candidates->first(
                fn ($row) =>
                    !in_array(
                        (string) $row->id,
                        $usedMutasiIds,
                        true
                    )
                    && $this->isAmountWithinTolerance(
                        $receipt->receipt_amount,
                        $row->cr
                    )
            );

            if (!$mutasiRow) {
                continue;
            }

            $matches[] = $this->makeMatch(
                $receipt,
                collect([$mutasiRow])
            );

            $usedMutasiIds[] = (string) $mutasiRow->id;
        }

        return $this->finishReconciliation(
            $context,
            'sls_date_site_amount',
            $matches,
            []
        );
    }

    /**
     * METODE 3: sls_sum_date_site_amount
     *
     * Pencocokan 1 receipt : N mutasi.
     *
     * Kriteria:
     * - Rekening sama.
     * - Tanggal sama.
     * - Total mutasi sama dengan nominal receipt.
     * - Seluruh mutasi memiliki dept = SLS.
     * - receipt.paid_by = mutasi_detail.site.
     *
     * Jika ada beberapa kombinasi yang cocok, gunakan kombinasi
     * pertama yang ditemukan.
     *
     * Mutasi yang telah dipilih langsung dikeluarkan dari kandidat
     * agar tidak digunakan lagi untuk receipt berikutnya.
     */
    public function reconcileSlsSumDateSiteAmount(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account,
            [
                'Setoran Sales per Shift',
                'Penerimaan Lain-Lain',
                'Hasil Penjualan Belum Diterima',
            ]
        );

        $receipts = $context['receipts']->values();

        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                strtoupper(trim((string) ($row->dept ?? ''))) === 'SLS'
            )
            ->values();

        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->no_rek,
                $row->tgl,
                $row->site
            )
        );

        $matches = [];
        $usedMutasiIds = [];

        /*
         * Proses receipt satu per satu.
         * Urutan mengikuti receipt_date lalu ID dari loadContext().
         */
        foreach ($receipts as $receipt) {
            $key = $this->buildSiteDateKey(
                $receipt->remittance_bank_account,
                $receipt->receipt_date,
                $receipt->paid_by
            );

            $mutasiGroup = $mutasiGroups->get($key, collect());

            if ($mutasiGroup->isEmpty()) {
                continue;
            }

            /*
             * Pastikan mutasi yang sudah dipilih untuk receipt
             * sebelumnya tidak digunakan lagi.
             */
            $candidates = $mutasiGroup
                ->reject(fn ($row) =>
                    in_array(
                        (string) $row->id,
                        $usedMutasiIds,
                        true
                    )
                )
                ->filter(fn ($row) =>
                    $this->toCents($row->cr) > 0
                )
                ->values();

            if ($candidates->isEmpty()) {
                continue;
            }

            $targetCents = $this->toCents(
                $receipt->receipt_amount
            );

            /*
             * Cari satu kombinasi pertama yang totalnya tepat sama
             * dengan nominal receipt.
             *
             * Fungsi berhenti setelah menemukan solusi pertama.
             */
            $solution = $this->findFirstSubset(
                $candidates,
                $targetCents
            );

            if ($solution === null || empty($solution)) {
                continue;
            }

            $matchedMutasi = collect($solution)
                ->map(fn ($index) => $candidates->get($index))
                ->values();

            /*
             * Pengaman tambahan: pastikan total kombinasi benar-benar
             * sama dengan nominal receipt sebelum membuat pasangan.
             */
            $matchedTotalCents = $matchedMutasi->sum(
                fn ($row) => $this->toCents($row->cr)
            );

            if (
                abs($matchedTotalCents - $targetCents)
                >= self::AMOUNT_TOLERANCE_CENTS
            ) {
                continue;
            }

            $matches[] = $this->makeMatch(
                $receipt,
                $matchedMutasi
            );

            /*
             * Tandai semua mutasi yang dipakai.
             * ID ini tidak akan digunakan lagi pada receipt berikutnya.
             */
            foreach ($matchedMutasi as $row) {
                $usedMutasiIds[] = (string) $row->id;
            }
        }

        return $this->finishReconciliation(
            $context,
            'sls_sum_date_site_amount',
            $matches,
            []
        );
    }

    /**
     * METODE 4: sls_sum_date_site_amount_reverse
     *
     * Pola: N receipt : 1 mutasi.
     *
     * Kriteria:
     * - Receipt dikelompokkan berdasarkan rekening, tanggal, dan paid_by.
     * - Mutasi memiliki rekening dan tanggal yang sama.
     * - mutasi_detail.dept = SLS.
     * - mutasi_detail.site = receipt.paid_by.
     * - Total beberapa receipt sama dengan mutasi_detail.cr.
     *
     * Jika ada beberapa kombinasi receipt yang cocok, ambil kombinasi
     * pertama berdasarkan urutan receipt_date dan ID.
     *
     * Semua receipt dalam satu pasangan dan mutasinya menggunakan reff
     * yang sama. Receipt dan mutasi yang sudah memiliki reff tidak dipakai lagi.
     */
    public function reconcileSlsSumDateSiteAmountReverse(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account,
            [
                'Setoran Sales per Shift',
                'Penerimaan Lain-Lain',
                'Hasil Penjualan Belum Diterima',
            ]
        );

        $receipts = $context['receipts']->values();

        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                strtoupper(trim((string) ($row->dept ?? ''))) === 'SLS'
            )
            ->values();

        /*
        * Kelompokkan receipt berdasarkan rekening + tanggal + paid_by.
        */
        $receiptGroups = $receipts->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->remittance_bank_account,
                $row->receipt_date,
                $row->paid_by
            )
        );

        /*
        * Kelompokkan mutasi berdasarkan rekening + tanggal + site.
        */
        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->no_rek,
                $row->tgl,
                $row->site
            )
        );

        $matches = [];
        $usedReceiptIds = [];
        $usedMutasiIds = [];

        /*
        * Iterasi mutasi satu per satu.
        * Setiap mutasi akan mencari kombinasi receipt yang totalnya
        * sama dengan nominal kredit mutasi tersebut.
        */
        foreach ($mutasiGroups as $key => $mutasiGroup) {
            $receiptGroup = $receiptGroups->get($key, collect());

            if ($receiptGroup->isEmpty()) {
                continue;
            }

            foreach ($mutasiGroup as $mutasiRow) {
                /*
                * Jangan gunakan mutasi yang sudah dipakai sebelumnya.
                */
                if (in_array(
                    (string) $mutasiRow->id,
                    $usedMutasiIds,
                    true
                )) {
                    continue;
                }

                /*
                * Ambil receipt yang belum dipakai dalam proses ini.
                */
                $availableReceipts = $receiptGroup
                    ->reject(fn ($row) =>
                        in_array(
                            (string) $row->id,
                            $usedReceiptIds,
                            true
                        )
                    )
                    ->filter(fn ($row) =>
                        $this->toCents($row->receipt_amount) > 0
                    )
                    ->values();

                if ($availableReceipts->isEmpty()) {
                    continue;
                }

                /*
                * Target adalah nominal satu mutasi.
                */
                $targetCents = $this->toCents($mutasiRow->cr);

                /*
                * Cari kombinasi receipt pertama yang jumlahnya sama
                * dengan nominal mutasi.
                */
                $solution = $this->findFirstReceiptSubset(
                    $availableReceipts,
                    $targetCents
                );

                if ($solution === null || empty($solution)) {
                    continue;
                }

                $matchedReceipts = collect($solution)
                    ->map(fn ($index) => $availableReceipts->get($index))
                    ->values();

                /*
                * Pengaman nominal: pastikan total receipt benar-benar
                * sama dengan nominal mutasi.
                */
                $matchedTotalCents = $matchedReceipts->sum(
                    fn ($row) => $this->toCents($row->receipt_amount)
                );

                if (
                    abs($matchedTotalCents - $targetCents)
                    >= self::AMOUNT_TOLERANCE_CENTS
                ) {
                    continue;
                }

                /*
                * Satu reff untuk seluruh receipt dan satu mutasi.
                */
                $reff = 'REG-' . $mutasiRow->id;

                $matches[] = [
                    'receipt_ids' => $matchedReceipts
                        ->pluck('id')
                        ->values()
                        ->all(),

                    'receipt_numbers' => $matchedReceipts
                        ->pluck('receipt_number')
                        ->values()
                        ->all(),

                    'receipt_dates' => $matchedReceipts
                        ->pluck('receipt_date')
                        ->values()
                        ->all(),

                    'receipt_amounts' => $matchedReceipts
                        ->map(fn ($row) => (float) $row->receipt_amount)
                        ->values()
                        ->all(),

                    'receipt_amount' => (float) $matchedReceipts->sum(
                        fn ($row) => (float) $row->receipt_amount
                    ),

                    'paid_by' => $matchedReceipts->first()->paid_by,

                    'mutasi_ids' => [$mutasiRow->id],
                    'mutasi_date' => $mutasiRow->tgl,
                    'mutasi_amount' => (float) $mutasiRow->cr,
                    'reff' => $reff,
                ];

                /*
                * Tandai semua receipt yang dipakai agar tidak digunakan
                * lagi oleh mutasi berikutnya.
                */
                foreach ($matchedReceipts as $receipt) {
                    $usedReceiptIds[] = (string) $receipt->id;
                }

                $usedMutasiIds[] = (string) $mutasiRow->id;
            }
        }

        return $this->finishReverseReconciliation(
            $context,
            'sls_sum_date_site_amount_reverse',
            $matches
        );
    }
        
    /**
     * METODE: sls_sum_date_activity_amount_reverse
     *
     * Pola: N receipt : 1 mutasi.
     *
     * Kriteria:
     * - Rekening receipt dan mutasi harus sama.
     * - Tanggal receipt dan mutasi harus sama.
     * - Receipt hanya dari activity:
     *   1. Hasil Penjualan Belum Diterima
     *   2. Penerimaan Lain-Lain
     *   3. Setoran Sales per Shift
     * - Activity boleh berbeda dalam satu kombinasi receipt.
     * - mutasi_detail.dept = SLS.
     * - Tidak menggunakan site atau paid_by sebagai filter.
     * - Total beberapa receipt harus sama dengan mutasi_detail.cr.
     *
     * Semua receipt yang cocok dan satu mutasi menggunakan reff yang sama.
     */
    public function reconcileSlsSumDateActivityAmountReverse(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $activities = [
            'Hasil Penjualan Belum Diterima',
            'Penerimaan Lain-Lain',
            'Setoran Sales per Shift',
            'Bayar Kurset Penjualan',
        ];

        /*
        * Ambil receipt yang belum direkonsiliasi dan memenuhi
        * filter cabang, periode, rekening, serta status loadContext().
        */
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account,
            $activities
        );

        $receipts = $context['receipts']
            ->filter(function ($row) use ($activities) {
                $activity = trim((string) ($row->activity ?? ''));
                $comments = trim((string) ($row->comments ?? ''));

                // Activity harus termasuk daftar yang diizinkan.
                if (!in_array($activity, $activities, true)) {
                    return false;
                }

                /*
                * Khusus Bayar Kurset Penjualan:
                * hanya receipt dengan comments yang diawali
                * "Bayar Kurset Sales" yang boleh diperhitungkan.
                */
                if ($activity === 'Bayar Kurset Penjualan') {
                    if (stripos($comments, 'Bayar Kurset Sales') !== 0) {
                        return false;
                    }
                }

                return true;
            })
            ->filter(fn ($row) =>
                $this->toCents($row->receipt_amount) > 0
            )
            ->values();

        /*
        * Ambil mutasi SLS saja.
        * Tidak ada filter site.
        */
        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                strtoupper(trim((string) ($row->dept ?? ''))) === 'SLS'
            )
            ->filter(fn ($row) =>
                $this->toCents($row->cr) > 0
            )
            ->values();

        /*
        * Kelompok receipt berdasarkan rekening + tanggal.
        *
        * Activity TIDAK dimasukkan ke key kelompok sehingga
        * receipt dari activity berbeda bisa dijumlahkan bersama.
        */
        $receiptGroups = $receipts->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->remittance_bank_account,
                $row->receipt_date,
                ''
            )
        );

        /*
        * Kelompok mutasi berdasarkan rekening + tanggal.
        * Parameter site dikosongkan.
        */
        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->no_rek,
                $row->tgl,
                ''
            )
        );

        $matches = [];
        $usedReceiptIds = [];
        $usedMutasiIds = [];

        /*
        * Proses mutasi satu per satu.
        * Cari kombinasi receipt dari seluruh activity yang diizinkan.
        */
        foreach ($mutasiGroups as $key => $mutasiGroup) {
            $receiptGroup = $receiptGroups->get($key, collect());

            if ($receiptGroup->isEmpty()) {
                continue;
            }

            foreach ($mutasiGroup as $mutasiRow) {
                // Pastikan mutasi belum digunakan.
                if (in_array(
                    (string) $mutasiRow->id,
                    $usedMutasiIds,
                    true
                )) {
                    continue;
                }

                // Nominal target adalah kredit mutasi.
                $targetCents = $this->toCents($mutasiRow->cr);

                if ($targetCents <= 0) {
                    continue;
                }

                /*
                * Ambil semua receipt yang masih tersedia.
                * Activity berbeda tetap berada dalam satu kandidat.
                */
                $availableReceipts = $receiptGroup
                    ->reject(fn ($row) =>
                        in_array(
                            (string) $row->id,
                            $usedReceiptIds,
                            true
                        )
                    )
                    ->filter(fn ($row) =>
                        $this->toCents($row->receipt_amount) > 0
                    )
                    ->sortBy([
                        ['receipt_date', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->values();

                if ($availableReceipts->isEmpty()) {
                    continue;
                }

                /*
                * Cari kombinasi pertama dari receipt lintas activity
                * yang totalnya tepat sama dengan kredit mutasi.
                */
                $solution = $this->findFirstReceiptSubset(
                    $availableReceipts,
                    $targetCents
                );

                if ($solution === null || empty($solution)) {
                    continue;
                }

                $matchedReceipts = collect($solution)
                    ->map(fn ($index) => $availableReceipts->get($index))
                    ->filter()
                    ->values();

                if ($matchedReceipts->isEmpty()) {
                    continue;
                }

                /*
                * Validasi ulang total dalam satuan sen
                * untuk menghindari selisih pembulatan.
                */
                $matchedTotalCents = $matchedReceipts->sum(
                    fn ($row) => $this->toCents($row->receipt_amount)
                );

                if (
                    abs($matchedTotalCents - $targetCents)
                    >= self::AMOUNT_TOLERANCE_CENTS
                ) {
                    continue;
                }

                /*
                * Gunakan satu reff untuk semua receipt
                * dan satu mutasi yang dipasangkan.
                */
                $reff = 'REG-' . $mutasiRow->id;

                $matches[] = [
                    'receipt_ids' => $matchedReceipts
                        ->pluck('id')
                        ->values()
                        ->all(),

                    'receipt_numbers' => $matchedReceipts
                        ->pluck('receipt_number')
                        ->values()
                        ->all(),

                    'receipt_dates' => $matchedReceipts
                        ->pluck('receipt_date')
                        ->values()
                        ->all(),

                    'receipt_amounts' => $matchedReceipts
                        ->map(fn ($row) => (float) $row->receipt_amount)
                        ->values()
                        ->all(),

                    'receipt_amount' => (float) $matchedReceipts->sum(
                        fn ($row) => (float) $row->receipt_amount
                    ),

                    // Informasi tambahan untuk hasil rekonsiliasi.
                    'activities' => $matchedReceipts
                        ->pluck('activity')
                        ->unique()
                        ->values()
                        ->all(),

                    'paid_by' => $matchedReceipts->first()->paid_by,

                    'mutasi_ids' => [$mutasiRow->id],
                    'mutasi_date' => $mutasiRow->tgl,
                    'mutasi_amount' => (float) $mutasiRow->cr,
                    'reff' => $reff,
                ];

                // Receipt yang sudah dipakai tidak boleh dipakai lagi.
                foreach ($matchedReceipts as $receipt) {
                    $usedReceiptIds[] = (string) $receipt->id;
                }

                // Mutasi hanya boleh dipasangkan satu kali.
                $usedMutasiIds[] = (string) $mutasiRow->id;
            }
        }

        /*
        * Simpan hasil menggunakan mekanisme penyimpanan yang sudah ada.
        */
        return $this->finishReverseReconciliation(
            $context,
            'sls_sum_date_activity_amount_reverse',
            $matches
        );
    }

    /**
     * METODE 5: sls_sum_date_site_amount_many
     *
     * Pola: N receipt : N mutasi.
     *
     * Alur:
     * 1. Mengambil receipt yang belum memiliki reff melalui loadContext().
     * 2. Mengelompokkan receipt berdasarkan rekening, tanggal, paid_by.
     * 3. Mengelompokkan mutasi SLS berdasarkan rekening, tanggal, site.
     * 4. Menjumlahkan seluruh receipt dalam satu kelompok.
     * 5. Mencari kombinasi mutasi yang totalnya sama dengan total receipt.
     * 6. Jika ditemukan, seluruh receipt dan mutasi dipasangkan dengan reff sama.
     *
     * Jika ada beberapa kombinasi mutasi yang cocok, gunakan kombinasi
     * pertama yang ditemukan.
     */
    public function reconcileSlsSumDateSiteAmountMany(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account,
            [
                'Setoran Sales per Shift',
                'Penerimaan Lain-Lain',
                'Hasil Penjualan Belum Diterima',
            ]
        );

        $receipts = $context['receipts']->values();

        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                strtoupper(trim((string) ($row->dept ?? ''))) === 'SLS'
            )
            ->values();

        /*
        * Kelompok receipt: rekening + tanggal + paid_by.
        */
        $receiptGroups = $receipts->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->remittance_bank_account,
                $row->receipt_date,
                $row->paid_by
            )
        );

        /*
        * Kelompok mutasi: rekening + tanggal + site.
        */
        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->no_rek,
                $row->tgl,
                $row->site
            )
        );

        $matches = [];
        $usedReceiptIds = [];
        $usedMutasiIds = [];

        /*
        * Proses berdasarkan kelompok receipt.
        */
        foreach ($receiptGroups as $key => $receiptGroup) {
            $mutasiGroup = $mutasiGroups->get($key, collect());

            if ($mutasiGroup->isEmpty()) {
                continue;
            }

            /*
            * Pastikan receipt kelompok ini belum dipakai oleh pasangan lain.
            */
            $availableReceipts = $receiptGroup
                ->reject(fn ($row) =>
                    in_array(
                        (string) $row->id,
                        $usedReceiptIds,
                        true
                    )
                )
                ->filter(fn ($row) =>
                    $this->toCents($row->receipt_amount) > 0
                )
                ->values();

            /*
            * Ambil mutasi yang belum dipakai.
            */
            $availableMutasi = $mutasiGroup
                ->reject(fn ($row) =>
                    in_array(
                        (string) $row->id,
                        $usedMutasiIds,
                        true
                    )
                )
                ->filter(fn ($row) =>
                    $this->toCents($row->cr) > 0
                )
                ->values();

            if (
                $availableReceipts->isEmpty() ||
                $availableMutasi->isEmpty()
            ) {
                continue;
            }

            /*
            * Target adalah total seluruh receipt dalam kelompok.
            */
            $targetCents = $availableReceipts->sum(
                fn ($row) => $this->toCents($row->receipt_amount)
            );

            if ($targetCents <= 0) {
                continue;
            }

            /*
            * Cari kombinasi mutasi yang totalnya sama dengan
            * total receipt.
            *
            * findFirstSubset() sudah tersedia pada service Anda.
            */
            $solution = $this->findFirstSubset(
                $availableMutasi,
                $targetCents
            );

            if ($solution === null || empty($solution)) {
                continue;
            }

            $matchedMutasi = collect($solution)
                ->map(fn ($index) => $availableMutasi->get($index))
                ->values();

            /*
            * Validasi ulang total nominal dalam satuan sen.
            */
            $matchedMutasiCents = $matchedMutasi->sum(
                fn ($row) => $this->toCents($row->cr)
            );

            if (
                abs($matchedMutasiCents - $targetCents)
                >= self::AMOUNT_TOLERANCE_CENTS
            ) {
                continue;
            }

            /*
            * Total receipt harus sama dengan total mutasi.
            */
            $receiptAmount = $availableReceipts->sum(
                fn ($row) => (float) $row->receipt_amount
            );

            $mutasiAmount = $matchedMutasi->sum(
                fn ($row) => (float) $row->cr
            );

            /*
            * Satu reff untuk semua receipt dan mutasi dalam pasangan.
            */
            $mutasiIds = $matchedMutasi
                ->pluck('id')
                ->values()
                ->all();

            $reff = 'REG-' . min($mutasiIds);

            $matches[] = [
                'receipt_ids' => $availableReceipts
                    ->pluck('id')
                    ->values()
                    ->all(),

                'receipt_numbers' => $availableReceipts
                    ->pluck('receipt_number')
                    ->values()
                    ->all(),

                'receipt_dates' => $availableReceipts
                    ->pluck('receipt_date')
                    ->values()
                    ->all(),

                'receipt_amounts' => $availableReceipts
                    ->map(fn ($row) => (float) $row->receipt_amount)
                    ->values()
                    ->all(),

                'receipt_amount' => (float) $receiptAmount,

                'paid_by' => $availableReceipts->first()->paid_by,

                'mutasi_ids' => $mutasiIds,

                'mutasi_dates' => $matchedMutasi
                    ->pluck('tgl')
                    ->values()
                    ->all(),

                'mutasi_amounts' => $matchedMutasi
                    ->map(fn ($row) => (float) $row->cr)
                    ->values()
                    ->all(),

                'mutasi_amount' => (float) $mutasiAmount,

                'reff' => $reff,
            ];

            /*
            * Tandai seluruh receipt dan mutasi yang digunakan.
            */
            foreach ($availableReceipts as $receipt) {
                $usedReceiptIds[] = (string) $receipt->id;
            }

            foreach ($matchedMutasi as $mutasiRow) {
                $usedMutasiIds[] = (string) $mutasiRow->id;
            }
        }

        /*
        * Simpan pasangan N receipt : N mutasi.
        */
        return $this->finishManyToManyReconciliation(
            $context,
            'sls_sum_date_site_amount_many',
            $matches
        );
    }

    /**
     * Mencari kombinasi pertama receipt dengan selisih total kurang dari Rp1.
     *
     * Mengembalikan indeks receipt dalam koleksi kandidat, atau null.
     */
    private function findFirstReceiptSubset(
        Collection $receipts,
        int $targetCents
    ): ?array {
        if ($targetCents <= 0 || $receipts->isEmpty()) {
            return null;
        }

        $items = $receipts
            ->map(fn ($row) => $this->toCents($row->receipt_amount))
            ->values()
            ->all();

        $count = count($items);

        $suffixSum = array_fill(0, $count + 1, 0);

        for ($i = $count - 1; $i >= 0; $i--) {
            $suffixSum[$i] = $suffixSum[$i + 1]
                + max(0, $items[$i]);
        }

        $current = [];

        $search = function (
            int $start,
            int $sum
        ) use (
            &$search,
            &$current,
            $items,
            $suffixSum,
            $count,
            $targetCents
        ): ?array {
            if (
                !empty($current)
                && abs($sum - $targetCents)
                    < self::AMOUNT_TOLERANCE_CENTS
            ) {
                return $current;
            }

            if (
                $sum >= $targetCents + self::AMOUNT_TOLERANCE_CENTS
                || $start >= $count
            ) {
                return null;
            }

            if (
                $sum + $suffixSum[$start]
                    <= $targetCents - self::AMOUNT_TOLERANCE_CENTS
            ) {
                return null;
            }

            for ($i = $start; $i < $count; $i++) {
                $amount = $items[$i];

                if (
                    $amount <= 0
                    || $sum + $amount
                        >= $targetCents + self::AMOUNT_TOLERANCE_CENTS
                ) {
                    continue;
                }

                $current[] = $i;

                $solution = $search(
                    $i + 1,
                    $sum + $amount
                );

                if ($solution !== null) {
                    return $solution;
                }

                array_pop($current);
            }

            return null;
        };

        return $search(0, 0);
    }
        
    /**
     * Menyimpan hasil rekonsiliasi N receipt : 1 mutasi.
     *
     * Semua receipt dalam satu pasangan dan mutasinya memakai reff yang sama.
     * Jika salah satu update gagal, seluruh transaksi dibatalkan.
     */
    private function finishReverseReconciliation(
        array $context,
        string $method,
        array $matches
    ): array {
        $savedMatches = [];

        DB::transaction(function () use (
            $context,
            $matches,
            &$savedMatches
        ) {
            $claimedReceiptIds = [];
            $claimedMutasiIds = [];

            foreach ($matches as $match) {
                $reff = $match['reff'];

                /*
                * Cegah receipt atau mutasi digunakan dua kali
                * dalam batch yang sama.
                */
                $duplicate = false;

                foreach ($match['receipt_ids'] as $receiptId) {
                    if (in_array(
                        (string) $receiptId,
                        $claimedReceiptIds,
                        true
                    )) {
                        $duplicate = true;
                        break;
                    }
                }

                foreach ($match['mutasi_ids'] as $mutasiId) {
                    if (in_array(
                        (string) $mutasiId,
                        $claimedMutasiIds,
                        true
                    )) {
                        $duplicate = true;
                        break;
                    }
                }

                if ($duplicate) {
                    continue;
                }

                /*
                * Update seluruh receipt.
                */
                foreach ($match['receipt_ids'] as $receiptId) {
                    $updated = DB::table('receipt')
                        ->where('id', $receiptId)
                        ->where(function ($query) {
                            $query->whereNull('reff')
                                ->orWhere('reff', '');
                        })
                        ->update([
                            'reff' => $reff,
                        ]);

                    if ($updated !== 1) {
                        throw new RuntimeException(
                            "Receipt ID {$receiptId} sudah berubah "
                            . 'atau sudah direkonsiliasi.'
                        );
                    }

                    $claimedReceiptIds[] = (string) $receiptId;
                }

                /*
                * Update mutasi yang dipasangkan.
                */
                foreach ($match['mutasi_ids'] as $mutasiId) {
                    $updated = DB::table('mutasi_detail')
                        ->where('id', $mutasiId)
                        ->where('cabang', $context['cabang'])
                        ->where('no_rek', $context['account'])
                        ->where(function ($query) {
                            $query->whereNull('reff')
                                ->orWhere('reff', '');
                        })
                        ->update([
                            'reff' => $reff,
                        ]);

                    if ($updated !== 1) {
                        throw new RuntimeException(
                            "Mutasi ID {$mutasiId} sudah berubah "
                            . 'atau sudah direkonsiliasi.'
                        );
                    }

                    $claimedMutasiIds[] = (string) $mutasiId;
                }

                $savedMatches[] = $match;
            }
        }, 3);

        $matchedReceiptIds = collect($savedMatches)
            ->flatMap(fn ($match) => $match['receipt_ids'])
            ->map(fn ($id) => (string) $id)
            ->all();

        $matchedMutasiIds = collect($savedMatches)
            ->flatMap(fn ($match) => $match['mutasi_ids'])
            ->map(fn ($id) => (string) $id)
            ->all();

        $unmatchedReceipts = $context['receipts']
            ->reject(fn ($row) =>
                in_array(
                    (string) $row->id,
                    $matchedReceiptIds,
                    true
                )
            )
            ->values();

        $unmatchedMutasi = $context['mutasi']
            ->reject(fn ($row) =>
                in_array(
                    (string) $row->id,
                    $matchedMutasiIds,
                    true
                )
            )
            ->values();

        $receiptAmount = collect($savedMatches)
            ->sum('receipt_amount');

        $mutasiAmount = collect($savedMatches)
            ->sum('mutasi_amount');

        return [
            'success' => true,
            'message' => "Rekonsiliasi REG {$method} selesai.",
            'periode' => [
                'tanggal_awal' => $context['tanggal_awal'],
                'tanggal_akhir' => $context['tanggal_akhir'],
            ],
            'rekening' => $context['account'],
            'method' => $method,
            'data' => [
                'matches' => $savedMatches,
                'ambiguous' => [],
                'unmatched_receipts' => $unmatchedReceipts,
                'unmatched_mutasi' => $unmatchedMutasi,
            ],
            'summary' => [
                'total_receipt' => $context['receipts']->count(),
                'total_mutasi' => $context['mutasi']->count(),
                'match' => count($savedMatches),
                'ambiguous' => 0,
                'receipt_only' => $unmatchedReceipts->count(),
                'mutasi_only' => $unmatchedMutasi->count(),
                'receipt_amount' => (float) $receiptAmount,
                'mutasi_amount' => (float) $mutasiAmount,
                'difference_amount' => (float) (
                    $mutasiAmount - $receiptAmount
                ),
            ],
        ];
    }

    /**
     * Menyimpan hasil rekonsiliasi N receipt : N mutasi.
     */
    private function finishManyToManyReconciliation(
        array $context,
        string $method,
        array $matches
    ): array {
        $savedMatches = [];

        DB::transaction(function () use (
            $context,
            $matches,
            &$savedMatches
        ) {
            $claimedReceiptIds = [];
            $claimedMutasiIds = [];

            foreach ($matches as $match) {
                $receiptIds = $match['receipt_ids'];
                $mutasiIds = $match['mutasi_ids'];
                $reff = $match['reff'];

                /*
                * Cegah ID berulang di dalam batch.
                */
                $duplicate = false;

                foreach ($receiptIds as $id) {
                    if (in_array(
                        (string) $id,
                        $claimedReceiptIds,
                        true
                    )) {
                        $duplicate = true;
                        break;
                    }
                }

                foreach ($mutasiIds as $id) {
                    if (in_array(
                        (string) $id,
                        $claimedMutasiIds,
                        true
                    )) {
                        $duplicate = true;
                        break;
                    }
                }

                if ($duplicate) {
                    continue;
                }

                /*
                * Update semua receipt.
                */
                foreach ($receiptIds as $receiptId) {
                    $updated = DB::table('receipt')
                        ->where('id', $receiptId)
                        ->where(function ($query) {
                            $query->whereNull('reff')
                                ->orWhere('reff', '');
                        })
                        ->update([
                            'reff' => $reff,
                        ]);

                    if ($updated !== 1) {
                        throw new RuntimeException(
                            "Receipt ID {$receiptId} sudah berubah "
                            . 'atau sudah direkonsiliasi.'
                        );
                    }

                    $claimedReceiptIds[] = (string) $receiptId;
                }

                /*
                * Update semua mutasi.
                */
                foreach ($mutasiIds as $mutasiId) {
                    $updated = DB::table('mutasi_detail')
                        ->where('id', $mutasiId)
                        ->where('cabang', $context['cabang'])
                        ->where('no_rek', $context['account'])
                        ->where('dept', 'SLS')
                        ->where(function ($query) {
                            $query->whereNull('reff')
                                ->orWhere('reff', '');
                        })
                        ->update([
                            'reff' => $reff,
                        ]);

                    if ($updated !== 1) {
                        throw new RuntimeException(
                            "Mutasi ID {$mutasiId} sudah berubah "
                            . 'atau sudah direkonsiliasi.'
                        );
                    }

                    $claimedMutasiIds[] = (string) $mutasiId;
                }

                $savedMatches[] = $match;
            }
        }, 3);

        /*
        * Hitung ID yang berhasil dipasangkan.
        */
        $matchedReceiptIds = collect($savedMatches)
            ->flatMap(fn ($match) => $match['receipt_ids'])
            ->map(fn ($id) => (string) $id)
            ->all();

        $matchedMutasiIds = collect($savedMatches)
            ->flatMap(fn ($match) => $match['mutasi_ids'])
            ->map(fn ($id) => (string) $id)
            ->all();

        /*
        * Sisa receipt dan mutasi yang belum dipasangkan.
        */
        $unmatchedReceipts = $context['receipts']
            ->reject(fn ($row) =>
                in_array(
                    (string) $row->id,
                    $matchedReceiptIds,
                    true
                )
            )
            ->values();

        $unmatchedMutasi = $context['mutasi']
            ->reject(fn ($row) =>
                in_array(
                    (string) $row->id,
                    $matchedMutasiIds,
                    true
                )
            )
            ->values();

        $receiptAmount = collect($savedMatches)
            ->sum('receipt_amount');

        $mutasiAmount = collect($savedMatches)
            ->sum('mutasi_amount');

        return [
            'success' => true,
            'message' => "Rekonsiliasi REG {$method} selesai.",
            'periode' => [
                'tanggal_awal' => $context['tanggal_awal'],
                'tanggal_akhir' => $context['tanggal_akhir'],
            ],
            'rekening' => $context['account'],
            'method' => $method,
            'data' => [
                'matches' => $savedMatches,
                'ambiguous' => [],
                'unmatched_receipts' => $unmatchedReceipts,
                'unmatched_mutasi' => $unmatchedMutasi,
            ],
            'summary' => [
                'total_receipt' => $context['receipts']->count(),
                'total_mutasi' => $context['mutasi']->count(),
                'match' => count($savedMatches),
                'ambiguous' => 0,
                'receipt_only' => $unmatchedReceipts->count(),
                'mutasi_only' => $unmatchedMutasi->count(),
                'receipt_amount' => (float) $receiptAmount,
                'mutasi_amount' => (float) $mutasiAmount,
                'difference_amount' => (float) (
                    $mutasiAmount - $receiptAmount
                ),
            ],
        ];
    }

    /**
     * Mengambil periode, memvalidasi rekening REG,
     * serta mengambil receipt dan mutasi yang belum memiliki reff.
     */
    private function loadContext(
        string $cabang,
        int $periodId,
        string $account,
        ?array $activities = null
    ): array {
        $period = DB::table('periode')
            ->where('id', $periodId)
            ->first();

        if (!$period) {
            throw new RuntimeException(
                'Periode rekonsiliasi tidak ditemukan.'
            );
        }

        $tanggalAwal = $period->start_date ?? null;
        $tanggalAkhir = $period->end_date ?? null;

        if (!$tanggalAwal || !$tanggalAkhir) {
            throw new RuntimeException(
                'Tanggal awal atau akhir periode belum tersedia.'
            );
        }

        if ($tanggalAwal > $tanggalAkhir) {
            throw new RuntimeException(
                'Tanggal awal periode melebihi tanggal akhir.'
            );
        }

        $bank = DB::table('bank')
            ->where('cabang', $cabang)
            ->where('no_rek', $account)
            ->where('site', 'REG')
            ->first();

        if (!$bank) {
            throw new RuntimeException(
                'Rekening REG tidak ditemukan untuk cabang tersebut.'
            );
        }

        
        $receiptQuery = DB::table('receipt')
            ->where('remittance_bank_account', $account)
            ->whereBetween('receipt_date', [
                $tanggalAwal,
                $tanggalAkhir,
            ])
            ->where(function ($query) {
                $query->whereNull('reff')
                    ->orWhere('reff', '');
            })
            ->where(function ($query) {
                $query->whereNull('receipt_status')
                    ->orWhereRaw(
                        'LOWER(receipt_status) <> ?',
                        ['reversed']
                    );
            })
            ->whereNull('note');

        // Filter activity hanya berlaku jika metode memintanya.
        if ($activities !== null) {
            $receiptQuery->whereIn('activity', $activities);
        }

        $receipts = $receiptQuery
            ->select([
                'id',
                'remittance_bank_account',
                'receipt_number',
                'receipt_amount',
                'receipt_date',
                'gl_date',
                'comments',
                'receipt_status',
                'paid_by',
                'reff',
                'activity',
                'category',
                'site',
            ])
            ->orderBy('receipt_date')
            ->orderBy('id')
            ->get();


        $mutasi = DB::table('mutasi_detail')
            ->where('cabang', $cabang)
            ->where('no_rek', $account)
            ->whereBetween('tgl', [
                $tanggalAwal,
                $tanggalAkhir,
            ])
            ->where('cr', '>', 0)
            ->where(function ($query) {
                $query->whereNull('reff')
                    ->orWhere('reff', '');
            })
            ->select([
                'id',
                'cabang',
                'no_rek',
                'tgl',
                'cr',
                'reff',
                'trx_code',
                'remark',
                'remark1',
                'dept',
                'site',
            ])
            ->orderBy('tgl')
            ->orderBy('id')
            ->get();

        return [
            'cabang' => $cabang,
            'account' => $account,
            'tanggal_awal' => $tanggalAwal,
            'tanggal_akhir' => $tanggalAkhir,
            'receipts' => $receipts,
            'mutasi' => $mutasi,
        ];
    }

    /**
     * Membuat struktur hasil pasangan.
     * Mendukung satu receipt dengan satu atau banyak mutasi.
     */
    private function makeMatch(
        object $receipt,
        Collection $mutasiRows
    ): array {
        if ($mutasiRows->isEmpty()) {
            throw new RuntimeException(
                'Tidak dapat membuat pasangan tanpa mutasi.'
            );
        }

        $mutasiIds = $mutasiRows
            ->pluck('id')
            ->values()
            ->all();

        $mutasiAmount = $mutasiRows->sum(
            fn ($row) => (float) $row->cr
        );

        /*
         * Satu reff digunakan bersama oleh receipt dan seluruh mutasi.
         */
        $reff = 'REG-' . min($mutasiIds);

        return [
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'receipt_date' => $receipt->receipt_date,
            'receipt_amount' => (float) $receipt->receipt_amount,
            'paid_by' => $receipt->paid_by,
            'mutasi_ids' => $mutasiIds,
            'mutasi_date' => $mutasiRows->first()->tgl,
            'mutasi_amount' => (float) $mutasiAmount,
            'reff' => $reff,
        ];
    }

    /**
     * Menyimpan reff pada receipt dan seluruh mutasi secara atomik.
     *
     * Pemeriksaan reff kosong diulang saat update agar data yang
     * sudah dipasangkan oleh proses lain tidak tertimpa.
     */
    private function finishReconciliation(
        array $context,
        string $method,
        array $matches,
        array $ambiguous = []
    ): array {
        $savedMatches = [];

        DB::transaction(function () use (
            $context,
            $matches,
            &$savedMatches
        ) {
            $claimedMutasiIds = [];
            $claimedReceiptIds = [];

            foreach ($matches as $match) {
                $receiptId = $match['receipt_id'];
                $reff = $match['reff'];

                if (in_array(
                    (string) $receiptId,
                    $claimedReceiptIds,
                    true
                )) {
                    continue;
                }

                /*
                 * Cegah ID mutasi yang sama muncul pada dua pasangan
                 * dalam batch yang sama.
                 */
                $duplicateInBatch = false;

                foreach ($match['mutasi_ids'] as $mutasiId) {
                    if (in_array(
                        (string) $mutasiId,
                        $claimedMutasiIds,
                        true
                    )) {
                        $duplicateInBatch = true;
                        break;
                    }
                }

                if ($duplicateInBatch) {
                    continue;
                }

                $receiptUpdated = DB::table('receipt')
                    ->where('id', $receiptId)
                    ->where(function ($query) {
                        $query->whereNull('reff')
                            ->orWhere('reff', '');
                    })
                    ->update([
                        'reff' => $reff,
                    ]);

                if ($receiptUpdated !== 1) {
                    throw new RuntimeException(
                        "Receipt ID {$receiptId} sudah berubah "
                        . 'atau sudah direkonsiliasi.'
                    );
                }

                foreach ($match['mutasi_ids'] as $mutasiId) {
                    $mutasiUpdated = DB::table('mutasi_detail')
                        ->where('id', $mutasiId)
                        ->where('cabang', $context['cabang'])
                        ->where('no_rek', $context['account'])
                        ->where(function ($query) {
                            $query->whereNull('reff')
                                ->orWhere('reff', '');
                        })
                        ->update([
                            'reff' => $reff,
                        ]);

                    if ($mutasiUpdated !== 1) {
                        throw new RuntimeException(
                            "Mutasi ID {$mutasiId} sudah berubah "
                            . 'atau sudah direkonsiliasi.'
                        );
                    }

                    $claimedMutasiIds[] = (string) $mutasiId;
                }

                $claimedReceiptIds[] = (string) $receiptId;
                $savedMatches[] = $match;
            }
        }, 3);

        $matchedReceiptIds = collect($savedMatches)
            ->pluck('receipt_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $matchedMutasiIds = collect($savedMatches)
            ->flatMap(fn ($row) => $row['mutasi_ids'])
            ->map(fn ($id) => (string) $id)
            ->all();

        $unmatchedReceipts = $context['receipts']
            ->reject(fn ($row) =>
                in_array(
                    (string) $row->id,
                    $matchedReceiptIds,
                    true
                )
            )
            ->values();

        $unmatchedMutasi = $context['mutasi']
            ->reject(fn ($row) =>
                in_array(
                    (string) $row->id,
                    $matchedMutasiIds,
                    true
                )
            )
            ->values();

        $matchedReceiptAmount = collect($savedMatches)
            ->sum('receipt_amount');

        $matchedMutasiAmount = collect($savedMatches)
            ->sum('mutasi_amount');

        return [
            'success' => true,
            'message' => "Rekonsiliasi REG {$method} selesai.",
            'periode' => [
                'tanggal_awal' => $context['tanggal_awal'],
                'tanggal_akhir' => $context['tanggal_akhir'],
            ],
            'rekening' => $context['account'],
            'method' => $method,
            'data' => [
                'matches' => $savedMatches,
                'ambiguous' => $ambiguous,
                'unmatched_receipts' => $unmatchedReceipts,
                'unmatched_mutasi' => $unmatchedMutasi,
            ],
            'summary' => [
                'total_receipt' => $context['receipts']->count(),
                'total_mutasi' => $context['mutasi']->count(),
                'match' => count($savedMatches),
                'ambiguous' => count($ambiguous),
                'receipt_only' => $unmatchedReceipts->count(),
                'mutasi_only' => $unmatchedMutasi->count(),
                'receipt_amount' => (float) $matchedReceiptAmount,
                'mutasi_amount' => (float) $matchedMutasiAmount,
                'difference_amount' => (float) (
                    $matchedMutasiAmount - $matchedReceiptAmount
                ),
            ],
        ];
    }

    /**
     * Mencari kombinasi pertama mutasi dengan selisih total kurang dari Rp1.
     *
     * Mengembalikan array indeks kandidat, atau null jika tidak ditemukan.
     * Fungsi berhenti segera setelah satu solusi ditemukan.
     */
    private function findFirstSubset(
        Collection $mutasi,
        int $targetCents
    ): ?array {
        if ($targetCents <= 0 || $mutasi->isEmpty()) {
            return null;
        }

        $items = $mutasi
            ->map(fn ($row) => $this->toCents($row->cr))
            ->values()
            ->all();

        $count = count($items);

        /*
         * Suffix sum dipakai untuk memangkas cabang yang tidak mungkin
         * mencapai target, tanpa mengubah urutan pencarian kandidat.
         */
        $suffixSum = array_fill(0, $count + 1, 0);

        for ($i = $count - 1; $i >= 0; $i--) {
            $suffixSum[$i] = $suffixSum[$i + 1]
                + max(0, $items[$i]);
        }

        $current = [];

        $search = function (
            int $start,
            int $sum
        ) use (
            &$search,
            &$current,
            $items,
            $suffixSum,
            $count,
            $targetCents
        ): ?array {
            if (
                !empty($current)
                && abs($sum - $targetCents)
                    < self::AMOUNT_TOLERANCE_CENTS
            ) {
                return $current;
            }

            if (
                $sum >= $targetCents + self::AMOUNT_TOLERANCE_CENTS
                || $start >= $count
            ) {
                return null;
            }

            /*
             * Jika seluruh kandidat tersisa pun tidak dapat masuk
             * ke dalam batas toleransi, hentikan cabang pencarian.
             */
            if (
                $sum + $suffixSum[$start]
                    <= $targetCents - self::AMOUNT_TOLERANCE_CENTS
            ) {
                return null;
            }

            for ($i = $start; $i < $count; $i++) {
                $amount = $items[$i];

                if (
                    $amount <= 0
                    || $sum + $amount
                        >= $targetCents + self::AMOUNT_TOLERANCE_CENTS
                ) {
                    continue;
                }

                $current[] = $i;

                $solution = $search(
                    $i + 1,
                    $sum + $amount
                );

                if ($solution !== null) {
                    return $solution;
                }

                array_pop($current);
            }

            return null;
        };

        return $search(0, 0);
    }

    /**
     * Kunci rekening + tanggal tanpa nominal.
     * Nominal diperiksa terpisah menggunakan toleransi.
     */
    private function buildMatchKey(
        $account,
        $date,
        $amount
    ): string {
        return implode('|', [
            trim((string) $account),
            substr(trim((string) $date), 0, 10),
        ]);
    }

    /**
     * Kunci rekening + tanggal + site/paid_by tanpa nominal.
     * Nominal diperiksa terpisah menggunakan toleransi.
     */
    private function buildSlsMatchKey(
        $account,
        $date,
        $amount,
        $site
    ): string {
        return implode('|', [
            trim((string) $account),
            substr(trim((string) $date), 0, 10),
            strtoupper(trim((string) $site)),
        ]);
    }

    /**
     * Kunci rekening + tanggal + site/paid_by.
     */
    private function buildSiteDateKey(
        $account,
        $date,
        $site
    ): string {
        return implode('|', [
            trim((string) $account),
            substr(trim((string) $date), 0, 10),
            strtoupper(trim((string) $site)),
        ]);
    }

    /**
     * Mengubah nominal menjadi integer satuan sen.
     */
    private function toCents($amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
