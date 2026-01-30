<?php

namespace App\Http\Controllers;

use App\Models\HomestayContent;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class HomestayContentController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        // Ambil record pertama, atau buat baru jika tidak ada
        $content = HomestayContent::firstOrCreate(['id' => 1]);
        return $this->successResponse($content, 'Konten homestay berhasil diambil.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HomestayContent $homestayContent)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $content = HomestayContent::firstOrFail();

        $validatedData = $request->validate([
            'alamat' => 'nullable|string|max:255',
            'telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'link_gmaps' => 'nullable|url',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:255',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validasi gambar
        ]);

        // Handle upload gambar
        if ($request->hasFile('hero_image')) {
            $path = $request->file('hero_image')->store('hero_images', 'public');
            $validatedData['hero_image_path'] = $path;
        }

        $content->update($validatedData);

        return $this->successResponse($content, 'Konten homestay berhasil diperbarui.');
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HomestayContent $homestayContent)
    {
        //
    }
}
