@php
    $sym = $currencySymbol ?? '€';
    $code = $currencyCode ?? 'EUR';
    $rateHeader = 'Rate '.$code.' ('.$sym.')';
    $amountHeader = 'Amount '.$code.' ('.$sym.')';
    $showFocYellowRow = !empty($showFocYellowRow);
    $focBf = $focTableBroughtForward ?? null;
    $pageFocQty = isset($focPageTotalQty) ? (float) $focPageTotalQty : (float) ($focTotalQty ?? 0);
    $pageFocAmt = isset($focPageTotalAmount) ? (float) $focPageTotalAmount : (float) ($focTotalAmount ?? 0);
    $fmtFocQty = static function ($qty): string {
        return rtrim(rtrim(number_format((float) $qty, 4, '.', ''), '0'), '.');
    };
@endphp
<div class="invoice-foc-overlay-wrap">
    <div class="invoice-foc-overlay-inner">
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
                @if(!empty($focBf))
                    <tr class="invoice-foc-body invoice-foc-bf-row">
                        <td class="foc-col-code">&nbsp;</td>
                        <td class="foc-col-desc bold">{{ $focTableBroughtForwardLabel ?? 'Brought Forward' }}</td>
                        <td class="foc-col-be">&nbsp;</td>
                        <td class="foc-col-qty">{{ $fmtFocQty($focBf['qty'] ?? 0) }}</td>
                        <td class="foc-col-units">&nbsp;</td>
                        <td class="foc-col-rate">&nbsp;</td>
                        <td class="foc-col-amt">{{ $sym }} {{ number_format((float) ($focBf['amount'] ?? 0), 2, '.', '') }}</td>
                    </tr>
                @endif
                @foreach ($focPdfRows as $row)
                    <tr class="invoice-foc-body">
                        <td class="foc-col-code">{{ $row['code'] }}</td>
                        <td class="foc-col-desc">{{ $row['description'] }}</td>
                        <td class="foc-col-be">{{ $row['beNo'] }}</td>
                        <td class="foc-col-qty">{{ $fmtFocQty($row['qty'] ?? 0) }}</td>
                        <td class="foc-col-units">{{ $row['units'] }}</td>
                        <td class="foc-col-rate">{{ $sym }} {{ number_format((float) $row['rate'], 3, '.', '') }}</td>
                        <td class="foc-col-amt">{{ $sym }} {{ number_format((float) $row['amount'], 2, '.', '') }}</td>
                    </tr>
                @endforeach
                @if($showFocYellowRow)
                <tr class="invoice-foc-yellow-row">
                    <td colspan="7" class="foc-yellow-cell">{{ $focYellowRowText }}</td>
                </tr>
                @endif
                <tr class="invoice-foc-total-row">
                    <td class="foc-col-code"></td>
                    <td class="foc-col-desc"></td>
                    <td class="foc-total-label">Total</td>
                    <td class="foc-total-qty">{{ $fmtFocQty($pageFocQty) }}</td>
                    <td class="foc-total-units"></td>
                    <td class="foc-total-rate"></td>
                    <td class="foc-total-amt">{{ $sym }} {{ number_format($pageFocAmt, 2, '.', '') }}</td>
                </tr>
                <tr class="invoice-foc-disclaimer-row">
                    <td colspan="7">VALUE FOR CUSTOMS PURPOSE ONLY.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
