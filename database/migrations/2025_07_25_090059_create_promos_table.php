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
// dalam function up()
Schema::create('promos', function (Blueprint $table) {
    $table->id();
    $table->string('nama_promo');
    $table->string('kode_promo')->unique();
    $table->text('deskripsi')->nullable();
    $table->enum('tipe_diskon', ['persen', 'nominal'])->default('nominal');
    $table->decimal('nilai_diskon', 10, 2);
    $table->integer('kuota')->nullable();
   $table->integer('kuota_terpakai')->default(0);
     $table->decimal('min_transaksi', 10, 2)->default(0);
     $table->boolean('is_active')->default(true);
    $table->date('berlaku_mulai');
    $table->date('berlaku_selesai');
    $table->timestamps();
    $table->softDeletes();
    });
 }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
