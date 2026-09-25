<?php

namespace App\Observers;

use App\Models\TcPosition;
use App\Services\FuelRefillDiffService;
use App\Services\GeofenceAlertService;

class TcPositionObserver
{
    public function __construct(
        private readonly FuelRefillDiffService $fuelRefillDiffService,
        private readonly GeofenceAlertService $geofenceAlertService,
    ) {}

    public function created(TcPosition $position): void
    {
        try {
            $this->fuelRefillDiffService->processPosition($position);
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $this->geofenceAlertService->processPosition($position);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
