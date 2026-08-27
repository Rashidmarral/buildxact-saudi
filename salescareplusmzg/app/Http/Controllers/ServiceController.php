<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;

class ServiceController extends Controller
{
    public function index()
    {
        $services = ContentItem::group('service')->visible()->ordered()->get();
        $steps = ContentItem::group('service_process_step')->visible()->ordered()->get();

        return view('pages.services', compact('services', 'steps'));
    }
}
