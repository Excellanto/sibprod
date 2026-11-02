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
    $dimLines = array_values(array_filter(array_map('trim', $dimLines), static fn ($l) => $l !== ''));
    if ($dimLines === []) {
        $dimLines = ['-'];
    }
@endphp

<div class="invoice-pkg-summary-wrap no-break{{ empty($showPackageSummary) ? ' is-placeholder' : '' }}">
    <table class="invoice-pkg-summary-kv" cellspacing="0" cellpadding="0">
        @php
            $qtyNos = (int) ($totalQtyNos ?? $totalQty ?? 0);
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
            <td class="inv-pkg-lbl">Total Quantity:</td>
            <td class="inv-pkg-val">{{ number_format($qtyCombined, 0, '.', '') }} {{ $qtyUnit }}</td>
        </tr>
        @endif
        @php
            $packagesCount = !empty($usePallets)
                ? (int) ($palletsCount ?? $totalPackages ?? 0)
                : (int) ($totalPackages ?? 0);
        @endphp
        <tr>
            <td class="inv-pkg-lbl">Total Packages:</td>
            <td class="inv-pkg-val">{{ number_format($packagesCount, 0, '.', '') }} Nos.</td>
        </tr>
        <tr>
            <td class="inv-pkg-lbl">Gross weight:</td>
            <td class="inv-pkg-val">{{ $fmtKg($grossWeightKg ?? null) }}</td>
        </tr>
        <tr>
            <td class="inv-pkg-lbl">Net Weight:</td>
            <td class="inv-pkg-val">{{ $fmtKg($netWeightKg ?? null) }}</td>
        </tr>
        <tr>
            <td class="inv-pkg-lbl">Dim. weight:</td>
            <td class="inv-pkg-val">{{ $fmtKg($dimWeightKg ?? null) }}</td>
        </tr>
    </table>
    <table class="invoice-pkg-summary-dim" cellspacing="0" cellpadding="0">
        <tr>
            <td class="inv-pkg-dim-legend">Dimension:</td>
            <td class="inv-pkg-dim-line">{{ $dimLines[0] ?? '—' }}</td>
        </tr>
        @foreach(array_slice($dimLines, 1) as $line)
            <tr>
                <td class="inv-pkg-dim-spacer">&nbsp;</td>
                <td class="inv-pkg-dim-line">{{ $line }}</td>
            </tr>
        @endforeach
    </table>
</div>
