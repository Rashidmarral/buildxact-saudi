<?php

namespace App\Http\Controllers;

use App\Models\Certification;
use App\Models\Principal;
use App\Models\TeamMember;

class AboutController extends Controller
{
    public function index()
    {
        $team = TeamMember::orderBy('sort_order')->get();
        $certifications = Certification::orderBy('sort_order')->limit(4)->get();
        $principalsCount = Principal::count();

        return view('pages.about', compact('team', 'certifications', 'principalsCount'));
    }
}
