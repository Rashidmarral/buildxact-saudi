<?php

namespace App\Http\Controllers;

use App\Models\Certification;
use App\Models\ContentItem;

class QualityController extends Controller
{
    public function index()
    {
        $certifications = Certification::orderBy('sort_order')->get();
        $standards = ContentItem::group('quality_standard')->visible()->ordered()->get();

        return view('pages.quality', compact('certifications', 'standards'));
    }
}
