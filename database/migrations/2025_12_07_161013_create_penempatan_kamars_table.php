<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('penempatan_kamars', function (Blueprint $table) {
            $table->id();
            
            // FK ke Detail Pemesanan (transaksi)
            // TIDAK UNIK, karena satu detail pemesanan bisa menghasilkan >1 penempatan jika jumlah_kamar > 1
            $table->unsignedBigInteger('detail_pemesanan_id'); 
            
            // FK ke Unit Fisik Kamar (misal: Kamar 101)
            $table->unsignedBigInteger('kamar_unit_id'); 
            
            // Status Siklus Hidup Penempatan
            $table->enum('status_penempatan', ['assigned', 'checked_in', 'checked_out', 'cleaning'])->default('assigned');
            
            // Waktu Check-in/Check-out Aktual (Real Time Front Office)
            $table->dateTime('check_in_aktual')->nullable();
            $table->dateTime('check_out_aktual')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Constraints
            // Jika detail pemesanan dihapus, record penempatan ikut hilang
            $table->foreign('detail_pemesanan_id')
                  ->references('id')->on('detail_pemesanans')
                  ->onDelete('cascade');
                  
            // Unit tidak bisa dihapus jika masih ada penempatan yang merujuk padanya
            $table->foreign('kamar_unit_id')
                  ->references('id')->on('kamar_units')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penempatan_kamars');
    }
};
