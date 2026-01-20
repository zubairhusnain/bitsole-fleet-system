<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Vehicle Status Report</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; padding: 0; }
        h2 { text-align: center; margin-bottom: 20px; }
        .header-info { margin-bottom: 20px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        th, td { border: 1px solid #ddd; padding: 4px; text-align: left; vertical-align: middle; word-wrap: break-word; }
        th { background-color: #0b0f28; color: #ffffff; font-weight: bold; }
        tr:nth-child(even) { background-color: #f8f9fb; }
        .badge { padding: 2px 4px; border-radius: 4px; font-weight: bold; font-size: 9px; }
        .bg-success { color: #198754; background-color: #d1e7dd; }
        .bg-danger { color: #dc3545; background-color: #f8d7da; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h2>Vehicle Status Report</h2>
    <div class="header-info">
        <strong>Date:</strong> {{ date('d/m/Y h:i A') }}<br>
        <strong>Total Vehicles:</strong> {{ count($rows) }}
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 6%;">Vehicle ID</th>
                <th style="width: 6%;">Owner</th>
                <th style="width: 6%;">Type/Model</th>
                <th style="width: 6%;">Device Model</th>
                <th style="width: 8%;">IMEI</th>
                <th style="width: 6%;">ICCID</th>
                <th style="width: 6%;">Odometer</th>
                <th style="width: 4%;">Power</th>
                <th style="width: 6%;">Last Report</th>
                <th style="width: 5%;">Lat</th>
                <th style="width: 5%;">Lon</th>
                <th style="width: 8%;">Location</th>
                <th style="width: 5%;">Speed</th>
                <th style="width: 4%;">GPS</th>
                <th style="width: 4%;">Ignition</th>
                <th style="width: 6%;">Last On</th>
                <th style="width: 6%;">Last Off</th>
                <th style="width: 6%;">Activation</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                <td>{{ $row['vehicle_id'] }}</td>
                <td>{{ $row['owner'] }}</td>
                <td>{{ $row['type_model'] }}</td>
                <td>{{ $row['device_model'] }}</td>
                <td>{{ $row['imei'] }}</td>
                <td>{{ $row['odometer'] }}</td>
                <td class="text-center">
                    <span class="badge {{ $row['power'] === 'On' ? 'bg-success' : 'bg-danger' }}">
                        {{ $row['power'] }}
                    </span>
                </td>
                <td>{{ $row['last_report'] }}</td>
                <td>{{ $row['latitude'] }}</td>
                <td>{{ $row['longitude'] }}</td>
                <td>{{ $row['location'] }}</td>
                <td>{{ $row['speed'] }}</td>
                <td>{{ $row['gps_signal'] }}</td>
                <td class="text-center">
                    <span class="badge {{ $row['ignition'] === 'ON' ? 'bg-success' : 'bg-danger' }}">
                        {{ $row['ignition'] }}
                    </span>
                </td>
                <td>{{ $row['last_ignition_on'] }}</td>
                <td>{{ $row['last_ignition_off'] }}</td>
                <td>{{ $row['activation_date'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
