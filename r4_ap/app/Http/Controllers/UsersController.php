<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Users;

class UsersController extends Controller
{
        public function usersList(Request $request)
        {
            $cabang = $request->query('cabang');

            if (!$cabang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter cabang tidak ditemukan'
                ], 400);
            }

            // Gunakan model yang benar: Users
            $data = Users::where('cabang', $cabang)
                    ->select('id', 'cabang', 'nama', 'username', 'pass', 'password', 'foto', 'level_user', 'ip', 'foto')
                    ->get();;

            return response()->json($data);
        }

        public function usersAdd(Request $request)
        {
            try {
                $validated = $request->validate([
                    'id' => 'nullable|integer',
                    'nama' => 'required|string|max:100',
                    'username' => 'required|string|max:100',
                    'password' => ['required', 'string', 'min:6', 'not_regex:/\s/'],
                    'level' => 'required|in:Cabang,Region',
                    'cabang' => 'required|in:Semarang,Klaten,Yogyakarta,Medan,Pontianak',
                    'ip_komp' => 'required|ip',
                    'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:1024',
                ]);

                $fotoPath = null;
                if ($request->hasFile('foto')) {
                    $fotoPath = $request->file('foto')->store('uploads/foto_users', 'public');
                }

                if (!empty($validated['id'])) {
                    // Update user
                    $existing = Users::find($validated['id']);

                    if ($existing) {
                        $existing->nama = $validated['nama'];
                        $existing->username = $validated['username'];
                        $existing->password = $validated['password']; // Simpan password asli
                        $existing->pass = bcrypt($validated['password']); // Simpan hash password
                        $existing->level_user = $validated['level'];
                        $existing->cabang = $validated['cabang'];
                        $existing->ip = $validated['ip_komp'];
                        if ($fotoPath) {
                            $existing->foto = $fotoPath;
                        }
                        $existing->save();

                        return response()->json([
                            'message' => 'User berhasil diperbarui.',
                            'data' => $existing
                        ]);
                    } else {
                        return response()->json(['message' => 'User tidak ditemukan.'], 404);
                    }
                } else {
                    // Tambah user baru, cek username duplikat
                    $exists = Users::where('username', $validated['username'])->first();
                    if ($exists) {
                        return response()->json(['message' => 'Username sudah digunakan.'], 422);
                    }

                    $newUser = Users::create([
                        'nama' => $validated['nama'],
                        'username' => $validated['username'],
                        'password' => $validated['password'], // Simpan password asli
                        'pass' => bcrypt($validated['password']), // Simpan hash password
                        'level_user' => $validated['level'],
                        'cabang' => $validated['cabang'],
                        'ip' => $validated['ip_komp'],
                        'foto' => $fotoPath,
                    ]);

                    return response()->json([
                        'message' => 'User berhasil ditambahkan.',
                        'data' => $newUser
                    ], 201);
                }

            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Terjadi kesalahan saat menyimpan user.',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

}
