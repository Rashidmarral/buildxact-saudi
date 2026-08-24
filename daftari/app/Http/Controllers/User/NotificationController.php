<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()->notifications()->paginate(30);

        return view('user.notifications.index', compact('notifications'));
    }

    public function read(Request $request, string $notification)
    {
        $record = Auth::user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        $url = $record->data['url'] ?? null;

        return $url ? redirect($url) : back();
    }

    public function readAll()
    {
        Auth::user()->unreadNotifications->markAsRead();

        return back();
    }
}
