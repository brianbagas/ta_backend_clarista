<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Laporan Pendapatan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }

        .header p {
            margin: 5px 0;
            color: #666;
        }

        .summary {
            margin: 20px 0;
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
        }

        .summary-item {
            display: inline-block;
            width: 48%;
            margin-bottom: 10px;
        }

        .summary-item strong {
            display: block;
            color: #666;
            font-size: 11px;
            margin-bottom: 5px;
        }

        .summary-item span {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background-color: #4CAF50;
            color: white;
            padding: 10px 5px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
        }

        td {
            padding: 8px 5px;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }

        .status-dikonfirmasi {
            background-color: #4CAF50;
            color: white;
        }

        .status-selesai {
            background-color: #2196F3;
            color: white;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>CLARISTA HOMESTAY</h1>
        <p>Laporan Pendapatan</p>
        <p><strong>Periode: {{ $periode }}</strong></p>
    </div>

    <div class="summary">
        <div class="summary-item">
            <strong>Total Pendapatan</strong>
            <span>Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</span>
        </div>
        <div class="summary-item">
            <strong>Jumlah Transaksi</strong>
            <span>{{ $jumlahTransaksi }} Pesanan</span>
        </div>
    </div>

    @if($transaksi->count() > 0)
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Kode</th>
                    <th style="width: 15%;">Nama Tamu</th>
                    <th style="width: 10%;">Tgl Bayar</th>
                    <th style="width: 10%;">Check In</th>
                    <th style="width: 10%;">Check Out</th>
                    <th style="width: 8%;">Durasi</th>
                    <th style="width: 12%;">Metode</th>
                    <th style="width: 15%;" class="text-right">Total</th>
                    <th style="width: 10%;" class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaksi as $item)
                    <tr>
                        <td>{{ $item->kode_booking }}</td>
                        <td>{{ $item->user ? $item->user->name : 'N/A' }}</td>
                        <td>{{ $item->pembayaran ? \Carbon\Carbon::parse($item->pembayaran->tanggal_bayar)->format('d/m/Y') : '-' }}
                        </td>
                        <td>{{ \Carbon\Carbon::parse($item->tanggal_check_in)->format('d/m/Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($item->tanggal_check_out)->format('d/m/Y') }}</td>
                        <td class="text-center">
                            {{ \Carbon\Carbon::parse($item->tanggal_check_in)->diffInDays(\Carbon\Carbon::parse($item->tanggal_check_out)) }}
                            malam
                        </td>
                        <td>{{ $item->pembayaran && $item->pembayaran->bank_tujuan ? $item->pembayaran->bank_tujuan : 'Transfer Bank' }}
                        </td>
                        <td class="text-right">Rp {{ number_format($item->total_bayar, 0, ',', '.') }}</td>
                        <td class="text-center">
                            <span class="status-badge status-{{ strtolower($item->status_pemesanan) }}">
                                {{ ucfirst($item->status_pemesanan) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-data">
            <p>Tidak ada transaksi pada periode ini.</p>
        </div>
    @endif

    <div class="footer">
        <p>Dokumen ini digenerate secara otomatis pada {{ \Carbon\Carbon::now()->format('d F Y, H:i') }} WIB</p>
        <p>&copy; {{ date('Y') }} Clarista Homestay. All rights reserved.</p>
    </div>
</body>

</html>