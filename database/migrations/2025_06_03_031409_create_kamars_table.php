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
        // dalam function up()
        Schema::create('kamars', function (Blueprint $table) {
            $table->id('id_kamar');
            $table->string('tipe_kamar', 50);
            $table->text('deskripsi')->nullable();
            $table->decimal('harga', 10, 2);
            $table->boolean('status_ketersediaan')->default(true);
            $table->integer('jumlah_total')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('kamar_images', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel 'kamars'
            // Perhatikan: di DB Anda primary key kamars adalah 'id_kamar', bukan 'id'
            $table->unsignedBigInteger('kamar_id');
            $table->string('image_path');
            $table->timestamps();
            $table->softDeletes();

            // Foreign Key Constraint
            $table->foreign('kamar_id')
                ->references('id_kamar') // Merujuk ke kolom id_kamar
                ->on('kamars')
                ->onDelete('cascade'); // Jika kamar dihapus, foto ikut terhapus
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kamars');
        Schema::dropIfExists('kamar_images');

    }
};
