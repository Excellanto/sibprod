<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PACKING LIST</title>
    <style>
        @page { size: A4; margin: 10mm; }

        body{
            font-family: Tahoma, Verdana, Arial, sans-serif;
            font-size: 8pt;
            letter-spacing: 0.5pt;
            margin: 0;
            padding: 0;
        }

        .main{
            width: 100%;
            border: 0.75px solid #000;
        }

        table{
            width: 100%;
            border-collapse: collapse;
        }

        td,th{
            padding: 3px;
            vertical-align: top;
        }

        /* Apply borders only to direct cells of .main tables */
        .main > thead > tr > th,
        .main > tbody > tr > th,
        .main > tbody > tr > td {
            border: 0.5px solid #000;
        }
        /* Hide divider between Remarks and the narrow trailing column (items table only) */
        .main.packing-items > thead > tr > th:nth-last-child(2),
        .main.packing-items > tbody > tr > td:nth-last-child(2) {
            border-right: none !important;
        }
        .main.packing-items > thead > tr > th:last-child,
        .main.packing-items > tbody > tr > td:last-child {
            border-left: none !important;
        }
        /* Keep center vertical divider on shipping / terms header row */
        .main > tbody > tr > td.header-ship-left {
            border-right: 0.75px solid #000 !important;
        }
        .main > tbody > tr > td.header-terms-right {
            border-left: none !important;
            border-top: 1px solid #000 !important;
            border-right: 0.5px solid #000 !important;
            border-bottom: 0.5px solid #000 !important;
        }
        .innerrow> th{ font-weight: normal;     border: none !important;}
