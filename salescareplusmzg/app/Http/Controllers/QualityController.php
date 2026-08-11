<?php

namespace App\Http\Controllers;

use App\Models\Certification;

class QualityController extends Controller
{
    public function index()
    {
        $certifications = Certification::orderBy('sort_order')->get();

        return view('pages.quality', compact('certifications'));
    }
}
