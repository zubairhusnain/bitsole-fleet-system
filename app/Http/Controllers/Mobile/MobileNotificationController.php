<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;

class MobileNotificationController extends Controller
{
    public function events(Request $request)
    {
        return app(NotificationController::class)->events($request);
    }

    public function unreadCount(Request $request)
    {
        return app(NotificationController::class)->unreadCount($request);
    }

    public function markAllRead(Request $request)
    {
        return app(NotificationController::class)->markAllRead($request);
    }

    public function destroy(Request $request, $id)
    {
        return app(NotificationController::class)->destroy($request, $id);
    }
}
