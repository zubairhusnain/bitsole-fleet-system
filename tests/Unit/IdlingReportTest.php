<?php

namespace Tests\Unit;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class IdlingReportTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_idling_report_returns_duration_and_epochs_from_idle_positions()
    {
        $tcDevice = Mockery::mock('alias:App\Models\TcDevice');
        $tcDevice->shouldReceive('whereIn')
            ->once()
            ->with('id', [1])
            ->andReturnSelf();
        $tcDevice->shouldReceive('pluck')
            ->once()
            ->with('name', 'id')
            ->andReturn(collect([1 => 'Vehicle 1']));

        $positions = [
            (object) [
                'fixtime' => '2026-03-08 03:18:53',
                'latitude' => 2.3193583,
                'longitude' => 102.391365,
                'address' => null,
                'speed' => 0,
            ],
            (object) [
                'fixtime' => '2026-03-08 03:34:31',
                'latitude' => 2.3193583,
                'longitude' => 102.391365,
                'address' => null,
                'speed' => 0,
            ],
        ];

        $ignitionEvents = [
            (object) [
                'type' => 'ignitionOn',
                'eventtime' => '2026-03-08 03:00:00',
            ],
            (object) [
                'type' => 'ignitionOff',
                'eventtime' => '2026-03-08 04:00:00',
            ],
        ];

        DB::shouldReceive('connection')
            ->times(2)
            ->with('pgsql')
            ->andReturnSelf();

        $selectCall = 0;
        DB::shouldReceive('select')
            ->twice()
            ->andReturnUsing(function () use (&$selectCall, $ignitionEvents, $positions) {
                $selectCall++;
                return $selectCall === 1 ? $ignitionEvents : $positions;
            });

        $service = new ReportService();
        $request = new Request([
            'from_date' => '2026-03-08',
            'to_date' => '2026-03-08',
        ]);

        $rows = $service->fetchIdlingReportDb($request, [1]);

        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertEquals('Vehicle 1', $row['vehicle']);
        $this->assertEquals(1, $row['deviceId']);
        $this->assertArrayHasKey('startEpoch', $row);
        $this->assertArrayHasKey('endEpoch', $row);
        $this->assertArrayHasKey('tripStartEpoch', $row);
        $this->assertArrayHasKey('tripEndEpoch', $row);
        $this->assertArrayHasKey('tripDurationSeconds', $row);
        $this->assertArrayHasKey('tripDurationFormatted', $row);
        $this->assertEquals($row['endEpoch'] - $row['startEpoch'], $row['durationSeconds']);
        $this->assertEquals(15 * 60 + 38, $row['durationSeconds']);
        $this->assertEquals(3600, $row['tripDurationSeconds']);
    }
}
