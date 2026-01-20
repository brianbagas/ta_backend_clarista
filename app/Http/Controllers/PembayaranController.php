<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\Request;
use App\Models\Pemesanan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\PesananDikonfirmasi;
class PembayaranController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

     public function indexForOwner()
    {
        $pemesanans = Pemesanan::where('status_pemesanan', 'menunggu_konfirmasi')
            ->with(['user', 'pembayaran', 'detailPemesanans.kamar'])// Muat relasi user dan pembayaran
            ->latest()
            ->get();

        return response()->json($pemesanans);
    }

     public function verifikasi(Request $request, Pemesanan $pemesanan)
    {
        $validated = $request->validate([
            'status' => 'required|in:dikonfirmasi,batal',
        ]);

        $pemesanan->update(['status_pemesanan' => $validated['status']]);

        // Kirim notifikasi email ke customer
        if ($pemesanan->user && $pemesanan->user->email) {

            Mail::to($pemesanan->user->email)->send(new PesananDikonfirmasi($pemesanan));
        }
        return response()->json([
            'message' => 'Status pemesanan berhasil diubah menjadi ' . $validated['status'],
            'pemesanan' => $pemesanan,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Pemesanan $pemesanan)
    {
        // 1. Otorisasi: Pastikan pengguna adalah pemilik pesanan
        if (Auth::id() !== $pemesanan->user_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // 2. Validasi: Pastikan ada file gambar yang diunggah
        $validated = $request->validate([
            'bukti_bayar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);
        // 3. Simpan File Bukti Bayar
        $path = $request->file('bukti_bayar')->store('bukti_pembayaran','public');
        
        // 4. Buat record pembayaran baru
        Pembayaran::create([
            'pemesanan_id' => $pemesanan->id,
            'bukti_bayar_path' => $path,
        ]);

        // 5. Update status pesanan utama
        $pemesanan->update(['status_pemesanan' => 'menunggu_konfirmasi']);

        return response()->json([
            'message' => 'Bukti pembayaran berhasil diunggah. Menunggu konfirmasi dari admin.',
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Pembayaran $pembayaran)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pembayaran $pembayaran)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Pembayaran $pembayaran)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pembayaran $pembayaran)
    {
        //
    }
}
