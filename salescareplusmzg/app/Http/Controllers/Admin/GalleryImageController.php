<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use Illuminate\Http\Request;

class GalleryImageController extends Controller
{
    use HandlesUploads;

    public function index()
    {
        $galleryImages = GalleryImage::orderBy('sort_order')->get();

        return view('admin.gallery-images.index', compact('galleryImages'));
    }

    public function create()
    {
        return view('admin.gallery-images.form', ['galleryImage' => new GalleryImage]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['image_path'] = $this->storeUpload($request, 'image', 'gallery');

        GalleryImage::create($validated);

        return redirect()->route('admin.gallery-images.index')->with('status', 'Gallery image added.');
    }

    public function edit(GalleryImage $galleryImage)
    {
        return view('admin.gallery-images.form', compact('galleryImage'));
    }

    public function update(Request $request, GalleryImage $galleryImage)
    {
        $validated = $this->validated($request);
        $validated['image_path'] = $this->storeUpload($request, 'image', 'gallery', $galleryImage->image_path);

        $galleryImage->update($validated);

        return redirect()->route('admin.gallery-images.index')->with('status', 'Gallery image updated.');
    }

    public function destroy(GalleryImage $galleryImage)
    {
        $galleryImage->delete();

        return redirect()->route('admin.gallery-images.index')->with('status', 'Gallery image removed.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'caption' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['is_visible'] = $request->boolean('is_visible');

        return $validated;
    }
}
