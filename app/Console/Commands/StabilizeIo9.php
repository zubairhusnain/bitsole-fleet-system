<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StabilizeIo9 extends Command
{
    protected $signature = 'traccar:stabilize-io9';

    protected $description = 'Stabilize io9 by copying last non-zero ignition-on value into latest zero-reading position';

    public function handle(): int
    {
        $deviceId = 46;

        $this->info("[StabilizeIo9] Starting io9 stabilization check for device {$deviceId}...");

        try {
            $updated = $this->stabilizeDeviceIo9($deviceId);
            $this->info("[StabilizeIo9] Completed. Updated {$updated} latest positions for device {$deviceId}.");
        } catch (\Throwable $e) {
            $this->error('[StabilizeIo9] Error: ' . $e->getMessage());
            try {
                Log::error('[StabilizeIo9] Exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            } catch (\Throwable $ignored) {
            }
        }

        return 0;
    }

    private function stabilizeDeviceIo9(int $deviceId): int
    {
        // 1. Get current device position (the one linked to tc_devices.positionid)
        $current = DB::connection('pgsql')
            ->table('tc_devices as d')
            ->join('tc_positions as p', 'p.id', '=', 'd.positionid')
            ->where('d.id', $deviceId)
            ->select('p.id', 'p.deviceid', 'p.attributes')
            ->first();

        if (!$current) {
            return 0;
        }

        $attrs = $this->decodeAttributes($current->attributes ?? null);
        $curIo9 = isset($attrs['io9']) ? (float) $attrs['io9'] : null;

        // Only stabilize when the current position has io9 exactly 0
        if ($curIo9 === null) {
            return 0;
        }

        if($curIo9==0){

            // 2. Find last position for this device where io9 > 0 (any past record)
            $prev = DB::connection('pgsql')
                ->table('tc_positions')
                ->where('deviceid', $current->deviceid)
                // ->where('id', '<>', $current->id)
                ->whereRaw("(attributes::jsonb ->> 'io9') IS NOT NULL")
                ->whereRaw("((attributes::jsonb ->> 'io9')::numeric > 0)")
                ->orderBy('fixtime', 'desc')
                ->limit(1)
                ->first();

            if (!$prev) {
                return 0;
            }

            $prevAttrs = $this->decodeAttributes($prev->attributes ?? null);
            $prevIo9 = isset($prevAttrs['io9']) ? (float) $prevAttrs['io9'] : null;

            if ($prevIo9 === null || $prevIo9 <= 0) {
                return 0;
            }

            $this->info("[StabilizeIo9] Device {$deviceId}: current position io9=0, using previous io9={$prevIo9} (pos id {$prev->id}) for current position id {$current->id}");

            $sql = "
                UPDATE tc_positions
                SET attributes = jsonb_set(
                    attributes::jsonb,
                    '{io9}',
                    to_jsonb(?::numeric),
                    true
                )
                WHERE id = ?
            ";

            DB::connection('pgsql')->update($sql, [$prevIo9, $current->id]);

            return 1;
        }
        return 0;
    }

    private function decodeAttributes($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            try {
                $decoded = json_decode($raw, true);
                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable $e) {
                return [];
            }
        }

        return [];
    }
}
