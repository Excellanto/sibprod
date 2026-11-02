@php
    $sym = $currencySymbol ?? '€';
@endphp

<table class="main invoice-items-table invoice-foc-page-table">
    <colgroup>
        <col class="col-sr" />
        <col class="col-hsn" />
        <col class="col-desc" />
        <col class="col-po-no" />
        <col class="col-ref" />
        <col class="col-qty" />
        <col class="col-rate" />
        <col class="col-amt" />
        <col class="col-less" />
        <col class="col-taxable" />
        <col class="col-igst-rate" />
        <col class="col-igst-amt" />
        <col class="col-final" />
    </colgroup>
    <thead>
        <tr class="invoice-thead-top">
            <th rowspan="2">Sr.<br/>No.</th>
            <th rowspan="2">HSN</th>
            <th rowspan="2" class="desc-col">Description of Goods</th>
            <th rowspan="2">PO<br/>No.</th>
            <th rowspan="2">Ref.<br/>Article</th>
            <th>Quantity</th>
            <th>Rate</th>
            <th>Amount</th>
            <th>Less<br/>Discount</th>
            <th>Taxable<br/>Value</th>
            <th colspan="2">IGST Value</th>
            <th>Amount</th>
        </tr>
        <tr class="invoice-thead-sub">
            <th>{{ $qtyUnitHeader ?? 'Nos.' }}</th>
            <th>{{ $currencySubheader }}</th>
            <th>{{ $currencySubheader }}</th>
            <th>{{ $currencySubheader }}</th>
            <th>{{ $currencySubheader }}</th>
            <th>Rate</th>
            <th>{{ $currencySubheader }}</th>
            <th>{{ $currencySubheader }}</th>
        </tr>
    </thead>
    <tbody>
        @if(!empty($broughtForward))
            @php $bf = $broughtForward; @endphp
            <tr class="line-item-row invoice-bf-row">
                <td class="center item-sr-cell">&nbsp;</td>
                <td class="item-hsn-cell">&nbsp;</td>
                <td class="desc-col bold">{{ $broughtForwardLabel ?? 'Brought Forward' }}</td>
                <td class="center">&nbsp;</td>
                <td class="center">&nbsp;</td>
                <td class="num">{{ number_format((int) ($bf['qty'] ?? 0), 0, '.', ',') }}</td>
                <td class="num">&nbsp;</td>
                <td class="num">{{ $sym }} {{ number_format((float) ($bf['amount'] ?? 0), 2, '.', ',') }}</td>
                <td class="num">{{ $sym }} {{ number_format((float) ($bf['lessDiscount'] ?? 0), 2, '.', ',') }}</td>
                <td class="num">{{ $sym }} {{ number_format((float) ($bf['taxable'] ?? 0), 2, '.', ',') }}</td>
                <td class="num">&nbsp;</td>
                <td class="num">{{ $sym }} {{ number_format((float) ($bf['igst'] ?? 0), 2, '.', ',') }}</td>
                <td class="num">{{ $sym }} {{ number_format((float) ($bf['final'] ?? 0), 2, '.', ',') }}</td>
            </tr>
        @endif
        @php $focEmbedSeen = false; @endphp
        @foreach($focPrintRows ?? [] as $row)
            @if(($row['type'] ?? '') === 'foc')
                @php
                    $focEmbedSeen = true;
                    $focTable = $row['table'] ?? [];
                @endphp
                <tr class="line-item-row invoice-foc-embed-row">
                    <td class="center item-sr-cell">&nbsp;</td>
                    <td colspan="4" class="invoice-foc-embed-cell">
                        @include('pdf.invoice-export.foc-overlay', [
                            'focPdfRows' => $focTable['focPdfRows'] ?? [],
                            'showFocYellowRow' => !empty($focTable['showFocYellowRow']),
                            'focYellowRowText' => $focTable['focYellowRowText'] ?? ($focYellowRowText ?? ''),
                            'focTableBroughtForward' => $focTable['focTableBroughtForward'] ?? null,
                            'focTableBroughtForwardLabel' => $focTable['focTableBroughtForwardLabel'] ?? null,
                            'focPageTotalQty' => $focTable['focPageTotalQty'] ?? 0,
                            'focPageTotalAmount' => $focTable['focPageTotalAmount'] ?? 0,
                            'currencySymbol' => $currencySymbol ?? '€',
                            'currencyCode' => $currencyCode ?? 'EUR',
                        ])
                    </td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                </tr>
            @elseif(($row['type'] ?? '') === 'blank')
                <tr class="line-item-row invoice-blank-line-row{{ $focEmbedSeen ? '' : ' invoice-foc-pre-embed-blank' }}">
                    <td class="center item-sr-cell">&nbsp;</td>
                    <td class="item-hsn-cell">&nbsp;</td>
                    <td class="desc-col">&nbsp;</td>
                    <td class="center">&nbsp;</td>
                    <td class="center">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                </tr>
            @endif
        @endforeach
    </tbody>
    @if(!empty($showPageSubtotal) && !empty($pageSubtotal))
        @php $ps = $pageSubtotal; @endphp
        <tbody class="invoice-table-footer invoice-page-subtotal-body">
            <tr class="invoice-footer-summary-row">
                <td colspan="5" class="invoice-footer-lbl">Sub Total</td>
                <td class="num">{{ number_format((int) ($ps['qty'] ?? 0), 0, '.', ',') }}</td>
                <td class="num">&nbsp;</td>
                <td class="num">{{ $sym }} {{ number_format((float) ($ps['amount'] ?? 0), 2, '.', ',') }}</td>
                <td class="num">{{ $sym }} {{ number_format((float) ($ps['lessDiscount'] ?? 0), 2, '.', ',') }}</td>
                <td class="num">{{ $sym }} {{ number_format((float) ($ps['taxable'] ?? 0), 2, '.', ',') }}</td>
                <td class="num">&nbsp;</td>
                <td class="num">{{ $sym }} {{ number_format((float) ($ps['igst'] ?? 0), 2, '.', ',') }}</td>
                <td class="num">{{ $sym }} {{ number_format((float) ($ps['final'] ?? 0), 2, '.', ',') }}</td>
            </tr>
        </tbody>
    @endif
    @if(!empty($showMiddleFooter))
        @include('pdf.invoice-export.middle-footer')
    @endif
</table>
