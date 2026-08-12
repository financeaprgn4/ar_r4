<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaranaToko;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;
use App\Jobs\ImportSaranaJob;
use Smalot\PdfParser\Parser;
use App\Services\RabService;

class SaranaTokoController extends Controller
{
    public function index(Request $request)
    {
        $rab = $request->query('rab');
        $kd_toko = $request->query('kd_toko');
        
        if (!$rab) {
            return response()->json(['error' => 'Parameter rab tidak ditemukan'], 400);
        }

        // 1. Data dengan flag_realisasi kosong atau null
        $data = DB::table('sarana_toko')
            ->where('rab', $rab)
            ->where(function ($query) {
                $query->whereNull('flag_realisasi')
                    ->orWhere('flag_realisasi', '');
            })
            ->get();

        // 2. Data dengan flag_realisasi selain null, '', 'FRCSEE', 'NONPP', 'MKT'
        $sarana = DB::table('sarana_toko')
            ->where('rab', $rab)
            ->whereNotNull('flag_realisasi')
            ->where('flag_realisasi', '!=', '')
            ->whereNotIn('flag_realisasi', ['FRCSEE', 'NONPP', 'MKT', 'FSEE', 'SEWA', 'SURKAS'])
            ->orderBy('flag_realisasi')
            ->get();

        // Ambil data dari tabel dinamis kd_toko
        $kdTokoData = DB::table($kd_toko)
            ->where('rab', $rab)
            ->get()
            ->groupBy('flag_sarana');

        // Gabungkan secara manual
        $groupedRealisasi = $sarana->groupBy('flag_realisasi')->map(function ($items, $flag) use ($kdTokoData) {
            $ktGroup = $kdTokoData->get($flag);

            return [
                'flag_realisasi' => $flag,
                'items' => $items,
                'realisasi_items' => $ktGroup ? $ktGroup->values() : [],
            ];
        });
        
        // 3. Data dengan flag_realisasi = 'FRCSEE'
        $frcsee = DB::table('sarana_toko')
            ->where('rab', $rab)
            ->whereIn('flag_realisasi', ['FRCSEE', 'FSEE'])
            ->get();

        $surkas = DB::table('sarana_toko')
            ->where('rab', $rab)
            ->whereIn('flag_realisasi', ['SURKAS'])
            ->get();

        // 4. Data dengan flag_realisasi = 'NONPP'
        $nonpp = DB::table('sarana_toko')
            ->where('rab', $rab)
            ->where('flag_realisasi', 'NONPP')
            ->get();

        // 5. Data dengan flag_realisasi = 'MKT'
        $mkt = DB::table('sarana_toko')
            ->where('rab', $rab)
            ->where('flag_realisasi', 'MKT')
            ->get();

        $sewa = DB::table('sarana_toko')
            ->where('rab', $rab)
            ->where('flag_realisasi', 'SEWA')
            ->get();

        $master = DB::table('master_sarana')
            ->get();

        $pp = DB::table('pp')
            ->where('rab', $rab)
            ->get();

        foreach ($pp as $item) {
            $item->invoice_matched = false;

            foreach ($kdTokoData as $group) {
                foreach ($group as $row) {
                    if ($row->inv_num && $item->btb && str_contains($row->inv_num, $item->btb)) {
                        $item->invoice_matched = true;
                        break 2;
                    }
                }
            }
        }

        // Gabungkan semuanya ke dalam response JSON terstruktur
        return response()->json([
            'data' => $data,
            'master' => $master,
            'groupedRealisasi' => $groupedRealisasi->toArray(),
            'fsee' => $frcsee,
            'surkas' => $surkas,
            'nonpp' => $nonpp,
            'mkt' => $mkt,
            'sewa' => $sewa,
            'pp' => $pp
        ]);
    }

