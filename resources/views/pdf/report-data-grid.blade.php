<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $report_title ?? 'Report' }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }

        body {
            font-family: Tahoma, Verdana, Arial, sans-serif;
            font-size: 6pt;
            margin: 0;
            padding: 0;
        }

        h1 {
            font-size: 12pt;
            margin: 0 0 4px 0;
        }

        .subtitle {
            font-size: 8pt;
            color: #444;
            margin-bottom: 10px;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.grid th,
        table.grid td {
            border: 0.5px solid #333;
            padding: 2px 3px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table.grid thead th {
            background-color: #d9d9d9;
            color: #000000;
            font-weight: bold;
            font-size: 5.5pt;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        table.grid td {
            font-size: 5.5pt;
        }
    </style>
</head>
<body>
    <h1>{{ $report_title ?? 'Report' }}</h1>
    <div class="subtitle">{{ $subtitle ?? '' }}</div>

    <table class="grid">
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($headings as $heading)
                        <td>{{ $row[$heading] ?? '---' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
