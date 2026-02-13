<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Dikonfirmasi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .content {
            padding: 30px;
        }

        .success-box {
            background-color: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin: 20px 0;
        }

        .detail-table {
            width: 100%;
            margin: 20px 0;
        }

        .detail-table td {
            padding: 10px 0;
            border-bottom: 1px solid #eeeeee;
        }

        .detail-table td:first-child {
            font-weight: bold;
            color: #666;
            width: 40%;
        }

        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>✅ Pesanan Dikonfirmasi</h1>
        </div>

        <div class="content">
            <p>Halo, <strong>{{ $pemesanan->user->name }}</strong>!</p>

            <p>Terima kasih telah memesan di <strong>Clarista Homestay</strong>. Kami dengan senang hati
                menginformasikan bahwa pesanan Anda dengan kode booking <strong>{{ $pemesanan->kode_booking }}</strong>
                telah <strong>DIKONFIRMASI</strong> oleh admin.</p>

            <div class="success-box">
                <strong>🎉 Pembayaran Berhasil Diverifikasi!</strong><br>
                Pesanan Anda telah dikonfirmasi dan kamar telah disiapkan untuk Anda.
            </div>

            <h3>📋 Detail Pesanan:</h3>
            <table class="detail-table">
                <tr>
                    <td>Kode Booking</td>
                    <td>{{ $pemesanan->kode_booking }}</td>
                </tr>
                <tr>
                    <td>Tipe Kamar</td>
                    <td>
                        @foreach($pemesanan->detailPemesanans as $detail)
                            {{ $detail->kamar->tipe_kamar }} ({{ $detail->jumlah_kamar }} kamar)
                            @if(!$loop->last), @endif
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td>Tanggal Check-in</td>
                    <td>{{ \Carbon\Carbon::parse($pemesanan->tanggal_check_in)->format('d F Y') }}</td>
                </tr>
                <tr>
                    <td>Tanggal Check-out</td>
                    <td>{{ \Carbon\Carbon::parse($pemesanan->tanggal_check_out)->format('d F Y') }}</td>
                </tr>
                <tr>
                    <td>Total Bayar</td>
                    <td><strong style="color: #4CAF50;">Rp
                            {{ number_format($pemesanan->total_bayar, 0, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td><span
                            style="background-color: #4CAF50; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">{{ ucfirst(str_replace('_', ' ', $pemesanan->status_pemesanan)) }}</span>
                    </td>
                </tr>
            </table>

            <div style="background-color: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0;">
                <strong>📌 Informasi Check-in:</strong><br><br>
                • Waktu check-in: 14:00 WIB<br>
                • Waktu check-out: 12:00 WIB<br>
                • Harap membawa identitas (KTP/SIM/Paspor)<br>
                • Tunjukkan kode booking ini saat check-in
            </div>

            <p style="margin-top: 30px;">
                Kami tunggu kedatangan Anda di Clarista Homestay. Jika ada pertanyaan, jangan ragu untuk menghubungi
                kami.
            </p>

            <center>
                <a href="{{ env('FRONTEND_URL', 'https://test.claristahomestay.web.id') }}/customer/riwayat-pemesanan"
                    class="button">
                    Lihat Detail Pesanan
                </a>
            </center>
        </div>

        <div class="footer">
            <p><strong>Clarista Homestay</strong></p>
            <p>Jl. Contoh No. 123, Kota, Provinsi</p>
            <p>Email: info@claristahomestay.com | Telp: (021) 1234-5678</p>
            <p>WhatsApp: +62 812-3456-7890</p>
            <p style="margin-top: 10px; color: #999;">
                Email ini dikirim secara otomatis, mohon tidak membalas email ini.
            </p>
        </div>
    </div>
</body>

</html>