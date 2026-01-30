<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Kamar;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        // FIX LOGIKA: Gunakan firstOrCreate.
        // Jika tabel kosong (saat testing), dia akan membuat role 'customer'.
        // Jika tabel ada isi (saat seeding), dia akan pakai yang ada.
        $roleCustomer = Role::firstOrCreate(['role' => 'customer']);

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => $roleCustomer->id, // Tidak akan pernah NULL lagi
            'no_hp' => fake()->phoneNumber(),
            'gender' => fake()->randomElement(['pria', 'wanita']),
        ];
    }

    // ... method owner dan customer tetap sama ...
    public function owner(): static
    {
        return $this->state(fn(array $attributes) => [
            'role_id' => Role::firstOrCreate(['role' => 'owner'])->id,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn(array $attributes) => [
            'role_id' => Role::firstOrCreate(['role' => 'customer'])->id,
        ]);
    }

    // PERBAIKAN 3 (Lihat di bawah): Tambahkan method unverified
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
