<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Box Wise List 2</title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; margin: 0; padding: 0; color: #000; }
        .header-container { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .header-left { width: auto; }
        .header-left table { border-collapse: collapse; }
        .header-left th, .header-left td { border: 0.75pt solid #000; padding: 4px 8px; text-align: left; font-size: 9pt; font-weight: bold; }
        .header-right { width: auto; }
        .header-right table { border-collapse: collapse; }
        .header-right th, .header-right td { border: 0.75pt solid #000; padding: 4px 8px; text-align: center; font-size: 9pt; }
        .header-right th { background: #fff; font-weight: bold; }
        .header-right td { background: #ffff00; font-weight: bold; }
        .header-right td.package-ref { color: #ff0000; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 0.75pt solid #000; padding: 3px 4px; text-align: left; font-size: 8pt; }
        th { background: #f5f5f5; font-weight: bold; }
        .center { text-align: center; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <!-- Header: Shipping Date, BOXWISE LIST SN -->
    <div class="header-container">
        <div class="header-right" style="margin-left: auto;">
            <table>
                <tr>
                    <th>Shipping Date</th>
                    <th>BOXWISE LIST SN/{{ $yearShort }}/</th>
                </tr>
                <tr>
                    <td>{{ $shippingDate }}</td>
                    <td class="package-ref">{{ $packageRefNumber }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 6%;"></th>
                <th style="width: 8%;"></th>
                <th style="width: 8%;" class="center">{{ $totalWeight }}</th>
                <th style="width: 8%;"></th>
                <th style="width: 8%;"></th>
                <th style="width: 15%;"></th>
                <th style="width: 25%;"></th>
                <th style="width: 10%;" class="right">{{ $totalQty }}</th>
            </tr>
            <tr>
                <th style="width: 6%;">Order</th>
                <th style="width: 8%;" class="center">BOX</th>
                <th style="width: 8%;" class="center">N.Weight</th>
                <th style="width: 8%;" class="center">PS NO</th>
                <th style="width: 8%;" class="center">SIZE</th>
                <th style="width: 15%;">ORDER NO.</th>
                <th style="width: 25%;">REFRENCE NO.</th>
                <th style="width: 10%;" class="right">QTY</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['orderNumber'] }}</td>
                    <td class="center">{{ $row['boxCode'] }}</td>
                    <td class="center">{{ number_format($row['netWeight'], 3, '.', '') }}</td>
                    <td class="center">{{ $row['psNumber'] }}</td>
                    <td class="center">{{ $row['size'] }}</td>
                    <td>{{ $row['orderNo'] }}</td>
                    <td>{{ $row['refNumber'] }}</td>
                    <td class="right">{{ $row['qty'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
