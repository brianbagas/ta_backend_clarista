<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use App\Traits\ApiResponseTrait;

class ApiAuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request)
    {
        // Validasi input untuk registrasi
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'min:8'],
            'no_hp' => ['required', 'string', 'max:20'],
            'gender' => ['required', 'in:pria,wanita'],
        ]);

        // Membuat user baru
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'no_hp' => $request->no_hp,
            'gender' => $request->gender,
            'role_id' => 2, // Default role customer
            'password' => Hash::make($request->password),
        ]);



        // Membuat token untuk user yang baru mendaftar
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->role ?? 'customer', // Handled if role relation not loaded
        ], 'Registrasi berhasil', 201);
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
            return $this->errorResponse('Kredensial yang diberikan salah.', 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'role' => $user->role->role,
        ], 'Login berhasil');
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout berhasil');
    }

    public function checkMe(Request $request)
    {
        $user = $request->user();

        // Kita susun data yang ingin dikembalikan
        $userData = [
            'user_id' => $user->id,
            'nama' => $user->name,
            'role' => $user->role->role, // Mengambil string role dari relasi
        ];

        // Menggunakan successResponse dari trait
        return $this->successResponse($userData, 'Token Valid!');
    }

    /**
     * Get authenticated user data.
     */
    public function getUser(Request $request)
    {
        $user = $request->user();

        // Load relasi role untuk memastikan data lengkap
        $user->load('role');

        return $this->successResponse($user, 'Data user berhasil diambil');
    }
}