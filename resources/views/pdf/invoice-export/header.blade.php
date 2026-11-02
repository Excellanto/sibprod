<table class="main no-break">
    <tr>
        <td colspan="2" class="center">
            <div class="title">{{ $invoiceHeading ?? 'EXPORT INVOICE' }}</div>
            <div class="bold">
                Supply Meant for Export Under Letter of Undertaking without Payment of Integrated Tax (IGST)
            </div>
        </td>
    </tr>

    <tr>
        <td width="58%">
            <div class="small">Exporter:</div>
            <div class="center bold">{{ $companyName }}</div>
            <div class="center small">
                @if($companyAddress)
                    {!! $companyAddress !!}
                @endif
            </div>
        </td>

        <td width="42%" class="header-right-cell">
            <table class="header-right-table">
                <colgroup>
                    <col class="hdr-rt-lbl" />
                    <col class="hdr-rt-val" />
                    <col class="hdr-rt-tick" />
                    <col class="hdr-rt-copy" />
                </colgroup>
                <tr>
                    <td class="label">Invoice No.</td>
                    <td rowspan="2" class="invoice-no-date-cell">
                        <div class="invoice-no-val">{{ $invoiceNumber }}</div>
                        <div class="value">{{ $invoiceDate }}</div>
                    </td>
                    <td colspan="2">Exporter's Ref. : <span class="value">{{ $exporterRef }}</span></td>
                </tr>
                <tr>
                    <td class="label">Invoice Date :</td>
                    <td colspan="2">IEC No. : <span class="value">{{ $iecNo }}</span></td>
                </tr>
                <tr>
                    <td class="label">Transport Mode :</td>
                    <td class="value">{{ $transportMode ?? '' }}</td>
                    <td class="tick-hd">Tick</td>
                    <td>PAN No. : <span class="value">{{ $panNo }}</span></td>
                </tr>
                <tr>
                    <td class="label">Vehicle Number :</td>
                    <td class="value">{{ $vehicleNumber ?? '' }}</td>
                    <td class="tick-cell">&nbsp;</td>
                    <td class="copy-kind">ORIGINAL FOR RECIPIENT</td>
                </tr>
                <tr>
                    <td class="label">Date of Supply :</td>
                    <td class="value">{{ $supplyDate }}</td>
                    <td class="tick-cell">&nbsp;</td>
                    <td class="copy-kind">DUPLICATE FOR TRANSPORTER</td>
                </tr>
                <tr>
                    <td class="label">Place of Supply :</td>
                    <td class="value">{{ strtoupper($countryOfFinalDestination ?: '') }}</td>
                    <td class="tick-cell">&nbsp;</td>
                    <td class="copy-kind">TRIPLICATE FOR SUPPLIER</td>
                </tr>
                <tr>
                    <td colspan="3" class="small header-right-bottom-left">Other Reference(s):</td>
                    <td class="small center bold">{{ $pageLabel }}</td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td class="header-consignee">
            <div class="small">Detail of Consignee | Shipped to</div>
            
           Name : {{ $consigneeName }}<br>
            @if($consigneeAddress)
                Address : {{ $consigneeAddress }}<br>
            @endif
            @if($countryOfFinalDestination)
                Country : {{ strtoupper($countryOfFinalDestination) }}<br>
            @endif
            @if($consigneePhone)
                Tel : {{ $consigneePhone }}
            @endif
        </td>

        <td>
            <div class="small">Details of Receiver | Billed to | Buyer</div>
            <div class="center bold header-buyer">
                {{ $buyerName }}<br>
                @if($buyerAddress)
                    {{ $buyerAddress }}
                @endif
            </div>
            <div class="header-country-of-origin-and-final-destination">
                <table>
                    <tr>
                        <td class="header-country-of-origin">Country of origin of goods</td>
                        <td class="header-country-of-final-destination">Country of Final Destination</td>
                    </tr>
                    <tr>
                        <td class="header-country-of-origin bold">{{ $countryOfOrigin }}</td>
                        <td class="header-country-of-final-destination bold">{{ $countryOfFinalDestination ?: '—' }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>

    <tr>
        <td style="padding:0;" class="no-border">
            <table class="no-border">
                <tr>
                    <td style="border-right: 0.75px solid #000;" width="50%">Pre-Carriage by</td>
                    <td style="border:0;" width="50%">Place of receipt by Pre-Carrier</td>
                </tr>
                <tr>
                    <td class="center bold" style="border-right: 0.75px solid #000;">{{ strtoupper($preCarriageBy ?: '-') }}</td>
                    <td class="center bold" style="border:0;">{{ strtoupper($placeOfReceipt ?: '-') }}</td>
                </tr>
                <tr>
                    <td style="border-right: 0.75px solid #000; border-top: 0.75px solid #000;">Vessel / Flight No.</td>
                    <td style="border:0; border-top: 0.75px solid #000;">Port of Loading</td>
                </tr>
                <tr>
                    <td class="center bold" style="border-right: 0.75px solid #000;">{{ strtoupper($vesselFlightNo ?: '-') }}</td>
                    <td class="center bold" style="border:0;">{{ strtoupper($portOfLoading ?: '-') }}</td>
                </tr>
                <tr>
                    <td style="border-right: 0.75px solid #000; border-top: 0.75px solid #000;">Port of Discharge</td>
                    <td style="border:0; border-top: 0.75px solid #000;">Final Destination</td>
                </tr>
                <tr>
                    <td class="center bold" style="border-right: 0.75px solid #000;">{{ strtoupper($portOfDischarge ?: '-') }}</td>
                    <td class="center bold" style="border:0;">{{ strtoupper($finalDestination ?: '-') }}</td>
                </tr>
            </table>
        </td>

        <td class="header-country-of-origin-and-final-destination">
            <table>
                <tr>
                    <td colspan="2" class="no-border terms-of-delivery-and-payments">
                        Terms of Delivery and Payments :
                        <span class="red">{{ strtoupper($termsOfPayment ?? 'EX-WORKS') }} ({{ $termsOfDeliveryOption ?? '30 Days' }})</span>
                        <br><br>
                        <div class="center bold">
                            {!! $paymentToDetails ?: '—' !!}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
