<table class="main no-break">
    <tr>
        <td colspan="2" class="center">
            <div class="title">PACKING LIST</div>
            <div class="bold">
                Supply Meant for Export Under Letter of Undertaking without Payment of Integrated Tax (IGST)
            </div>
        </td>
    </tr>

    <tr>
        <td width="65%">
            <div class="small">Exporter:</div>
            <div class="center bold">{{ $companyName }}</div>

            <div class="center small">
                @if($companyAddress)
                    {!! $companyAddress !!}
                @endif
             
            </div>

        </td>

        <td width="35%" class="header-right-cell">
            <table class="header-right-table">
                <tr class="header-right-top">
                    <td class="top-left header-right-left">
                        <table>
                            <tr>
                                <td class="label bold">Invoice Number :</td>
                                <td class="value" style="white-space: nowrap;">{{ $invoiceNumber }}</td>
                            </tr>
                            <tr>
                                <td class="label bold">Invoice Date :</td>
                                <td class="value">{{ $invoiceDate }}</td>
                            </tr>
                        </table>
                    </td>
                    <td class="top-right">
                        <table>
                            <tr>
                                <td class="label">Exporter's Ref. :</td>
                                <td class="value">{{ $exporterRef }}</td>
                            </tr>
                            <tr>
                                <td class="label">IEC No. :</td>
                                <td class="value">{{ $iecNo }}</td>
                            </tr>
                            <tr>
                                <td class="label">PAN No. :</td>
                                <td class="value">{{ $panNo }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="orders header-right-orders">
                        <div class="small">Buyer's Order No. &amp; Date:</div>
                        <div class="small">{{ $buyersOrderNoAndDate }}</div>
                    </td>
                </tr>
                <tr>
                    <td class="small header-right-bottom-left">Other Reference(s):</td>
                    <td class="small center bold">{{ $pageLabel }}</td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td class="header-consignee">
            <div class="small">Consignee:</div>
            <b>Attention : {{ $attention }}</b><br>
            {{ $consigneeName }}<br>
            @if($consigneeAddress)
                {{ $consigneeAddress }}<br>
            @endif
            @if($countryOfFinalDestination)
                {{ strtoupper($countryOfFinalDestination) }}<br>
            @endif
            @if($consigneePhone)
                Tel : {{ $consigneePhone }}
            @endif
        </td>

        <td >
            <div class="small">Buyer(if other than consignee):</div>
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
                    <td class="header-country-of-final-destination bold">{{ $countryOfFinalDestination }}</td>
                </tr>
               
            </table>
            </div>
        </td>
    </tr>

    <tr>
        <td style="padding:0px;" class="header-ship-left">
            <table class="no-border">
                <tr>
                    <td  style="border-right: 0.75px solid #000;" width="50%">Pre-Carriage by</td>
                    <td  style="border:0;" width="50%">Place of receipt by Pre-Carrier</td>
                </tr>
                <tr>
                    <td class=" center bold" style="border-right: 0.75px solid #000;">{{ strtoupper($preCarriageBy ?: '-') }}</td>
                    <td class="center bold" style="border:0; ">{{ strtoupper($placeOfReceipt ?: '-') }}</td>
                </tr>
                <tr>
                    <td style="border-right: 0.75px solid #000; border-top: 0.75px solid #000;" >Vessel / Flight No.</td>
                    <td style="border:0; border-top: 0.75px solid #000;">Port of Loading</td>
                </tr>
                <tr>
                    <td class=" center bold" style="border-right: 0.75px solid #000;">{{ strtoupper($vesselFlightNo ?: '-') }}</td>
                    <td class="center bold" style="border:0; ">{{ strtoupper($portOfLoading ?: '-') }}</td>
                </tr>
                <tr>
                    <td style="border-right: 0.75px solid #000;border-top: 0.75px solid #000;">Port of Discharge</td>
                    <td style="border:0;border-top: 0.75px solid #000;">Final Destination</td>
                </tr>
                <tr>
                    <td class=" center bold" style="border-right: 0.75px solid #000;">{{ strtoupper($portOfDischarge ?: '-') }}</td>
                    <td class="center bold" style="border:0; ">{{ strtoupper($finalDestination ?: '-') }}</td>
                </tr>
            </table>
        </td>

        <td class="header-terms-right">
        <table>
           
                <tr>
                    <td colspan="2" class="no-border terms-of-delivery-and-payments">
                        Terms of Delivery and Payments :
                        <span class="red">{{ strtoupper($termsOfPayment ?? 'EX-WORKS') }} ({{ $termsOfDeliveryOption ?? '30 Days' }})</span>
                        <br><br>
                        <div class="center bold">
                            {!! $paymentToDetails !!}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
