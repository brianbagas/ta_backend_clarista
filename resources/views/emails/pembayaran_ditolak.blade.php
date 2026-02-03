<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Ditolak</title>
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
            background: linear-gradient(135deg, #d32f2f 0%, #c62828 100%);
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

        .alert-box {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 20px 0;
        }

        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
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
            padding: 15px 40px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
            font-size: 16px;
        }

        .button:hover {
            background-color: #45a049;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>❌ Pembayaran Ditolak</h1>
        </div>

        <div class="content">
            <p>Halo, <strong>{{ $pemesanan->user->name }}</strong>!</p>

            <p>Kami informasikan bahwa bukti pembayaran untuk pesanan <strong>{{ $pemesanan->kode_booking }}</strong>
                tidak dapat diverifikasi.</p>

            <div class="alert-box">
                <strong>⚠️ Catatan dari Admin:</strong><br>
                {{ $catatanAdmin }}
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
                    <td>Total yang Harus Dibayar</td>
                    <td><strong style="color: #f44336;">Rp
                            {{ number_format($pemesanan->total_bayar, 0, ',', '.') }}</strong></td>
                </tr>
            </table>

            <div class="info-box">
                <strong>📌 Langkah Selanjutnya:</strong><br><br>
                1. Pastikan Anda transfer ke rekening yang benar<br>
                2. Jumlah transfer harus <strong>SESUAI</strong> dengan total tagihan<br>
                3. Upload bukti transfer yang <strong>JELAS</strong> dan <strong>TERBACA</strong><br>
                4. Pastikan foto menampilkan:<br>
                &nbsp;&nbsp;&nbsp;• Nama pengirim<br>
                &nbsp;&nbsp;&nbsp;• Jumlah transfer<br>
                &nbsp;&nbsp;&nbsp;• Tanggal & waktu transfer<br>
                &nbsp;&nbsp;&nbsp;• Bank tujuan
            </div>

            @if($pemesanan->expired_at && \Carbon\Carbon::parse($pemesanan->expired_at)->isFuture())
                <p style="background-color: #fff3e0; padding: 15px; border-radius: 5px; border-left: 4px solid #ff9800;">
                    <strong>⏰ Batas Waktu Pembayaran:</strong><br>
                    {{ \Carbon\Carbon::parse($pemesanan->expired_at)->format('d F Y, H:i') }} WIB<br>
                    <em style="color: #666; font-size: 14px;">
                        ({{ \Carbon\Carbon::parse($pemesanan->expired_at)->diffForHumans() }})
                    </em>
                </p>
            @else
                <p style="background-color: #ffebee; padding: 15px; border-radius: 5px; border-left: 4px solid #f44336;">
                    <strong>⚠️ Perhatian:</strong><br>
                    Batas waktu pembayaran telah habis. Pesanan ini akan dibatalkan secara otomatis jika tidak ada
                    pembayaran yang valid.
                </p>
            @endif

            <center>
                <a href="{{ env('FRONTEND_URL', 'http://localhost:5173') }}/customer/riwayat-pemesanan" class="button">
                    📤 Upload Bukti Bayar Ulang
                </a>
            </center>

            <p style="margin-top: 30px; font-size: 14px; color: #666;">
                Jika Anda mengalami kesulitan atau memiliki pertanyaan, silakan hubungi kami melalui WhatsApp atau email
                yang tertera di bawah.
            </p>
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