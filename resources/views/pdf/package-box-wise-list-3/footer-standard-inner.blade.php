<table class="footer-box no-break">
    {{-- colgroup + 3 cells in row 1 so PDF engines (wkhtmltopdf) apply column widths; colspan breaks width rules --}}
    <colgroup>
        <col class="footer-col-left" style="width: 30%;" />
        <col class="footer-col-mid" style="width: 50%;" />
        <col class="footer-col-right" style="width: 20%;" />
    </colgroup>
    <tr class="top-row">
        <td></td>
        <td></td>
        <td class="footer-top-right">{{ $customerReference }}</td>
    </tr>
    <tr>
        <td class="left-col">
            100% EXPORT ORIENTED UNIT<br />
            A Govt. Recognized Two Star Export House
        </td>
        <td class="mid-col">
            {!! $companyFeatures !!}
        </td>
        <td class="right-col">
            <div class="sign-box">
                <div class="sign-header">Signature &amp; Date:</div>
                <div>&nbsp;</div>
            </div>
        </td>
    </tr>
</table>
