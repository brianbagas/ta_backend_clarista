<?php

namespace App\Http\Controllers;

use App\Models\KamarUnit;
use Illuminate\Http\Request;
use App\Models\Pemesanan;
use App\Models\PenempatanKamar;
use Carbon\Carbon;
use App\Traits\ApiResponseTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class LaporanController extends Controller
{
    use ApiResponseTrait;

    public function getKalenderData(Request $request)
    {
        // 1. Validasi / Default Value
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        // Tentukan Range Tanggal
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // 2. Ambil Semua Data Kamar
        $rooms = KamarUnit::select('id', 'nomor_unit')->get();

        // 3. Query Booking
        $bookings = PenempatanKamar::with('user')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('check_in_aktual', '<=', $endDate)
                    ->where(function ($q) use ($startDate) {
                        $q->where('check_out_aktual', '>=', $startDate)
                            ->orWhereNull('check_out_aktual');
                    });
            })
            // Pastikan status yang diambil mencakup yang sedang menginap DAN reservasi masa depan
            ->whereIn('status_penempatan', ['assigned', 'booked', 'reserved'])
            ->get();

        // 4. Formatting Data
        $bookingsFormatted = $bookings->map(function ($item) {
            return [
                'id' => $item->id,
                'kamar_unit_id' => $item->kamar_unit_id,
                // Sekarang akses $item->user tidak akan melakukan query baru lagi
                'nama_tamu' => $item->user ? $item->user->name : 'Tamu Umum',

                'check_in_aktual' => Carbon::parse($item->check_in_aktual)->format('Y-m-d'),

                // Logic "Ongoing"
                'check_out_aktual' => $item->check_out_aktual
                    ? Carbon::parse($item->check_out_aktual)->format('Y-m-d')
                    : null,

                'status' => $item->status_penempatan,
            ];
        });

        return $this->successResponse([
            'meta' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'rooms' => $rooms,
            'bookings' => $bookingsFormatted
        ], 'Data kalender berhasil diambil');
    }

    public function index(Request $request)
    {
        // 1. Ambil input filter (Prioritas: Date Range, Fallback: Bulan ini)
        if ($request->has(['start_date', 'end_date'])) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $periodeLabel = $startDate->translatedFormat('d M Y') . ' - ' . $endDate->translatedFormat('d M Y');
        } else {
            $bulan = $request->input('bulan', date('m'));
            $tahun = $request->input('tahun', date('Y'));
            $startDate = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth();
            $periodeLabel = $startDate->translatedFormat('F Y');
        }

        // 2. Query Dasar
        // Filter berdasarkan TANGGAL BAYAR (Cashflow)
        // Include 'tidak_datang' karena uang sudah masuk (no refund policy)
        $query = Pemesanan::whereIn('status_pemesanan', ['dikonfirmasi', 'selesai', 'tidak_datang'])
            ->whereHas('pembayaran', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('tanggal_bayar', [$startDate, $endDate]);
            });

        // 3. Hitung Agregasi
        $totalPendapatan = $query->sum('total_bayar');
        $jumlahTransaksi = $query->count();

        // 4. Ambil Detail Transaksi
        $detailTransaksi = $query->with(['user', 'pembayaran'])->orderBy('tanggal_check_in', 'asc')->get();

        return $this->successResponse([
            'summary' => [
                'total_pendapatan' => (int) $totalPendapatan,
                'total_transaksi' => $jumlahTransaksi,
                'periode' => $periodeLabel
            ],
            'transaksi' => $detailTransaksi
        ], "Laporan periode $periodeLabel berhasil diambil");
    }
    public function dashboard(Request $request)
    {
        // 1. Hitung Kamar Terisi (Realtime)
        $activeRooms = PenempatanKamar::where('status_penempatan', 'assigned')->count();

        return $this->successResponse([
            'active_rooms' => $activeRooms
        ], 'Data dashboard berhasil diambil');
    }

    public function exportPdf(Request $request)
    {
        // // Increase memory and time limit for PDF generation
        // ini_set('memory_limit', '512M');
        // set_time_limit(300);

        try {
            // 1. Ambil input filter (sama seperti method index)
            if ($request->has(['start_date', 'end_date'])) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();
                $periodeLabel = $startDate->translatedFormat('d M Y') . ' - ' . $endDate->translatedFormat('d M Y');
            } else {
                $bulan = $request->input('bulan', date('m'));
                $tahun = $request->input('tahun', date('Y'));
                $startDate = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
                $endDate = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth();
                $periodeLabel = $startDate->translatedFormat('F Y');
            }

            // 2. Query Dasar (optimize dengan select & eager loading)
            // Include 'tidak_datang' karena uang sudah masuk (no refund policy)
            $query = Pemesanan::select('id', 'kode_booking', 'user_id', 'tanggal_check_in', 'tanggal_check_out', 'total_bayar', 'status_pemesanan')
                ->whereIn('status_pemesanan', ['dikonfirmasi', 'selesai', 'tidak_datang'])
                ->whereHas('pembayaran', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('tanggal_bayar', [$startDate, $endDate]);
                })
                ->with([
                    'user:id,name,email',
                    'pembayaran:id,pemesanan_id,tanggal_bayar,jumlah_bayar'
                ]);

            // 3. Hitung Agregasi
            $totalPendapatan = $query->sum('total_bayar');
            $jumlahTransaksi = $query->count();

            // 4. Ambil Detail Transaksi (order & get)
            $transaksi = $query->orderBy('tanggal_check_in', 'asc')->get();

            // 5. Generate PDF
            $pdf = Pdf::loadView('laporan.pdf', [
                'periode' => $periodeLabel,
                'totalPendapatan' => $totalPendapatan,
                'jumlahTransaksi' => $jumlahTransaksi,
                'transaksi' => $transaksi
            ]);

            // 6. Set paper size dan orientation
            $pdf->setPaper('a4', 'landscape');

            // Optimize PDF rendering
            $pdf->setOption([
                'dpi' => 96, // Lower DPI for faster generation
                'enable_html5_parser' => true,
                'enable_remote' => false, // Disable remote resource loading
            ]);

            // 7. Return PDF sebagai download
            $filename = 'laporan-pendapatan-' . $startDate->format('Y-m-d') . '.pdf';

            return $pdf->download($filename);

        } catch (\Throwable $e) {
            Log::error('PDF Export Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal export PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}