<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class ApiAuthController extends Controller
{
    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request)
    {
        // Validasi input untuk registrasi
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required'],
        ]);

        // Membuat user baru
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role_id'=> 2, // Default role customer
            'password' => Hash::make($request->password),
        ]);

        // Membuat token untuk user yang baru mendaftar
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'name' => $user->name,
            'email'=> $user->email,
            'role' => $user->role, 
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Kredensial yang diberikan salah.'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'role' => $user->role->role,
        ]);
    }
}