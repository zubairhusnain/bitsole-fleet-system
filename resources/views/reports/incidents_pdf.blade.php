<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Incident Analysis Report</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; padding: 0; }
        h2 { text-align: center; margin-bottom: 20px; }
        .header-info { margin-bottom: 20px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        th, td { border: 1px solid #ddd; padding: 4px; text-align: left; vertical-align: middle; word-wrap: break-word; }
        th { background-color: #0b0f28; color: #ffffff; font-weight: bold; }
        tr:nth-child(even) { background-color: #f8f9fb; }
    </style>
</head>
<body>
    <h2>Incident Analysis Report</h2>
    <div class="header-info">
        <strong>Date:</strong> {{ isset($date) ? date('d/m/Y', strtotime($date)) : date('d/m/Y') }}<br>
        <strong>Total Rows:</strong> {{ is_array($rows) ? count($rows) : 0 }}
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Vehicle ID</th>
                <th style="width: 12%;">Type/Model</th>
                <th style="width: 10%;">Incident Start</th>
                <th style="width: 10%;">Incident End</th>
                <th style="width: 12%;">Impact Date/Time</th>
                <th style="width: 12%;">Driver</th>
                <th style="width: 22%;">Description</th>
                <th style="width: 10%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($rows ?? []) as $row)
            <tr>
                <td>{{ $row['vehicleId'] ?? '' }}</td>
                <td>{{ $row['typeModel'] ?? '' }}</td>
                <td>{{ $row['incidentStart'] ?? '' }}</td>
                <td>{{ $row['incidentEnd'] ?? '' }}</td>
                <td>{{ $row['impactTime'] ?? '' }}</td>
                <td>{{ $row['driver'] ?? '' }}</td>
                <td>{{ $row['description'] ?? '' }}</td>
                <td>{{ $row['remarks'] ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
