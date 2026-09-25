<?php

namespace App\Services;

use App\Models\TcEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeviceCommandEventService
{
    public const COMMAND_EVENT_TYPES = ['commandResult', 'queuedCommandSent'];

    private const SEND_TIME_TOLERANCE_SECONDS = 6 * 3600;

    private const SEND_MATCH_WINDOW_SECONDS = 30 * 24 * 3600;

    private const COMMAND_TYPE_LABELS = [
        'custom' => 'Custom command',
        'deviceIdentification' => 'Device Identification',
        'positionSingle' => 'Single Reporting',
        'positionPeriodic' => 'Periodic Reporting',
        'positionStop' => 'Stop Reporting',
        'engineStop' => 'Stop Engine',
        'engineResume' => 'Start Engine',
        'alarmArm' => 'Lock Doors',
        'alarmDisarm' => 'Unlock Doors',
        'immobilize' => 'Immobilize Vehicle',
        'unimmobilize' => 'Unimmobilize Vehicle',
    ];

    /**
     * @return array{items: Collection, total: int}
     */
    public function historyForDevice(int $deviceId, int $limit = 10): array
    {
        $query = TcEvent::query()
            ->where('deviceid', $deviceId)
            ->whereIn('type', self::COMMAND_EVENT_TYPES)
            ->orderByDesc('eventtime')
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $events = $query->limit($limit)->get();
        $sendLogs = $this->sendActivitiesForDevice($deviceId);
        $usedLogIds = [];

        $savedCommandIds = $events
            ->filter(fn (TcEvent $event) => $event->type === 'queuedCommandSent')
            ->map(function (TcEvent $event) {
                $attrs = $this->decodeAttributes($event->attributes);

                return isset($attrs['id']) ? (int) $attrs['id'] : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $savedCommands = $this->savedCommandsById($savedCommandIds);

        // Newest device responses first — each gets the latest unmatched send before it.
        $eventsChronological = $events
            ->sortByDesc(fn (TcEvent $event) => sprintf('%s-%010d', $this->traccarEventTimeIso($event->eventtime) ?? '', $event->id))
            ->values();

        $paired = [];

        foreach ($eventsChronological as $event) {
            $attrs = $this->decodeAttributes($event->attributes);
            $eventTimeUtc = $this->traccarEventTimeCarbon($event->eventtime);
            $activity = $this->nearestSendActivity($sendLogs, $eventTimeUtc, $usedLogIds);

            if ($activity && isset($activity->id)) {
                $usedLogIds[] = (int) $activity->id;
            }

            $paired[$event->id] = [
                'id' => $event->id,
                'created_at' => $this->traccarEventTimeIso($event->eventtime),
                'sent_at' => $this->activitySendTimeIso($activity),
                'command' => $this->commandLabel($event, $attrs, $savedCommands, $activity),
                'user' => $this->userLabel($activity),
                'status' => $this->statusForEvent($event, $attrs),
            ];
        }

        $items = $events
            ->map(fn (TcEvent $event) => $paired[$event->id])
            ->values()
            ->sortByDesc(function (array $item) {
                $value = $item['sent_at'] ?? $item['created_at'] ?? null;

                return $value ? Carbon::parse($value)->timestamp : 0;
            })
            ->values();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    private function activitySendTimeIso(?object $activity): ?string
    {
        if (! $activity || empty($activity->created_at)) {
            return null;
        }

        return Carbon::parse($activity->created_at, config('app.timezone'))->toIso8601String();
    }

    private function traccarEventTimeCarbon(?Carbon $eventTime): ?Carbon
    {
        if (! $eventTime) {
            return null;
        }

        return Carbon::parse($eventTime->format('Y-m-d H:i:s'), 'UTC');
    }

    /** Traccar stores eventtime as UTC wall-clock in the database. */
    private function traccarEventTimeIso(?Carbon $eventTime): ?string
    {
        $utc = $this->traccarEventTimeCarbon($eventTime);

        return $utc?->toIso8601String();
    }

    private function decodeAttributes(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, object>
     */
    private function savedCommandsById(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::connection('pgsql')
            ->table('tc_commands')
            ->whereIn('id', $ids)
            ->get(['id', 'description', 'type'])
            ->keyBy('id')
            ->all();
    }

    private function sendActivitiesForDevice(int $deviceId): Collection
    {
        return DB::table('system_activity_logs')
            ->where('request_path', 'like', '%commands/send%')
            ->where(function ($query) use ($deviceId) {
                $query->where('request_path', 'like', '%/vehicles/'.$deviceId.'/%')
                    ->orWhere('module', (string) $deviceId)
                    ->orWhere('new_data', 'like', '%"device_id":'.$deviceId.'%')
                    ->orWhere('new_data', 'like', '%"device_id": '.$deviceId.'%')
                    ->orWhere('new_data', 'like', '%"deviceId":'.$deviceId.'%')
                    ->orWhere('new_data', 'like', '%"deviceId": '.$deviceId.'%');
            })
            ->orderBy('created_at')
            ->get(['id', 'user_name', 'new_data', 'created_at']);
    }

    /**
     * @param  list<int>  $usedLogIds
     */
    private function nearestSendActivity(Collection $logs, ?Carbon $eventTimeUtc, array $usedLogIds = []): ?object
    {
        if (! $eventTimeUtc || $logs->isEmpty()) {
            return null;
        }

        $eventTs = $eventTimeUtc->timestamp;
        $tolerance = self::SEND_TIME_TOLERANCE_SECONDS;
        $window = self::SEND_MATCH_WINDOW_SECONDS;
        $used = array_flip($usedLogIds);

        $candidates = $logs
            ->filter(fn ($log) => ! isset($used[(int) $log->id]))
            ->map(function ($log) use ($eventTs) {
                $logTs = Carbon::parse($log->created_at, config('app.timezone'))->timestamp;

                return (object) [
                    'log' => $log,
                    'log_ts' => $logTs,
                    'diff' => abs($logTs - $eventTs),
                ];
            })
            ->filter(fn ($item) => $item->diff <= $window);

        if ($candidates->isEmpty()) {
            return null;
        }

        // Send happens before device response — use the latest send still before this response.
        $sentBeforeResponse = $candidates
            ->filter(fn ($item) => $item->log_ts <= $eventTs)
            ->sortByDesc('log_ts');

        if ($sentBeforeResponse->isNotEmpty()) {
            return $sentBeforeResponse->first()->log;
        }

        return null;
    }

    private function userLabel(?object $activity): string
    {
        $name = trim((string) ($activity->user_name ?? ''));

        return $name !== '' ? $name : '—';
    }

    /**
     * @param  array<int, object>  $savedCommands
     */
    private function commandLabel(TcEvent $event, array $attrs, array $savedCommands, ?object $activity): string
    {
        $fromActivity = $this->commandLabelFromActivity($activity);
        if ($fromActivity !== null) {
            return $fromActivity;
        }

        if ($event->type === 'queuedCommandSent') {
            $savedId = isset($attrs['id']) ? (int) $attrs['id'] : 0;
            if ($savedId && isset($savedCommands[$savedId])) {
                $saved = $savedCommands[$savedId];
                $description = trim((string) ($saved->description ?? ''));
                if ($description !== '') {
                    return $description;
                }

                return $this->formatCommandTypeLabel((string) ($saved->type ?? ''));
            }

            return '—';
        }

        return '—';
    }

    private function commandLabelFromActivity(?object $activity): ?string
    {
        if (! $activity || empty($activity->new_data)) {
            return null;
        }

        $payload = json_decode((string) $activity->new_data, true);
        if (! is_array($payload)) {
            return null;
        }

        if (! empty($payload['id'])) {
            $saved = DB::connection('pgsql')
                ->table('tc_commands')
                ->where('id', (int) $payload['id'])
                ->first(['description', 'type']);

            if ($saved) {
                $description = trim((string) ($saved->description ?? ''));
                if ($description !== '') {
                    return $description;
                }

                if (! empty($saved->type)) {
                    return $this->formatCommandTypeLabel((string) $saved->type);
                }
            }
        }

        if (! empty($payload['type'])) {
            return $this->formatCommandTypeLabel((string) $payload['type']);
        }

        return null;
    }

    private function formatCommandTypeLabel(string $type): string
    {
        if (isset(self::COMMAND_TYPE_LABELS[$type])) {
            return self::COMMAND_TYPE_LABELS[$type];
        }

        return trim(preg_replace('/([A-Z])/', ' $1', $type) ?? $type) ?: '—';
    }

    private function statusForEvent(TcEvent $event, array $attrs): string
    {
        if ($event->type === 'queuedCommandSent') {
            return 'pending';
        }

        $result = strtolower(trim((string) ($attrs['result'] ?? '')));
        if ($result === '') {
            return 'success';
        }

        if (
            str_contains($result, 'unknown')
            || str_contains($result, 'invalid')
            || str_contains($result, 'error')
            || str_contains($result, 'fail')
        ) {
            return 'failed';
        }

        return 'success';
    }
}
