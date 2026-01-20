<?php

namespace App\Http\Controllers;

use App\Models\Pemesanan;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
class ReviewController extends Controller
{
    private function formatReview($review) {
    return [
        'id' => $review->id,
        'rating' => $review->rating,
        'komentar' => $review->komentar,
        'tanggal' => $review->created_at->format('d F Y'),
        'user' => [
            'name' => $review->user->name ?? 'Pelanggan',
        ]
    ];
}

public function getFeaturedReviews() {
    $reviews = Review::where('status', 'disetujui')
        ->with('user')
        ->latest()
        ->take(9)
        ->get();

    return response()->json($reviews->map(fn($r) => $this->formatReview($r)));
}
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
         $reviews = Review::where('status', 'disetujui')
                         ->with('user') // Muat data user untuk menampilkan nama
                         ->latest()
                         ->get();

        
        $formattedReviews = $reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'komentar' => $review->komentar,
                    'tanggal' => $review->created_at->format('d F Y'),
                    'user' => [
                        'name' => $review->user->name,
                        // Anda bisa menambahkan data lain di sini jika perlu
                    ]
                ];
            });
        return response()->json($formattedReviews);
    }
    // app/Http/Controllers/ReviewController.php

// ... (method index, store, dll.)

/**
 * Mengambil 9 review terbaru yang disetujui untuk homepage.
 */
  
    public function indexForOwner()
    {
        $reviews = Review::with('user')->latest()->get();
        return response()->json($reviews);
    }

     public function updateStatus(Request $request, Review $review)
    {
        $validated = $request->validate([
            'status' => 'required|in:disetujui,disembunyikan',
        ]);

        $review->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Status review berhasil diperbarui.',
            'data' => $review,
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
    public function store(Request $request)
    {
        
        $validated = $request->validate([
            'pemesanan_id' => 'required|exists:pemesanans,id',
            'rating' => 'required|integer|min:1|max:5',
            'komentar' => 'nullable|string',
        ]);

        $pemesanan = Pemesanan::find($validated['pemesanan_id']);

        // --- Pengecekan Keamanan & Aturan Bisnis ---

        // 1. Otorisasi: Pastikan user adalah pemilik pesanan
        if (Auth::id() !== $pemesanan->user_id) {
            return response()->json(['message' => 'Anda tidak bisa mereview pesanan ini.'], 403);
        }

        // 2. Aturan Bisnis: Pastikan status pesanan sudah "selesai"
        if ($pemesanan->status_pemesanan !== 'selesai') {
            return response()->json(['message' => 'Anda hanya bisa mereview pesanan yang sudah selesai.'], 422);
        }

        // 3. Aturan Bisnis: Pastikan pesanan belum pernah direview
        if ($pemesanan->review()->exists()) {
            return response()->json(['message' => 'Pesanan ini sudah pernah Anda review.'], 422);
        }

        // --- Simpan Review ---
        $review = Review::create([
            'pemesanan_id' => $pemesanan->id,
            'user_id' => Auth::id(),
            'rating' => $validated['rating'],
            'komentar' => $validated['komentar'],
        ]);

        return response()->json([
            'message' => 'Terima kasih atas ulasan Anda!',
            'data' => $review,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Review $review)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Review $review)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Review $review)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Review $review)
    {
        //
    }

    
}
