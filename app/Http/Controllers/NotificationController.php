<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return view('notifications.index', ['notifications' => auth()->user()->visibleNotifications()->latest()->paginate(20)]);
    }

    public function read(string $id)
    {
        $notification = auth()->user()->visibleNotifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request)
    {
        $request->user()->visibleUnreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
