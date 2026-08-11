<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;

class CareersController extends Controller
{
    public function index()
    {
        $jobs = ContentItem::group('job_opening')->visible()->ordered()->get();

        return view('pages.careers', compact('jobs'));
    }
}
