<?php

namespace App\Http\Controllers;

use App\Models\Pemesanan;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class ReviewController extends Controller
{
    use ApiResponseTrait;

    private function formatReview($review)
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'komentar' => $review->komentar,
            'tanggal' => $review->created_at->format('d F Y'),
            'user' => [
                'name' => $review->pemesanan->user->name ?? 'Pelanggan',
            ]
        ];
    }

    public function getFeaturedReviews()
    {
        $reviews = Review::where('status', 'setujui')
            ->with('pemesanan.user')
            ->latest()
            ->take(9)
            ->get();

        return $this->successResponse($reviews->map(fn($r) => $this->formatReview($r)), 'Featured reviews retrieved successfully.');
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reviews = Review::where('status', 'setujui')
            ->with('pemesanan.user')
            ->latest()
            ->get();


        $formattedReviews = $reviews->map(function ($review) {
            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'komentar' => $review->komentar,
                'tanggal' => $review->created_at->format('d F Y'),
                'user' => [
                    'name' => $review->pemesanan->user->name ?? 'Pelanggan',
                ]
            ];
        });
        return $this->successResponse($formattedReviews, 'Daftar review berhasil ditampilkan.');
    }
    // app/Http/Controllers/ReviewController.php

    // ... (method index, store, dll.)

    /**
     * Mengambil 9 review terbaru yang disetujui untuk homepage.
     */

    public function indexForOwner()
    {
        $reviews = Review::with('pemesanan.user')->latest()->get();
        return $this->successResponse($reviews, 'Daftar review untuk owner berhasil ditampilkan.');
    }

    public function updateStatus(Request $request, Review $review)
    {
        $validated = $request->validate([
            'status' => 'required|in:setujui,sembunyikan',
        ]);

        $review->update(['status' => $validated['status']]);

        return $this->successResponse($review, 'Status review berhasil diperbarui.');
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

        // 1. Otorisasi
        if (Auth::id() !== $pemesanan->user_id) {
            return $this->errorResponse('Anda tidak bisa mereview pesanan ini.', 403);
        }

        // 2. Aturan Bisnis
        if ($pemesanan->status_pemesanan !== 'selesai') {
            return $this->errorResponse('Anda hanya bisa mereview pesanan yang sudah selesai.', 422);
        }

        // 3. Aturan Bisnis
        if ($pemesanan->review()->exists()) {
            return $this->errorResponse('Pesanan ini sudah pernah Anda review.', 422);
        }

        // --- Simpan Review ---
        $review = Review::create([
            'pemesanan_id' => $pemesanan->id,
            'rating' => $validated['rating'],
            'komentar' => $validated['komentar'],
        ]);

        return $this->successResponse($review, 'Terima kasih atas ulasan Anda!', 201);
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
        $validated = $request->validate([
            'status' => 'required|in:setujui,sembunyikan',
        ]);

        $review->update(['status' => $validated['status']]);

        return $this->successResponse($review, 'Status review berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Review $review)
    {
        //
    }


}
