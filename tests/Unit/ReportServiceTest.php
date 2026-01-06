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

    public function test_fetch_utilisation_report_returns_correct_structure()
    {
        Http::fake([
            'http://traccar.test/api/reports/trips*' => Http::response([
                [
                    'deviceId' => 1,
                    'deviceName' => 'Vehicle 1',
                    'startTime' => '2023-10-25T08:10:00Z',
                    'endTime' => '2023-10-25T08:50:00Z',
                    'distance' => 10000,
                    'duration' => 2400000, // 40 mins
                ]
            ], 200),
            'http://traccar.test/api/reports/stops*' => Http::response([
                [
                    'deviceId' => 1,
                    'startTime' => '2023-10-25T09:00:00Z',
                    'endTime' => '2023-10-25T09:30:00Z',
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

        $result = $service->fetchUtilisationReport($request, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('rows', $result);

        $summary = $result['summary'];
        $this->assertEquals('Vehicle 1', $summary['vehicleIdDisplay']);
        $this->assertEquals(1, $summary['deviceId']);

        $rows = $result['rows'];
        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertEquals('10 KM', $row['dist']);
        $this->assertEquals('0 hours 40 minutes', $row['move']);

        // 40m move + 30m idle = 70m total. 40/70 = 57.14% => 57%
        $this->assertEquals('57%', $row['usage']);

        $hours = $row['hours'];
        $this->assertTrue($hours[8], 'Hour 8 should be active');
        $this->assertFalse($hours[7], 'Hour 7 should be inactive');
        $this->assertFalse($hours[9], 'Hour 9 should be inactive');
    }

    public function test_fetch_fleet_summary_validates_unrealistic_speed()
    {
        Http::fake([
            'http://traccar.test/api/reports/summary*' => Http::response([
                [
                    'deviceId' => 1,
                    'deviceName' => 'Vehicle Normal',
                    'distance' => 10000,
                    'averageSpeed' => 54, // ~100 km/h
                    'spentFuel' => ['value' => 10],
                    'engineHours' => 3600000,
                ],
                [
                    'deviceId' => 2,
                    'deviceName' => 'Vehicle Error',
                    'distance' => 20000,
                    'averageSpeed' => 115938, // Unrealistic speed (~214,717 km/h)
                    'spentFuel' => ['value' => 20],
                    'engineHours' => 7200000,
                ]
            ], 200),
            'http://traccar.test/api/reports/stops*' => Http::response([], 200),
            'http://traccar.test/api/reports/events*' => Http::response([], 200),
        ]);

        $service = new ReportService();
        $request = new Request([
            'from_date' => '2023-10-25',
            'to_date' => '2023-10-25'
        ]);

        $user = new User();
        $user->traccarSession = 'JSESSIONID=123';
        $request->setUserResolver(fn () => $user);

        $result = $service->fetchFleetSummary($request, [1, 2]);

        $this->assertCount(2, $result);

        // Check Normal Vehicle
        $normal = $result->firstWhere('vehicleId', 1);
        $this->assertEquals('100 km/h', $normal['speed']);

        // Check Error Vehicle (Should be 0 km/h due to validation)
        $error = $result->firstWhere('vehicleId', 2);
        $this->assertEquals('0 km/h', $error['speed']);
    }

    public function test_fetch_daily_trips_validates_max_speed()
    {
        Http::fake([
            'http://traccar.test/api/reports/trips*' => Http::response([
                [
                    'deviceId' => 1,
                    'distance' => 1000,
                    'duration' => 60000,
                    'startTime' => '2023-10-25T08:00:00Z',
                    'endTime' => '2023-10-25T08:01:00Z',
                    'maxSpeed' => 115938, // Unrealistic
                ]
            ], 200),
            'http://traccar.test/api/reports/stops*' => Http::response([], 200),
            'http://traccar.test/api/reports/events*' => Http::response([], 200),
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

        $summary = $result['summary'];
        $this->assertEquals(0, $summary['maxSpeed']); // Should be clamped to 0
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

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rows', $result);
        $rows = $result['rows'];
        $this->assertCount(1, $rows);

        $first = $rows[0];
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
                    'startTime' => '2023-10-25T08:30:00Z',
                    'endTime' => '2023-10-25T09:00:00Z',
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
                    'endTime' => '2023-10-01T09:00:00Z',
                    'distance' => 10000, // 10km
                    'duration' => 3600000, // 1 hour
                ]
            ], 200),
            'http://traccar.test/api/reports/stops*' => Http::response([], 200),
        ]);

        $service = new ReportService();
        $request = new Request([
            'from_date' => '2023-10-01',
            'to_date' => '2023-10-31',
            'group_by' => 'month'
        ]);

        $user = new User();
        $user->traccarSession = 'JSESSIONID=123';
        $request->setUserResolver(fn () => $user);

        $result = $service->fetchMonthlySummary($request, [1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rows', $result);
        $rows = $result['rows'];
        $this->assertCount(1, $rows);
        $first = $rows[0];
        $this->assertEquals('10/2023', $first['date']);
        $this->assertEquals('10 KM', $first['distance']);
    }

    public function test_fetch_daily_breakdown_map_returns_correct_data()
    {
        Http::fake([
            'http://traccar.test/api/reports/trips*' => Http::response([
                [
                    'deviceId' => 1,
                    'deviceName' => 'Vehicle 1',
                    'startTime' => '2023-10-25T08:00:00Z',
                    'endTime' => '2023-10-25T09:00:00Z',
                    'startAddress' => 'Start',
                    'endAddress' => 'End',
                    'startLat' => 10,
                    'startLon' => 10,
                    'endLat' => 11,
                    'endLon' => 11,
                    'distance' => 10000,
                    'duration' => 3600000,
                ]
            ], 200),
            'http://traccar.test/api/reports/events*' => Http::response([], 200),
            'http://traccar.test/api/reports/stops*' => Http::response([], 200),
            'http://traccar.test/api/reports/route*' => Http::response([
                [
                    'deviceId' => 1,
                    'latitude' => 10.0,
                    'longitude' => 10.0,
                    'fixTime' => '2023-10-25T08:00:00Z'
                ],
                [
                    'deviceId' => 1,
                    'latitude' => 10.5,
                    'longitude' => 10.5,
                    'fixTime' => '2023-10-25T08:30:00Z'
                ],
                [
                    'deviceId' => 1,
                    'latitude' => 11.0,
                    'longitude' => 11.0,
                    'fixTime' => '2023-10-25T09:00:00Z'
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

        $result = $service->fetchDailyBreakdownMap($request, [1]);

        $this->assertCount(1, $result);
        $day = $result[0];

        $this->assertArrayHasKey('route', $day);
        $this->assertCount(3, $day['route']);
        // Check first point: [lat, lon, time_ms]
        $this->assertEquals(10.0, $day['route'][0][0]);
        $this->assertEquals(10.0, $day['route'][0][1]);

        $this->assertArrayHasKey('timeline', $day);
        // Start + End = 2 items
        $this->assertCount(2, $day['timeline']);
    }

    public function test_fetch_daily_breakdown_map_filters_routes_outside_trips()
    {
        Http::fake([
            'http://traccar.test/api/reports/trips*' => Http::response([
                [
                    'deviceId' => 1,
                    'deviceName' => 'Vehicle 1',
                    'startTime' => '2023-10-25T08:00:00Z',
                    'endTime' => '2023-10-25T09:00:00Z',
                    'distance' => 10000,
                    'duration' => 3600000,
                ]
            ], 200),
            'http://traccar.test/api/reports/events*' => Http::response([], 200),
            'http://traccar.test/api/reports/stops*' => Http::response([], 200),
            'http://traccar.test/api/reports/route*' => Http::response([
                [
                    'deviceId' => 1,
                    'latitude' => 10.0,
                    'longitude' => 10.0,
                    'fixTime' => '2023-10-25T08:00:00Z' // Inside
                ],
                [
                    'deviceId' => 1,
                    'latitude' => 10.5,
                    'longitude' => 10.5,
                    'fixTime' => '2023-10-25T08:30:00Z' // Inside
                ],
                [
                    'deviceId' => 1,
                    'latitude' => 11.0,
                    'longitude' => 11.0,
                    'fixTime' => '2023-10-25T09:00:00Z' // Inside
                ],
                [
                    'deviceId' => 1,
                    'latitude' => 12.0,
                    'longitude' => 12.0,
                    'fixTime' => '2023-10-25T09:30:00Z' // Outside
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

        $result = $service->fetchDailyBreakdownMap($request, [1]);

        $this->assertCount(1, $result);
        $day = $result[0];

        $this->assertArrayHasKey('route', $day);
        // Should only have 3 points (08:00, 08:30, 09:00)
        $this->assertCount(3, $day['route']);

        // Verify the last point is 09:00
        $lastPoint = end($day['route']);
        $this->assertEquals(strtotime('2023-10-25T09:00:00Z') * 1000, $lastPoint[2]);
    }

    public function test_fetch_monthly_summary_grouped_by_vehicle_returns_correct_data()
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

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rows', $result);
        $rows = $result['rows'];
        $this->assertCount(1, $rows);

        $first = $rows[0];
        $this->assertEquals('50 KM', $first['distance']);
        $this->assertEquals('0d 02h 00m', $first['trip']);
        $this->assertEquals('1h 0m', $first['idle']);
        $this->assertEquals('33.3%', $first['idlePct']); // 1h / (2h + 1h) = 33.3%
    }
}
