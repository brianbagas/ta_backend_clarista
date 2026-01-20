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
        Schema::create('pemesanans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('tanggal_check_in');
            $table->date('tanggal_check_out');
            $table->decimal('total_bayar', 15, 2);
            $table->string('status_pemesanan')->default('menunggu_pembayaran'); // misal: menunggu_pembayaran, dikonfirmasi, selesai, batal
            $table->foreignId('promo_id')
                  ->nullable() // Boleh kosong karena tidak semua pesanan pakai promo
                  ->constrained('promos')
                  ->onDelete('set null'); // Jika promo dihapus, kolom ini menjadi null
            $table->dateTime('expired_at')->nullable(); // Waktu kadaluarsa pembayaran
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemesanans');
    }
};
