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
        // Cari ID role 'customer' yang sudah di-seed
        $defaultRoleId = Role::where('role', 'customer')->value('id');

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // FIX: Sekarang menetapkan role_id (One-to-Many)
            'role_id' => $defaultRoleId,
        ];
    }

    public function owner(): static
        {
            return $this->state(fn (array $attributes) => [
                // FIX: Dapatkan ID role 'owner'
                'role_id' => Role::where('role', 'owner')->value('id'),
            ]);
        }
            public function customer(): static
        {
            return $this->state(fn (array $attributes) => [
                // FIX: Dapatkan ID role 'customer'
                'role_id' => Role::where('role', 'customer')->value('id'),
            ]);
        }
}

class KamarFactory extends Factory
{
    public function definition(): array
    {
        $jumlah = fake()->numberBetween(1,10);
        return [
            'tipe_kamar' => 'Kamar ' . fake()->words(2, true),
            'deskripsi' => fake()->paragraph(),
            'harga' => fake()->numberBetween(300000, 1000000),
            'status_ketersediaan' => $jumlah > 0,
            // 'jumlah_tersedia'=> $jumlah,
            'jumlah_total'=> $jumlah,
            // 'status_ketersediaan' => fake()->boolean(90), // 90% kemungkinan true
            // 'jumlah_tersedia'=> fake()->numberBetween(1,10),
        ];
    }
}
class KamarImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Ambil ID kamar secara acak dari database, atau buat baru jika kosong
            'kamar_id' => Kamar::inRandomOrder()->first()->id_kamar ?? Kamar::factory(),
            
            // Simulasi nama file gambar
            'image_path' => 'images/kamars/dummy-' . $this->faker->numberBetween(1, 10) . '.jpg',
        ];
    }
}
class PromoFactory extends Factory
{
    public function definition(): array
    {
        $tipe = fake()->randomElement(['persen', 'nominal']);
        $nilai = ($tipe === 'persen') ? fake()->numberBetween(10, 50) : fake()->numberBetween(10000, 50000);

        return [
            'nama_promo' => 'Promo ' . fake()->words(2, true),
            'kode_promo' => strtoupper(fake()->unique()->word() . fake()->numberBetween(10, 99)),
            'deskripsi' => fake()->sentence(),
            'tipe_diskon' => $tipe,
            'nilai_diskon' => $nilai,
            'berlaku_mulai' => now(),
            'berlaku_selesai' => now()->addDays(fake()->numberBetween(7, 30)),
        ];
    }
}

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rating' => fake()->numberBetween(3, 5),
            'komentar' => fake()->paragraph(),
            'status' => fake()->randomElement(['disetujui', 'menunggu_persetujuan']),
        ];
    }
}

class PemesananFactory extends Factory
{
    public function definition(): array
    {
      // 1. Ambil ID role 'customer' dari tabel roles
        $customerRoleId = Role::where('role', 'customer')->value('id');

        // 2. Tentukan customer ID
        // Cari user yang sudah ada dengan role_id yang sesuai
        $existingCustomer = User::where('role_id', $customerRoleId)->inRandomOrder()->first();
        
        // 3. Jika customer tidak ditemukan (database kosong), buat user baru dengan role_id yang benar
        if (!$existingCustomer) {
            $userId = User::factory()->create(['role_id' => $customerRoleId])->id;
        } else {
            $userId = $existingCustomer->id;
        }
        
        // Tentukan tanggal
        $checkIn = $this->faker->dateTimeBetween('now', '+1 month');
        $checkOut = Carbon::instance($checkIn)->addDays($this->faker->numberBetween(1, 5));

        return [
            // FIX: Gunakan User ID yang sudah dicari berdasarkan role_id
            'user_id' => $userId, 
            
            'tanggal_check_in' => $checkIn->format('Y-m-d'),
            'tanggal_check_out' => $checkOut->format('Y-m-d'),
            
            'total_bayar' => 0, 
            
            // Perhatikan: Status 'confirmed' lama harus diubah ke 'dikonfirmasi' jika Anda pakai bahasa Indonesia di migrasi.
            'status_pemesanan' => $this->faker->randomElement(['menunggu_pembayaran', 'dikonfirmasi', 'selesai']),
        ];
    }
}