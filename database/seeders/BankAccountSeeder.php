<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\BankAccount; // Pastikan model ini ada

class BankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Data Bank yang akan di-seed
        $banks = [
            [
                'nama_bank' => 'BCA',
                'nomor_rekening' => '123-456-7890',
                'atas_nama' => 'Clarista Owner',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_bank' => 'BRI',
                'nomor_rekening' => '098-765-4321',
                'atas_nama' => 'Clarista Owner',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Tambahkan bank lain jika perlu
        ];

        // Masukkan data ke tabel bank_accounts
        DB::table('bank_accounts')->insert($banks);
    }
}
