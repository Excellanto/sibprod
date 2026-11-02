<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Box Wise List</title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; margin: 0; padding: 0; color: #000; }
        .header { text-align: center; margin-bottom: 8px; }
        .company { font-size: 16pt; font-weight: bold; margin-bottom: 4px; }
        .client { font-size: 12pt; font-weight: bold; margin-bottom: 4px; }
        .subtitle { font-size: 11pt; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.75pt solid #000; padding: 4px 6px; text-align: left; font-size: 9pt; }
        th { background: #f5f5f5; font-weight: bold; }
        .box-header { font-weight: bold; background: #e8e8e8; }
        .qty-right { text-align: right; }
        .center { text-align: center; }
        .total-row td { font-weight: bold; border: 0.75pt solid #000; }
        .total-row td:last-child { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">SIBRAMA NEWAGE</div>
        <div class="client">{{ $clientName }}</div>
        <div class="subtitle">BOX WISE LIST SN/{{ $yearShort }}/ DT.{{ $todayDate }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">BOX</th>
                <th style="width: 20%;">ORDER NO.</th>
                <th style="width: 15%;">REFERENCE</th>
                <th style="width: 12%;" class="center">PS NO</th>
                <th style="width: 12%;">BUCKLES</th>
                <th style="width: 10%;" class="center">SIZE</th>
                <th style="width: 10%;" class="qty-right">QTY</th>
            </tr>
        </thead>
        <tbody>
            @foreach($boxes as $box)
                @php 
                    $pos = $box['orderPOs'];
                    $boxTotal = 0;
                    if (count($pos) > 0) {
                        $boxTotal = array_sum(array_column($pos, 'qty'));
                    }
                @endphp
                @if(count($pos) > 0)
                    @foreach($pos as $index => $row)
                        <tr>
                            @if($index === 0)
                                <td rowspan="{{ count($pos) + 1 }}" class="box-header" style="vertical-align: top;">{{ $box['boxNumber'] }}</td>
                            @endif
                            <td>{{ $row['orderNumber'] }}</td>
                            <td>{{ $row['refNumber'] }}</td>
                            <td class="center">{{ $row['psNumber'] }}</td>
                            <td>{{ $row['buckles'] }}</td>
                            <td class="center">{{ $row['size'] }}</td>
                            <td class="qty-right">{{ $row['qty'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="5"></td>
                        <td class="qty-right">{{ $boxTotal }}</td>
                    </tr>
                @else
                    <tr>
                        <td class="box-header">{{ $box['boxNumber'] }}</td>
                        <td colspan="6">-</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
