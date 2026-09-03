<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DatPR;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DatPRController extends Controller
{
    public function index(Request $request)
    {
        $rab = $request->query('rab');
        $kd_toko = $request->query('kd_toko');
        
        if (!$rab) {
            return response()->json(['error' => 'Parameter rab tidak ditemukan'], 400);
        }

        $data = DB::table('dat_pr')
            ->where('rab', $rab)
            ->where(function ($query) {
                $query->whereNull('flag_realisasi')
                    ->orWhere('flag_realisasi', '');
            })
            ->where('surkas', '!=', 'Y')
            ->get();

        $dat_pr = DB::table('dat_pr')
            ->where('rab', $rab)
            ->whereNotNull('flag_realisasi')
            ->where('flag_realisasi', '!=', '')
            ->where('surkas', '!=', 'Y')
            ->orderBy('flag_realisasi')
            ->get();
        
        $master = DB::table('master_sarana')
            ->get();

        $sarana = DB::table('sarana_toko')
            ->where('rab', $rab)
            ->get();

        $kdTokoData = DB::table($kd_toko)
            ->where('rab', $rab)
            ->get()
            ->groupBy('flag_dat_pr');

        $groupedAtPR = $dat_pr->groupBy('flag_realisasi')->map(function ($items, $flag) use ($kdTokoData) {
            $ktGroup = $kdTokoData->get($flag);

            return [
                'flag_realisasi' => $flag,
                'items' => $items,
                'realisasi_items' => $ktGroup ? $ktGroup->values() : [],
            ];
        });
        
        $surkas = DB::table('dat_pr')
            ->where('rab', $rab)
            ->where('surkas', 'Y')
            ->get();

        return response()->json([
            'data' => $data,
            'sarana' => $sarana,
            'master' => $master,
            'surkas' => $surkas,
            'groupedAtPR' => $groupedAtPR->toArray(),
        ]);
    }

    public function destroy(Request $request)
    {
        $flag = $request->input('flag');
        $rab = $request->input('rab');
        $kd_toko = $request->input('kd_toko');

        if (!$flag || !$rab || !$kd_toko) {
            return response()->json(['error' => 'Parameter tidak lengkap.'], 400);
        }

        // Update sarana_toko: set flag_realisasi = null
        DB::table('dat_pr')
            ->where('rab', $rab)
            ->where('flag_realisasi', $flag)
            ->update(['flag_realisasi' => '']);

        // Update tabel dinamis (kd_toko): set flag_sarana = null
        DB::table($kd_toko)
            ->where('rab', $rab)
            ->where('flag_dat_pr', $flag)
            ->update(['flag_dat_pr' => '']);

        return response()->json(['message' => 'Data berhasil dipindahkan (flag dikosongkan).']);
    }

    public function getMatchSetting(Request $request)
    {
        $cabang = $request->query('cabang');

        $data = DB::table('setup_lpd')
            ->where('cabang', $cabang)
            ->where('attr', 'match_at_by_desc')
            ->select('value')
            ->first();

        return response()->json([
            'value' => $data ? $data->value : 'N',
        ]);
    }

    public function updateMatchSetting(Request $request)
    {
        $cabang = $request->input('cabang');
        $value  = $request->input('value'); // Y / N

        DB::table('setup_lpd')
            ->updateOrInsert(
                ['cabang' => $cabang, 'attr' => 'match_at_by_desc'],
                ['value' => $value]
            );

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan Match berhasil diperbarui.'
        ]);
    }

    public function match(Request $request)
    {
        $request->validate([
            'no_rab' => 'required|string',
            'site'   => 'required|string',
            'pilih'  => 'required|array',
        ]);

        $rab   = $request->input('no_rab');
        $site  = $request->input('site');
        $pilih = $request->input('pilih');
        $iden  = $request->input('iden', []);
        $tipe  = strtoupper(trim($request->input('tipe')));
        $reff  = min($pilih) . '-' . $site;

        DB::beginTransaction();
        try {
            if ($tipe === 'RETIRE') {
                DB::table('dat_pr')
                    ->where('rab', $rab)
                    ->whereIn('id', $pilih)
                    ->delete();

                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'DAT PR berhasil diperbarui (retire).',
                ]);
            }

            if ($tipe === 'CHANGE') {
                $records = DB::table('dat_pr')
                    ->where('rab', $rab)
                    ->whereIn('id', $pilih)
                    ->get(['id', 'surkas']);

                foreach ($records as $record) {
                    $newValue = $record->surkas === 'Y' ? 'L' : 'Y';

                    DB::table('dat_pr')
                        ->where('id', $record->id)
                        ->update(['surkas' => $newValue]);
                }

                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'DAT PR berhasil diperbarui (toggle surkas).',
                ]);
            }

            if ($tipe === 'MODAL') {
                // STEP 1 — Ambil semua seri dari dat_pr berdasarkan id yang dipilih
                $seriList = DB::table('dat_pr')
                    ->where('rab', $rab)
                    ->whereIn('id', $pilih)
                    ->pluck('seri')
                    ->toArray();

                if (empty($seriList)) {
                    throw new \Exception('Tidak ditemukan seri untuk id yang dipilih.');
                }

                // STEP 2 — Update master_dat_pr.modal = 'Y' berdasarkan seri
                DB::table('master_dat_pr')
                    ->whereIn('seri', $seriList)
                    ->update([
                        'modal' => 'Y',
                    ]);

                // STEP 3 — Delete dat_pr (sama seperti RETIRE)
                DB::table('dat_pr')
                    ->where('rab', $rab)
                    ->whereIn('id', $pilih)
                    ->delete();

                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'DAT PR berhasil diproses sebagai MODAL.',
                ]);
            }
            
            if ($tipe === 'MODAL') {
                DB::table('dat_pr')
                    ->where('rab', $rab)
                    ->whereIn('id', $pilih)
                    ->delete();

                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'DAT PR berhasil diperbarui (retire).',
                ]);
            }

            if (!empty($iden)) {
                foreach ($iden as $id) {
                    DB::table($site)
                        ->where('rab', $rab)
                        ->where('id', $id)
                        ->update(['flag_dat_pr' => $reff]);
                }

                foreach ($pilih as $id) {
                    DB::table('dat_pr')
                        ->where('rab', $rab)
                        ->where('id', $id)
                        ->update(['flag_realisasi' => $reff]);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Matching berhasil disimpan.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error saat melakukan proses: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $data = $request->input('data');

        if (!is_array($data) || count($data) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Data kosong atau format tidak valid.'
            ], 400);
        }

        /**
         * Fungsi konversi encoding Excel → UTF-8 (TIDAK menghapus karakter)
         */
        $fixEncoding = function ($value) {
            if (!is_string($value)) {
                return $value;
            }

            // 1️⃣ Hilangkan BOM jika ada
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

            // 2️⃣ Ganti NBSP (Excel non-breaking space) → spasi biasa
            $value = str_replace("\xC2\xA0", ' ', $value);

            // 3️⃣ Pastikan UTF-8 TANPA merusak karakter
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            }

            // 4️⃣ Rapikan spasi
            return trim(preg_replace('/\s+/', ' ', $value));
        };

        DB::beginTransaction();

        try {
            foreach ($data as $row) {

                // Pastikan setiap row adalah array
                if (!is_array($row)) {
                    continue;
                }

                // Ambil & perbaiki encoding setiap kolom
                $rab   = $fixEncoding($row['rab'] ?? null);
                $toko  = $fixEncoding($row['Toko'] ?? null);

                // Skip baris wajib kosong
                if (empty($rab) || empty($toko)) {
                    continue;
                }

                DB::table('dat_pr')->insert([
                    'rab'           => $rab,
                    'site'          => $toko,
                    'seri'          => $fixEncoding($row['No. Seri'] ?? null),
                    'keterangan'    => $fixEncoding($row['Keterangan'] ?? null),
                    'surkas'        => $fixEncoding($row['Surplus'] ?? null),
                    'inv_num'       => $fixEncoding($row['Invoice Number'] ?? null),
                    'tgl_perolehan' => !empty($row['Bulan Mulai Diamortasi'])
                        ? date('Y-m-d', strtotime($row['Bulan Mulai Diamortasi']))
                        : null,
                    'harga'         => is_numeric($row['Harga Perolehan'] ?? null)
                        ? (int)$row['Harga Perolehan']
                        : 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Import DAT/PR berhasil disimpan.'
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            \Log::error('IMPORT DAT_PR ERROR', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data.'
            ], 500);
        }
    }

    public function matchInv(Request $request)
    {
        $data = $request->input('data');

        if (!$data || !is_array($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid.'
            ], 400);
        }

        try {
            DB::beginTransaction();

            foreach ($data as $item) {
                $id_datpr = $item['id_datpr'] ?? null;
                $id_realisasi = $item['id_realisasi'] ?? null;
                $no_rab = $item['no_rab'] ?? null;
                $kd_toko = $item['kd_toko'] ?? null;

                if (!$id_datpr || !$id_realisasi || !$kd_toko) {
                    continue;
                }

                $reff = $id_datpr . '-' . $kd_toko;
                $tableRealisasi = strtolower($kd_toko);

                DB::table('dat_pr')
                    ->where('id', $id_datpr)
                    ->update([
                        'flag_realisasi' => $reff
                    ]);
                
                DB::table($tableRealisasi)
                    ->where('id', $id_realisasi)
                    ->update([
                        'flag_dat_pr' => $reff
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Matching berhasil disimpan.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error match-plu: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.'
            ], 500);
        }
    }

    public function datprunmatch(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter cabang tidak ditemukan.'
            ], 400);
        }

        $data = DB::table('dat_pr')
            ->join('lpd', 'dat_pr.rab', '=', 'lpd.no_rab')
            ->where(function ($query) {
                $query->whereNull('dat_pr.flag_realisasi')
                    ->orWhere('dat_pr.flag_realisasi', '');
            })
            ->whereIn('dat_pr.surkas', ['L', 'N'])
            ->where('lpd.cabang', $cabang)
            ->where('lpd.status', '!=', 'Final')
            ->select('dat_pr.*', 'lpd.no_rab', 'lpd.kd_toko', 'lpd.nama_toko', 'lpd.jns_toko', 'lpd.no_rab', 'lpd.tgl_wrlb')
            ->orderBy('lpd.tgl_wrlb')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function filter(Request $request)
    {
        try {
            // ambil parameter cabang dari query (?cabang=xxx)
            $cabang = $request->query('cabang');

            // ===============================
            // STATUS
            // ===============================
            $statusQuery = DB::table('master_dat_pr')
                ->select('status')
                ->whereNotNull('status');

            if ($cabang) {
                $statusQuery->where('cabang', $cabang);
            }

            $status = $statusQuery
                ->distinct()
                ->orderBy('status')
                ->pluck('status');

            // ===============================
            // KD TOKO
            // ===============================
            $tokoQuery = DB::table('master_dat_pr')
                ->select('kd_toko')
                ->whereNotNull('kd_toko');

            if ($cabang) {
                $tokoQuery->where('cabang', $cabang);
            }

            $kd_toko = $tokoQuery
                ->distinct()
                ->orderBy('kd_toko')
                ->pluck('kd_toko');

            // ===============================
            // MIN & MAX TANGGAL
            // ===============================
            $tglQuery = DB::table('master_dat_pr');

            if ($cabang) {
                $tglQuery->where('cabang', $cabang);
            }

            $min_tgl = $tglQuery->min('tgl_mulai_susut');
            $max_tgl = $tglQuery->max('tgl_mulai_susut');

            // ===============================
            // RESPONSE
            // ===============================
            return response()->json([
                'status'   => $status,
                'kd_toko'  => $kd_toko,
                'min_tgl'  => $min_tgl,
                'max_tgl'  => $max_tgl,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil filter',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request)
    {
        $request->validate([
            'status'    => 'required|string',
            'kd_toko'   => 'nullable|array',
            'tgl_awal'  => 'required|date',
            'tgl_akhir' => 'required|date|after_or_equal:tgl_awal',
        ]);

        try {
            $query = DB::table('master_dat_pr')
                ->where('status', $request->status)
                ->whereBetween('tgl_mulai_susut', [
                    $request->tgl_awal,
                    $request->tgl_akhir
                ]);

            if (!empty($request->kd_toko)) {
                $query->whereIn('kd_toko', $request->kd_toko);
            }

            $data = $query
                ->orderBy('tgl_mulai_susut', 'desc')
                ->get();

            return response()->json([
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mencari data',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function importATPR(Request $request)
    {
        // ================= VALIDASI =================
        $validator = Validator::make($request->all(), [
            'cabang'   => 'required|string',
            'files'    => 'required',
            'files.*'  => 'file|mimes:txt',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $cabang = $request->input('cabang');
        $files  = $request->file('files');

        DB::beginTransaction();

        try {
            foreach ($files as $file) {

                $lines = file(
                    $file->getRealPath(),
                    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
                );

                // Skip header
                foreach (array_slice($lines, 1) as $line) {

                    $cols = array_map('trim', explode('|', $line));
                    $colCount = count($cols);

                    // Valid minimum kolom
                    if ($colCount < 28) {
                        continue;
                    }

                    // ================= DETEKSI JENIS =================
                    $jns = $colCount >= 30 ? 'PR' : 'AT';

                    if ($jns === 'PR') {
                        // ===== PR =====
                        $status         = $cols[2]  ?? null;
                        $kdToko         = isset($cols[3]) ? substr(trim($cols[3]), 0, 4) : null;
                        $seri           = $cols[6]  ?? null;
                        $keterangan     = $this->fixEncoding($cols[7] ?? null);
                        $surkas         = $cols[10] ?? null;
                        $invNum         = $this->fixEncoding($cols[13] ?? null);
                        $tglMulaiSusut  = $this->parseTanggal($cols[20] ?? null);
                        $harga          = $cols[24] ?? 0;

                    } else {
                        // ===== AT =====
                        $status         = $cols[16] ?? null;
                        $kdToko         = isset($cols[2]) ? substr(trim($cols[2]), 0, 4) : null;
                        $seri           = $cols[5]  ?? null;
                        $keterangan     = $this->fixEncoding($cols[6] ?? null);
                        $surkas         = $cols[9]  ?? null;
                        $invNum         = $this->fixEncoding($cols[13] ?? null);
                        $tglMulaiSusut  = $this->parseTanggal($cols[18] ?? null);
                        $harga          = $cols[22] ?? 0; // Biaya Perolehan
                    }

                    // ===== VALIDASI WAJIB =====
                    if (!$kdToko || !$seri) {
                        continue;
                    }

                    $harga = str_replace(',', '', $harga);

                    // ================== LOGIKA BARU (CEK → INSERT / UPDATE / SKIP) ==================

                    $existing = DB::table('master_dat_pr')
                        ->where('seri', $seri)
                        ->where('cabang', $cabang)   // penting jika seri bisa sama di cabang berbeda
                        ->first();

                    if (!$existing) {
                        // ✅ STEP 1 — BELUM ADA → INSERT BARU
                        DB::table('master_dat_pr')->insert([
                            'cabang'          => $cabang,
                            'status'          => $status,
                            'kd_toko'         => $kdToko,
                            'seri'            => $seri,
                            'keterangan'      => $keterangan,
                            'surkas'          => $surkas,
                            'inv_num'         => $invNum,
                            'tgl_mulai_susut' => $tglMulaiSusut,
                            'harga_perolehan' => is_numeric($harga) ? (float)$harga : 0,
                            'jns'             => $jns,
                            'tgl_proses'      => now(),
                        ]);

                    } else {
                        // ================= STEP 2 — SERI ADA, CEK STATUS =================
                        $updates = [];

                        if ($existing->status !== $status) {
                            $updates['status'] = $status;
                        }

                        // ================= STEP 3 — CEK SURKAS =================
                        if ($existing->surkas !== $surkas) {
                            $updates['surkas'] = $surkas;
                        }

                        // Kalau ada perubahan, update
                        if (!empty($updates)) {
                            $updates['tgl_proses'] = now(); // opsional, tapi biasanya perlu

                            DB::table('master_dat_pr')
                                ->where('seri', $seri)
                                ->where('cabang', $cabang)
                                ->update($updates);
                        }
                        // Jika $updates kosong → otomatis SKIP (tidak ada perubahan)
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Import Aktiva & Prepaid berhasil.',
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('IMPORT ATPR ERROR', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import data.',
            ], 500);
        }
    }

    public function atprAction(Request $request)
    {
        /**
         * =========================
         * VALIDASI REQUEST
         * =========================
         */
        $validator = Validator::make($request->all(), [
            'cabang' => 'required|string',
            'action' => 'required|string',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $cabang = $request->cabang;
        $ids    = $request->ids;

        $inserted = 0;
        $skipped  = 0;

        DB::beginTransaction();

        try {

            foreach ($ids as $id) {

                /**
                 * =========================
                 * 1. AMBIL MASTER DATA PR
                 * =========================
                 */
                $master = DB::table('master_dat_pr')
                    ->where('id', $id)
                    ->first();

                if (!$master) {
                    $skipped++;
                    continue;
                }

                /**
                 * =========================
                 * 2. CEK LPD (NEW + CABANG)
                 * =========================
                 */
                $lpd = DB::table('lpd')
                    ->where('kd_toko', $master->kd_toko)
                    ->where('status', 'NEW')
                    ->where('cabang', $cabang)
                    ->first();

                if (!$lpd) {
                    $skipped++;
                    continue;
                }

                /**
                 * =========================
                 * 3. CEK DUPLIKASI SERI
                 * =========================
                 */
                if (!empty($master->seri)) {
                    $seriExist = DB::table('dat_pr')
                        ->where('seri', $master->seri)
                        ->exists();

                    if ($seriExist) {
                        $skipped++;
                        continue;
                    }
                }

                /**
                 * =========================
                 * 4. INSERT KE dat_pr
                 * =========================
                 */
                DB::table('dat_pr')->insert([
                    'rab'           => $lpd->no_rab,                  // dari LPD
                    'site'          => $master->kd_toko,              // dari master
                    'seri'          => $master->seri ?? null,
                    'keterangan'    => $master->keterangan ?? null,
                    'surkas'        => $master->surkas ?? null,
                    'inv_num'       => $master->inv_num ?? null,
                    'tgl_perolehan' => $master->tgl_mulai_susut ?? null,
                    'harga'         => $master->harga_perolehan ?? 0,
                ]);

                $inserted++;
            }

            DB::commit();

            return response()->json([
                'message' => "Proses selesai. Berhasil: {$inserted}, Dilewati: {$skipped}",
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            \Log::error('ATPR ACTION ERROR', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses data',
            ], 500);
        }
    }
    
    public function atprToko(Request $request)
    {
        $kdToko = $request->query('kd_toko');

        if (!$kdToko) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter kd_toko wajib diisi',
                'data' => []
            ], 422);
        }

        $data = DB::table('master_dat_pr')
            ->where('kd_toko', $kdToko)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function atprImport(Request $request)
    {
        // ✅ Validasi input
        $validator = Validator::make($request->all(), [
            'no_rab' => 'required|string',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $noRab = $request->no_rab;
        $ids   = $request->ids;

        // Ambil data master_dat_pr
        $masters = DB::table('master_dat_pr')
            ->whereIn('id', $ids)
            ->get();

        if ($masters->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data master AT/PR tidak ditemukan',
            ], 404);
        }

        $inserted = 0;
        $skipped  = 0;

        DB::beginTransaction();

        try {
            foreach ($masters as $m) {
                // 🔎 Cek apakah sudah ada di dat_pr
                $exists = DB::table('dat_pr')
                    ->where('seri', $m->seri)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // ➕ Insert ke dat_pr
                DB::table('dat_pr')->insert([
                    'rab'            => $noRab,
                    'site'           => $m->kd_toko,
                    'seri'           => $m->seri,
                    'keterangan'     => $m->keterangan,
                    'surkas'         => $m->surkas,
                    'inv_num'        => $m->inv_num,
                    'tgl_perolehan'  => $m->tgl_mulai_susut,
                    'harga'          => $m->harga_perolehan,
                ]);

                $inserted++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import AT/PR selesai. Berhasil: {$inserted}, Dilewati: {$skipped}",
                'data' => [
                    'inserted' => $inserted,
                    'skipped'  => $skipped,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import AT/PR',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ================= HELPER =================
     */

    private function parseTanggal($value)
    {
        if (!$value) return null;

        try {
            // Contoh: 31-JUL-19
            return \Carbon\Carbon::createFromFormat('d-M-y', strtoupper($value))
                ->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fixEncoding($value)
    {
        if (!is_string($value)) return $value;

        $value = iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        $value = str_replace("\xC2\xA0", ' ', $value);

        return trim($value);
    }

}
