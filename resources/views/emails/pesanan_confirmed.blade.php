<!DOCTYPE html>
<html>
<head>
    <title>Konfirmasi Pesanan</title>
</head>
<body>
    <h1>Halo, {{ $pemesanan->user->name }}!</h1>
    <p>Terima kasih telah memesan di Clarista Homestay.</p>
    <p>Pesanan Anda untuk tanggal <strong>{{ $pemesanan->tanggal_check_in }}</strong> telah <strong>DIKONFIRMASI</strong> oleh admin.</p>
    
    <h3>Detail Pesanan:</h3>
    <ul>
        <li>Total Bayar: Rp {{ number_format($pemesanan->total_bayar, 0, ',', '.') }}</li>
        <li>Status: {{ $pemesanan->status_pemesanan }}</li>
    </ul>

    <p>Silakan datang sesuai jadwal check-in. Terima kasih!</p>
</body>
</html>