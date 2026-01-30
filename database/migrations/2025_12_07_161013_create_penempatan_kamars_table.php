<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('penempatan_kamars', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // FK ke Detail Pemesanan (transaksi)
            // TIDAK UNIK, karena satu detail pemesanan bisa menghasilkan >1 penempatan jika jumlah_kamar > 1
            $table->foreignUlid('detail_pemesanan_id')->constrained('detail_pemesanans')->onDelete('cascade');

            // FK ke Unit Fisik Kamar (misal: Kamar 101)
            $table->unsignedBigInteger('kamar_unit_id');

            // Status Siklus Hidup Penempatan
            $table->string('status_penempatan')->default('assigned');
            $table->string('catatan')->nullable();
            $table->enum('dibatalkan_oleh', ['customer', 'owner', 'system'])->nullable();
            $table->dateTime('dibatalkan_at')->nullable();
            // Waktu Check-in/Check-out Aktual (Real Time Front Office)
            $table->dateTime('check_in_aktual')->nullable();
            $table->dateTime('check_out_aktual')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Constraints
            // Jika detail pemesanan dihapus, record penempatan ikut hilang
            // $table->foreign('detail_pemesanan_id')
            //       ->references('id')->on('detail_pemesanans')
            //       ->onDelete('cascade');

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
