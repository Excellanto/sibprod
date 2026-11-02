@php
    $sym = $currencySymbol ?? '€';
    $code = $currencyCode ?? 'EUR';
    $rateHeader = 'Rate '.$code.' ('.$sym.')';
    $amountHeader = 'Amount '.$code.' ('.$sym.')';
@endphp
<style type="text/css">
    .invoice-foc-block {
        width: 100%;
        margin-top: 14px;
        page-break-inside: avoid;
    }
    .invoice-foc-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif;
        font-size: 8pt;
        border: 2px solid #000;
    }
    .invoice-foc-table th,
    .invoice-foc-table td {
        border: 2px solid #000;
        padding: 4px 5px;
        vertical-align: middle;
    }
    .invoice-foc-title-row td {
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        font-size: 8.5pt;
        letter-spacing: 0.3px;
        line-height: 1.25;
    }
    .invoice-foc-head-row th {
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        font-size: 7.5pt;
        line-height: 1.2;
    }
    .invoice-foc-head-row .foc-h-desc {
        text-align: center;
    }
    .invoice-foc-body .foc-col-code {
        text-align: center;
        font-size: 8pt;
    }
    .invoice-foc-body .foc-col-desc {
        text-align: left;
        text-transform: none;
        font-size: 8pt;
        line-height: 1.3;
    }
    .invoice-foc-body .foc-col-be {
        text-align: center;
        font-size: 8pt;
    }
    .invoice-foc-body .foc-col-qty {
        text-align: right;
        white-space: nowrap;
    }
    .invoice-foc-body .foc-col-units {
        text-align: center;
        white-space: nowrap;
    }
    .invoice-foc-body .foc-col-rate {
        text-align: right;
        white-space: nowrap;
    }
    .invoice-foc-body .foc-col-amt {
        text-align: right;
        white-space: nowrap;
    }
    .invoice-foc-yellow-row td.foc-yellow-cell {
        background-color: #ffff00;
        font-weight: bold;
        text-align: left;
        font-size: 8pt;
        line-height: 1.3;
    }
    .invoice-foc-total-row td {
        font-weight: bold;
        text-transform: uppercase;
        font-size: 8pt;
        border-bottom: 0.5px solid #999 !important;
    }
    .invoice-foc-total-row .foc-total-label {
        text-align: center;
    }
    .invoice-foc-total-row .foc-total-qty {
        text-align: right;
    }
    .invoice-foc-total-row .foc-total-rate {
        text-align: right;
    }
    .invoice-foc-total-row .foc-total-amt {
        text-align: right;
    }
    .invoice-foc-disclaimer-row td {
        font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif;
        font-size: 8.5pt;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-top: 0.5px solid #999 !important;
        border-bottom: 2px solid #000 !important;
        border-left: 2px solid #000 !important;
        border-right: 2px solid #000 !important;
        padding: 6px 5px;
    }
</style>

<div class="invoice-foc-block">
    <table class="invoice-foc-table" cellspacing="0" cellpadding="0">
        <colgroup>
            <col style="width: 8%;" />
            <col style="width: 28%;" />
            <col style="width: 10%;" />
            <col style="width: 10%;" />
            <col style="width: 10%;" />
            <col style="width: 16%;" />
            <col style="width: 18%;" />
        </colgroup>
        <tbody>
            <tr class="invoice-foc-title-row">
                <td colspan="7">
                    THIS INVOICE INCLUDES FOLLOWING ITEMS IMPORTED FREE OF COST AS PER DETAILS HEREUNDER.
                </td>
            </tr>
            <tr class="invoice-foc-head-row">
                <th colspan="2" class="foc-h-desc">Description</th>
                <th>B/E No.</th>
                <th>Qty</th>
                <th>Units</th>
                <th>{{ $rateHeader }}</th>
                <th>{{ $amountHeader }}</th>
            </tr>
            @foreach ($focPdfRows as $row)
                <tr class="invoice-foc-body">
                    <td class="foc-col-code">{{ $row['code'] }}</td>
                    <td class="foc-col-desc">{{ $row['description'] }}</td>
                    <td class="foc-col-be">{{ $row['beNo'] }}</td>
                    <td class="foc-col-qty">{{ rtrim(rtrim(number_format((float) $row['qty'], 4, '.', ''), '0'), '.') }}</td>
                    <td class="foc-col-units">{{ $row['units'] }}</td>
                    <td class="foc-col-rate">{{ $sym }} {{ number_format((float) $row['rate'], 3, '.', '') }}</td>
                    <td class="foc-col-amt">{{ $sym }} {{ number_format((float) $row['amount'], 2, '.', '') }}</td>
                </tr>
            @endforeach
            <tr class="invoice-foc-yellow-row">
                <td colspan="7" class="foc-yellow-cell">{{ $focYellowRowText }}</td>
            </tr>
            <tr class="invoice-foc-total-row">
                <td class="foc-col-code"></td>
                <td class="foc-col-desc"></td>
                <td class="foc-total-label">Total</td>
                <td class="foc-total-qty">{{ rtrim(rtrim(number_format((float) $focTotalQty, 4, '.', ''), '0'), '.') }}</td>
                <td class="foc-total-units"></td>
                <td class="foc-total-rate"></td>
                <td class="foc-total-amt">{{ $sym }} {{ number_format((float) $focTotalAmount, 2, '.', '') }}</td>
            </tr>
            <tr class="invoice-foc-disclaimer-row">
                <td colspan="7">VALUE FOR CUSTOMS PURPOSE ONLY.</td>
            </tr>
        </tbody>
    </table>
</div>
