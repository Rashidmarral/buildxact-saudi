<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\IncomeCategory;
use Illuminate\Http\Request;

class IncomeCategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
        ]);

        IncomeCategory::create($data);

        return back()->with('status', __('Category added.'));
    }

    public function destroy(IncomeCategory $incomeCategory)
    {
        $incomeCategory->delete();

        return back()->with('status', __('Category deleted.'));
    }
}
