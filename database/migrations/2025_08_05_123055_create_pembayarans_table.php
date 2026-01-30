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
        Schema::create('pembayarans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pemesanan_id')->constrained('pemesanans')->onDelete('cascade');
            $table->string('bukti_bayar_path'); // Path file bukti bayar
            $table->decimal('jumlah_bayar', 15, 2); // Nominal yang dibayar customer
            $table->string('bank_tujuan', 255)->nullable(); // Bank asal transfer
            $table->string('nama_pengirim', 100)->nullable(); // Nama pengirim di bukti transfer
            $table->datetime('tanggal_bayar')->nullable(); // Tanggal transfer
            $table->string('status_verifikasi')->default('menunggu_verifikasi');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayarans');
    }
};
