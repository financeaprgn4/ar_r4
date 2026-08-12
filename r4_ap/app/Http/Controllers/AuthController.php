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

            $t1 = microtime(true);

            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string'
            ]);

            $t2 = microtime(true);

            $user = DB::table('users')
                ->where('username', $request->username)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ], 404);
            }

            $t3 = microtime(true);

            $valid = Hash::check(
                $request->password,
                $user->pass
            );

            if (!$valid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password salah'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'user' => [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'username' => $user->username,
                    'level_user' => $user->level_user,
                    'cabang' => $user->cabang,
                    'ip' => $user->ip
                ]
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $e->getMessage()
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
