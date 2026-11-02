<table class="main packing-items no-break">
    <thead>
        <tr class="innerrow">
            <th width="10%" style="border-right:0.75px solid #000 !important;">Marks &amp; Nos. <br/>Container No.</th>
            <th style="border-right:0.75px solid #000 !important; text-align: left;" >No. &amp; Kind of Pkgs. Description of Goods</th>
            <th style="border-right:0.75px solid #000 !important;">Ref./Article</th>
            <th style="border-right:0.75px solid #000 !important;" class="center">
                Quantity <br/>
                @if(!empty($allPairs))
                    Pairs
                @elseif(!empty($hasPairs))
                    Nos. / Pairs
                @else
                    Nos.
                @endif
            </th>
            <th  class="center">Remarks / <br/> Net Weight</th>
            <th style="border-right:0.75px solid #000 !important;"></th>
        </tr>

    </thead>

    <tbody>
        @if(!empty($broughtForward))
            @php
                $bf = $broughtForward;
                $bfW = (float) ($bf['weight'] ?? 0);
                $bfWFmt = rtrim(rtrim(number_format($bfW, 3, '.', ''), '0'), '.');
                if ($bfWFmt === '') {
                    $bfWFmt = '0';
                }
            @endphp
            <tr class="packing-bf-row">
                <td style="border-right: 0.75px solid #000 !important;">&nbsp;</td>
                <td class="bold" style="border-right: 0.75px solid #000 !important;">Brought Forward</td>
                <td style="border-right: 0.75px solid #000 !important;">&nbsp;</td>
                <td class="right bold" style="border-right: 0.75px solid #000 !important;">{{ (int) ($bf['qty'] ?? 0) }}</td>
                <td class="center bold" style="border-right: none !important;">{{ $bfWFmt }} kg</td>
                <td style="border-left: none !important; border-right: 0.75px solid #000 !important;"></td>
            </tr>
        @endif
    @if(!empty($usePallets) && !empty($palletSections))
        @foreach($palletSections as $sectionIndex => $section)
            @php
                $palletTopBorder = $sectionIndex === 0 && empty($broughtForward) ? 'border-top: 0.75px solid #000 !important;' : '';
                $showPalletLabel = ! isset($section['showLabel']) || ! empty($section['showLabel']);
            @endphp
            @if($showPalletLabel)
            <tr>
                <td class="center bold" style="border: none !important;{{ $palletTopBorder }}border-right: 0.75px solid #000 !important;border-bottom: none !important;">
                    {{ $section['label'] }}<br/>
                    <span class="underline" style="font-weight: normal;">Corrugated Boxes</span>
                </td>
                <td style="border: none !important;{{ $palletTopBorder }}border-right: 0.75px solid #000 !important;border-bottom: none !important;"></td>
                <td style="border: none !important;{{ $palletTopBorder }}border-right: 0.75px solid #000 !important;border-bottom: none !important;"></td>
                <td style="border: none !important;{{ $palletTopBorder }}border-right: 0.75px solid #000 !important;border-bottom: none !important;"></td>
                <td style="border: none !important;{{ $palletTopBorder }}border-bottom: none !important;"></td>
                <td style="border: none !important;{{ $palletTopBorder }}border-right: 0.75px solid #000 !important;border-bottom: none !important;"></td>
            </tr>
            @endif
            @foreach(($section['boxes'] ?? []) as $boxGroup)
                @php $rowCount = count($boxGroup['rows']); @endphp
                @foreach($boxGroup['rows'] as $index => $row)
                    <tr>
                        @if($index === 0)
                            <td style="border: none !important; vertical-align: top;border-right: 0.75px solid #000 !important;" rowspan="{{ $rowCount }}">
                                {{ $boxGroup['boxNumber'] }}
                            </td>
                        @endif
                        <td style="border: none !important;border-right: 0.75px solid #000 !important;">{{ $row['description'] }}</td>
                        <td style="border: none !important; border-right: 0.75px solid #000 !important;" class="center">{{ $row['refArticle'] }}</td>
                        <td style="border-bottom: none !important; border-right: 0.75px solid #000 !important;border-top:none !important;" class="right">{{ !empty($row['isPairs']) ? $row['quantity'] . ' Pairs' : $row['quantity'] . ' Nos.' }}</td>
                        <td style="border-bottom: none !important; border-right: none !important; border-top:none !important;" class="center">
                            @if(($row['weight'] ?? null) !== null)
                                @php
                                    $w = (float) $row['weight'];
                                    $fw = rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.');
                                @endphp
                                {{ $fw }} kg
                            @endif
                        </td>
                        <td style="border: none !important;border-right: 0.75px solid #000 !important; border-left: none !important;"></td>
                    </tr>
                @endforeach
            @endforeach
        @endforeach
    @else
        <tr>
            <td class="underline" style="border: none !important;border-top: 0.75px solid #000 !important; border-right: 0.75px solid #000 !important;">Corrugated Boxes</td>
            <td style="border: none !important;border-top: 0.75px solid #000 !important; border-right: 0.75px solid #000 !important;"></td>
            <td style="border: none !important;border-top: 0.75px solid #000 !important; border-right: 0.75px solid #000 !important;" class="underline"></td>
            <td style="border: none !important;border-top: 0.75px solid #000 !important; border-right: 0.75px solid #000 !important;"></td>
            <td style="border: none !important;border-top: 0.75px solid #000 !important; "></td>
            <td style="border: none !important;border-top: 0.75px solid #000 !important; border-right: 0.75px solid #000 !important;"></td>
        </tr>
        @foreach(($tableRows ?? []) as $boxGroup)
            @php $rowCount = count($boxGroup['rows']); @endphp
            @foreach($boxGroup['rows'] as $index => $row)
                <tr>
                    @if($index === 0)
                        <td style="border: none !important; vertical-align: top;border-right: 0.75px solid #000 !important;" rowspan="{{ $rowCount }}">
                            {{ $boxGroup['boxNumber'] }}
                        </td>
                    @endif
                    <td style="border: none !important;border-right: 0.75px solid #000 !important;">{{ $row['description'] }}</td>
                    <td style="border: none !important; border-right: 0.75px solid #000 !important;" class="center">{{ $row['refArticle'] }}</td>
                    <td style="border-bottom: none !important; border-right: 0.75px solid #000 !important;border-top:none !important;" class="right">{{ !empty($row['isPairs']) ? $row['quantity'] . ' Pairs' : $row['quantity'] . ' Nos.' }}</td>
                    <td style="border-bottom: none !important; border-right: none !important; border-top:none !important;" class="center">
                        @if(($row['weight'] ?? null) !== null)
                            @php
                                $w = (float) $row['weight'];
                                $fw = rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.');
                            @endphp
                            {{ $fw }} kg
                        @endif
                    </td>
                    <td style="border: none !important;border-right: 0.75px solid #000 !important; border-left: none !important;"></td>
                </tr>
            @endforeach
        @endforeach
    @endif

        @for($i = 0; $i < ($fillerRows ?? 0); $i++)
            <tr class="filler-row">
                <td style="border-top: none !important; border-bottom: none !important;border-right: 0.75px solid #000 !important;">&nbsp;</td>
                <td style="border-top: none !important; border-bottom: none !important;border-right: 0.75px solid #000 !important;"></td>
                <td style="border-top: none !important; border-bottom: none !important; border-right: 0.75px solid #000 !important;"></td>
                <td style="border-top: none !important; border-bottom: none !important; border-right: 0.75px solid #000 !important;"></td>
                <td style="border-top: none !important; border-bottom: none !important;border-right: none !important; "></td>
                <td style="border-top: none !important; border-bottom: none !important;border-left: none !important;  border-right: 0.75px solid #000 !important;"></td>
            </tr>
        @endfor

        @php
            $st = $pageSubtotal ?? null;
            $stQty = $st !== null ? (int) ($st['qty'] ?? 0) : (int) ($totalQty ?? 0);
            $stW = $st !== null ? (float) ($st['weight'] ?? 0) : 0.0;
            $stWFmt = rtrim(rtrim(number_format($stW, 3, '.', ''), '0'), '.');
            if ($stWFmt === '') {
                $stWFmt = '0';
            }
            $stLabel = ! empty($isLast) ? 'Total' : 'Sub Total';
        @endphp
        <tr class="packing-subtotal-row">
            <td colspan="3" class="right bold">{{ $stLabel }}</td>
            <td class="right bold">{{ $stQty }}</td>
            <td class="center bold" style="border-right: none !important;">{{ $stWFmt }} kg</td>
            <td style="border-left: none !important;"></td>
        </tr>
    </tbody>
</table>
