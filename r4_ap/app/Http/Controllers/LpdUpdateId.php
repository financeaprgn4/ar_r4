<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\LpdRealisasiService;

class LpdUpdateId extends Controller
{
    public function update_id(Request $request)
    {
        try {
            $items = $request->all();

            foreach ($items as $item) {
                $validator = Validator::make($item, $this->rules());
                
                if ($validator->fails()) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Validasi gagal',
                        'errors'  => $validator->errors(),
                    ], 422);
                }

                $validated = $validator->validated();
                $tableName = $validated['kd_toko'];
                $total = ($validated['dpp'] ?? 0) + ($validated['ppn'] ?? 0);

                // Update ke tabel toko
                DB::table($tableName)
                    ->where('id', $validated['id'])
                    ->update([
                        'no_sjf'     => $validated['no_sjf'] ?? '',
                        'line_num'   => $validated['line_num'] ?? '',
                        'kd_group'   => $validated['kd_group'] ?? '',
                        'plu'        => $validated['plu'] ?? '',
                        'keterangan' => $validated['keterangan'] ?? '',
                        'dpp'        => $validated['dpp'] ?? 0,
                        'ppn'        => $validated['ppn'] ?? 0,
                        'total'      => $total,
                        'inv_num'    => $validated['inv_num'] ?? '',
                    ]);

                LpdRealisasiService::updateDetail($validated);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Data berhasil diperbarui.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function lpdtrxAdd(Request $request)
    {
        try {
            $items = $request->all();

            foreach ($items as $item) {
                $validator = Validator::make($item, $this->rules(['id' => 'nullable']));

                if ($validator->fails()) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Validasi gagal',
                        'errors'  => $validator->errors(),
                    ], 422);
                }

                $validated = $validator->validated();
                $tableName = $validated['kd_toko'];
                $total = ($validated['dpp'] ?? 0) + ($validated['ppn'] ?? 0);

                // Insert ke tabel toko
                DB::table($tableName)->insert([
                    'rab'         => $validated['rab'] ?? '',
                    'no_sjf'      => $validated['no_sjf'] ?? '',
                    'line_num'    => $validated['line_num'] ?? '',
                    'kd_group'    => $validated['kd_group'] ?? '',
                    'plu'         => $validated['plu'] ?? '',
                    'keterangan'  => $validated['keterangan'] ?? '',
                    'dpp'         => $validated['dpp'] ?? 0,
                    'ppn'         => $validated['ppn'] ?? 0,
                    'total'       => $total,
                    'inv_num'     => $validated['inv_num'] ?? '',
                    'flag_sarana' => $validated['flag_sarana'] ?? '',
                    'flag_renov'  => $validated['flag_renov'] ?? '',
                    'flag_dat_pr' => $validated['flag_dat_pr'] ?? '',
                ]);

                LpdRealisasiService::updateDetail($validated);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Data berhasil ditambahkan.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function rules($override = [])
    {
        return array_merge([
            'id'         => 'required|integer',
            'inv_num'    => 'nullable|string',
            'no_sjf'     => 'nullable|string',
            'line_num'   => 'nullable|string',
            'kd_group'   => 'nullable|string',
            'plu'        => 'nullable|string',
            'dpp'        => 'nullable|numeric',
            'ppn'        => 'nullable|numeric',
            'keterangan' => 'nullable|string',
            'kd_toko'    => 'required|string',
            'rab'        => 'nullable|string',
        ], $override);
    }
}