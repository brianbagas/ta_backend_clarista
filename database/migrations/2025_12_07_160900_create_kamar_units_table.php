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
        Schema::create('kamar_units', function (Blueprint $table) {
            $table->id();
            
            // FK ke tabel kamars (menghubungkan unit fisik ke tipe kamar)
            // Menggunakan unsignedBigInteger karena id_kamar adalah bigint(20) UNSIGNED
            $table->unsignedBigInteger('kamar_id'); 
            
            // Nomor unit kamar, harus unik (tidak boleh ada dua kamar 101)
            $table->string('nomor_unit', 10)->unique(); 
            
            // Status unit fisik (bukan status booking)
            $table->enum('status_unit', ['available', 'occupied', 'maintenance'])->default('available');
            
            $table->timestamps();

            // Definisi Foreign Key Constraint
            $table->foreign('kamar_id')->references('id_kamar')->on('kamars')->onDelete('cascade');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kamar_units');
    }
};
