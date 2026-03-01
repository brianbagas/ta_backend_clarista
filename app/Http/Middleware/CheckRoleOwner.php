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
    public function handle(Request $request, Closure $next, string $roleDibutuhkan): Response
    {


        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();

        // Load relasi role
        /** @var \App\Models\User $user */
        $user->load('role');

        // Cek role
        if ($user->role?->role === $roleDibutuhkan) {
            return $next($request);
        }


        return response()->json([
            'success' => false,
            'message' => 'Akses Ditolak (Forbidden). Role tidak sesuai.',
            'errors' => [
                'role_anda' => $user->role?->role ?? 'Tidak punya role',
                'role_dibutuhkan' => $roleDibutuhkan
            ]
        ], 403);
    }
}