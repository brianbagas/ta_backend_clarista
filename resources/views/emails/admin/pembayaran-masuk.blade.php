<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Baru Masuk</title>
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
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
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
            padding: 12px 30px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>💳 Pembayaran Baru Masuk</h1>
        </div>

        <div class="content">
            <p>Halo Admin,</p>

            <p>Ada pembayaran baru dari tamu yang perlu diverifikasi. Mohon segera cek bukti pembayaran tersebut.</p>

            <div class="info-box">
                <strong>📝 Status: Menunggu Konfirmasi</strong><br>
                Tamu telah mengupload bukti pembayaran.
            </div>

            <h3>📋 Detail Pesanan:</h3>
            <table class="detail-table">
                <tr>
                    <td>Kode Booking</td>
                    <td>{{ $pemesanan->kode_booking }}</td>
                </tr>
                <tr>
                    <td>Nama Tamu</td>
                    <td>{{ $pemesanan->user->name }}</td>
                </tr>
                <tr>
                    <td>Total Tagihan</td>
                    <td><strong style="color: #2196F3;">Rp
                            {{ number_format($pemesanan->total_bayar, 0, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td>Tanggal Check-in</td>
                    <td>{{ \Carbon\Carbon::parse($pemesanan->tanggal_check_in)->translatedFormat('d F Y') }}</td>
                </tr>
            </table>

            <p style="text-align: center; margin-top: 30px;">
                Silakan login ke dashboard owner untuk memverifikasi bukti pembayaran ini.
            </p>

            <center>
                <a href="{{ env('FRONTEND_URL', 'https://claristahomestay.web.id') }}/admin/verifikasi-pembayaran/{{ $pemesanan->id }}"
                    class="button">
                    Verifikasi Pembayaran
                </a>
            </center>
        </div>

        <div class="footer">
            <p><strong>Clarista Homestay</strong></p>
            <p>Email ini dikirim otomatis oleh sistem.</p>
        </div>
    </div>
</body>

</html>