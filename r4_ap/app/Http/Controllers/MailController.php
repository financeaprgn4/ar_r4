<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mail;

class MailController extends Controller
{
    public function mailAdd(Request $request)
    {
        try {
            $request->merge([
                'id' => $request->id === '' ? null : $request->id
            ]);

            $validated = $request->validate([
                'id'     => 'sometimes|nullable|integer',
                'cabang' => 'required|string',
                'mail' => 'required|email',
                'untuk' => 'required|string',
                'sub' => 'required|string',
            ]);

            if (!empty($validated['id'])) {
                // Kalau id ada, cari data berdasarkan id dan update
                $existing = mail::find($validated['id']);

                if ($existing) {
                    $existing->cabang = $validated['cabang'];
                    $existing->mail = $validated['mail'];
                    $existing->untuk = $validated['untuk'];
                    $existing->sub = $validated['sub'];
                    $existing->save();

                    return response()->json([
                        'message' => 'Email berhasil diperbarui.',
                        'data' => $existing
                    ]);
                } else {
                    // Kalau id tidak ditemukan, bisa pilih untuk insert baru atau kirim error
                    return response()->json(['message' => 'Data tidak ditemukan untuk diperbarui.'], 404);
                }
            } else {
                // Kalau id kosong, lakukan cek apakah data sudah ada berdasarkan cabang, mail, dan untuk
                $existing = mail::where('cabang', $validated['cabang'])
                                ->where('mail', $validated['mail'])
                                ->where('untuk', $validated['untuk'])
                                ->first();

                if ($existing) {
                    // Update sub jika data sudah ada
                    $existing->sub = $validated['sub'];
                    $existing->save();

                    return response()->json([
                        'message' => 'Email berhasil diperbarui.',
                        'data' => $existing
                    ]);
                } else {
                    // Insert data baru jika belum ada
                    $new = mail::create($validated);
                    
                    return response()->json([
                        'message' => 'Email berhasil disimpan.',
                        'data' => $new
                    ]);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function mailList(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json([
                'message' => 'Cabang tidak ditemukan dalam request.'
            ], 400);
        }

        $data = Mail::where('cabang', $cabang)
                    ->select('id','cabang', 'mail', 'untuk', 'sub')
                    ->get();

        return response()->json($data);
    }

    public function mailDelete($id)
    {
        $email = mail::find($id);

        if (!$email) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $email->delete();

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }
}
