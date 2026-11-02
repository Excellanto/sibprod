@php
    /** @var array $totals */
    $sym = $currencySymbol ?? '€';
    $t = $totals ?? [];
    $fmt = static fn (float $v): string => $sym.' '.number_format($v, 2, '.', ',');
    $chg = static function ($v) use ($sym): string {
        if ($v === null) {
            return '—';
        }

        return $sym.' '.number_format((float) $v, 2, '.', ',');
    };
    $hasIgst = ! empty($t['hasIgst']);
    $igstVal = (float) ($t['totalIgst'] ?? 0);
    $igstCell = $hasIgst && $igstVal != 0.0 ? $fmt($igstVal) : '—';
    $chargesTotal = (float) ($t['freight'] ?? 0) + (float) ($t['insurance'] ?? 0) + (float) ($t['packing'] ?? 0);
    // Before-tax total should include line taxable value plus footer charges.
    $beforeTaxCell = $fmt(round((float) ($t['totalTaxable'] ?? 0) + $chargesTotal, 2));
    $afterTaxCell = $fmt((float) ($t['grandTotal'] ?? 0));
    $amountInWords = amount_in_words((float) ($t['grandTotal'] ?? 0), $currencyCode ?? 'EUR');
@endphp
<tbody class="invoice-table-footer">
    <tr class="invoice-footer-summary-row">
        <td colspan="7" class="invoice-footer-lbl">Sub Total</td>
        <td class="num">{{ $fmt((float) ($t['totalAmount'] ?? 0)) }}</td>
        <td class="num">{{ $fmt((float) ($t['totalLessDiscount'] ?? 0)) }}</td>
        <td class="num">{{ $fmt((float) ($t['totalTaxable'] ?? 0)) }}</td>
        <td class="num">&nbsp;</td>
        <td class="num">{{ $igstCell }}</td>
        <td class="num">{{ $fmt((float) ($t['totalFinalLines'] ?? 0)) }}</td>
    </tr>
    <tr class="invoice-footer-summary-row">
        <td colspan="12" class="invoice-footer-lbl">Freight Charges</td>
        <td class="num">{{ $chg($t['freight'] ?? null) }}</td>
    </tr>
    <tr class="invoice-footer-summary-row">
        <td colspan="12" class="invoice-footer-lbl">Insurance Charges</td>
        <td class="num">{{ $chg($t['insurance'] ?? null) }}</td>
    </tr>
    <tr class="invoice-footer-summary-row">
        <td colspan="12" class="invoice-footer-lbl">Packing Charges</td>
        <td class="num">{{ $chg($t['packing'] ?? null) }}</td>
    </tr>
    <tr class="invoice-footer-summary-row invoice-footer-grand-total-row">
        <td colspan="5" class="invoice-footer-grand-lbl center">Grand Total</td>
        <td class="num bold">{{ number_format((int) ($sumQty ?? 0), 0, '.', ',') }}</td>
        <td colspan="6" class="invoice-footer-grand-fill">&nbsp;</td>
        <td class="num bold">{{ $beforeTaxCell }}</td>
    </tr>
    <tr class="invoice-footer-shipping-row">
        <td colspan="13" class="invoice-footer-shipping-wrap">
            <table class="invoice-shipping-inner">
                <colgroup>
                    <col class="col-sf-marks" />
                    <col class="col-sf-pkgs" />
                    <col class="col-sf-buyer" />
                    <col class="col-sf-tax" />
                </colgroup>
                <tr>
                    <td class="invoice-footer-marks">
                        <div class="mf-subhd mf-marks-hd">Marks &amp; Nos. / Container No.</div>
                        <div class="mf-body mf-marks-body">{{ $marksAndNosText ?? '—' }}</div>
                    </td>
                    <td class="invoice-footer-pkgs">
                        <div class="mf-subhd">No. &amp; Kind of Pkgs.</div>
                        <div class="mf-body mf-pkgs-body">{{ $packageKindLine ?? '—' }}</div>
                    </td>
                    <td class="invoice-footer-buyer">
                        <div class="mf-buyer-hdr">Buyer&apos;s Order No. &amp; Date:</div>
                        <div class="mf-buyer-text">{{ $buyersOrderNoAndDate !== '' ? $buyersOrderNoAndDate : '—' }}</div>
                        <div class="mf-buyer-sep"></div>
                        <div>AGREEMENT CODE : {{ $agreementCode ?? 'GSTP' }}</div>
                        <div class="mf-buyer-sep"></div>
                        <div>Remarks :</div>
                        <div class="mf-buyer-remarks">{{ $invoiceRemarks ?? '' }}</div>
                    </td>
                    <td class="invoice-footer-tax-wrap">
                        <table class="invoice-tax-mini">
                            <tr>
                                <td class="lbl">Total Amount Before Tax</td>
                                <td class="num">&nbsp;</td>
                            </tr>
                            <tr>
                                <td class="lbl">Add : IGST</td>
                                <td class="num">{{ $igstCell }}</td>
                            </tr>
                            <tr>
                                <td class="lbl">Tax Amount : GST</td>
                                <td class="num">{{ $igstCell }}</td>
                            </tr>
                            <tr>
                                <td class="lbl">Total Amount After Tax</td>
                                <td class="num">{{ $afterTaxCell }}</td>
                            </tr>
                            <tr class="invoice-tax-gst-row">
                                <td class="lbl">GST Payable on Reverse Charge</td>
                                <td class="num">—</td>
                            </tr>
                        </table>
                    </td>
                </tr>
             
            </table>
        </td>
    </tr>
    <tr class="invoice-footer-amount-words-row">
        <td colspan="13" class="invoice-footer-amount-words">
            <span class="invoice-footer-amount-words-lbl">Amount in Words :</span>
            <span class="invoice-footer-amount-words-val">{{ $amountInWords }}</span>
        </td>
    </tr>

</tbody>
