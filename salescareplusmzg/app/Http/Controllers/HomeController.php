<?php

namespace App\Http\Controllers;

use App\Models\Certification;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Testimonial;

class HomeController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::orderBy('sort_order')->get();
        $featuredProducts = Product::with('category')->where('is_featured', true)->orderBy('sort_order')->limit(8)->get();
        $testimonials = Testimonial::orderBy('sort_order')->get();
        $certifications = Certification::orderBy('sort_order')->limit(4)->get();

        return view('pages.home', compact('categories', 'featuredProducts', 'testimonials', 'certifications'));
    }
}
