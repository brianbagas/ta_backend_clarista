<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->renameColumn('tanggal_bayar', 'tanggal_konfirmasi');
            $table->renameColumn('status_verifikasi', 'status_konfirmasi');
        });
    }

    public function down(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->renameColumn('tanggal_konfirmasi', 'tanggal_bayar');
            $table->renameColumn('status_konfirmasi', 'status_verifikasi');
        });
    }
};
