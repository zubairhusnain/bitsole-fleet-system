<?php

namespace App\Http\Controllers;

use App\Helpers\Curl;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Services\DeviceService;
use App\Services\LiveLocationService;
use App\Services\PositionService;
use App\Services\UserService;
use App\Services\DriverService;
use App\Services\GeofencesService;
use App\Services\PermissionService;
use App\Services\ReportService;
use App\Services\GroupService;
use App\Services\NotificationService;
class Controller extends BaseController
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    use Curl;

    protected DeviceService $deviceService;
    protected LiveLocationService $liveLocationService;
    protected PositionService $positionService;
    protected UserService $userService;
    protected DriverService $driverService;
    protected GeofencesService $geofencesService;
    protected PermissionService $permissionService;
    protected ReportService $reportService;
    protected GroupService $groupService;
    protected NotificationService $notificationService;

    public function __construct(
        DeviceService $deviceService,
        LiveLocationService $liveLocationService,
        PositionService $positionService,
        UserService $userService,
        DriverService $driverService,
        GeofencesService $geofencesService,
        PermissionService $permissionService,
        ReportService $reportService,
        GroupService $groupService,
        NotificationService $notificationService
    ) {
        $this->deviceService = $deviceService;
        $this->liveLocationService = $liveLocationService;
        $this->positionService = $positionService;
        $this->userService = $userService;
        $this->driverService = $driverService;
        $this->geofencesService = $geofencesService;
        $this->permissionService = $permissionService;
        $this->reportService = $reportService;
        $this->groupService = $groupService;
        $this->notificationService = $notificationService;
    }
}
