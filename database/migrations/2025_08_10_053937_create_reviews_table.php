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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('pemesanan_id')->unique()->constrained('pemesanans'); // 1 pesanan hanya bisa punya 1 review
            $table->unsignedTinyInteger('rating'); // Rating 1-5
            $table->text('komentar')->nullable();
            $table->string('status')->default('menunggu_persetujuan');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
