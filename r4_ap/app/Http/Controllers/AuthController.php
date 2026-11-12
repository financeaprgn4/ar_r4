<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $start = microtime(true);

        try {
            // 1. Validasi
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string'
            ]);

            // 2. Query user
            $user = DB::table('users')
                ->where('username', $request->username)
                ->first();

            // 3. Verifikasi password
            // Tetap lakukan pemeriksaan hash yang aman
            $valid = $user
                ? Hash::check($request->password, $user->pass)
                : false;

            if (!$user || !$valid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username atau password salah'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'user' => [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'username' => $user->username,
                    'role' => strtoupper(trim($user->role)),
                    'level_user' => $user->level_user,
                    'cabang' => $user->cabang,
                    'ip' => $user->ip
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {

            Log::error('LOGIN ERROR', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    }

    public function logout()
    {
        // Tidak perlu Session::forget
        return response()->json(['message' => 'Logout berhasil']);
    }

    public function cabang()
    {
        try {
            $cabang = DB::table('cabang')
                ->select('cabang')
                ->orderBy('cabang', 'asc')
                ->get();

            return response()->json($cabang, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data cabang',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