    public function import(Request $request)
    {
        try {
            $data = $request->input('data');

            if (!$data || !is_array($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan atau format tidak valid',
                ], 400);
            }

            $inserted = 0;
            $errors = [];

            foreach ($data as $index => $row) {
                $row = array_change_key_case($row, CASE_LOWER);

                $validator = Validator::make($row, [
                    'rab'       => 'required|string|max:100',
                    'kategori'  => 'nullable|string|max:100',
                    'kode'      => 'required|string|max:50',
                    'uraian'    => 'nullable|string|max:255',
                    'satuan'    => 'nullable|string|max:20',
                    'vol'       => 'nullable|numeric',
                    'harga'     => 'nullable|numeric',
                    'dpp'       => 'nullable|numeric',
                    'ppn'       => 'nullable|numeric',
                    'total'     => 'nullable|numeric',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $index + 1,
                        'messages' => $validator->errors()->all(),
                    ];
                    continue;
                }

                try {
                    $master = DB::table('master_sarana')->where('kode', $row['kode'])->first();

                    if (!$master) {
                        DB::table('master_sarana')->insert([
                            'kategori' => $row['kategori'] ?? null,
                            'kode'     => $row['kode'],
                            'uraian'   => $row['uraian'] ?? null,
                        ]);
                    }

                    SaranaToko::create([
                        'rab'           => $row['rab'],
                        'kategori'      => $row['kategori'] ?? null,
                        'kode'          => $row['kode'],
                        'uraian'        => $row['uraian'] ?? null,
                        'satuan'        => $row['satuan'] ?? null,
                        'qty'           => floatval($row['vol'] ?? 0),
                        'harga_satuan'  => floatval($row['harga'] ?? 0),
                        'dpp'           => floatval($row['dpp'] ?? 0),
                        'ppn'           => floatval($row['ppn'] ?? 0),
                        'total'         => floatval($row['total'] ?? 0),
                    ]);

                    $inserted++;
                } catch (\Throwable $e) {
                    \Log::error("Gagal insert row {$index}: " . $e->getMessage());
                    $errors[] = [
                        'row' => $index + 1,
                        'messages' => [$e->getMessage()],
                    ];
                }
            }

            return response()->json([
                'success' => $inserted > 0,
                'message' => $inserted > 0 
                    ? "Import berhasil. $inserted data berhasil disimpan."
                    : "Tidak ada data yang berhasil disimpan.",
                'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            \Log::error("Import gagal total: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function match(Request $request)
    {
        $request->validate([
            'no_rab' => 'required|string',
            'site'   => 'required|string',
            'pilih'  => 'array', // boleh kosong
            'iden'   => 'array', // boleh kosong
        ]);

        $rab   = $request->input('no_rab');
        $site  = $request->input('site');
        $pilih = $request->input('pilih', []); // id sarana
        $iden  = $request->input('iden', []);  // id realisasi
        $tipe  = strtoupper($request->input('tipe'));

        $reff = !empty($pilih) ? (min($pilih) . '-' . $site) : null;

        DB::beginTransaction();
        try {
            if (!empty($pilih) && !empty($iden)) {
                foreach ($iden as $id) {
                    DB::table($site)
                        ->where('rab', $rab)
                        ->where('id', $id)
                        ->update(['flag_sarana' => $reff]);
                }

                foreach ($pilih as $id) {
                    DB::table('sarana_toko')
                        ->where('rab', $rab)
                        ->where('id', $id)
                        ->update(['flag_realisasi' => $reff]);
                }
            }

            // ✅ Kondisi 2: Hanya sarana dipilih
            elseif (!empty($pilih) && empty($iden)) {
                foreach ($pilih as $id) {
                    DB::table('sarana_toko')
                        ->where('rab', $rab)
                        ->where('id', $id)
                        ->update(['flag_realisasi' => $tipe]);
                }
            }

            // ✅ Kondisi 3: Hanya realisasi dipilih
            elseif (empty($pilih) && !empty($iden)) {
                foreach ($iden as $id) {
                    DB::table($site)
                        ->where('rab', $rab)
                        ->where('id', $id)
                        ->update(['flag_sarana' => $tipe]);
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
                'message' => 'Error saat melakukan matching: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function matchPlu(Request $request)
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
                $id_sarana = $item['id_sarana'] ?? null;
                $id_realisasi = $item['id_realisasi'] ?? null;
                $no_rab = $item['no_rab'] ?? null;
                $kd_toko = $item['kd_toko'] ?? null;

                if (!$id_sarana || !$id_realisasi || !$kd_toko) {
                    continue;
                }

                $reff = $id_sarana . '-' . $kd_toko;
                $tableRealisasi = strtolower($kd_toko);

                // Update sarana_toko.flag_realisasi
                DB::table('sarana_toko')
                    ->where('id', $id_sarana)
                    ->update([
                        'flag_realisasi' => $reff
                    ]);
                
                DB::table($tableRealisasi)
                    ->where('id', $id_realisasi)
                    ->update([
                        'flag_sarana' => $reff
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

    public function destroy(Request $request)
    {
        $flag = $request->input('flag');
        $rab = $request->input('rab');
        $kd_toko = $request->input('kd_toko');

        if (!$flag || !$rab || !$kd_toko) {
            return response()->json(['error' => 'Parameter tidak lengkap.'], 400);
        }

        // Update sarana_toko: set flag_realisasi = null
        DB::table('sarana_toko')
            ->where('rab', $rab)
            ->where('flag_realisasi', $flag)
            ->update(['flag_realisasi' => '']);

        // Update tabel dinamis (kd_toko): set flag_sarana = null
        DB::table($kd_toko)
            ->where('rab', $rab)
            ->where('flag_sarana', $flag)
            ->update(['flag_sarana' => '']);

        return response()->json(['message' => 'Data berhasil dipindahkan (flag dikosongkan).']);
    }

    public function Fseeprocess(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'tipe' => 'required|string|in:UNREALIZED,SEWA,NONPP,SURKAS',
            'rab' => 'required|string'
        ]);

        try {
            $flagValue = match ($validated['tipe']) {
                'UNREALIZED' => null,
                'NONPP'      => 'NONPP',
                'SEWA'       => 'SEWA',
                'SURKAS'     => 'SURKAS',
                default      => null,
            };

            $affected = DB::table('sarana_toko')
                ->where('rab', $validated['rab'])
                ->whereIn('id', $validated['ids'])
                ->update([
                    'flag_realisasi' => $flagValue
                ]);

            return response()->json([
                'success' => true,
                'message' => "Sebanyak {$affected} data berhasil diperbarui."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function Surkasprocess(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'tipe' => 'required|string|in:UNREALIZED,SEWA,FSEE,NONPP',
            'rab' => 'required|string'
        ]);

        try {
            $flagValue = match ($validated['tipe']) {
                'UNREALIZED' => null,
                'NONPP'      => 'NONPP',
                'SEWA'       => 'SEWA',
                'FSEE'       => 'FSEE',
                default      => null,
            };

            $affected = DB::table('sarana_toko')
                ->where('rab', $validated['rab'])
                ->whereIn('id', $validated['ids'])
                ->update([
                    'flag_realisasi' => $flagValue
                ]);

            return response()->json([
                'success' => true,
                'message' => "Sebanyak {$affected} data berhasil diperbarui."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function Nonppprocess(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'tipe' => 'required|string|in:UNREALIZED,SEWA,FSEE,SURKAS',
            'rab' => 'required|string'
        ]);

        try {
            $flagValue = match ($validated['tipe']) {
                'UNREALIZED' => null,
                'SURKAS'     => 'SURKAS',
                'SEWA'       => 'SEWA',
                'FSEE'       => 'FSEE',
                default      => null,
            };

            $affected = DB::table('sarana_toko')
                ->where('rab', $validated['rab'])
                ->whereIn('id', $validated['ids'])
                ->update([
                    'flag_realisasi' => $flagValue
                ]);

            return response()->json([
                'success' => true,
                'message' => "Sebanyak {$affected} data berhasil diperbarui."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadBA(Request $request)
    {
        $request->validate([
            'rab' => 'required|string',
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $rab = $request->input('rab');
        $file = $request->file('file');

        $lpd = DB::table('lpd')->where('no_rab', $rab)->first();
        if (!$lpd) {
            return response()->json(['message' => 'Data LPD tidak ditemukan'], 404);
        }

        try {
            // Format nama file
            $tgl = date('Y-m-d', strtotime($lpd->tgl_wrlb ?? now()));
            $kdToko = preg_replace('/[^a-zA-Z0-9]/', '_', $lpd->kd_toko);
            $fileName = $kdToko . "_BA_Sarana_Tidak_Realisasi_" . $tgl . ".pdf";

            // Lokasi simpan file
            $destPath = "D:/xampp/htdocs/AP_R4/File/BA_Tidak_Realisasi";
            if (!File::exists($destPath)) {
                File::makeDirectory($destPath, 0755, true);
            }

            // Simpan file
            $file->move($destPath, $fileName);

            // Update tabel berkas_lpd
            DB::table('berkas_lpd')
                ->where('rab', $rab)
                ->update(['item_tdk_realisasi' => $fileName]);

            // Update tabel sarana_toko
            DB::table('sarana_toko')
                ->where('rab', $rab)
                ->where(function ($query) {
                    $query->whereNull('flag_realisasi')
                        ->orWhere('flag_realisasi', '');
                })
                ->update(['flag_realisasi' => 'MKT']);

            return response()->json(['message' => 'File berhasil diunggah dan data berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengunggah file: ' . $e->getMessage()], 500);
        }
    }

    public function importpp(Request $request)
    {
        try {
            $cabang = $request->input('cabang');

            if (!$cabang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang wajib diisi'
                ], 422);
            }

            $files = $request->file('files');

            if (!$files) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada file diupload'
                ], 422);
            }

            // Pastikan array
            if ($files instanceof \Illuminate\Http\UploadedFile) {
                $files = [$files];
            }

            app(\App\Services\ImportPPService::class)
                ->handleUploadFiles($files, $cabang);

            return response()->json([
                'success' => true,
                'message' => 'Import berhasil'
            ]);

        } catch (\Throwable $e) {

            \Log::error('Import PP Error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function parsePdfDate($date)
    {
        if (!$date) return null;

        return \Carbon\Carbon::createFromFormat('d-m-Y', $date)
            ->format('Y-m-d');
    }

    private function excelDateToDate($value)
    {
        if (!$value) return null;
        if (is_numeric($value)) {
            $unix = ($value - 25569) * 86400;
            return gmdate('Y-m-d', $unix);
        }
        return $value ?: null;
    }

    public function masterSarana()
    {
        $data = DB::table('master_sarana')
            ->select('kode')
            ->get();

        return response()->json($data);
    }

    protected $rabService;
    public function __construct(RabService $rabService)
    {
        $this->rabService = $rabService;
    }

    public function getRabDetail(Request $request)
    {
        $kodeRab = $request->query('kode_rab');

        if (!$kodeRab) {
            return response()->json([
                'success' => false,
                'message' => 'Kode RAB wajib diisi'
            ], 422);
        }

        try {
            $data = $this->rabService->fetchRabDetail($kodeRab);

            return response()->json($data);

        } catch (\Throwable $e) {
            \Log::error('RAB Sync Error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data RAB'
            ], 500);
        }
    }
    
    public function importSarana(Request $request)
    {
        $data = $request->input('data', []);

        if (empty($data)) {
            return response()->json([
                'message' => 'Data kosong, tidak ada yang diimport.'
            ], 400);
        }

        $inserted = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            foreach ($data as $item) {
                $rab     = $item['rab'] ?? null;
                $kode    = $item['kode'] ?? null;
                $uraian  = $item['uraian'] ?? null;
                $qty     = $item['qty'] ?? 0;
                $dpp     = $item['dpp'] ?? 0;
                $ppn     = $item['ppn'] ?? 0;
                $total   = $item['total'] ?? 0;

                if (!$rab || !$kode) {
                    $skipped++;
                    continue;
                }

                // 1. Cek di master_sarana
                $master = DB::table('master_sarana')->where('kode', $kode)->first();
                if (!$master) {
                    DB::table('master_sarana')->insert([
                        'kode'   => $kode,
                        'uraian' => $uraian,
                    ]);
                    // reload master setelah insert supaya dapat kategori (default/null)
                    $master = DB::table('master_sarana')->where('kode', $kode)->first();
                }

                // Ambil kategori dari master_sarana
                $kategori = $master->kategori ?? null;

                // 2. Cek di sarana_toko (kombinasi rab + kode)
                $existsSarana = DB::table('sarana_toko')
                    ->where('rab', $rab)
                    ->where('kode', $kode)
                    ->exists();

                if (!$existsSarana) {
                    DB::table('sarana_toko')->insert([
                        'rab'      => $rab,
                        'kode'     => $kode,
                        'uraian'   => $uraian,
                        'qty'      => $qty,
                        'dpp'      => $dpp,
                        'ppn'      => $ppn,
                        'total'    => $total,
                        'kategori' => $kategori,
                    ]);
                    $inserted++;
                } else {
                    $skipped++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Import selesai. $inserted data berhasil dimasukkan, $skipped data dilewati.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            logger()->error('IMPORT SARANA ERROR', [
                'message' => $e->getMessage(),
                'item'    => $item ?? null,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Gagal import: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateSurkas(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'surkas' => 'nullable|string'
        ]);

        $updated = DB::table('pp')
            ->where('id', $request->id)
            ->update([
                'surkas' => $request->surkas ? 'Y' : null,
            ]);

        return response()->json([
            'success' => $updated > 0,
            'message' => $updated > 0 ? 'Updated' : 'No change'
        ]);
    }

}
