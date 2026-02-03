<?php

namespace App\Mail;

use App\Models\Pemesanan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PembayaranDitolak extends Mailable
{
    use Queueable, SerializesModels;

    public $pemesanan;
    public $catatanAdmin;

    /**
     * Create a new message instance.
     */
    public function __construct(Pemesanan $pemesanan, string $catatanAdmin = '')
    {
        $this->pemesanan = $pemesanan;
        $this->catatanAdmin = $catatanAdmin ?: 'Bukti pembayaran tidak valid. Silakan upload ulang dengan bukti yang jelas.';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pembayaran Ditolak - ' . $this->pemesanan->kode_booking,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.pembayaran_ditolak',
        );
    }
}
