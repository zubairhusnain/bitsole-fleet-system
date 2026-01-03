<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ReportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;
use App\Models\User;

class ReportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('constants.Constants.host', 'http://traccar.test');
    }

    public function test_fetch_daily_summary_returns_correct_data()
    {
        Http::fake([
            'http://traccar.test/api/reports/trips*' => Http::response([
                [
                    'deviceId' => 1,
                    'deviceName' => 'Vehicle 1',
                    'startTime' => '2023-10-25T08:00:00Z',
                    'endTime' => '2023-10-25T09:00:00Z',
                    'distance' => 10000, // 10km
                    'duration' => 3600000, // 1 hour
                    'maxSpeed' => 50,
                    'averageSpeed' => 40,
                ]
            ], 200),
            'http://traccar.test/api/reports/stops*' => Http::response([
                [
                    'deviceId' => 1,
                    'startTime' => '2023-10-25T09:00:00Z',
                    'duration' => 1800000, // 30 mins
                ]
            ], 200),
        ]);

        $service = new ReportService();
        $request = new Request([
            'from_date' => '2023-10-25',
            'to_date' => '2023-10-25',
            'group_by' => 'vehicle_date'
        ]);

        // Mock user with traccar session
        $user = new User();
        $user->traccarSession = 'JSESSIONID=123';
        $request->setUserResolver(fn () => $user);

        $result = $service->fetchDailySummary($request, [1]);

        $this->assertIsArray($result->toArray());
        $this->assertCount(1, $result);

        $first = $result->first();
        $this->assertEquals('10 KM', $first['distance']);
        $this->assertEquals('1h 0m 0s', $first['trip']);
        $this->assertEquals('0h 30m 0s', $first['idle']);
        $this->assertEquals('33.3%', $first['idlePct']); // 30m / (60m + 30m) = 33.3%
    }

    public function test_fetch_daily_trips_returns_correct_data()
    {
        Http::fake([
            'http://traccar.test/api/reports/trips*' => Http::response([
                [
                    'deviceId' => 1,
                    'deviceName' => 'Vehicle 1',
                    'startTime' => '2023-10-25T08:00:00Z',
                    'endTime' => '2023-10-25T09:00:00Z',
                    'startAddress' => 'Start Loc',
                    'endAddress' => 'End Loc',
                    'distance' => 10000, // 10km
                    'duration' => 3600000, // 1 hour
                    'maxSpeed' => 54, // ~100 km/h
                ]
            ], 200),
            'http://traccar.test/api/reports/stops*' => Http::response([
                [
                    'deviceId' => 1,
                    'duration' => 1800000, // 30 mins
                ]
            ], 200),
        ]);

        $service = new ReportService();
        $request = new Request([
            'from_date' => '2023-10-25',
            'to_date' => '2023-10-25'
        ]);

        $user = new User();
        $user->traccarSession = 'JSESSIONID=123';
        $request->setUserResolver(fn () => $user);

        $result = $service->fetchDailyTrips($request, [1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('summary', $result);

        $rows = $result['rows'];
        $this->assertCount(1, $rows);
        $this->assertEquals('10 KM', $rows[0]['distance']);

        $summary = $result['summary'];
        $this->assertEquals(10000, $summary['totalDistance']);
        $this->assertEquals(3600000, $summary['totalDuration']);
        $this->assertEquals(1800000, $summary['totalIdle']);
        $this->assertEqualsWithDelta(100.008, $summary['maxSpeed'], 0.001); // 54 * 1.852
    }

    public function test_fetch_monthly_summary_returns_correct_data()
    {
        Http::fake([
            'http://traccar.test/api/reports/trips*' => Http::response([
                [
                    'deviceId' => 1,
                    'deviceName' => 'Vehicle 1',
                    'startTime' => '2023-10-01T08:00:00Z',
                    'endTime' => '2023-10-01T10:00:00Z',
                    'distance' => 50000, // 50km
                    'duration' => 7200000, // 2 hours
                ]
            ], 200),
            'http://traccar.test/api/reports/stops*' => Http::response([
                [
                    'deviceId' => 1,
                    'startTime' => '2023-10-01T10:00:00Z',
                    'duration' => 3600000, // 1 hour
                ]
            ], 200),
        ]);

        $service = new ReportService();
        $request = new Request([
            'from_date' => '2023-10-01',
            'to_date' => '2023-10-31',
            'group_by' => 'vehicle_month'
        ]);

        $user = new User();
        $user->traccarSession = 'JSESSIONID=123';
        $request->setUserResolver(fn () => $user);

        $result = $service->fetchMonthlySummary($request, [1]);

        $this->assertIsArray($result->toArray());
        $this->assertCount(1, $result);

        $first = $result->first();
        $this->assertEquals('50 KM', $first['distance']);
        $this->assertEquals('0d 02h 00m', $first['trip']);
        $this->assertEquals('1h 0m', $first['idle']);
        $this->assertEquals('33.3%', $first['idlePct']); // 1h / (2h + 1h) = 33.3%
    }
}
