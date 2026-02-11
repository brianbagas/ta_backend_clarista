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
        // 1. Users Table
        Schema::table('users', function (Blueprint $table) {
            $table->string('name', 100)->change();
        });

        // 2. Pemesanans Table
        Schema::table('pemesanans', function (Blueprint $table) {
            // Preserving default value 'menunggu_pembayaran'
            $table->string('status_pemesanan', 30)->default('menunggu_pembayaran')->change();
        });

        // 3. Homestay Contents Table
        Schema::table('homestay_contents', function (Blueprint $table) {
            // Preserving nullable()
            $table->string('telepon', 20)->nullable()->change();
            $table->string('email', 100)->nullable()->change();
        });

        // 4. Promos Table
        Schema::table('promos', function (Blueprint $table) {
            $table->string('nama_promo', 100)->change();
            $table->string('kode_promo', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original VARCHAR(255)

        Schema::table('users', function (Blueprint $table) {
            $table->string('name', 255)->change();
        });

        Schema::table('pemesanans', function (Blueprint $table) {
            $table->string('status_pemesanan', 255)->default('menunggu_pembayaran')->change();
        });

        Schema::table('homestay_contents', function (Blueprint $table) {
            $table->string('telepon', 255)->nullable()->change();
            $table->string('email', 255)->nullable()->change();
        });

        Schema::table('promos', function (Blueprint $table) {
            $table->string('nama_promo', 255)->change();
            $table->string('kode_promo', 255)->change();
        });
    }
};
