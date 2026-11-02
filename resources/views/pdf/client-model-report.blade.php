<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Client Model Report</title>
    <style>
        body {
            font-family: Tahoma, Verdana, Arial, sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 0;
        }

        h1 {
            font-size: 14pt;
            margin: 0 0 4px 0;
        }

        .subtitle {
            font-size: 9pt;
            color: #444;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            width: 32%;
            background: #f3f4f6;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <h1>Client Model Report</h1>
    <div class="subtitle">
        Client: {{ $client_name ?? '-' }}
        @if (!empty($fields['ModelNo']))
            | Model No: {{ $fields['ModelNo'] }}
        @endif
        @if (!empty($fields['RefNumber']))
            | Ref No: {{ $fields['RefNumber'] }}
        @endif
    </div>

    <table>
        @foreach ($fields as $label => $value)
            <tr>
                <th>{{ $label }}</th>
                <td>{{ $value }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
