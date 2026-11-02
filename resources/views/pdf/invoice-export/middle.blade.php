@php
    $sym = $currencySymbol ?? '€';
@endphp

<table class="main invoice-items-table">
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
        @foreach($printRows as $row)
            @if(($row['type'] ?? '') === 'group')
                <tr class="group-row">
                    <td class="center">{{ $row['groupSrNo'] ?? '' }}</td>
                    <td class="center">{{ $row['hsn'] ?? '' }}</td>
                    <td class="desc-col group-title-cell">{{ $row['title'] ?? '' }}</td>
                    <td class="center group-row-empty">&nbsp;</td>
                    <td class="center group-row-empty">&nbsp;</td>
                    <td class="num group-row-empty">&nbsp;</td>
                    <td class="num group-row-empty">&nbsp;</td>
                    <td class="num group-row-empty">&nbsp;</td>
                    <td class="num group-row-empty">&nbsp;</td>
                    <td class="num group-row-empty">&nbsp;</td>
                    <td class="num group-row-empty">&nbsp;</td>
                    <td class="num group-row-empty">&nbsp;</td>
                    <td class="num group-row-empty">&nbsp;</td>
                </tr>
            @elseif(($row['type'] ?? '') === 'line')
                @php
                    $lessDisc = (float) ($row['lessDiscount'] ?? 0);
                    $igstPct = $row['igstRatePercent'] ?? null;
                    $igstAmt = $row['igstAmount'] ?? null;
                @endphp
                <tr class="line-item-row">
                    <td class="center item-sr-cell">&nbsp;</td>
                    <td class="item-hsn-cell">&nbsp;</td>
                    <td class="desc-col">{!! nl2br(e($row['description'] ?? '')) !!}</td>
                    <td class="center">{{ !empty($row['poTag']) ? $row['poTag'] : '—' }}</td>
                    <td class="center">{{ $row['refArticle'] ?? '—' }}</td>
                    <td class="num">{{ number_format((int) ($row['qty'] ?? 0), 0, '.', ',') }}{{ !empty($row['isPairs']) ? ' Pairs' : ' Nos.' }}</td>
                    <td class="num">{{ $sym }} {{ number_format((float) ($row['rate'] ?? 0), 2, '.', ',') }}</td>
                    <td class="num">{{ $sym }} {{ number_format((float) ($row['amount'] ?? 0), 2, '.', ',') }}</td>
                    <td class="num">{{ $lessDisc > 0 ? ($sym.' '.number_format($lessDisc, 2, '.', ',')) : '—' }}</td>
                    <td class="num">{{ $sym }} {{ number_format((float) ($row['taxableValue'] ?? 0), 2, '.', ',') }}</td>
                    <td class="num">
                        @if($igstPct !== null && (float) $igstPct != 0.0)
                            {{ number_format((float) $igstPct, 2, '.', ',') }}%
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">
                        @if($igstAmt !== null && (float) $igstAmt != 0.0)
                            {{ $sym }} {{ number_format((float) $igstAmt, 2, '.', ',') }}
                        @endif
                    </td>
                    <td class="num">{{ $sym }} {{ number_format((float) ($row['finalAmount'] ?? 0), 2, '.', ',') }}</td>
                </tr>
            @elseif(($row['type'] ?? '') === 'highlighter')
                <tr class="line-item-row invoice-highlighter-row">
                    <td class="center item-sr-cell">&nbsp;</td>
                    <td class="item-hsn-cell">&nbsp;</td>
                    <td class="desc-col invoice-highlighter-desc">
                        <div class="invoice-highlighter-text">{{ $row['text'] ?? '' }}</div>
                    </td>
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
            @elseif(($row['type'] ?? '') === 'blank')
                <tr class="line-item-row invoice-blank-line-row">
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
