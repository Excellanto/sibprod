<table class="invoice-pdf-footer no-break">
    <colgroup>
        <col class="invoice-footer-col-left" />
        <col class="invoice-footer-col-mid" />
        <col class="invoice-footer-col-right" />
    </colgroup>
    <tbody>
        <tr class="invoice-footer-row-co">
            <td colspan="3" class="invoice-footer-co-cell">
                @if($customerReference !== '')
                    {{ $customerReference }}
                @else
                    &nbsp;
                @endif
            </td>
        </tr>
        <tr class="invoice-footer-row-statement">
            <td
                colspan="3"
                class="invoice-footer-statement-cell{{ empty($includeBottomStatement) ? ' is-empty' : '' }}"
            >
                @if(!empty($includeBottomStatement))
                    {!! export_invoice_statement_html($exportStatement ?? '', $invoiceAgreementCode ?? null) !!}
                @else
                    <span class="invoice-footer-statement-spacer">&nbsp;</span>
                @endif
            </td>
        </tr>
        <tr class="invoice-footer-row-columns">
            <td class="invoice-footer-left-col">
                <div class="invoice-footer-eou-hd">
                    100% EXPORT ORIENTED UNIT<br />
                    A Govt. Recognized Two Star Export House
                </div>
                @include('pdf.invoice-export.invoice-package-summary', array_merge($invoicePackageSummary ?? [], [
                    'showPackageSummary' => $showPackageSummary ?? false,
                ]))
            </td>
            <td class="invoice-footer-mid-col">
                {!! $companyFeatures !== '' ? $companyFeatures : '&nbsp;' !!}
            </td>
            <td class="invoice-footer-right-col">
                <div class="invoice-footer-sign-box">
                    <div class="invoice-footer-sign-header">Signature &amp; Date:</div>
                    <div class="invoice-footer-sign-body">&nbsp;</div>
                </div>
            </td>
        </tr>
        <tr class="invoice-footer-row-declaration">
            <td colspan="3" class="invoice-footer-declaration-cell">
                <span class="invoice-footer-declaration-title">Declaration</span>
                : We declare that this invoice shows the actual price of goods described and that all particulars are true and correct.
            </td>
        </tr>
    </tbody>
</table>

@php
    $rs = $middleSectionRowStats ?? [];
@endphp
<!-- <div class="invoice-footer-row-stats">
    Middle table rows — Total: {{ (int) ($rs['total'] ?? 0) }} · Content: {{ (int) ($rs['content'] ?? 0) }} · Remaining (blank): {{ (int) ($rs['remaining'] ?? 0) }}
    @if(isset($rs['minCapacity']))
        <span class="invoice-footer-row-stats-cap"> (min. capacity {{ (int) $rs['minCapacity'] }})</span>
    @endif
</div> -->
