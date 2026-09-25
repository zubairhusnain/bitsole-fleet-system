<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Report' }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; padding: 0; }
        h2 { text-align: center; margin: 0 0 14px; }
        .meta { margin: 0 0 12px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; }
        th, td { border: 1px solid #ddd; padding: 4px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background-color: #886654; color: #ffffff; font-weight: bold; }
        tr:nth-child(even) { background-color: #f8f9fb; }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Report' }}</h2>
    @include('reports.partials.pdf_meta', ['meta' => $meta ?? [], 'rows' => $rows ?? []])
    <table>
        <thead>
            <tr>
                @foreach(($headers ?? []) as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach(($rows ?? []) as $r)
                <tr>
                    @foreach(($r ?? []) as $c)
                        <td>{{ is_scalar($c) ? $c : json_encode($c) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
