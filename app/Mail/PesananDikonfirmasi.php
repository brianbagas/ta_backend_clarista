<?php

namespace App\Mail;
use App\Models\Pemesanan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class PesananDikonfirmasi extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $pemesanan; // Variable untuk menampung data

    // Terima data pemesanan saat class ini dipanggil
    public function __construct(Pemesanan $pemesanan)
    {
        $this->pemesanan = $pemesanan;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pesanan Anda Telah Dikonfirmasi - Clarista Homestay',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pesanan_confirmed', // Kita akan buat view ini nanti
        );
    }
}
