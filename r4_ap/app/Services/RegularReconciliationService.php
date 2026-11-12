<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegularReconciliationService
{
    /** Toleransi nominal: selisih harus kurang dari Rp1 (100 sen). */
    private const AMOUNT_TOLERANCE_CENTS = 110;

    /** Membandingkan dua nominal dengan toleransi kurang dari Rp1. */
    private function isAmountWithinTolerance($amount1, $amount2): bool
    {
        return abs(
            $this->toCents($amount1) - $this->toCents($amount2)
        ) < self::AMOUNT_TOLERANCE_CENTS;
    }

    private function normalizeMatchText($value): string
    {
        $value = strtoupper((string) ($value ?? ''));

        // Hilangkan whitespace, termasuk spasi, tab, dan baris baru.
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        // Hilangkan titik, koma, petik satu biasa dan tipografis.
        return str_replace(
            ['.', ',', "'", '’', ';','_'],
            '',
            $value
        );
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
        /*
        |--------------------------------------------------------------------------
        | 1. DAFTAR ACTIVITY VIRTUAL
        |--------------------------------------------------------------------------
        */

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
            'Biaya Debit Mandiri On Us',
            'SELISIH PEMBULATAN (MISC)',
        ];

        $miscActivity = 'SELISIH PEMBULATAN (MISC)';

        /*
        |--------------------------------------------------------------------------
        | 2. LOAD CONTEXT
        |--------------------------------------------------------------------------
        */

        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account,
            $activities
        );

        /*
        |--------------------------------------------------------------------------
        | 3. FILTER MUTASI
        |--------------------------------------------------------------------------
        |
        | Hanya mutasi dengan dept VIR.
        |
        */

        $mutasi = $context['mutasi']
            ->filter(function ($row) {
                return strtoupper(
                    trim((string) ($row->dept ?? ''))
                ) === 'VIR';
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 4. FILTER RECEIPT
        |--------------------------------------------------------------------------
        |
        | Activity biasa:
        | Tetap diproses seperti sebelumnya.
        |
        | SELISIH PEMBULATAN (MISC):
        | Comments wajib mengandung VIR atau VIRTUAL.
        |
        */

        $receipts = $context['receipts']
            ->filter(function ($row) use ($miscActivity) {

                $activity = strtoupper(
                    trim((string) ($row->activity ?? ''))
                );

                /*
                * Activity selain MISC tidak memerlukan
                * pemeriksaan comments tambahan.
                */
                if ($activity !== strtoupper($miscActivity)) {
                    return true;
                }

                /*
                * Khusus SELISIH PEMBULATAN (MISC),
                * comments wajib mengandung VIRTUAL
                */
                $comments = strtoupper(
                    (string) ($row->comments ?? '')
                );

                return str_contains($comments, ' VIRTUAL');
            })
            ->values();

        $updatedMutasi = 0;
        $updatedReceipt = 0;

        /*
        |--------------------------------------------------------------------------
        | 5. UPDATE TRANSAKSI
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (
            $cabang,
            $account,
            $mutasi,
            $receipts,
            $activities,
            $miscActivity,
            &$updatedMutasi,
            &$updatedReceipt
        ) {

            /*
            |--------------------------------------------------------------------------
            | 5A. UPDATE MUTASI
            |--------------------------------------------------------------------------
            */

            foreach ($mutasi as $row) {

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
            | 5B. UPDATE RECEIPT
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
                    ->whereIn('activity', $activities)

                    /*
                    * Pastikan receipt belum memiliki reff.
                    */
                    ->where(function ($query) {
                        $query->whereNull('reff')
                            ->orWhere('reff', '');
                    })

                    /*
                    * Khusus SELISIH PEMBULATAN (MISC),
                    * validasi comments dilakukan kembali di database.
                    *
                    * Activity lainnya tidak terpengaruh.
                    */
                    ->where(function ($query) use ($miscActivity) {

                        $query->where(
                            'activity',
                            '<>',
                            $miscActivity
                        );

                        $query->orWhere(function ($subQuery) use (
                            $miscActivity
                        ) {
                            $subQuery
                                ->where('activity', $miscActivity)
                                ->where(function ($commentsQuery) {
                                    $commentsQuery
                                        ->whereRaw(
                                            "UPPER(COALESCE(comments, '')) LIKE ?",
                                            ['%VIR%']
                                        )
                                        ->orWhereRaw(
                                            "UPPER(COALESCE(comments, '')) LIKE ?",
                                            ['%VIRTUAL%']
                                        );
                                });
                        });
                    })

                    ->update([
                        'reff' => $reff,
                    ]);

                $updatedReceipt += $affected;
            }

        }, 3);

        /*
        |--------------------------------------------------------------------------
        | 6. RETURN HASIL
        |--------------------------------------------------------------------------
        */

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
        |--------------------------------------------------------------------------
        | 1. Kelompokkan mutasi berdasarkan rekening + tanggal + nominal.
        |--------------------------------------------------------------------------
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
        |--------------------------------------------------------------------------
        | 2. Proses receipt satu per satu.
        |--------------------------------------------------------------------------
        */
    
        foreach ($receipts as $receipt) {
            $key = $this->buildMatchKey(
                $receipt->remittance_bank_account,
                $receipt->receipt_date,
                $receipt->receipt_amount
            );
    
            $candidates = $mutasiGroups->get($key, collect());
    
            /*
            |--------------------------------------------------------------------------
            | 3. Normalisasi comments receipt.
            |--------------------------------------------------------------------------
            | Menggunakan helper normalizeMatchText() agar aturan normalisasi
            | konsisten dengan metode rekonsiliasi lainnya.
            */
    
            $normalizedComments = $this->normalizeMatchText(
                $receipt->comments ?? ''
            );
    
            // Comments kosong tidak boleh digunakan untuk pencocokan remark.
            if ($normalizedComments === '') {
                continue;
            }
    
            /*
            |--------------------------------------------------------------------------
            | 4. Cari mutasi yang memenuhi seluruh kriteria.
            |--------------------------------------------------------------------------
            | - Mutasi belum digunakan.
            | - Nominal sesuai toleransi.
            | - Remark sama dengan comments setelah normalisasi.
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
                    && $normalizedComments === $this->normalizeMatchText(
                        $row->remark ?? ''
                    )
            );
    
            if (!$mutasiRow) {
                continue;
            }
    
            /*
            |--------------------------------------------------------------------------
            | 5. Buat pasangan satu receipt : satu mutasi.
            |--------------------------------------------------------------------------
            */
    
            $matches[] = $this->makeMatch(
                $receipt,
                collect([$mutasiRow])
            );
    
            /*
            |--------------------------------------------------------------------------
            | 6. Tandai ID agar tidak digunakan ulang.
            |--------------------------------------------------------------------------
            */
    
            $usedReceiptIds[] = (string) $receipt->id;
            $usedMutasiIds[] = (string) $mutasiRow->id;
        }
    
        /*
        |--------------------------------------------------------------------------
        | 7. Simpan hasil rekonsiliasi.
        |--------------------------------------------------------------------------
        */
    
        return $this->finishReconciliation(
            $context,
            'date_amount_remark',
            $matches,
            $ambiguous
        );
    }      
        
    public function reconcileSumDateAmountRemarkReverse(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        /*
        |--------------------------------------------------------------------------
        | 1. LOAD CONTEXT
        |--------------------------------------------------------------------------
        */

        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account
        );

        /*
        |--------------------------------------------------------------------------
        | 2. FILTER RECEIPT
        |--------------------------------------------------------------------------
        |
        | Receipt boleh memiliki nominal positif maupun negatif.
        | Nominal nol tidak diproses.
        |
        */

        $receipts = $context['receipts']
            ->filter(fn ($row) =>
                trim((string) ($row->comments ?? '')) !== ''
                && $this->toCents($row->receipt_amount) !== 0
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 3. FILTER MUTASI
        |--------------------------------------------------------------------------
        |
        | Mutasi hanya dengan kredit positif.
        | Remark tidak boleh kosong.
        |
        */

        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                trim((string) ($row->remark ?? '')) !== ''
                && $this->toCents($row->cr) > 0
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 4. KELOMPOKKAN RECEIPT
        |--------------------------------------------------------------------------
        |
        | Key:
        | rekening + tanggal + comments ternormalisasi.
        |
        */

        $receiptGroups = $receipts->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->remittance_bank_account,
                $row->receipt_date,
                $this->normalizeMatchText($row->comments)
            )
        );

        /*
        |--------------------------------------------------------------------------
        | 5. KELOMPOKKAN MUTASI
        |--------------------------------------------------------------------------
        |
        | Key:
        | rekening + tanggal + remark ternormalisasi.
        |
        */

        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                $row->no_rek,
                $row->tgl,
                $this->normalizeMatchText($row->remark)
            )
        );

        $matches = [];

        /*
        |--------------------------------------------------------------------------
        | 6. BATAS PENCARIAN KOMBINASI
        |--------------------------------------------------------------------------
        |
        | Maksimal jumlah kombinasi yang disimpan.
        | Digunakan untuk mengendalikan penggunaan memori.
        |
        */

        $maxStates = 50000;

        /*
        |--------------------------------------------------------------------------
        | 7. HELPER SUBSET SUM
        |--------------------------------------------------------------------------
        |
        | Mendukung nominal positif dan negatif.
        |
        | Contoh:
        |
        | Receipt:
        | 57004
        | -4
        |
        | Kombinasi:
        | 57004 + (-4) = 57000
        |
        | Setiap baris hanya boleh digunakan satu kali.
        |
        */

        $buildSubsetMap = function (
            array $rows,
            int $minTotal,
            int $maxTotal
        ) use ($maxStates): array {

            /*
            * Nominal 0 digunakan sebagai kondisi awal.
            */
            $states = [
                0 => []
            ];

            $limitReached = false;

            foreach ($rows as $index => $row) {

                if ($limitReached) {
                    break;
                }

                $amount = (int) $row['amount'];

                if ($amount === 0) {
                    continue;
                }

                /*
                * Snapshot agar satu baris tidak digunakan
                * berulang kali dalam kombinasi yang sama.
                */
                $existingStates = $states;

                foreach ($existingStates as $sum => $indexes) {

                    $newSum = (int) $sum + $amount;

                    /*
                    * Batasi rentang nominal kombinasi.
                    */
                    if (
                        $newSum < $minTotal
                        || $newSum > $maxTotal
                    ) {
                        continue;
                    }

                    /*
                    * Simpan hanya satu kombinasi untuk setiap nominal.
                    */
                    if (!isset($states[$newSum])) {

                        $states[$newSum] = array_merge(
                            $indexes,
                            [$index]
                        );
                    }

                    /*
                    * Hentikan pembentukan kombinasi
                    * jika batas memori tercapai.
                    */
                    if (count($states) >= $maxStates) {
                        $limitReached = true;
                        break;
                    }
                }
            }

            /*
            * Hapus kondisi awal nominal nol.
            */
            unset($states[0]);

            return $states;
        };

        /*
        |--------------------------------------------------------------------------
        | 8. PROSES SETIAP KELOMPOK MUTASI
        |--------------------------------------------------------------------------
        */

        foreach ($mutasiGroups as $key => $mutasiGroup) {

            $receiptGroup = $receiptGroups->get(
                $key,
                collect()
            );

            if ($receiptGroup->isEmpty()) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 8A. SIAPKAN RECEIPT
            |--------------------------------------------------------------------------
            |
            | Nominal negatif tetap dipertahankan.
            |
            */

            $remainingReceipts = $receiptGroup
                ->map(fn ($row) => [
                    'row' => $row,
                    'amount' => $this->toCents(
                        $row->receipt_amount
                    ),
                ])
                ->values()
                ->all();

            /*
            |--------------------------------------------------------------------------
            | 8B. SIAPKAN MUTASI
            |--------------------------------------------------------------------------
            */

            $remainingMutasi = $mutasiGroup
                ->map(fn ($row) => [
                    'row' => $row,
                    'amount' => $this->toCents(
                        $row->cr
                    ),
                ])
                ->values()
                ->all();

            /*
            |--------------------------------------------------------------------------
            | 8C. PROSES PENCARIAN BERULANG
            |--------------------------------------------------------------------------
            |
            | Setelah kombinasi ditemukan, baris yang sudah digunakan
            | dikeluarkan dan pencarian dilanjutkan.
            |
            */

            while (
                !empty($remainingReceipts)
                && !empty($remainingMutasi)
            ) {

                /*
                |--------------------------------------------------------------------------
                | HITUNG BATAS NOMINAL RECEIPT
                |--------------------------------------------------------------------------
                |
                | Karena receipt dapat negatif, jangan menggunakan
                | total receipt sebagai batas maksimum pencarian.
                |
                | Contoh:
                | 57004 + (-4) = 57000
                |
                | Maksimum kombinasi receipt tetap 57004.
                | Minimum kombinasi receipt adalah -4.
                |
                */

                $minReceiptSum = 0;
                $maxReceiptSum = 0;

                foreach ($remainingReceipts as $row) {

                    $amount = $row['amount'];

                    if ($amount < 0) {
                        $minReceiptSum += $amount;
                    } else {
                        $maxReceiptSum += $amount;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | HITUNG TOTAL MUTASI
                |--------------------------------------------------------------------------
                */

                $totalMutasi = array_sum(
                    array_column($remainingMutasi, 'amount')
                );

                if ($totalMutasi <= 0) {
                    break;
                }

                /*
                |--------------------------------------------------------------------------
                | 8D. BENTUK KOMBINASI RECEIPT
                |--------------------------------------------------------------------------
                |
                | Mendukung kombinasi positif + negatif.
                |
                */

                $receiptSubsetMap = $buildSubsetMap(
                    $remainingReceipts,
                    $minReceiptSum,
                    $maxReceiptSum
                );

                /*
                |--------------------------------------------------------------------------
                | 8E. BENTUK KOMBINASI MUTASI
                |--------------------------------------------------------------------------
                |
                | Mutasi hanya memiliki nominal positif.
                |
                */

                $mutasiSubsetMap = $buildSubsetMap(
                    $remainingMutasi,
                    1,
                    $totalMutasi
                );

                if (
                    empty($receiptSubsetMap)
                    || empty($mutasiSubsetMap)
                ) {
                    break;
                }

                /*
                |--------------------------------------------------------------------------
                | 8F. CARI KOMBINASI TERBAIK
                |--------------------------------------------------------------------------
                */

                $bestMatch = null;

                $bestRowsCount = PHP_INT_MAX;
                $bestDifference = PHP_INT_MAX;
                $bestAmount = 0;

                foreach (
                    $receiptSubsetMap as $receiptSum => $receiptIndexes
                ) {

                    /*
                    * Receipt dapat memiliki total negatif.
                    * Namun, total yang dicocokkan harus positif
                    * karena mutasi hanya memiliki kredit positif.
                    */

                    $minMutasiSum = max(
                        1,
                        (int) $receiptSum
                            - self::AMOUNT_TOLERANCE_CENTS + 1
                    );

                    $maxMutasiSum = (int) $receiptSum
                        + self::AMOUNT_TOLERANCE_CENTS - 1;

                    for (
                        $mutasiSum = $minMutasiSum;
                        $mutasiSum <= $maxMutasiSum;
                        $mutasiSum++
                    ) {

                        if (!isset($mutasiSubsetMap[$mutasiSum])) {
                            continue;
                        }

                        $mutasiIndexes = $mutasiSubsetMap[$mutasiSum];

                        $difference = abs(
                            (int) $receiptSum - $mutasiSum
                        );

                        $totalRows = count($receiptIndexes)
                            + count($mutasiIndexes);

                        $matchedAmount = min(
                            (int) $receiptSum,
                            $mutasiSum
                        );

                        /*
                        * Prioritas pencocokan:
                        *
                        * 1. Jumlah baris paling sedikit.
                        * 2. Selisih nominal paling kecil.
                        * 3. Nominal pencocokan paling besar.
                        */

                        $isBetter =
                            $totalRows < $bestRowsCount
                            || (
                                $totalRows === $bestRowsCount
                                && $difference < $bestDifference
                            )
                            || (
                                $totalRows === $bestRowsCount
                                && $difference === $bestDifference
                                && $matchedAmount > $bestAmount
                            );

                        if ($isBetter) {

                            $bestRowsCount = $totalRows;
                            $bestDifference = $difference;
                            $bestAmount = $matchedAmount;

                            $bestMatch = [
                                'receipt_indexes' => $receiptIndexes,
                                'mutasi_indexes' => $mutasiIndexes,
                                'receipt_total' => (int) $receiptSum,
                                'mutasi_total' => (int) $mutasiSum,
                                'difference' => $difference,
                            ];
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 8G. JIKA TIDAK ADA KOMBINASI
                |--------------------------------------------------------------------------
                */

                if (!$bestMatch) {
                    break;
                }

                /*
                |--------------------------------------------------------------------------
                | 8H. AMBIL RECEIPT YANG MATCH
                |--------------------------------------------------------------------------
                */

                $matchedReceipts = [];

                foreach ($bestMatch['receipt_indexes'] as $index) {

                    $matchedReceipts[] = $remainingReceipts[$index]['row'];
                }

                /*
                |--------------------------------------------------------------------------
                | 8I. AMBIL MUTASI YANG MATCH
                |--------------------------------------------------------------------------
                */

                $matchedMutasi = [];

                foreach ($bestMatch['mutasi_indexes'] as $index) {

                    $matchedMutasi[] = $remainingMutasi[$index]['row'];
                }

                /*
                |--------------------------------------------------------------------------
                | 8J. AMBIL ID
                |--------------------------------------------------------------------------
                */

                $receiptIds = collect($matchedReceipts)
                    ->pluck('id')
                    ->values()
                    ->all();

                $mutasiIds = collect($matchedMutasi)
                    ->pluck('id')
                    ->values()
                    ->all();

                /*
                * Satu reff untuk seluruh receipt dan mutasi.
                * Menggunakan ID mutasi pertama sebagai identitas.
                */
                $reff = 'REG-' . $mutasiIds[0];

                /*
                |--------------------------------------------------------------------------
                | 8K. HITUNG TOTAL NOMINAL
                |--------------------------------------------------------------------------
                */

                $receiptTotalCents = $bestMatch['receipt_total'];
                $mutasiTotalCents = $bestMatch['mutasi_total'];

                /*
                |--------------------------------------------------------------------------
                | 8L. SIMPAN HASIL MATCH
                |--------------------------------------------------------------------------
                */

                $matches[] = [

                    'receipt_ids' => $receiptIds,

                    'receipt_numbers' => collect($matchedReceipts)
                        ->pluck('receipt_number')
                        ->values()
                        ->all(),

                    'receipt_dates' => collect($matchedReceipts)
                        ->pluck('receipt_date')
                        ->values()
                        ->all(),

                    'receipt_amounts' => collect($matchedReceipts)
                        ->map(fn ($row) =>
                            (float) $row->receipt_amount
                        )
                        ->values()
                        ->all(),

                    'receipt_amount' => $receiptTotalCents / 100,

                    'comments' => $matchedReceipts[0]->comments,

                    'mutasi_ids' => $mutasiIds,

                    'mutasi_dates' => collect($matchedMutasi)
                        ->pluck('tgl')
                        ->values()
                        ->all(),

                    'mutasi_amounts' => collect($matchedMutasi)
                        ->map(fn ($row) =>
                            (float) $row->cr
                        )
                        ->values()
                        ->all(),

                    'mutasi_date' => $matchedMutasi[0]->tgl,

                    'mutasi_amount' => $mutasiTotalCents / 100,

                    'difference' => (
                        $mutasiTotalCents - $receiptTotalCents
                    ) / 100,

                    'reff' => $reff,
                ];

                /*
                |--------------------------------------------------------------------------
                | 8M. HAPUS RECEIPT YANG SUDAH DIGUNAKAN
                |--------------------------------------------------------------------------
                */

                $usedReceiptIndexes = array_flip(
                    $bestMatch['receipt_indexes']
                );

                $remainingReceipts = array_values(
                    array_filter(
                        $remainingReceipts,
                        fn ($row, $index) =>
                            !isset($usedReceiptIndexes[$index]),
                        ARRAY_FILTER_USE_BOTH
                    )
                );

                /*
                |--------------------------------------------------------------------------
                | 8N. HAPUS MUTASI YANG SUDAH DIGUNAKAN
                |--------------------------------------------------------------------------
                */

                $usedMutasiIndexes = array_flip(
                    $bestMatch['mutasi_indexes']
                );

                $remainingMutasi = array_values(
                    array_filter(
                        $remainingMutasi,
                        fn ($row, $index) =>
                            !isset($usedMutasiIndexes[$index]),
                        ARRAY_FILTER_USE_BOTH
                    )
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 9. SIMPAN HASIL REKONSILIASI
        |--------------------------------------------------------------------------
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
            $account
        );
    
        /*
        |--------------------------------------------------------------------------
        | 1. FILTER RECEIPT
        |--------------------------------------------------------------------------
        | Hanya receipt dengan comments diawali PELUNASAN KURSET.
        |--------------------------------------------------------------------------
        */
    
        $receipts = $context['receipts']
            ->filter(fn ($row) =>
                str_starts_with(
                    strtoupper(trim((string) ($row->comments ?? ''))),
                    'PELUNASAN KURSET'
                )
            )
            ->values();
    
        /*
        |--------------------------------------------------------------------------
        | 2. FILTER MUTASI FAS
        |--------------------------------------------------------------------------
        */
    
        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                strtoupper(trim((string) ($row->dept ?? ''))) === 'FAS'
            )
            ->values();
    
        /*
        |--------------------------------------------------------------------------
        | 3. KELOMPOKKAN MUTASI
        |--------------------------------------------------------------------------
        | Berdasarkan rekening + tanggal + nominal + site.
        |--------------------------------------------------------------------------
        */
    
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
    
        /*
        |--------------------------------------------------------------------------
        | 4. CARI PASANGAN RECEIPT DAN MUTASI
        |--------------------------------------------------------------------------
        */
    
        foreach ($receipts as $receipt) {
    
            /*
            |--------------------------------------------------------------------------
            | SITE RECEIPT
            |--------------------------------------------------------------------------
            | Prioritas:
            | 1. Gunakan receipt.site jika tersedia.
            | 2. Jika kosong, gunakan 4 karakter paling kanan comments.
            |--------------------------------------------------------------------------
            */
    
            $site = trim(
                (string) ($receipt->site ?? '')
            );
    
            if ($site === '') {
                $site = substr(
                    trim((string) ($receipt->comments ?? '')),
                    -4
                );
            }
    
            $site = strtoupper(trim($site));
    
            /*
            |--------------------------------------------------------------------------
            | BENTUK KEY RECEIPT
            |--------------------------------------------------------------------------
            */
    
            $key = $this->buildSlsMatchKey(
                $receipt->remittance_bank_account,
                $receipt->receipt_date,
                $receipt->receipt_amount,
                $site
            );
    
            /*
            |--------------------------------------------------------------------------
            | CARI MUTASI DENGAN KEY YANG SAMA
            |--------------------------------------------------------------------------
            */
    
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
    
            /*
            |--------------------------------------------------------------------------
            | BENTUK DATA MATCH
            |--------------------------------------------------------------------------
            */
    
            $matches[] = $this->makeMatch(
                $receipt,
                collect([$mutasiRow])
            );
    
            /*
            |--------------------------------------------------------------------------
            | TANDAI MUTASI SUDAH DIGUNAKAN
            |--------------------------------------------------------------------------
            */
    
            $usedMutasiIds[] = (string) $mutasiRow->id;
        }
    
        /*
        |--------------------------------------------------------------------------
        | 5. SIMPAN HASIL REKONSILIASI
        |--------------------------------------------------------------------------
        */
    
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
        /*
        |--------------------------------------------------------------------------
        | 1. LOAD CONTEXT
        |--------------------------------------------------------------------------
        */
    
        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account
        );
    
        /*
        |--------------------------------------------------------------------------
        | 2. FILTER RECEIPT
        |--------------------------------------------------------------------------
        | Receipt boleh positif maupun negatif.
        | Nilai nol tidak dipakai sebagai kandidat kombinasi.
        |--------------------------------------------------------------------------
        */
    
        $receipts = $context['receipts']
            ->filter(function ($row) {
                return trim((string) ($row->comments ?? '')) !== ''
                    && $this->toCents($row->receipt_amount) !== 0;
            })
            ->values();
    
        /*
        |--------------------------------------------------------------------------
        | 3. FILTER MUTASI FAS
        |--------------------------------------------------------------------------
        */
    
        $mutasi = $context['mutasi']
            ->filter(function ($row) {
                return strtoupper(
                    trim((string) ($row->dept ?? ''))
                ) === 'FAS';
            })
            ->filter(function ($row) {
                return trim((string) ($row->remark ?? '')) !== ''
                    && $this->normalizeMatchText(
                        $row->remark
                    ) !== ''
                    && $this->toCents($row->cr) > 0;
            })
            ->values();
    
        /*
        |--------------------------------------------------------------------------
        | 4. KELOMPOKKAN RECEIPT BERDASARKAN REKENING + TANGGAL
        |--------------------------------------------------------------------------
        | Site tidak dimasukkan ke key karena ada dua aturan pencocokan:
        |
        | A. PELUNASAN KURSET:
        |    Site receipt harus sama dengan site mutasi.
        |
        | B. Comments sama dengan remark:
        |    Site tidak perlu sama.
        |--------------------------------------------------------------------------
        */
    
        $receiptGroups = $receipts->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                trim((string) $row->remittance_bank_account),
                $row->receipt_date
            )
        );
    
        /*
        |--------------------------------------------------------------------------
        | 5. KELOMPOKKAN MUTASI BERDASARKAN REKENING + TANGGAL
        |--------------------------------------------------------------------------
        */
    
        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                trim((string) $row->no_rek),
                $row->tgl
            )
        );
    
        /*
        |--------------------------------------------------------------------------
        | 6. INISIALISASI HASIL
        |--------------------------------------------------------------------------
        */
    
        $matches = [];
        $usedReceiptIds = [];
        $usedMutasiIds = [];
    
        /*
        |--------------------------------------------------------------------------
        | 7. PROSES PENCARIAN MATCH
        |--------------------------------------------------------------------------
        */
    
        foreach ($mutasiGroups as $key => $mutasiGroup) {
    
            $receiptGroup = $receiptGroups->get($key, collect());
    
            if ($receiptGroup->isEmpty()) {
                continue;
            }
    
            foreach ($mutasiGroup as $mutasiRow) {
    
                /*
                |--------------------------------------------------------------------------
                | CEK MUTASI SUDAH DIGUNAKAN
                |--------------------------------------------------------------------------
                */
    
                if (in_array(
                    (string) $mutasiRow->id,
                    $usedMutasiIds,
                    true
                )) {
                    continue;
                }
    
                $normalizedRemark = $this->normalizeMatchText(
                    $mutasiRow->remark
                );
    
                $mutasiSite = strtoupper(
                    trim((string) ($mutasiRow->site ?? ''))
                );
    
                if ($normalizedRemark === '') {
                    continue;
                }
    
                /*
                |--------------------------------------------------------------------------
                | 7A. PILIH KANDIDAT RECEIPT
                |--------------------------------------------------------------------------
                |
                | Aturan A:
                | Comments diawali PELUNASAN KURSET / PELUNSAN KURSET.
                | Site receipt harus sama dengan site mutasi.
                |
                | Penentuan site receipt:
                | 1. Gunakan field receipt.site jika tersedia.
                | 2. Jika kosong, gunakan 4 karakter paling kanan comments.
                |
                | Aturan B:
                | Comments sama dengan remark setelah normalisasi.
                | Site tidak diperiksa untuk aturan ini.
                |--------------------------------------------------------------------------
                */
    
                $availableReceipts = $receiptGroup
                    ->reject(function ($row) use ($usedReceiptIds) {
                        return in_array(
                            (string) $row->id,
                            $usedReceiptIds,
                            true
                        );
                    })
                    ->filter(function ($row) use (
                        $normalizedRemark,
                        $mutasiSite
                    ) {
    
                        $comments = trim(
                            (string) ($row->comments ?? '')
                        );
    
                        if ($comments === '') {
                            return false;
                        }
    
                        $normalizedComments =
                            $this->normalizeMatchText($comments);
    
                        /*
                        |--------------------------------------------------------------------------
                        | ATURAN A: PELUNASAN KURSET BERDASARKAN SITE
                        |--------------------------------------------------------------------------
                        */
    
                        $isPelunasanKurset =
                            str_starts_with(
                                $normalizedComments,
                                'PELUNASANKURSET'
                            )
                            || str_starts_with(
                                $normalizedComments,
                                'PELUNSANKURSET'
                            );
    
                        if ($isPelunasanKurset) {
    
                            /*
                            |--------------------------------------------------------------------------
                            | SITE RECEIPT
                            |--------------------------------------------------------------------------
                            | Prioritas:
                            | 1. Gunakan receipt.site jika tersedia.
                            | 2. Jika kosong, gunakan 4 karakter terakhir comments.
                            |--------------------------------------------------------------------------
                            */
    
                            $receiptSite = trim(
                                (string) ($row->site ?? '')
                            );
    
                            if ($receiptSite === '') {
                                $receiptSite = substr(
                                    $comments,
                                    -4
                                );
                            }
    
                            $receiptSite = strtoupper(
                                trim($receiptSite)
                            );
    
                            if (
                                $receiptSite !== ''
                                && $mutasiSite !== ''
                                && $receiptSite === $mutasiSite
                            ) {
                                return true;
                            }
                        }
    
                        /*
                        |--------------------------------------------------------------------------
                        | ATURAN B: COMMENTS SAMA DENGAN REMARK
                        |--------------------------------------------------------------------------
                        | Aturan ini tetap berlaku walaupun site berbeda.
                        |--------------------------------------------------------------------------
                        */
    
                        return $normalizedComments === $normalizedRemark;
                    })
                    ->values();
    
                if ($availableReceipts->isEmpty()) {
                    continue;
                }
    
                /*
                |--------------------------------------------------------------------------
                | 7B. CARI KOMBINASI NOMINAL
                |--------------------------------------------------------------------------
                | Menggunakan integer sen agar tidak terkena masalah presisi float.
                | Nominal negatif ikut diakumulasikan dengan tanda aslinya.
                |
                | Dynamic programming:
                | sum => daftar indeks receipt yang membentuk jumlah tersebut.
                |--------------------------------------------------------------------------
                */
    
                $targetCents = $this->toCents($mutasiRow->cr);
    
                $sumStates = [
                    0 => [],
                ];
    
                foreach ($availableReceipts as $index => $receipt) {
    
                    $amountCents = $this->toCents(
                        $receipt->receipt_amount
                    );
    
                    if ($amountCents === 0) {
                        continue;
                    }
    
                    // Salin state agar receipt yang sama tidak digunakan dua kali.
                    $previousStates = $sumStates;
    
                    foreach ($previousStates as $currentSum => $indices) {
    
                        $newSum = (int) $currentSum + $amountCents;
    
                        // Simpan satu kombinasi untuk setiap jumlah.
                        if (!array_key_exists($newSum, $sumStates)) {
                            $sumStates[$newSum] = array_merge(
                                $indices,
                                [$index]
                            );
                        }
                    }
    
                    if (isset($sumStates[$targetCents])) {
                        break;
                    }
                }
    
                if (!isset($sumStates[$targetCents])) {
                    continue;
                }
    
                $solutionIndices = $sumStates[$targetCents];
    
                if (empty($solutionIndices)) {
                    continue;
                }
    
                /*
                |--------------------------------------------------------------------------
                | 7C. AMBIL RECEIPT YANG TERPILIH
                |--------------------------------------------------------------------------
                */
    
                $matchedReceipts = collect($solutionIndices)
                    ->map(function ($index) use ($availableReceipts) {
                        return $availableReceipts->get($index);
                    })
                    ->filter()
                    ->values();
    
                if ($matchedReceipts->isEmpty()) {
                    continue;
                }
    
                /*
                |--------------------------------------------------------------------------
                | 7D. VALIDASI ULANG JUMLAH
                |--------------------------------------------------------------------------
                */
    
                $matchedTotalCents = $matchedReceipts->sum(
                    fn ($row) => $this->toCents(
                        $row->receipt_amount
                    )
                );
    
                if ($matchedTotalCents !== $targetCents) {
                    continue;
                }
    
                /*
                |--------------------------------------------------------------------------
                | 7E. SIAPKAN HASIL MATCH
                |--------------------------------------------------------------------------
                */
    
                $firstReceipt = $matchedReceipts->first();
    
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
    
                    // Total mempertahankan tanda positif dan negatif.
                    'receipt_amount' => $matchedReceipts->sum(
                        fn ($row) => (float) $row->receipt_amount
                    ),
    
                    'comments' => $firstReceipt->comments ?? '',
                    'paid_by' => $firstReceipt->paid_by ?? '',
    
                    'mutasi_ids' => [$mutasiRow->id],
                    'mutasi_date' => $mutasiRow->tgl,
                    'mutasi_amount' => (float) $mutasiRow->cr,
    
                    'reff' => 'REG-' . $mutasiRow->id,
                ];
    
                /*
                |--------------------------------------------------------------------------
                | 7F. TANDAI DATA SUDAH DIGUNAKAN
                |--------------------------------------------------------------------------
                */
    
                foreach ($matchedReceipts as $receipt) {
                    $usedReceiptIds[] = (string) $receipt->id;
                }
    
                $usedMutasiIds[] = (string) $mutasiRow->id;
            }
        }
    
        /*
        |--------------------------------------------------------------------------
        | 8. SIMPAN HASIL REKONSILIASI
        |--------------------------------------------------------------------------
        */
    
        return $this->finishReverseReconciliation(
            $context,
            'fas_sum_date_site_amount_reverse',
            $matches
        );
    }
        
    public function reconcileFasSumDateSiteAmountMany(
        string $cabang,
        int $periodId,
        string $account
    ): array {

        /*
        |--------------------------------------------------------------------------
        | 1. LOAD CONTEXT
        |--------------------------------------------------------------------------
        */

        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account
        );

        /*
        |--------------------------------------------------------------------------
        | 2. FILTER RECEIPT FAS
        |--------------------------------------------------------------------------
        | Kandidat receipt:
        |
        | A. Comments diawali PELUNASAN KURSET.
        | B. Comments diawali PELUNSAN KURSET.
        | C. Category = FAS.
        | D. Comments diawali Kurset sales (CRM).
        |--------------------------------------------------------------------------
        */

        $receipts = $context['receipts']
            ->filter(function ($row) {

                $comments = strtoupper(
                    trim((string) ($row->comments ?? ''))
                );

                $category = strtoupper(
                    trim((string) ($row->category ?? ''))
                );

                $isPelunasanKurset =
                    str_starts_with(
                        $comments,
                        'PELUNASAN KURSET'
                    )
                    || str_starts_with(
                        $comments,
                        'PELUNSAN KURSET'
                    );

                $isCategoryFas = $category === 'FAS';

                $isKursetSalesCrm =
                    str_starts_with(
                        $comments,
                        'KURSET SALES (CRM)'
                    )
                    || str_starts_with(
                        $comments,
                        'BAYAR KURSET SALES'
                    );

                return (
                    $isPelunasanKurset
                    || $isCategoryFas
                    || $isKursetSalesCrm
                ) && $this->toCents($row->receipt_amount) > 0;

            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 3. FILTER MUTASI FAS
        |--------------------------------------------------------------------------
        | Hanya menggunakan mutasi dengan dept = FAS
        | dan cr positif.
        |--------------------------------------------------------------------------
        */

        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                strtoupper(
                    trim((string) ($row->dept ?? ''))
                ) === 'FAS'
            )
            ->filter(fn ($row) =>
                $this->toCents($row->cr) > 0
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 4. KELOMPOKKAN RECEIPT BERDASARKAN
        |    REKENING + TANGGAL + SITE
        |--------------------------------------------------------------------------
        |
        | Aturan site receipt:
        |
        | A. PELUNASAN KURSET:
        |    - Jika site terisi, gunakan site.
        |    - Jika kosong, gunakan RIGHT(comments, 4).
        |
        | B. Category = FAS:
        |    - Gunakan field site.
        |
        | C. KURSET SALES (CRM):
        |    - Gunakan field paid_by.
        |--------------------------------------------------------------------------
        */

        $receiptGroups = $receipts->groupBy(
            function ($row) {

                $comments = strtoupper(
                    trim((string) ($row->comments ?? ''))
                );

                $category = strtoupper(
                    trim((string) ($row->category ?? ''))
                );

                $isPelunasanKurset =
                    str_starts_with(
                        $comments,
                        'PELUNASAN KURSET'
                    );

                $isKursetSalesCrm =
                    str_starts_with(
                        $comments,
                        'KURSET SALES (CRM)'
                    )
                    || str_starts_with(
                        $comments,
                        'BAYAR KURSET SALES'
                    );

                $site = '';

                if ($isKursetSalesCrm) {

                    // Kurset Sales CRM menggunakan paid_by
                    $site = trim(
                        (string) ($row->paid_by ?? '')
                    );

                } elseif ($isPelunasanKurset) {

                    // Pelunasan Kurset menggunakan site
                    $site = trim(
                        (string) ($row->site ?? '')
                    );

                    // Fallback jika site kosong
                    if ($site === '') {

                        $site = substr(
                            trim((string) ($row->comments ?? '')),
                            -4
                        );

                    }

                } elseif ($category === 'FAS') {

                    // Receipt kategori FAS menggunakan site
                    $site = trim(
                        (string) ($row->site ?? '')
                    );

                }

                $site = strtoupper(trim($site));

                return $this->buildSiteDateKey(
                    trim((string) $row->remittance_bank_account),
                    $row->receipt_date,
                    $site
                );

            }
        );

        /*
        |--------------------------------------------------------------------------
        | 5. KELOMPOKKAN MUTASI BERDASARKAN
        |    REKENING + TANGGAL + SITE
        |--------------------------------------------------------------------------
        */

        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $this->buildSiteDateKey(
                trim((string) $row->no_rek),
                $row->tgl,
                strtoupper(trim((string) ($row->site ?? '')))
            )
        );

        /*
        |--------------------------------------------------------------------------
        | 6. INISIALISASI
        |--------------------------------------------------------------------------
        */

        $matches = [];

        $usedReceiptIds = [];
        $usedMutasiIds = [];

        /*
        |--------------------------------------------------------------------------
        | 7. HELPER UNTUK MEMBENTUK DATA MATCH
        |--------------------------------------------------------------------------
        */

        $makeManyMatch = function (
            $matchedReceipts,
            $matchedMutasi
        ) use (&$matches, &$usedReceiptIds, &$usedMutasiIds) {

            $matchedReceipts = collect($matchedReceipts)->values();
            $matchedMutasi = collect($matchedMutasi)->values();

            if (
                $matchedReceipts->isEmpty()
                || $matchedMutasi->isEmpty()
            ) {
                return;
            }

            $receiptAmountCents = $matchedReceipts->sum(
                fn ($row) => $this->toCents($row->receipt_amount)
            );

            $mutasiAmountCents = $matchedMutasi->sum(
                fn ($row) => $this->toCents($row->cr)
            );

            /*
            * Validasi nominal dengan toleransi kurang dari Rp1.
            */

            if (
                abs($receiptAmountCents - $mutasiAmountCents)
                >= self::AMOUNT_TOLERANCE_CENTS
            ) {
                return;
            }

            $receiptIds = $matchedReceipts
                ->pluck('id')
                ->values()
                ->all();

            $mutasiIds = $matchedMutasi
                ->pluck('id')
                ->values()
                ->all();

            /*
            * Satu reff untuk seluruh receipt dan mutasi.
            */

            $reff = 'REG-' . min($mutasiIds);

            $matches[] = [

                'receipt_ids' => $receiptIds,

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
                    fn ($row) => (float) $row->receipt_amount
                ),

                'comments' => $matchedReceipts->first()->comments ?? '',

                'paid_by' => $matchedReceipts->first()->paid_by ?? '',

                'mutasi_ids' => $mutasiIds,

                'mutasi_dates' => $matchedMutasi
                    ->pluck('tgl')
                    ->values()
                    ->all(),

                'mutasi_amounts' => $matchedMutasi
                    ->map(fn ($row) => (float) $row->cr)
                    ->values()
                    ->all(),

                'mutasi_amount' => (float) $matchedMutasi->sum(
                    fn ($row) => (float) $row->cr
                ),

                'reff' => $reff,

            ];

            /*
            * Tandai semua ID agar tidak digunakan kembali.
            */

            foreach ($matchedReceipts as $receipt) {
                $usedReceiptIds[] = (string) $receipt->id;
            }

            foreach ($matchedMutasi as $mutasiRow) {
                $usedMutasiIds[] = (string) $mutasiRow->id;
            }

        };

        /*
        |--------------------------------------------------------------------------
        | 8. PROSES SETIAP KELOMPOK
        |--------------------------------------------------------------------------
        */

        foreach ($receiptGroups as $key => $receiptGroup) {

            $mutasiGroup = $mutasiGroups->get(
                $key,
                collect()
            );

            if ($mutasiGroup->isEmpty()) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 8A. AMBIL DATA YANG BELUM DIGUNAKAN
            |--------------------------------------------------------------------------
            */

            $getAvailableReceipts = function () use (
                $receiptGroup,
                &$usedReceiptIds
            ) {

                return $receiptGroup
                    ->reject(fn ($row) =>
                        in_array(
                            (string) $row->id,
                            $usedReceiptIds,
                            true
                        )
                    )
                    ->values();

            };

            $getAvailableMutasi = function () use (
                $mutasiGroup,
                &$usedMutasiIds
            ) {

                return $mutasiGroup
                    ->reject(fn ($row) =>
                        in_array(
                            (string) $row->id,
                            $usedMutasiIds,
                            true
                        )
                    )
                    ->values();

            };

            /*
            |--------------------------------------------------------------------------
            | 8B. POLA 1 MUTASI : 1 RECEIPT
            |--------------------------------------------------------------------------
            */

            foreach ($getAvailableReceipts() as $receipt) {

                $availableMutasi = $getAvailableMutasi();

                $mutasiRow = $availableMutasi->first(
                    fn ($row) =>
                        $this->isAmountWithinTolerance(
                            $receipt->receipt_amount,
                            $row->cr
                        )
                );

                if (!$mutasiRow) {
                    continue;
                }

                $makeManyMatch(
                    collect([$receipt]),
                    collect([$mutasiRow])
                );

            }

            /*
            |--------------------------------------------------------------------------
            | 8C. POLA N RECEIPT : 1 MUTASI
            |--------------------------------------------------------------------------
            */

            foreach ($getAvailableMutasi() as $mutasiRow) {

                $availableReceipts = $getAvailableReceipts();

                if ($availableReceipts->isEmpty()) {
                    continue;
                }

                $targetCents = $this->toCents(
                    $mutasiRow->cr
                );

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
                    ->filter()
                    ->values();

                if ($matchedReceipts->isEmpty()) {
                    continue;
                }

                $makeManyMatch(
                    $matchedReceipts,
                    collect([$mutasiRow])
                );

            }

            /*
            |--------------------------------------------------------------------------
            | 8D. POLA 1 RECEIPT : N MUTASI
            |--------------------------------------------------------------------------
            */

            foreach ($getAvailableReceipts() as $receipt) {

                $availableMutasi = $getAvailableMutasi();

                if ($availableMutasi->isEmpty()) {
                    continue;
                }

                $targetCents = $this->toCents(
                    $receipt->receipt_amount
                );

                $solution = $this->findFirstSubset(
                    $availableMutasi,
                    $targetCents
                );

                if ($solution === null || empty($solution)) {
                    continue;
                }

                $matchedMutasi = collect($solution)
                    ->map(fn ($index) =>
                        $availableMutasi->get($index)
                    )
                    ->filter()
                    ->values();

                if ($matchedMutasi->isEmpty()) {
                    continue;
                }

                $makeManyMatch(
                    collect([$receipt]),
                    $matchedMutasi
                );

            }

            /*
            |--------------------------------------------------------------------------
            | 8E. POLA N RECEIPT : N MUTASI
            |--------------------------------------------------------------------------
            */

            $availableReceipts = $getAvailableReceipts();
            $availableMutasi = $getAvailableMutasi();

            if (
                $availableReceipts->isEmpty()
                || $availableMutasi->isEmpty()
            ) {
                continue;
            }

            $targetCents = $availableReceipts->sum(
                fn ($row) => $this->toCents(
                    $row->receipt_amount
                )
            );

            if ($targetCents <= 0) {
                continue;
            }

            $solution = $this->findFirstSubset(
                $availableMutasi,
                $targetCents
            );

            if ($solution === null || empty($solution)) {
                continue;
            }

            $matchedMutasi = collect($solution)
                ->map(fn ($index) =>
                    $availableMutasi->get($index)
                )
                ->filter()
                ->values();

            if ($matchedMutasi->isEmpty()) {
                continue;
            }

            $makeManyMatch(
                $availableReceipts,
                $matchedMutasi
            );

        }

        /*
        |--------------------------------------------------------------------------
        | 9. SIMPAN HASIL REKONSILIASI FAS
        |--------------------------------------------------------------------------
        */

        return $this->finishManyToManyReconciliation(
            $context,
            'fas_sum_date_site_amount_many',
            $matches,
            'FAS'
        );

    }

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
            
    public function reconcileDeptSiteDateAmountManyToMany(
        string $cabang,
        int $periodId,
        string $account
    ): array {
        /*
        |--------------------------------------------------------------------------
        | 1. LOAD CONTEXT
        |--------------------------------------------------------------------------
        */

        $context = $this->loadContext(
            $cabang,
            $periodId,
            $account
        );

        /*
        |--------------------------------------------------------------------------
        | 2. ACTIVITY KHUSUS SLS
        |--------------------------------------------------------------------------
        */

        $slsActivities = [
            'hasil penjualan belum diterima',
            'setoran sales per shift',
        ];

        $normalizeText = static function ($value): string {
            return strtoupper(trim((string) ($value ?? '')));
        };

        $normalizeActivity = static function ($value): string {
            return strtolower(trim((string) ($value ?? '')));
        };

        /*
        |--------------------------------------------------------------------------
        | 3. FILTER RECEIPT
        |--------------------------------------------------------------------------
        |
        | SLS:
        |   category = SLS
        |   site     = paid_by
        |
        | Non SLS:
        |   category = receipt.category
        |   site     = receipt.site
        |
        | Nominal positif dan negatif diperbolehkan.
        |
        */

        $receipts = $context['receipts']
            ->filter(function ($row) use (
                $slsActivities,
                $normalizeActivity
            ) {
                $accountNumber = trim(
                    (string) ($row->remittance_bank_account ?? '')
                );

                $date = trim(
                    (string) ($row->receipt_date ?? '')
                );

                $amount = $this->toCents(
                    $row->receipt_amount ?? 0
                );

                $activity = $normalizeActivity(
                    $row->activity ?? ''
                );

                $isSls = in_array(
                    $activity,
                    $slsActivities,
                    true
                );

                if (
                    $accountNumber === ''
                    || $date === ''
                    || $amount === 0
                ) {
                    return false;
                }

                if ($isSls) {
                    return trim(
                        (string) ($row->paid_by ?? '')
                    ) !== '';
                }

                return trim(
                    (string) ($row->category ?? '')
                ) !== '' && trim(
                    (string) ($row->site ?? '')
                ) !== '';
            })
            ->map(function ($row) use (
                $slsActivities,
                $normalizeActivity
            ) {
                $activity = $normalizeActivity(
                    $row->activity ?? ''
                );

                $isSls = in_array(
                    $activity,
                    $slsActivities,
                    true
                );

                $row->_match_category = $isSls
                    ? 'SLS'
                    : trim((string) ($row->category ?? ''));

                $row->_match_site = $isSls
                    ? trim((string) ($row->paid_by ?? ''))
                    : trim((string) ($row->site ?? ''));

                return $row;
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 4. FILTER MUTASI
        |--------------------------------------------------------------------------
        */

        $mutasi = $context['mutasi']
            ->filter(fn ($row) =>
                trim((string) ($row->no_rek ?? '')) !== ''
                && trim((string) ($row->tgl ?? '')) !== ''
                && trim((string) ($row->dept ?? '')) !== ''
                && trim((string) ($row->site ?? '')) !== ''
                && $this->toCents($row->cr ?? 0) > 0
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 5. GROUP KEY
        |--------------------------------------------------------------------------
        */

        $makeGroupKey = function (
            $accountNumber,
            $date,
            $deptOrCategory,
            $site
        ): string {
            return json_encode([
                'account' => preg_replace(
                    '/\s+/u',
                    '',
                    trim((string) $accountNumber)
                ),

                'date' => \Carbon\Carbon::parse($date)
                    ->format('Y-m-d'),

                'dept' => strtoupper(
                    trim((string) $deptOrCategory)
                ),

                'site' => strtoupper(
                    trim((string) $site)
                ),
            ], JSON_UNESCAPED_UNICODE);
        };

        /*
        |--------------------------------------------------------------------------
        | 6. GROUP RECEIPT
        |--------------------------------------------------------------------------
        */

        $receiptGroups = $receipts->groupBy(
            fn ($row) => $makeGroupKey(
                $row->remittance_bank_account,
                $row->receipt_date,
                $row->_match_category,
                $row->_match_site
            )
        );

        /*
        |--------------------------------------------------------------------------
        | 7. GROUP MUTASI
        |--------------------------------------------------------------------------
        */

        $mutasiGroups = $mutasi->groupBy(
            fn ($row) => $makeGroupKey(
                $row->no_rek,
                $row->tgl,
                $row->dept,
                $row->site
            )
        );

        /*
        |--------------------------------------------------------------------------
        | 8. KONFIGURASI PENCARIAN
        |--------------------------------------------------------------------------
        |
        | Maksimum state disimpan.
        |
        | Nilai ini merupakan pengaman penggunaan memori.
        | Jika tercapai, state dipilih berdasarkan kedekatan
        | dengan target nominal.
        |
        */

        $maxStates = 250000;

        /*
        |--------------------------------------------------------------------------
        | 9. HELPER MEMBANGUN SUBSET SUM
        |--------------------------------------------------------------------------
        |
        | Hasil:
        |
        | [
        |   'states' => [
        |       nominal => [index, index, ...]
        |   ],
        |   'truncated' => bool,
        |   'state_count' => int
        | ]
        |
        | Setiap record hanya boleh digunakan satu kali.
        |
        */

        $buildSubsetMap = function (
            array $rows,
            int $maxTarget,
            int $maxStates,
            bool $positiveOnly = false
        ): array {

            $states = [
                0 => [],
            ];

            $truncated = false;

            /*
            * Urutkan nominal terbesar lebih dahulu.
            * Index asli tetap dipertahankan.
            */
            $orderedRows = [];

            foreach ($rows as $index => $row) {
                $amount = (int) $row['amount'];

                if ($amount === 0) {
                    continue;
                }

                if ($positiveOnly && $amount < 0) {
                    continue;
                }

                $orderedRows[] = [
                    'index' => $index,
                    'amount' => $amount,
                ];
            }

            usort(
                $orderedRows,
                fn ($a, $b) =>
                    abs($b['amount']) <=> abs($a['amount'])
            );

            foreach ($orderedRows as $item) {

                $amount = (int) $item['amount'];
                $originalIndex = $item['index'];

                /*
                * Snapshot agar satu baris tidak dipakai
                * berulang kali dalam iterasi yang sama.
                */
                $snapshot = $states;

                foreach ($snapshot as $sum => $indexes) {

                    $newSum = (int) $sum + $amount;

                    /*
                    * Mutasi hanya positif.
                    * Jangan menyimpan kombinasi yang
                    * sudah melewati batas nominal receipt.
                    */
                    if (
                        $positiveOnly
                        && $newSum > $maxTarget
                    ) {
                        continue;
                    }

                    /*
                    * Satu nominal cukup menyimpan kombinasi
                    * dengan jumlah baris paling sedikit.
                    */
                    if (
                        !isset($states[$newSum])
                        || count($indexes) + 1
                            < count($states[$newSum])
                    ) {
                        $states[$newSum] = array_merge(
                            $indexes,
                            [$originalIndex]
                        );
                    }
                }

                /*
                * Pangkas state jika melewati batas.
                * Prioritaskan nominal yang paling dekat
                * dengan target maksimum.
                */
                if (count($states) > $maxStates) {

                    $truncated = true;

                    unset($states[0]);

                    $stateKeys = array_keys($states);

                    usort(
                        $stateKeys,
                        function ($a, $b) use ($maxTarget, $states) {

                            $distanceA = abs(
                                $maxTarget - (int) $a
                            );

                            $distanceB = abs(
                                $maxTarget - (int) $b
                            );

                            if ($distanceA !== $distanceB) {
                                return $distanceA <=> $distanceB;
                            }

                            return count($states[$a])
                                <=> count($states[$b]);
                        }
                    );

                    $selected = [];

                    foreach (
                        array_slice($stateKeys, 0, $maxStates - 1)
                        as $key
                    ) {
                        $selected[$key] = $states[$key];
                    }

                    $selected[0] = [];

                    $states = $selected;
                }
            }

            unset($states[0]);

            return [
                'states' => $states,
                'truncated' => $truncated,
                'state_count' => count($states),
            ];
        };

        /*
        |--------------------------------------------------------------------------
        | 10. HELPER MENCARI KOMBINASI TERBAIK
        |--------------------------------------------------------------------------
        */

        $findBestMatch = function (
            array $receiptRows,
            array $mutasiRows
        ) use (
            $buildSubsetMap,
            $maxStates
        ): array {

            if (
                empty($receiptRows)
                || empty($mutasiRows)
            ) {
                return [
                    'match' => null,
                    'receipt_truncated' => false,
                    'mutasi_truncated' => false,
                    'receipt_states' => 0,
                    'mutasi_states' => 0,
                ];
            }

            /*
            * Batas jumlah receipt positif dan negatif.
            */
            $minReceiptSum = 0;
            $maxReceiptSum = 0;

            foreach ($receiptRows as $row) {

                $amount = (int) $row['amount'];

                if ($amount < 0) {
                    $minReceiptSum += $amount;
                } else {
                    $maxReceiptSum += $amount;
                }
            }

            if ($maxReceiptSum <= 0) {
                return [
                    'match' => null,
                    'receipt_truncated' => false,
                    'mutasi_truncated' => false,
                    'receipt_states' => 0,
                    'mutasi_states' => 0,
                ];
            }

            /*
            * Receipt boleh positif dan negatif.
            */
            $receiptResult = $buildSubsetMap(
                $receiptRows,
                $maxReceiptSum,
                $maxStates,
                false
            );

            /*
            * Mutasi hanya positif.
            * Batasi total mutasi sampai maksimum
            * nominal receipt + toleransi.
            */
            $mutasiResult = $buildSubsetMap(
                $mutasiRows,
                $maxReceiptSum
                    + self::AMOUNT_TOLERANCE_CENTS,
                $maxStates,
                true
            );

            $receiptSubsetMap = $receiptResult['states'];
            $mutasiSubsetMap = $mutasiResult['states'];

            if (
                empty($receiptSubsetMap)
                || empty($mutasiSubsetMap)
            ) {
                return [
                    'match' => null,
                    'receipt_truncated' => $receiptResult['truncated'],
                    'mutasi_truncated' => $mutasiResult['truncated'],
                    'receipt_states' => $receiptResult['state_count'],
                    'mutasi_states' => $mutasiResult['state_count'],
                ];
            }

            /*
            * Urutkan nominal mutasi.
            * Pencarian menggunakan binary search.
            */
            $mutasiSums = array_keys($mutasiSubsetMap);

            sort($mutasiSums, SORT_NUMERIC);

            $tolerance = max(
                0,
                (int) self::AMOUNT_TOLERANCE_CENTS - 1
            );

            $bestMatch = null;
            $bestRowsCount = PHP_INT_MAX;
            $bestDifference = PHP_INT_MAX;
            $bestAmount = 0;

            foreach (
                $receiptSubsetMap as $receiptSum => $receiptIndexes
            ) {

                $receiptSum = (int) $receiptSum;

                /*
                * Total receipt yang dicocokkan harus positif.
                */
                if (
                    $receiptSum <= 0
                    || $receiptSum < $minReceiptSum
                    || $receiptSum > $maxReceiptSum
                ) {
                    continue;
                }

                $minTarget = max(
                    1,
                    $receiptSum - $tolerance
                );

                $maxTarget = $receiptSum + $tolerance;

                /*
                * Binary search untuk menemukan posisi
                * nominal mutasi pertama >= minTarget.
                */
                $left = 0;
                $right = count($mutasiSums);

                while ($left < $right) {

                    $mid = intdiv(
                        $left + $right,
                        2
                    );

                    if ($mutasiSums[$mid] < $minTarget) {
                        $left = $mid + 1;
                    } else {
                        $right = $mid;
                    }
                }

                /*
                * Periksa hanya nominal dalam rentang toleransi.
                */
                for (
                    $i = $left;
                    $i < count($mutasiSums);
                    $i++
                ) {

                    $mutasiSum = (int) $mutasiSums[$i];

                    if ($mutasiSum > $maxTarget) {
                        break;
                    }

                    $mutasiIndexes = $mutasiSubsetMap[$mutasiSum];

                    $difference = abs(
                        $receiptSum - $mutasiSum
                    );

                    $totalRows = count($receiptIndexes)
                        + count($mutasiIndexes);

                    $matchedAmount = min(
                        $receiptSum,
                        $mutasiSum
                    );

                    /*
                    * Prioritas:
                    * 1. Jumlah baris paling sedikit.
                    * 2. Selisih paling kecil.
                    * 3. Nominal terbesar.
                    */
                    $isBetter =
                        $totalRows < $bestRowsCount
                        || (
                            $totalRows === $bestRowsCount
                            && $difference < $bestDifference
                        )
                        || (
                            $totalRows === $bestRowsCount
                            && $difference === $bestDifference
                            && $matchedAmount > $bestAmount
                        );

                    if ($isBetter) {

                        $bestRowsCount = $totalRows;
                        $bestDifference = $difference;
                        $bestAmount = $matchedAmount;

                        $bestMatch = [
                            'receipt_indexes' => $receiptIndexes,
                            'mutasi_indexes' => $mutasiIndexes,
                            'receipt_total' => $receiptSum,
                            'mutasi_total' => $mutasiSum,
                            'difference' => $difference,
                        ];
                    }
                }
            }

            return [
                'match' => $bestMatch,
                'receipt_truncated' => $receiptResult['truncated'],
                'mutasi_truncated' => $mutasiResult['truncated'],
                'receipt_states' => $receiptResult['state_count'],
                'mutasi_states' => $mutasiResult['state_count'],
            ];
        };

        /*
        |--------------------------------------------------------------------------
        | 11. PROSES SETIAP GROUP MUTASI
        |--------------------------------------------------------------------------
        */

        $matches = [];

        $searchDiagnostics = [];

        foreach ($mutasiGroups as $key => $mutasiGroup) {

            $receiptGroup = $receiptGroups->get(
                $key,
                collect()
            );

            if ($receiptGroup->isEmpty()) {
                continue;
            }

            /*
            * Siapkan receipt.
            */
            $remainingReceipts = $receiptGroup
                ->map(fn ($row) => [
                    'row' => $row,
                    'amount' => $this->toCents(
                        $row->receipt_amount
                    ),
                ])
                ->values()
                ->all();

            /*
            * Siapkan mutasi.
            */
            $remainingMutasi = $mutasiGroup
                ->map(fn ($row) => [
                    'row' => $row,
                    'amount' => $this->toCents(
                        $row->cr
                    ),
                ])
                ->values()
                ->all();

            $iteration = 0;

            while (
                !empty($remainingReceipts)
                && !empty($remainingMutasi)
            ) {

                $iteration++;

                $searchResult = $findBestMatch(
                    $remainingReceipts,
                    $remainingMutasi
                );

                $searchDiagnostics[] = [
                    'group_key' => $key,
                    'iteration' => $iteration,
                    'receipt_count' => count($remainingReceipts),
                    'mutasi_count' => count($remainingMutasi),
                    'receipt_states' => $searchResult['receipt_states'],
                    'mutasi_states' => $searchResult['mutasi_states'],
                    'receipt_truncated' => $searchResult['receipt_truncated'],
                    'mutasi_truncated' => $searchResult['mutasi_truncated'],
                    'found' => $searchResult['match'] !== null,
                ];

                $bestMatch = $searchResult['match'];

                /*
                * Tidak ditemukan kombinasi.
                */
                if (!$bestMatch) {
                    break;
                }

                /*
                * Ambil receipt yang match.
                */
                $matchedReceipts = [];

                foreach (
                    $bestMatch['receipt_indexes'] as $index
                ) {
                    $matchedReceipts[] = $remainingReceipts[$index]['row'];
                }

                /*
                * Ambil mutasi yang match.
                */
                $matchedMutasi = [];

                foreach (
                    $bestMatch['mutasi_indexes'] as $index
                ) {
                    $matchedMutasi[] = $remainingMutasi[$index]['row'];
                }

                $receiptIds = collect($matchedReceipts)
                    ->pluck('id')
                    ->values()
                    ->all();

                $mutasiIds = collect($matchedMutasi)
                    ->pluck('id')
                    ->values()
                    ->all();

                if (
                    empty($receiptIds)
                    || empty($mutasiIds)
                ) {
                    break;
                }

                /*
                * Satu reff untuk seluruh record kombinasi.
                */
                $reff = 'REG-' . $mutasiIds[0];

                $receiptTotalCents = $bestMatch['receipt_total'];
                $mutasiTotalCents = $bestMatch['mutasi_total'];

                /*
                * Simpan hasil match.
                */
                $matches[] = [

                    'receipt_ids' => $receiptIds,

                    'receipt_numbers' => collect($matchedReceipts)
                        ->pluck('receipt_number')
                        ->values()
                        ->all(),

                    'receipt_dates' => collect($matchedReceipts)
                        ->pluck('receipt_date')
                        ->values()
                        ->all(),

                    'receipt_amounts' => collect($matchedReceipts)
                        ->map(fn ($row) =>
                            (float) $row->receipt_amount
                        )
                        ->values()
                        ->all(),

                    'receipt_amount' => $receiptTotalCents / 100,

                    'categories' => collect($matchedReceipts)
                        ->map(fn ($row) => $row->_match_category)
                        ->unique()
                        ->values()
                        ->all(),

                    'sites' => collect($matchedReceipts)
                        ->map(fn ($row) => $row->_match_site)
                        ->unique()
                        ->values()
                        ->all(),

                    'paid_by' => collect($matchedReceipts)
                        ->pluck('paid_by')
                        ->unique()
                        ->values()
                        ->all(),

                    'mutasi_ids' => $mutasiIds,

                    'mutasi_dates' => collect($matchedMutasi)
                        ->pluck('tgl')
                        ->values()
                        ->all(),

                    'mutasi_amounts' => collect($matchedMutasi)
                        ->map(fn ($row) =>
                            (float) $row->cr
                        )
                        ->values()
                        ->all(),

                    'mutasi_date' => $matchedMutasi[0]->tgl,

                    'mutasi_amount' => $mutasiTotalCents / 100,

                    'difference' => (
                        $mutasiTotalCents - $receiptTotalCents
                    ) / 100,

                    'reff' => $reff,
                ];

                /*
                * Hapus receipt yang sudah digunakan.
                */
                $usedReceiptIndexes = array_flip(
                    $bestMatch['receipt_indexes']
                );

                $remainingReceipts = array_values(
                    array_filter(
                        $remainingReceipts,
                        fn ($row, $index) =>
                            !isset($usedReceiptIndexes[$index]),
                        ARRAY_FILTER_USE_BOTH
                    )
                );

                /*
                * Hapus mutasi yang sudah digunakan.
                */
                $usedMutasiIndexes = array_flip(
                    $bestMatch['mutasi_indexes']
                );

                $remainingMutasi = array_values(
                    array_filter(
                        $remainingMutasi,
                        fn ($row, $index) =>
                            !isset($usedMutasiIndexes[$index]),
                        ARRAY_FILTER_USE_BOTH
                    )
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 13. SIMPAN HASIL REKONSILIASI
        |--------------------------------------------------------------------------
        */

        return $this->finishReverseReconciliation(
            $context,
            'dept_site_date_amount_many_to_many',
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
        array $matches,
        string $dept = 'SLS'
    ): array {
        $savedMatches = [];

        $dept = strtoupper(trim($dept));

        DB::transaction(function () use (
            $context,
            $matches,
            $dept,
            &$savedMatches
        ) {
            $claimedReceiptIds = [];
            $claimedMutasiIds = [];

            foreach ($matches as $match) {
                $receiptIds = $match['receipt_ids'];
                $mutasiIds = $match['mutasi_ids'];
                $reff = $match['reff'];

                /*
                |--------------------------------------------------------------------------
                | 1. CEGAH ID BERULANG DALAM BATCH
                |--------------------------------------------------------------------------
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

                if (!$duplicate) {
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
                }

                if ($duplicate) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | 2. UPDATE SELURUH RECEIPT
                |--------------------------------------------------------------------------
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
                |--------------------------------------------------------------------------
                | 3. UPDATE SELURUH MUTASI
                |--------------------------------------------------------------------------
                | Dept mengikuti parameter:
                | - SLS untuk metode SLS.
                | - FAS untuk metode FAS.
                |--------------------------------------------------------------------------
                */

                foreach ($mutasiIds as $mutasiId) {
                    $updated = DB::table('mutasi_detail')
                        ->where('id', $mutasiId)
                        ->where('cabang', $context['cabang'])
                        ->where('no_rek', $context['account'])
                        ->whereRaw(
                            'UPPER(TRIM(COALESCE(dept, ?))) = ?',
                            ['', $dept]
                        )
                        ->where(function ($query) {
                            $query->whereNull('reff')
                                ->orWhere('reff', '');
                        })
                        ->update([
                            'reff' => $reff,
                        ]);

                    if ($updated !== 1) {
                        throw new RuntimeException(
                            "Mutasi ID {$mutasiId} tidak dapat diperbarui. "
                            . "Pastikan dept = {$dept}, rekening dan cabang "
                            . 'sesuai, serta reff masih kosong.'
                        );
                    }

                    $claimedMutasiIds[] = (string) $mutasiId;
                }

                /*
                |--------------------------------------------------------------------------
                | 4. CATAT MATCH YANG BERHASIL DISIMPAN
                |--------------------------------------------------------------------------
                */

                $savedMatches[] = $match;
            }
        }, 3);

        /*
        |--------------------------------------------------------------------------
        | 5. HITUNG ID RECEIPT DAN MUTASI YANG BERHASIL DIPASANGKAN
        |--------------------------------------------------------------------------
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
        |--------------------------------------------------------------------------
        | 6. DATA RECEIPT YANG BELUM MATCH
        |--------------------------------------------------------------------------
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

        /*
        |--------------------------------------------------------------------------
        | 7. DATA MUTASI YANG BELUM MATCH
        |--------------------------------------------------------------------------
        */

        $unmatchedMutasi = $context['mutasi']
            ->reject(fn ($row) =>
                in_array(
                    (string) $row->id,
                    $matchedMutasiIds,
                    true
                )
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 8. HITUNG TOTAL NOMINAL MATCH
        |--------------------------------------------------------------------------
        */

        $receiptAmount = collect($savedMatches)
            ->sum('receipt_amount');

        $mutasiAmount = collect($savedMatches)
            ->sum('mutasi_amount');

        /*
        |--------------------------------------------------------------------------
        | 9. RETURN HASIL REKONSILIASI
        |--------------------------------------------------------------------------
        */

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
        $site = null
    ): string {
        $parts = [
            trim((string) $account),
            substr(trim((string) $date), 0, 10),
        ];
    
        // Tambahkan site hanya jika parameter site diberikan dan tidak kosong.
        if ($site !== null && trim((string) $site) !== '') {
            $parts[] = strtoupper(trim((string) $site));
        }
    
        return implode('|', $parts);
    }

    /**
     * Mengubah nominal menjadi integer satuan sen.
     */
    private function toCents($amount): int
    {
        return (int) round((float) $amount * 100);
    }
}