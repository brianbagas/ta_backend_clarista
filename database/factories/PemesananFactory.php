<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pemesanan>
 */
class PemesananFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Logic untuk memastikan user valid punya role customer
        $customerUser = User::whereHas('role', function ($q) {
            $q->where('role', 'customer');
        })->inRandomOrder()->first();

        // Fallback jika belum ada user customer sama sekali
        if (!$customerUser) {
            $roleId = Role::firstOrCreate(['role' => 'customer'])->id;
            $customerUser = User::factory()->create(['role_id' => $roleId]);
        }

        $checkIn = fake()->dateTimeBetween('now', '+1 month');
        $checkOut = Carbon::instance($checkIn)->addDays(fake()->numberBetween(1, 5));

        return [
            'user_id' => $customerUser->id,
            'kode_booking' => 'CL-' . strtoupper(fake()->lexify('??????')),
            'tanggal_check_in' => $checkIn->format('Y-m-d'),
            'tanggal_check_out' => $checkOut->format('Y-m-d'),
            'total_bayar' => fake()->numberBetween(300000, 2000000),
            'status_pemesanan' => fake()->randomElement(['menunggu_pembayaran', 'dikonfirmasi', 'selesai']),
            'catatan' => fake()->sentence(),
        ];
    }
}
