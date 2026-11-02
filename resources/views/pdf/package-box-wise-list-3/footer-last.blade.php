@php
    $fmtKg = static function ($v) {
        if ($v === null || $v === '') {
            return '-';
        }

        return number_format((float) $v, 3, '.', '').' Kg';
    };
    $dimLines = $specifyDimensionLines ?? [];
    if (! is_array($dimLines)) {
        $dimLines = [];
    }
    $dimLines = array_slice($dimLines, 0, 5);
    while (count($dimLines) < 5) {
        $dimLines[] = '';
    }
    $dimAllBlank = true;
    foreach ($dimLines as $dl) {
        if (trim((string) $dl) !== '') {
            $dimAllBlank = false;
            break;
        }
    }
    if ($dimAllBlank) {
        $dimLines[0] = '-';
    }
@endphp

<div class="footer-last-wrap no-break">
    <table class="footer-last-outer">
        <colgroup>
            <col class="footer-last-col-left" style="width: 45%;" />
            <col class="footer-last-col-mid" style="width: 35%;" />
            <col class="footer-last-col-right" style="width: 20%;" />
        </colgroup>
        <tr>
            <td colspan="3" class="footer-last-title-cell">100% EXPORT ORIENTED UNIT</td>
        </tr>
        <tr>
            <td class="footer-last-left">
                <div class="footer-last-subtitle">A Govt. Recognized Two Star Export House</div>
                <table class="footer-last-kv" cellspacing="0" cellpadding="0">
                    <colgroup>
                        <col class="footer-last-kv-label-col" />
                        <col />
                    </colgroup>
                    @php
                        $qtyNos = (int) ($totalQtyNos ?? 0);
                        $qtyPairs = (int) ($totalQtyPairs ?? 0);
                        $qtyCombined = $qtyNos + $qtyPairs;
                        if (!empty($hasNos) && !empty($hasPairs)) {
                            $qtyUnit = 'Nos. / Pairs';
                        } elseif (!empty($hasPairs)) {
                            $qtyUnit = 'Pairs';
                        } else {
                            $qtyUnit = 'Nos.';
                        }
                    @endphp
                    @if(!empty($hasNos) || !empty($hasPairs) || $qtyCombined > 0)
                    <tr>
                        <td class="footer-last-label">Total Quantity:</td>
                        <td class="footer-last-val">{{ number_format($qtyCombined, 0, '.', '') }} {{ $qtyUnit }}</td>
                    </tr>
                    @endif
                    @php
                        $packagesCount = !empty($usePallets)
                            ? (int) ($palletsCount ?? $totalPackages ?? 0)
                            : (int) ($totalPackages ?? 0);
                    @endphp
                    <tr>
                        <td class="footer-last-label">Total Packages:</td>
                        <td class="footer-last-val">{{ number_format($packagesCount, 0, '.', '') }} Nos.</td>
                    </tr>
                    <tr>
                        <td class="footer-last-label">Gross weight:</td>
                        <td class="footer-last-val">{{ $fmtKg($grossWeightKg ?? null) }}</td>
                    </tr>
                    <tr>
                        <td class="footer-last-label">Net Weight:</td>
                        <td class="footer-last-val">{{ $fmtKg($netWeightKg ?? null) }}</td>
                    </tr>
                    <tr>
                        <td class="footer-last-label">Dim. weight:</td>
                        <td class="footer-last-val">{{ $fmtKg($dimWeightKg ?? null) }}</td>
                    </tr>
                </table>
                <table class="footer-last-dim-rows" cellspacing="0" cellpadding="0">
                    <colgroup>
                        <col class="footer-last-kv-label-col" />
                        <col />
                    </colgroup>
                    <tr>
                        <td class="footer-last-dim-legend">Dimension:</td>
                        <td class="footer-last-dim-line">
                            @if(trim((string) ($dimLines[0] ?? '')) !== '')
                                {{ $dimLines[0] }}
                            @else
                                &nbsp;
                            @endif
                        </td>
                    </tr>
                    @foreach(array_slice($dimLines, 1) as $line)
                        <tr>
                            <td class="footer-last-dim-legend-spacer">&nbsp;</td>
                            <td class="footer-last-dim-line">
                                @if(trim((string) $line) !== '')
                                    {{ $line }}
                                @else
                                    &nbsp;
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>
            <td class="footer-last-mid">
                <div class="footer-last-regulatory">
                    {!! $companyFeatures !!}
                </div>
            </td>
            <td class="footer-last-sign-col right-col">
                <div class="sign-box">
                    <div class="sign-header">Signature &amp; Date:</div>
                    <div>&nbsp;</div>
                </div>
            </td>
        </tr>
    </table>
</div>
