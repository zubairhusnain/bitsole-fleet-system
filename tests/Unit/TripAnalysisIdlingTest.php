<?php

namespace Tests\Unit;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class TripAnalysisIdlingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_daily_trips_summary_uses_idle_seconds_from_positions()
    {
        DB::shouldReceive('connection')
            ->once()
            ->with('pgsql')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->once()
            ->andReturn([
                (object) ['deviceid' => 1, 'idle_seconds' => 600],
            ]);

        $service = new class extends ReportService {
            public array $tripsByDevice = [];

            public function fetchTripsDb($deviceId, $from, $to)
            {
                return $this->tripsByDevice[$deviceId] ?? [];
            }
        };

        $service->tripsByDevice = [
            1 => [
                [
                    'startTime' => '2026-03-10 08:00:00',
                    'endTime' => '2026-03-10 09:00:00',
                    'distance' => 10000,
                    'duration' => 3600000,
                ],
            ],
        ];

        $request = new Request([
            'from_date' => '2026-03-10',
            'to_date' => '2026-03-10',
        ]);

        $result = $service->fetchDailyTripsDb($request, [1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertEquals(600000, $result['summary']['totalIdle']);
    }

    public function test_daily_summary_idle_is_grouped_by_day_from_positions()
    {
        $tcDevice = Mockery::mock('alias:App\Models\TcDevice');
        $tcDevice->shouldReceive('whereIn')->once()->with('id', [1])->andReturnSelf();
        $tcDevice->shouldReceive('get')->once()->andReturn(collect([(object) ['id' => 1, 'name' => 'Vehicle 1']]));

        DB::shouldReceive('connection')
            ->once()
            ->with('pgsql')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->once()
            ->andReturn([
                (object) ['deviceid' => 1, 'day' => '2026-03-10', 'idle_seconds' => 1800],
            ]);

        $service = new class extends ReportService {
            public array $tripsByDevice = [];

            public function fetchTripsDb($deviceId, $from, $to)
            {
                return $this->tripsByDevice[$deviceId] ?? [];
            }
        };

        $service->tripsByDevice = [
            1 => [
                [
                    'startTime' => '2026-03-10 08:00:00',
                    'endTime' => '2026-03-10 09:00:00',
                    'distance' => 10000,
                    'duration' => 3600000,
                ],
            ],
        ];

        $request = new Request([
            'from_date' => '2026-03-10',
            'to_date' => '2026-03-10',
            'group_by' => 'date',
        ]);

        $result = $service->fetchDailySummary($request, [1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertCount(1, $result['rows']);
        $this->assertEquals('0h 30m 0s', $result['rows'][0]['idle']);
        $this->assertEquals(1800 * 1000, $result['summary']['totalIdle']);
    }

    public function test_monthly_summary_idle_is_grouped_by_month_from_positions()
    {
        $tcDevice = Mockery::mock('alias:App\Models\TcDevice');
        $tcDevice->shouldReceive('whereIn')->once()->with('id', [1])->andReturnSelf();
        $tcDevice->shouldReceive('get')->once()->andReturn(collect([(object) ['id' => 1, 'name' => 'Vehicle 1']]));

        DB::shouldReceive('connection')
            ->once()
            ->with('pgsql')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->once()
            ->andReturn([
                (object) ['deviceid' => 1, 'month' => '2026-03', 'idle_seconds' => 3600],
            ]);

        $service = new class extends ReportService {
            public array $tripsByDevice = [];

            public function fetchTripsDb($deviceId, $from, $to)
            {
                return $this->tripsByDevice[$deviceId] ?? [];
            }
        };

        $service->tripsByDevice = [
            1 => [
                [
                    'startTime' => '2026-03-10 08:00:00',
                    'endTime' => '2026-03-10 09:00:00',
                    'distance' => 10000,
                    'duration' => 3600000,
                ],
            ],
        ];

        $request = new Request([
            'from_date' => '2026-03-01',
            'to_date' => '2026-03-31',
            'group_by' => 'date',
        ]);

        $result = $service->fetchMonthlySummary($request, [1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertCount(1, $result['rows']);
        $this->assertEquals('1h 0m 0s', $result['rows'][0]['idle']);
        $this->assertEquals(3600 * 1000, $result['summary']['totalIdle']);
    }
}

