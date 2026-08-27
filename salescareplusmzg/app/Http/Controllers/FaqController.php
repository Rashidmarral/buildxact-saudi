<?php

namespace App\Http\Controllers;

use App\Models\Faq;

class FaqController extends Controller
{
    public function index()
    {
        $faqsByCategory = Faq::orderBy('sort_order')->get()->groupBy('category');

        return view('pages.faq', compact('faqsByCategory'));
    }
}
