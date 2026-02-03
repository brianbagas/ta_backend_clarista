<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Dibatalkan</title>
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
            background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
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

        .info-box {
            background-color: #fff3e0;
            border-left: 4px solid #ff9800;
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
            <h1>🚫 Pesanan Dibatalkan</h1>
        </div>

        <div class="content">
            <p>Halo, <strong>{{ $pemesanan->user->name }}</strong>!</p>

            <p>Kami informasikan bahwa pesanan Anda dengan kode booking <strong>{{ $pemesanan->kode_booking }}</strong>
                telah dibatalkan.</p>

            <div class="info-box">
                <strong>ℹ️ Informasi Pembatalan</strong><br>
                @if($pemesanan->dibatalkan_oleh === 'owner')
                    Pesanan ini dibatalkan oleh pihak homestay.
                @else
                    Pesanan ini dibatalkan atas permintaan Anda.
                @endif

                @if($pemesanan->alasan_batal)
                    <br><br>
                    <strong>Alasan:</strong> {{ $pemesanan->alasan_batal }}
                @endif
            </div>

            <h3>📋 Detail Pesanan yang Dibatalkan:</h3>
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
                    <td><strong>Rp {{ number_format($pemesanan->total_bayar, 0, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td>Dibatalkan pada</td>
                    <td>{{ \Carbon\Carbon::parse($pemesanan->dibatalkan_at)->format('d F Y, H:i') }} WIB</td>
                </tr>
            </table>

            @if($pemesanan->promo_id)
                <p style="color: #666; font-size: 14px;">
                    <em>* Kuota promo yang digunakan telah dikembalikan.</em>
                </p>
            @endif

            <p style="margin-top: 30px;">
                Anda dapat melakukan pemesanan baru kapan saja melalui website kami.
            </p>

            <center>
                <a href="{{ env('FRONTEND_URL', 'http://localhost:5173') }}/booking" class="button">
                    Pesan Lagi
                </a>
            </center>
        </div>

        <div class="footer">
            <p><strong>Clarista Homestay</strong></p>
            <p>Jl. Contoh No. 123, Kota, Provinsi</p>
            <p>Email: info@claristahomestay.com | Telp: (021) 1234-5678</p>
            <p style="margin-top: 10px; color: #999;">
                Email ini dikirim secara otomatis, mohon tidak membalas email ini.
            </p>
        </div>
    </div>
</body>

</html>