<?php

namespace App\Http\Middleware;
use Illuminate\Database\Eloquent\Model;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

use function Laravel\Prompts\error;

class CheckRoleOwner
{
    // public function handle(Request $request, Closure $next, string $role): Response
    // {
    //     // Periksa apakah pengguna sudah login DAN memiliki role 'owner'
    //     if (Auth::check() && Auth::user()->role->role === $role) {
    //         // Jika ya, lanjutkan permintaan
    //         return $next($request);
    //     }else{
    //         return response()->json(['message'=> 'GAGALTERAUTENTIKASI'], 401);
    //     }
    //     return response()->json(['message' => 'RUSAK']);

    //     // Jika tidak, tolak akses dengan pesan error

    //     if($role === 'owner'){
    //    return response()->json(['message' => 'Akses ditolak. Hanya untuk Owner.'], 403);
    //     }
    //     else{
    //         return response()->json(['message' => 'Akses ditolak. Hanya untuk Customer.'], 403);
    //     }
 
    // }

    public function handle(Request $request, Closure $next, string $roleDibutuhkan): Response
{
    
    // 1. Cek Login
    if (!Auth::check()) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    $user = Auth::user();

    // 2. LOAD RELASI (PENTING!)
    // Kita pastikan data role ter-load. 
    // Jika tidak di-load, $user->role kadang cuma mereturn ID, bukan Object.
   /** @var \App\Models\User $user */
    $user->load('role');

    // 3. LOGIKA CEK ROLE (NORMALISASI)
    // Bacaannya: "Ambil User -> Masuk ke Tabel Role -> Ambil kolom nama 'role'"
    // Kita pakai operator '?->' (safe navigation) biar kalau user ga punya role, ga error system.
    if ($user->role?->role === $roleDibutuhkan) {
        return $next($request);
    }

    // 4. Debugging & Error Message
    return response()->json([
        'message' => 'Akses Ditolak (Forbidden). Role tidak sesuai.',
        'role_anda' => $user->role?->role ?? 'Tidak punya role', // Cek apa yang dibaca sistem
        'role_dibutuhkan' => $roleDibutuhkan
    ], 403);
}
}