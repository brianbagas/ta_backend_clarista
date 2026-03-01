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
        $bookings = PenempatanKamar::with('detailPemesanan.pemesanan.user')
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
                'nama_tamu' => $item->detailPemesanan?->pemesanan?->user?->name ?? 'Tamu Umum',

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
        $query = Pemesanan::whereIn('status_pemesanan', ['dikonfirmasi', 'selesai', 'tidak_datang'])
            ->whereHas('pembayaran', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('tanggal_konfirmasi', [$startDate, $endDate]);
            });

        $totalPendapatan = $query->sum('total_bayar');
        $jumlahTransaksi = $query->count();

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
        // Default to current month/year if not provided
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        // Date Ranges
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // 1. TOTAL PENDAPATAN (Income) - Berdasarkan Tanggal Konfirmasi Pembayaran
        $paidBookingsQuery = Pemesanan::whereIn('status_pemesanan', ['dikonfirmasi', 'selesai'])
            ->whereHas('pembayaran', function ($q) use ($startDate, $endDate) {
                // Asumsikan status_konfirmasi terverifikasi / tanggal_konfirmasi valid
                $q->whereBetween('tanggal_konfirmasi', [$startDate, $endDate])
                    ->whereNotNull('tanggal_konfirmasi');
            });

        $incomeMonth = (int) $paidBookingsQuery->sum('total_bayar');

        // 2. SEMUA STATUS COUNT - Based on Created Date (konsisten untuk chart "Pemesanan Bulan Ini")
        // Semua status count harus pakai filter yang sama agar: total = lunas + pending + baru + batal
        $monthlyBookingsQuery = Pemesanan::whereBetween('created_at', [$startDate, $endDate]);

        $totalBookings = $monthlyBookingsQuery->count();

        // Clone query untuk setiap status
        $lunasCount = (clone $monthlyBookingsQuery)->whereIn('status_pemesanan', ['dikonfirmasi', 'selesai', 'tidak_datang'])->count();
        $cancelledCount = (clone $monthlyBookingsQuery)->where('status_pemesanan', 'batal')->count();
        $newCount = (clone $monthlyBookingsQuery)->where('status_pemesanan', 'menunggu_pembayaran')->count();
        $pendingVerifMonth = (clone $monthlyBookingsQuery)->where('status_pemesanan', 'menunggu_konfirmasi')->count();



        // 3. REALTIME ALERTS (Global, not just this month)
        // For "Perlu Verifikasi" card, we usually want to know ALL pending items, not just this month's.
        $pendingVerifAll = Pemesanan::where('status_pemesanan', 'menunggu_konfirmasi')->count();

        // 4. ACTIVE ROOMS (Realtime)
        $activeRooms = PenempatanKamar::where('status_penempatan', 'assigned')->count();

        return $this->successResponse([
            'income_month' => $incomeMonth,
            'lunas_count' => $lunasCount,
            'total_bookings' => $totalBookings,
            'cancelled_count' => $cancelledCount,
            'new_count' => $newCount,
            'pending_verif_month' => $pendingVerifMonth,
            'pending_count' => $pendingVerifAll, // Global pending for alert
            'active_rooms' => $activeRooms,
            'period_label' => $startDate->translatedFormat('F Y')
        ], 'Data dashboard berhasil diambil');
    }

    public function exportPdf(Request $request)
    {
        try {
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
            $query = Pemesanan::select('id', 'kode_booking', 'user_id', 'tanggal_check_in', 'tanggal_check_out', 'total_bayar', 'status_pemesanan', 'created_at')
                ->whereIn('status_pemesanan', ['dikonfirmasi', 'selesai'])
                ->whereHas('pembayaran', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('tanggal_konfirmasi', [$startDate, $endDate])
                        ->whereNotNull('tanggal_konfirmasi');
                })
                ->with([
                    'user:id,name,email',
                    'pembayaran:id,pemesanan_id,tanggal_konfirmasi,jumlah_bayar'
                ]);

            $totalPendapatan = $query->sum('total_bayar');
            $jumlahTransaksi = $query->count();

            $transaksi = $query->orderBy('tanggal_check_in', 'asc')->get();
            $pdf = Pdf::loadView('laporan.pdf', [
                'periode' => $periodeLabel,
                'totalPendapatan' => $totalPendapatan,
                'jumlahTransaksi' => $jumlahTransaksi,
                'transaksi' => $transaksi
            ]);

            $pdf->setPaper('a4', 'landscape');
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