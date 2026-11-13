<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale)
    {
        if (Locales::isValid($locale)) {
            $request->session()->put('locale', $locale);
        }

        return back();
    }
}
