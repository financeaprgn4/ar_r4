<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Lpd;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $cabang = $request->input('cabang');

        if (!$cabang) {
            return response()->json(['error' => 'Cabang tidak ditemukan'], 400);
        }

        $periodeAktif = DB::table('periode')
            ->where('kategori', 'LPD')
            ->where('status', 'Aktif')
            ->where('cabang', $cabang)
            ->first();

        if (!$periodeAktif || !isset($periodeAktif->end_date)) {
            return response()->json(['error' => 'Periode aktif tidak ditemukan'], 404);
        }

        $baseQuery = Lpd::where('status', '!=', 'final')->where('cabang', $cabang);
        $endDate = $periodeAktif->end_date;

        $total = $baseQuery->count();
        $ppj = (clone $baseQuery)->where('jns_toko', 'ppj')->count();
        $ns = (clone $baseQuery)->where('jns_toko', 'ns')->count();
        $up = (clone $baseQuery)->where('jns_toko', 'up')->count();

        $jatuhTempo = (clone $baseQuery)->whereDate('tgl_jt', '<=', $endDate)->count();
        $jatuhTempo_ns = (clone $baseQuery)->where('jns_toko', 'ns')->whereDate('tgl_jt', '<=', $endDate)->count();
        $jatuhTempo_ppj = (clone $baseQuery)->where('jns_toko', 'ppj')->whereDate('tgl_jt', '<=', $endDate)->count();
        $jatuhTempo_up = (clone $baseQuery)->where('jns_toko', 'up')->whereDate('tgl_jt', '<=', $endDate)->count();

        return response()->json([
            'total' => $total,
            'ppj' => $ppj,
            'ns' => $ns,
            'up' => $up,
            'jatuh_tempo' => $jatuhTempo,
            'jatuh_tempo_ns' => $jatuhTempo_ns,
            'jatuh_tempo_ppj' => $jatuhTempo_ppj,
            'jatuh_tempo_up' => $jatuhTempo_up,
        ]);
    }

    public function index(Request $request)
    {
        try {

            // Role untuk pengujian awal
            $role = strtoupper(
                trim($request->query('role', ''))
            );

            if (!in_array($role, ['USER', 'ADMIN'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role tidak valid',
                    'widgets' => []
                ], 400);
            }

            // Ambil konfigurasi dashboard
            $widgets = DB::table('dashboard')
                ->where('role', $role)
                ->where('is_active', 1)
                ->orderBy('position', 'asc')
                ->orderBy('id', 'asc')
                ->get([
                    'id',
                    'widget_key',
                    'title',
                    'position',
                    'col_span',
                    'is_active',
                    'config'
                ]);

            return response()->json([
                'success' => true,
                'role' => $role,
                'widgets' => $widgets
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil konfigurasi dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function absenMutasireg(Request $request)
    {
        try {
            // ==========================================
            // 1. AMBIL CABANG
            // ==========================================

            $cabang = $request->query('cabang');

            if (!$cabang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter cabang wajib diisi.'
                ], 422);
            }

            // ==========================================
            // 2. AMBIL PERIODE MUTASI AKTIF
            // ==========================================

            $periode = DB::table('periode')
                ->where('Cabang', $cabang)
                ->where('kategori', 'Mutasi')
                ->whereRaw("UPPER(TRIM(status)) = 'AKTIF'")
                ->orderByDesc('id')
                ->first();

            if (!$periode) {
                return response()->json([
                    'success' => true,
                    'message' => 'Periode mutasi aktif tidak ditemukan.',
                    'data' => [
                        'tgl_awal' => null,
                        'tgl_akhir' => null,
                        'tanggal' => [],
                        'rekening' => []
                    ]
                ]);
            }

            $tglAwal = Carbon::parse($periode->start_date)->startOfDay();
            $tglAkhir = Carbon::parse($periode->end_date)->startOfDay();

            if ($tglAwal->gt($tglAkhir)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rentang tanggal periode tidak valid.'
                ], 422);
            }

            // ==========================================
            // 3. GENERATE DAFTAR TANGGAL
            // ==========================================

            $tanggal = [];
            $tanggalKeys = [];

            $cursor = $tglAwal->copy();

            while ($cursor->lte($tglAkhir)) {
                $key = $cursor->format('Y-m-d');

                $tanggal[] = [
                    'tanggal' => $key,
                    'label' => $cursor->format('j')
                ];

                $tanggalKeys[] = $key;

                $cursor->addDay();
            }

            // ==========================================
            // 4. AMBIL DAFTAR REKENING REGULER
            // ==========================================

            $rekening = DB::table('bank')
                ->where('cabang', $cabang)
                ->where('site', 'REG')
                ->whereRaw("UPPER(TRIM(bank)) <> 'TITIPAN'")
                ->orderBy('bank')
                ->orderBy('no_rek')
                ->get([
                    'bank',
                    'no_rek'
                ]);

            if ($rekening->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'tgl_awal' => $tglAwal->format('Y-m-d'),
                        'tgl_akhir' => $tglAkhir->format('Y-m-d'),
                        'tanggal' => $tanggal,
                        'rekening' => []
                    ]
                ]);
            }

            // ==========================================
            // 5. AMBIL DATA MUTASI DALAM PERIODE
            // ==========================================

            $mutasi = DB::table('mutasi')
                ->where('cabang', $cabang)
                ->whereDate('tgl_awal', '<=', $tglAkhir->format('Y-m-d'))
                ->whereDate('tgl_akhir', '>=', $tglAwal->format('Y-m-d'))
                ->get([
                    'bank',
                    'no_rek',
                    'tgl_awal',
                    'tgl_akhir'
                ]);

            // ==========================================
            // 6. KELOMPOKKAN MUTASI PER REKENING
            // ==========================================

            $mutasiMap = [];

            foreach ($mutasi as $item) {
                $key = trim($item->bank) . '|' . trim($item->no_rek);

                $mutasiMap[$key][] = [
                    'tgl_awal' => Carbon::parse($item->tgl_awal)->format('Y-m-d'),
                    'tgl_akhir' => Carbon::parse($item->tgl_akhir)->format('Y-m-d')
                ];
            }

            // ==========================================
            // 7. BANGUN ABSENSI SETIAP REKENING
            // ==========================================

            $hasilRekening = [];

            foreach ($rekening as $item) {
                $key = trim($item->bank) . '|' . trim($item->no_rek);

                $dataMutasi = $mutasiMap[$key] ?? [];

                $absensi = [];

                foreach ($tanggalKeys as $tgl) {
                    $sudahUpload = false;

                    foreach ($dataMutasi as $range) {
                        if (
                            $tgl >= $range['tgl_awal'] &&
                            $tgl <= $range['tgl_akhir']
                        ) {
                            $sudahUpload = true;
                            break;
                        }
                    }

                    $absensi[$tgl] = $sudahUpload;
                }

                $hasilRekening[] = [
                    'no_rek' => $item->no_rek,
                    'nama_bank' => $item->bank,
                    'absensi' => $absensi
                ];
            }

            // ==========================================
            // 8. RESPONSE
            // ==========================================

            return response()->json([
                'success' => true,
                'data' => [
                    'tgl_awal' => $tglAwal->format('Y-m-d'),
                    'tgl_akhir' => $tglAkhir->format('Y-m-d'),
                    'tanggal' => $tanggal,
                    'rekening' => $hasilRekening
                ]
            ]);

        } catch (\Throwable $e) {

            \Log::error('ABSEN MUTASI REG ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil absensi mutasi.'
            ], 500);
        }
    }

    public function rekapRekonFrc(Request $request)
    {
        try {

            // ==========================================
            // 1. VALIDASI PARAMETER CABANG
            // ==========================================

            $request->validate([
                'cabang' => 'required|string|max:15'
            ]);

            $cabang = trim($request->cabang);

            // ==========================================
            // 2. AMBIL PERIODE MUTASI AKTIF
            // ==========================================

            $periode = DB::table('periode')
                ->where('Cabang', $cabang)
                ->whereRaw('LOWER(kategori) = ?', ['mutasi'])
                ->whereRaw('LOWER(status) IN (?, ?)', [
                    'aktif',
                    'active'
                ])
                ->orderByDesc('id')
                ->first();

            if (!$periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode mutasi aktif tidak ditemukan.',
                    'data' => [
                        'tgl_awal' => null,
                        'tgl_akhir' => null,
                        'items' => []
                    ]
                ], 404);
            }

            $tglAwal = $periode->start_date;
            $tglAkhir = $periode->end_date;

            // ==========================================
            // 3. AMBIL DISTINCT JENIS BANK FRC
            // ==========================================

            $jenisBank = DB::table('bank')
                ->where('cabang', $cabang)
                ->where('site', '<>', 'REG')
                ->where('bank', '<>', 'Titipan')
                ->whereNotNull('jns_bank')
                ->where('jns_bank', '<>', '')
                ->select('jns_bank')
                ->distinct()
                ->orderBy('jns_bank')
                ->pluck('jns_bank');

            // ==========================================
            // 4. PROSES REKAP PER JENIS BANK
            // ==========================================

            $items = [];

            foreach ($jenisBank as $jnsBank) {

                // --------------------------------------
                // 4A. AMBIL NO REK BERDASARKAN JENIS BANK
                // --------------------------------------

                $rekening = DB::table('bank')
                    ->where('cabang', $cabang)
                    ->where('site', '<>', 'REG')
                    ->where('bank', '<>', 'Titipan')
                    ->where('jns_bank', $jnsBank)
                    ->pluck('no_rek')
                    ->unique()
                    ->values()
                    ->all();

                if (empty($rekening)) {
                    continue;
                }

                // --------------------------------------
                // 4B. MUTASI BELUM JURNAL
                // reff IS NULL
                // --------------------------------------

                $belumJurnal = DB::table('mutasi_detail_frc')
                    ->where('cabang', $cabang)
                    ->where('cr', '<>', 0)
                    ->whereIn('no_rek', $rekening)
                    ->whereBetween('tgl', [
                        $tglAwal,
                        $tglAkhir
                    ])
                    ->whereNull('reff')
                    ->count();

                // --------------------------------------
                // 4C. MUTASI SUDAH JURNAL
                // reff IS NOT NULL
                // --------------------------------------

                $sudahJurnal = DB::table('mutasi_detail_frc')
                    ->where('cabang', $cabang)
                    ->where('cr', '<>', 0)
                    ->whereIn('no_rek', $rekening)
                    ->whereBetween('tgl', [
                        $tglAwal,
                        $tglAkhir
                    ])
                    ->whereNotNull('reff')
                    ->count();

                // --------------------------------------
                // 4D. RECEIPT UNMATCH
                // reff IS NULL
                // --------------------------------------

                $receiptUnmatch = DB::table('receipt')
                    ->whereIn('remittance_bank_account', $rekening)
                    ->whereBetween('receipt_date', [
                        $tglAwal,
                        $tglAkhir
                    ])
                    ->whereNull('reff')
                    ->count();

                // --------------------------------------
                // 4E. SIMPAN HASIL
                // --------------------------------------

                $items[] = [
                    'jns_bank' => $jnsBank,
                    'belum_jurnal' => (int) $belumJurnal,
                    'sudah_jurnal' => (int) $sudahJurnal,
                    'receipt_unmatch' => (int) $receiptUnmatch,
                ];
            }

            // ==========================================
            // 5. RESPONSE JSON
            // ==========================================

            return response()->json([
                'success' => true,
                'message' => 'Rekap rekonsiliasi FRC berhasil diambil.',
                'data' => [
                    'tgl_awal' => $tglAwal,
                    'tgl_akhir' => $tglAkhir,
                    'items' => $items
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Throwable $e) {

            Log::error('REKAP MUTASI FRC ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil rekap rekonsiliasi FRC.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null
            ], 500);
        }
    }

}
