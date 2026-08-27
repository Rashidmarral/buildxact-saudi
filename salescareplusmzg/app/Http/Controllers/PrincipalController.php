<?php

namespace App\Http\Controllers;

use App\Models\Principal;

class PrincipalController extends Controller
{
    public function index()
    {
        $principals = Principal::orderBy('sort_order')->get();

        return view('pages.principals', compact('principals'));
    }
}
