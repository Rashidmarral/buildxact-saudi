<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;

class GalleryController extends Controller
{
    public function index()
    {
        $images = GalleryImage::where('is_visible', true)->orderBy('sort_order')->get();

        return view('pages.gallery', compact('images'));
    }
}
