<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;

class AboutController extends Controller
{
    public function index()
    {
        $team = TeamMember::orderBy('sort_order')->get();

        return view('pages.about', compact('team'));
    }
}