.underline{ text-decoration: underline; }
.filler-row td{ height: 20px; padding: 0 !important; }
        .center{ text-align: center; }
        .right{ text-align: right; }
        .bold{ font-weight: bold; }
        .title{ font-size: 10pt; font-weight: bold; line-height: 1.1; letter-spacing: 1px;}
        .small{ font-size: 6pt; line-height: 1.25; }
        .red{ color:#c00000; font-weight: bold; }
        .no-break{ page-break-inside: avoid; }
        .no-border{ border:0px; }
.header-country-of-origin-and-final-destination{padding:0px; vertical-align: bottom;border:0px; border-top:1px solid #000;}
.header-country-of-origin{text-align: center;border:0px;border-right:1px solid #000;}
.header-country-of-final-destination{text-align: center;border:0px;}

        .header-right-cell{ padding: 0; }
        .header-right-table td{ font-size: 7.5pt; padding: 1px 2px; border: 0; line-height:1.3;}
        .header-right-table table td,
        .header-right-table table th { border: 0 !important; }
        .header-right-table .label{ white-space: nowrap; }
        .header-right-table .value{ font-weight: bold; }
        .header-right-table .top-left{ width: 70%; }
        .header-right-table .top-right{ width: 30%; }
        .header-right-table .top-right{ text-align: left; letter-spacing: normal; word-spacing: normal; }
        .header-right-table .orders{ height: 64px; border-bottom: 1px solid #000; }
        .header-right-top{ border-bottom: 1px solid #000; }
        .header-right-left{ border-right: 1px solid #000 !important; }
        .header-right-orders{ border-bottom: 1px solid #000; }
      .header-consignee{height: 70px;}
      .header-buyer{height: 70px;}
        .footer-box {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .footer-box td {
            border: 1px solid #000;
            vertical-align: top;
            padding: 6px 8px;
        }

        .top-row td {
            border-bottom: 1px solid #000;
            padding: 2px 8px;
            height: 18px;
            font-weight: 600;
            font-size: 12px;
        }

        .footer-top-right {
            text-align: right;
            letter-spacing: 0.3px;
           
        }

        .left-col {
            font-weight: 700;
            line-height: 1.8;
            padding-top: 14px;
        }

        .mid-col {
            line-height: 1.5;
            padding-top: 14px;
            font-size: 11px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .right-col {
            padding: 0;
        }

        .sign-box {
            min-height: 95px;
        }

        .sign-header {
            border-bottom: 1px solid #000;
            padding: 6px 8px;
            font-size: 11px;
            min-height: 18px;
        }

        /* Last-page-only footer: title row + 3 cols (summary | legal | signature) */
        .footer-last-wrap {
            width: 100%;
            margin-top: 0;
            font-size: 8pt;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .footer-last-outer {
            width: 100%;
            border: 1.5px solid #000;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .footer-last-outer > tbody > tr > td {
            border: 0.5px solid #000;
            vertical-align: top;
        }
        .footer-last-title-cell {
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            font-size: 9pt;
            padding: 5px 8px 6px 8px;
            border-bottom: 1px solid #000 !important;
            white-space: nowrap;
        }
        .footer-last-left {
            width: 45%;
            padding: 6px 8px 16px 8px;
            vertical-align: top;
        }
        .footer-last-mid {
            padding: 8px;
        }
        .footer-last-subtitle {
            font-weight: bold;
            margin: 0 0 2px 0;
            line-height: 1.25;
        }
        .footer-last-kv {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 0;
        }
        .footer-last-kv-label-col {
            width: 12.5em;
        }
        .footer-last-kv td {
            padding: 1px 2px 2px 0;
            vertical-align: top;
        }
        .footer-last-label {
            font-weight: normal;
            white-space: nowrap;
            padding-right: 12px;
            width: 12.5em;
        }
        .footer-last-val {
            font-weight: bold;
            padding-left: 4px;
        }
        .footer-last-dim-rows {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 4px;
            margin-bottom: 8px;
        }
        .footer-last-dim-rows tr {
            height: 1.15em;
        }
        .footer-last-dim-rows td {
            padding: 0 2px 1px 0;
            vertical-align: top;
            line-height: 1.15;
        }
        .footer-last-dim-legend,
        .footer-last-dim-legend-spacer {
            font-weight: normal;
            white-space: nowrap;
            width: 12.5em;
            padding-right: 12px;
        }
        .footer-last-dim-legend-spacer {
            border: 0;
        }
        .footer-last-dim-line {
            font-weight: bold;
            padding-left: 4px;
        }
        .footer-last-regulatory {
            line-height: 1.45;
            font-size: 8pt;
            word-wrap: break-word;
        }
        .footer-last-sign-col {
            padding: 0;
            width: 20%;
        }
        .packing-pdf-page-break {
            page-break-before: always;
            break-before: page;
            height: 0;
            margin: 0;
            padding: 0;
            font-size: 0;
            line-height: 0;
        }
        .packing-bf-row td,
        .packing-subtotal-row td {
            font-weight: bold;
            border-top: 0.75px solid #000 !important;
        }
    </style>
</head>
<body>
    @forelse(($packingPdfPages ?? []) as $page)
        @if(!empty($page['pageBreakBefore']))
            <div class="packing-pdf-page-break">&nbsp;</div>
        @endif
        @include('pdf.package-box-wise-list-3.header', ['pageLabel' => $page['pageLabel'] ?? $pageLabel ?? ''])
        @include('pdf.package-box-wise-list-3.middle', [
            'tableRows' => $page['tableRows'] ?? [],
            'palletSections' => $page['palletSections'] ?? [],
            'usePallets' => $page['usePallets'] ?? $usePallets ?? false,
            'showCorrugatedHeader' => $page['showCorrugatedHeader'] ?? true,
            'broughtForward' => $page['broughtForward'] ?? null,
            'pageSubtotal' => $page['pageSubtotal'] ?? null,
            'fillerRows' => $page['fillerRows'] ?? 0,
            'isLast' => ! empty($page['isLast']),
        ])
        @if(!empty($page['isLast']))
            @include('pdf.package-box-wise-list-3.footer-last')
        @else
            @include('pdf.package-box-wise-list-3.footer')
        @endif
    @empty
        @include('pdf.package-box-wise-list-3.header')
        @include('pdf.package-box-wise-list-3.middle')
        @include('pdf.package-box-wise-list-3.footer-last')
    @endforelse
</body>
</html>
