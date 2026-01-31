<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Kamar;
use App\Models\KamarUnit;

class KamarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Data kamar dengan units
        $kamars = [
            [
                'tipe_kamar' => 'Deluxe Room',
                'deskripsi' => 'Kamar deluxe dengan fasilitas lengkap termasuk AC, TV LED 32 inch, kamar mandi dalam dengan shower air panas, lemari pakaian, dan balkon pribadi dengan pemandangan taman. Cocok untuk pasangan atau keluarga kecil.',
                'harga' => 350000.00,
                'status_ketersediaan' => true,
                'jumlah_total' => 2,
                'units' => ['DLX-101', 'DLX-102']
            ],
            [
                'tipe_kamar' => 'Standard Room',
                'deskripsi' => 'Kamar standard yang nyaman dengan fasilitas AC, TV LED 24 inch, kamar mandi dalam, dan lemari pakaian. Ideal untuk tamu yang mencari akomodasi dengan harga terjangkau namun tetap nyaman.',
                'harga' => 250000.00,
                'status_ketersediaan' => true,
                'jumlah_total' => 2,
                'units' => ['STD-201', 'STD-202']
            ],
        ];

        foreach ($kamars as $kamarData) {
            // Extract units from kamar data
            $units = $kamarData['units'];
            unset($kamarData['units']);

            // Create kamar
            $kamar = Kamar::create($kamarData);

            // Create units for this kamar
            foreach ($units as $nomorUnit) {
                KamarUnit::create([
                    'kamar_id' => $kamar->id_kamar,
                    'nomor_unit' => $nomorUnit,
                    'status_unit' => 'available',
                ]);
            }

            $this->command->info("Created kamar: {$kamar->tipe_kamar} with " . count($units) . " units");
        }

        $this->command->info('Kamar seeding completed successfully!');
    }
}
