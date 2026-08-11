<?php

namespace App\Http\Controllers;

use App\Models\Principal;
use App\Models\Product;
use App\Models\Testimonial;

class HomeController extends Controller
{
    public function index()
    {
        $principals = Principal::orderBy('sort_order')->limit(8)->get();
        $featuredProducts = Product::with('category')->where('is_featured', true)->orderBy('sort_order')->limit(4)->get();
        $testimonials = Testimonial::orderBy('sort_order')->get();

        return view('pages.home', compact('principals', 'featuredProducts', 'testimonials'));
    }
}
