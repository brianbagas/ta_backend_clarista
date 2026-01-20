<?php

namespace App\Http\Controllers;

use App\Models\KamarUnit;
use Illuminate\Http\Request;
use App\Models\Pemesanan;
use App\Models\PenempatanKamar;
use Carbon\Carbon;

class LaporanController extends Controller
{
   
public function getKalenderData(Request $request)
{
    // 1. Validasi / Default Value (Penting agar tidak error jika param kosong)
    // Jika tidak dikirim, pakai tahun & bulan saat ini
    $year = $request->input('year', now()->year);
    $month = $request->input('month', now()->month);

    // Tentukan Range Tanggal
    $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
    $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

    // 2. Ambil Semua Data Kamar
    // Opsional: Select kolom seperlunya saja untuk hemat memori
    $rooms = KamarUnit::select('id', 'nomor_unit')->get(); 

    // 3. Query Booking
    $bookings = PenempatanKamar::with('user') // <--- SOLUSI N+1 QUERY
        ->where(function($query) use ($startDate, $endDate) {
            $query->where('check_in_aktual', '<=', $endDate)
                  ->where(function($q) use ($startDate) {
                      $q->where('check_out_aktual', '>=', $startDate)
                        ->orWhereNull('check_out_aktual'); 
                  });
        })
        // Pastikan status yang diambil mencakup yang sedang menginap DAN reservasi masa depan
        // Sesuaikan array ini dengan status di database Anda
        ->whereIn('status_penempatan', ['assigned', 'booked', 'reserved']) 
        ->get();

    // 4. Formatting Data
    $bookingsFormatted = $bookings->map(function($item) {
        return [
            'id' => $item->id,
            'kamar_unit_id' => $item->kamar_unit_id,
            // Sekarang akses $item->user tidak akan melakukan query baru lagi
            'nama_tamu' => $item->user ? $item->user->name : 'Tamu Umum', 
            
            'check_in_aktual' => Carbon::parse($item->check_in_aktual)->format('Y-m-d'),
            
            // Logic "Ongoing"
            'check_out_aktual' => $item->check_out_aktual 
                                    ? Carbon::parse($item->check_out_aktual)->format('Y-m-d') 
                                    : null, // Lebih baik kirim null, biarkan JS Frontend yang handle visualnya (misal: "s.d. Hari Ini")
             
             // Tambahan info status biar frontend bisa bedakan warna (Misal: Merah untuk Assigned, Kuning untuk Booked)
            'status' => $item->status_penempatan, 
        ];
    });

    return response()->json([
        'meta' => [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ],
        'rooms' => $rooms,
        'bookings' => $bookingsFormatted
    ]);
}

    public function index(Request $request)
    {
        // 1. Ambil input filter (Default: Bulan ini)
        $bulan = $request->input('bulan', date('m'));
        $tahun = $request->input('tahun', date('Y'));

        // 2. Query Dasar: Hanya yang statusnya 'dikonfirmasi' atau 'selesai'
        $query = Pemesanan::whereIn('status_pemesanan', ['dikonfirmasi', 'selesai'])
                    ->whereMonth('tanggal_check_in', $bulan)
                    ->whereYear('tanggal_check_in', $tahun);

        // 3. Hitung Agregasi (Biarkan Database yang kerja)
        $totalPendapatan = $query->sum('total_bayar');
        $jumlahTransaksi = $query->count();

        // 4. Ambil Detail Transaksinya juga (untuk Tabel)
        // Eager load 'user' agar nama pemesan muncul
        $detailTransaksi = $query->with('user')->orderBy('tanggal_check_in', 'asc')->get();

        // 5. Return JSON Standar
        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => "Laporan periode $bulan-$tahun berhasil diambil"
            ],
            'data' => [
                'summary' => [
                    'total_pendapatan' => (int) $totalPendapatan,
                    'total_transaksi' => $jumlahTransaksi,
                    'periode' => Carbon::createFromDate($tahun, $bulan)->translatedFormat('F Y')
                ],
            'transaksi' => $detailTransaksi
            ]
        ]);
    }
}