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
        $last = DB::connection('pgsql')
            ->table('tc_positions')
            ->where('deviceid', $deviceId)
            ->orderBy('fixtime', 'desc')
            ->limit(1)
            ->first();

        if (!$last) {
            return 0;
        }

        $attrs = $this->decodeAttributes($last->attributes ?? null);

        $lastIo9 = isset($attrs['io9']) ? (float) $attrs['io9'] : null;

        if ($lastIo9 === null || $lastIo9 > 0) {
            return 0;
        }

        $prev = DB::connection('pgsql')
            ->table('tc_positions')
            ->where('deviceid', $deviceId)
            ->where('fixtime', '<', $last->fixtime)
            ->whereRaw("(attributes::jsonb ->> 'io9') IS NOT NULL")
            ->whereRaw("((attributes::jsonb ->> 'io9')::numeric > 0)")
            ->whereRaw("COALESCE(attributes::jsonb ->> 'ignition', '') IN ('1','true','TRUE','on','ON')")
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

        $this->info("[StabilizeIo9] Device {$deviceId}: last io9=0, using previous ignition-on io9={$prevIo9} (pos id {$prev->id}) for last position id {$last->id}");

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

        DB::connection('pgsql')->update($sql, [$prevIo9, $last->id]);

        return 1;
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
