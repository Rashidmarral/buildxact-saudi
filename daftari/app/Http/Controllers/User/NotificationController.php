<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $notifications = Auth::user()->notifications()->paginate($this->resolvePerPage($request, 30))->withQueryString();

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
