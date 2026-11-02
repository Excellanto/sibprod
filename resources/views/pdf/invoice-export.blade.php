<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $invoiceHeading ?? 'EXPORT INVOICE' }}</title>
    <style>
        @page { size: A4; margin: 10mm; }

        body {
            font-family: Tahoma, Verdana, Arial, sans-serif;
            font-size: 8pt;
            letter-spacing: 0.5pt;
            margin: 0;
            padding: 0;
        }

        .main {
            width: 100%;
            border: 0.75px solid #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            padding: 3px;
            vertical-align: top;
        }

        .main > thead > tr > th,
        .main > tbody > tr > th,
        .main > tbody > tr > td {
            border: 0.5px solid #000;
        }

        .innerrow > th {
            font-weight: normal;
            border: none !important;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .title { font-size: 10pt; font-weight: bold; line-height: 1.1; letter-spacing: 1px; }
        .small { font-size: 6pt; line-height: 1.25; }
        .red { color: #c00000; font-weight: bold; }
        .no-break { page-break-inside: avoid; }
        .no-border { border: 0; }

        .header-country-of-origin-and-final-destination {
            padding: 0;
            vertical-align: bottom;
            border: 0;
            border-top: 1px solid #000;
        }

        .header-country-of-origin {
            text-align: center;
            border: 0;
            border-right: 1px solid #000;
        }

        .header-country-of-final-destination {
            text-align: center;
            border: 0;
        }

        .header-right-cell { padding: 0; vertical-align: top; }
        .header-right-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .header-right-table col.hdr-rt-lbl { width: 24%; }
        .header-right-table col.hdr-rt-val { width: 30%; }
        .header-right-table col.hdr-rt-tick { width: 7%; }
        .header-right-table col.hdr-rt-copy { width: 39%; }
        .header-right-table > tbody > tr > td {
            font-size: 6.5pt;
            padding: 2px 3px;
            border: 0.5px solid #000 !important;
            line-height: 1.25;
            vertical-align: middle;
        }
        .header-right-table .label { white-space: nowrap; }
        .header-right-table .value { font-weight: bold; }
        .header-right-table .invoice-no-date-cell {
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
        }
        .header-right-table .invoice-no-val {
            white-space: nowrap;
            font-size: 6.25pt;
            font-weight: bold;
            line-height: 1.25;
        }
        .header-right-table .tick-hd {
            text-align: center;
            font-weight: bold;
            font-size: 6pt;
        }
        .header-right-table .tick-cell { text-align: center; height: 14px; }
        .header-right-table .copy-kind {
            font-size: 6pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
        .header-consignee { min-height: 70px; }
        .header-buyer { min-height: 70px; }

        .invoice-lines {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        .invoice-lines th,
        .invoice-lines td {
            border: 0.5px solid #000;
            padding: 2px;
            vertical-align: top;
        }
        .invoice-lines thead th {
            font-weight: bold;
            text-align: center;
        }
        .invoice-lines .desc-col { text-align: left; max-width: 140px; word-wrap: break-word; }
        .invoice-lines .group-row td {
            font-weight: bold;
            background: #f5f5f5;
        }
        .invoice-lines .num { text-align: right; white-space: nowrap; }

        /* Middle invoice line table: compact, normal-weight headers (match commercial invoice) */
        table.main.invoice-items-table {
            font-size: 6pt;
            letter-spacing: 0.12pt;
            table-layout: fixed;
            border-collapse: collapse !important;
            border-spacing: 0;
            border: none !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        /* Narrow numeric block; more room for description + ref */
        table.main.invoice-items-table col.col-sr { width: 2%; }
        table.main.invoice-items-table col.col-hsn { width: 5%; }
        table.main.invoice-items-table col.col-desc { width: 26%; }
        table.main.invoice-items-table col.col-po-no { width: 12.6%; }
        table.main.invoice-items-table col.col-ref { width: 10.5%; }
        table.main.invoice-items-table col.col-qty { width: 5.5%; }
        table.main.invoice-items-table col.col-rate { width: 4.1%; }
        table.main.invoice-items-table col.col-amt { width: 6%; }
        table.main.invoice-items-table col.col-less { width: 5.1%; }
        table.main.invoice-items-table col.col-taxable { width: 6%; }
        table.main.invoice-items-table col.col-igst-rate { width: 4.1%; }
        table.main.invoice-items-table col.col-igst-amt { width: 4.3%; }
        table.main.invoice-items-table col.col-final { width: 8.5%; }
        table.main.invoice-items-table > thead > tr > th {
            font-weight: normal;
            font-size: 5.25pt;
            padding: 1px 2px;
            line-height: 1.15;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #000 !important;
        }
        table.main.invoice-items-table > thead > tr.invoice-thead-sub > th {
            border-top: none !important;
        }
        table.main.invoice-items-table > tbody > tr > td {
            font-size: 6pt;
            padding: 1px 2px;
            line-height: 1.2;
            vertical-align: top;
        }
        /*
         * Body: full-height verticals — each cell draws a 1px left rule; last cell adds right outer edge.
         * No row horizontal lines. (Works reliably in wkhtmltopdf vs. right-only / 0.5px borders.)
         */
        table.main.invoice-items-table > tbody > tr.line-item-row > td,
        table.main.invoice-items-table > tbody > tr.group-row > td {
            border-style: solid !important;
            border-color: #000 !important;
            border-width: 0 !important;
            border-left: 1px solid #000 !important;
            border-top: none !important;
            border-bottom: none !important;
            border-right: none !important;
        }
        table.main.invoice-items-table > tbody > tr.line-item-row > td:last-child,
        table.main.invoice-items-table > tbody > tr.group-row > td:last-child {
            border-right: 1px solid #000 !important;
        }
        /* Taller filler rows so padded pages better fill A4 below the header */
        table.main.invoice-items-table > tbody > tr.invoice-blank-line-row > td {
            height: 18px;
            min-height: 18px;
        }
        table.main.invoice-items-table .invoice-highlighter-text {
            display: inline-block;
            max-width: 100%;
            box-sizing: border-box;
            background: #fff566;
            padding: 2px 3px 4px 3px;
            text-align: left;
            font-size: 6pt;
            line-height: 1.25;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: top;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        /* “No line items” row: full grid */
        table.main.invoice-items-table > tbody > tr.invoice-empty-row > td {
            border: 1px solid #000 !important;
        }
        /* Keep description + PO tag inside this column (no bleed into Ref.) */
        table.main.invoice-items-table td.desc-col {
            overflow: hidden;
            max-width: 0;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.main.invoice-items-table td.desc-col.invoice-highlighter-desc {
            overflow: visible;
            vertical-align: top;
        }
        /* Nested layout inside description cell (leather name + PO tag) */
        table.main.invoice-items-table table.desc-tag-row {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.main.invoice-items-table table.desc-tag-row td {
            border: none !important;
            padding: 0;
            vertical-align: top;
            font-size: inherit;
            line-height: inherit;
            overflow: hidden;
        }
        table.main.invoice-items-table table.desc-tag-row .desc-text-cell {
            width: 72%;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.main.invoice-items-table table.desc-tag-row .desc-po-tag-cell {
            width: 28%;
            text-align: right;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            padding-left: 3px;
            box-sizing: border-box;
        }
        table.main.invoice-items-table tbody .num {
            text-align: right;
            white-space: nowrap;
        }
        table.main.invoice-items-table .invoice-bf-row td {
            font-weight: bold;
            border-bottom: 1px solid #000 !important;
        }
        table.main.invoice-items-table .group-row td:not(.group-title-cell) {
            font-weight: normal;
            text-decoration: none;
            text-transform: none;
            background: #fff;
            font-size: 6pt;
        }
        table.main.invoice-items-table .group-row td.group-title-cell {
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            text-align: left;
            background: #fff;
            font-size: 6pt;
        }

        /* Footer block under line items: full grid (horizontal + vertical borders) */
        table.main.invoice-items-table > tbody.invoice-table-footer > tr:not(.invoice-footer-shipping-row) > td {
            border: 1px solid #000 !important;
            font-size: 6pt;
            padding: 2px 3px;
            line-height: 1.2;
            vertical-align: top;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer > tr.invoice-footer-summary-row > td.invoice-footer-lbl {
            text-align: left;
            font-weight: normal;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer > tr.invoice-footer-grand-total-row > td {
            border-top: 2px solid #000 !important;
            border-right: 1px solid #000 !important;
            border-bottom: 1px solid #000 !important;
            border-left: 1px solid #000 !important;
            padding: 3px 3px !important;
            vertical-align: middle;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer > tr.invoice-footer-grand-total-row > td.invoice-footer-grand-lbl {
            font-weight: bold;
            vertical-align: middle;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer > tr.invoice-footer-grand-total-row > td.invoice-footer-grand-fill {
            border-top: 2px solid #000 !important;
            border-right: 1px solid #000 !important;
            border-bottom: 1px solid #000 !important;
            border-left: 1px solid #000 !important;
        }
        /* Nested shipping row: explicit column widths (independent of main 12-col grid) */
        table.main.invoice-items-table > tbody.invoice-table-footer td.invoice-footer-shipping-wrap {
            padding: 0 !important;
            vertical-align: top;
        }
        /*
         * Do not use the `border` shorthand on this row — wkhtmltopdf will not let
         * border-top:none override it, which draws a double line under Grand Total.
         */
        table.main.invoice-items-table > tbody.invoice-table-footer > tr.invoice-footer-shipping-row > td.invoice-footer-shipping-wrap {
            font-size: 6pt;
            line-height: 1.2;
            vertical-align: top;
            border-top-width: 0 !important;
            border-top-style: none !important;
            border-right: 1px solid #000 !important;
            border-bottom: 1px solid #000 !important;
            border-left: 1px solid #000 !important;
        }
        table.invoice-shipping-inner {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 6pt;
        }
        table.invoice-shipping-inner > tbody > tr > td {
            padding: 1px 3px;
            line-height: 1.15;
            vertical-align: top;
            height: 1px;
            border-top: none !important;
        }
        table.invoice-shipping-inner col.col-sf-marks { width: 15%; }
        table.invoice-shipping-inner col.col-sf-pkgs { width: 14%; }
        table.invoice-shipping-inner col.col-sf-buyer { width: 40%; }
        table.invoice-shipping-inner col.col-sf-tax { width: 31%; }
        table.main.invoice-items-table > tbody.invoice-table-footer td.invoice-footer-marks,
        table.main.invoice-items-table > tbody.invoice-table-footer td.invoice-footer-pkgs {
            vertical-align: top;
            border-right: 1px solid #000 !important;      
            text-align: center;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            font-size: 5.5pt;
            line-height: 1.15;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .mf-marks-hd {
            text-align: center;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .mf-marks-body {
            text-align: center;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .mf-subhd {
            font-weight: bold;
            margin-bottom: 1px;
            font-size: 5.5pt;
            line-height: 1.15;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .mf-body {
            font-size: 6pt;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .mf-pkgs-body {
            text-align: center;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer td.invoice-footer-buyer {
            text-align: left;
            vertical-align: top;
            border-right: 1px solid #000 !important;    
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .mf-buyer-hdr {
            font-weight: bold;
            margin-bottom: 1px;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .mf-buyer-text {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .mf-buyer-sep {
            border-top: 1px solid #000;
            margin: 2px 0;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .mf-buyer-remarks {
            min-height: 0;
            padding: 0;
            margin: 0;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer td.invoice-footer-tax-wrap {
            padding: 0 !important;
            vertical-align: top;
        }
        table.invoice-tax-mini {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 6pt;
        }
        table.invoice-tax-mini td {
            border-top: none !important;
            border-right: 1px solid #000 !important;
            border-bottom: 1px solid #000 !important;
            padding: 1px 3px;
            line-height: 1.1;
            vertical-align: middle;
        }
        table.invoice-tax-mini td:last-child {
            border-right: none !important;
        }
        table.invoice-tax-mini tr.invoice-tax-gst-row td {
            border-top: 1px solid #000 !important;
            border-bottom: 1px solid #000 !important;
        }
        table.invoice-tax-mini td.lbl {
            text-align: left;
            width: 62%;
        }
        table.invoice-tax-mini td.num {
            text-align: right;
            white-space: nowrap;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer > tr.invoice-footer-amount-words-row > td.invoice-footer-amount-words {
            text-align: left;
            vertical-align: middle;
            min-height: 18px;
            padding: 3px 4px;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .invoice-footer-amount-words-lbl {
            font-weight: bold;
            margin-right: 4px;
        }
        table.main.invoice-items-table > tbody.invoice-table-footer .invoice-footer-amount-words-val {
            font-weight: normal;
        }

        .totals-wrap {
            width: 100%;
            margin-top: 6px;
            page-break-inside: avoid;
        }
        .totals-table {
            width: 55%;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 8pt;
        }
        .totals-table td {
            border: 0.5px solid #000;
            padding: 4px 6px;
        }
        .totals-table .lbl { font-weight: bold; }
        .totals-table .amt { text-align: right; }

        /* Invoice PDF footer (C/O, export statement, EOU | features | signature, declaration) */
        table.invoice-pdf-footer {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
            break-inside: avoid;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        /* Mid & right each −20% vs prior (42%→33.6%, 28%→22.4%); left absorbs remainder → 44%. */
        table.invoice-pdf-footer col.invoice-footer-col-left { width: 44%; }
        table.invoice-pdf-footer col.invoice-footer-col-mid { width: 33.6%; }
        table.invoice-pdf-footer col.invoice-footer-col-right { width: 22.4%; }
        table.invoice-pdf-footer > tbody > tr > td {
            border: 1px solid #000;
            vertical-align: top;
        }
        table.invoice-pdf-footer td.invoice-footer-co-cell {
            text-align: right;
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0.3px;
            padding: 4px 8px;
            min-height: 16px;
            line-height: 1.2;
            border-top: none;
        }
        table.invoice-pdf-footer td.invoice-footer-statement-cell {
            font-size: 7.5pt;
            line-height: 1.3;
            padding: 5px 8px;
            text-align: left;
            border: 1px solid #000 !important;
            border-top: 1px solid #000 !important;
            vertical-align: top;
        }
        /* Keep empty statement box visible (top border + reserved height) in DomPDF. */
        table.invoice-pdf-footer td.invoice-footer-statement-cell.is-empty {
            height: 40px;
            min-height: 40px;
            padding: 5px 8px;
        }
        table.invoice-pdf-footer td.invoice-footer-statement-cell .invoice-footer-statement-spacer {
            display: block;
            height: 28px;
            line-height: 28px;
            font-size: 1px;
        }
        table.invoice-pdf-footer td.invoice-footer-statement-cell b,
        table.invoice-pdf-footer td.invoice-footer-statement-cell strong {
            font-weight: 700;
        }
        table.invoice-pdf-footer td.invoice-footer-left-col {
            font-weight: 700;
            line-height: 1.3;
            padding: 3px 4px 8px 4px;
            font-size: 8pt;
            vertical-align: top;
        }
        .invoice-footer-eou-hd {
            line-height: 1.3;
            margin: 0 0 2px 0;
            padding: 0;
        }
        table.invoice-pdf-footer td.invoice-footer-mid-col {
            line-height: 1.35;
            padding: 6px 6px;
            font-size: 8pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.invoice-pdf-footer td.invoice-footer-mid-col b,
        table.invoice-pdf-footer td.invoice-footer-mid-col strong {
            font-weight: 700;
        }
        table.invoice-pdf-footer td.invoice-footer-right-col {
            padding: 0;
            vertical-align: top;
        }
        .invoice-footer-sign-box {
            min-height: 72px;
            margin: 0;
            height: 100%;
            box-sizing: border-box;
        }
        .invoice-footer-sign-header {
            border-bottom: 1px solid #000;
            padding: 4px 6px;
            font-size: 8pt;
            line-height: 1.2;
        }
        .invoice-footer-sign-body {
            min-height: 44px;
        }
        table.invoice-pdf-footer td.invoice-footer-declaration-cell {
            font-size: 7pt;
            line-height: 1.3;
            text-align: left;
            border: 1px solid #000 !important;
            border-top: none !important;
            padding: 5px 8px;
            vertical-align: middle;
        }
        .invoice-footer-declaration-title {
            text-decoration: underline;
            font-weight: 700;
        }
        .invoice-footer-row-stats {
            font-size: 5.75pt;
            line-height: 1.35;
            page-break-inside: avoid;
            text-align: center;
            color: #222;
            border: 1px solid #000;
            border-top: none;
            padding: 5px 8px 6px;
        }
        .invoice-footer-row-stats-cap {
            color: #444;
        }

        /* Package-style summary: visible only on the final PDF page (FOC if present, else last invoice page) */
        .invoice-pkg-summary-wrap {
            width: 100%;
            margin-top: 0;
            page-break-inside: avoid;
        }
        .invoice-pkg-summary-wrap.is-placeholder {
            visibility: hidden;
        }
        .invoice-footer-left-col .invoice-pkg-summary-wrap {
            margin-top: 0;
            margin-bottom: 0;
            max-width: 100%;
        }
        .invoice-footer-left-col .invoice-pkg-summary-kv,
        .invoice-footer-left-col .invoice-pkg-summary-dim {
            max-width: 100%;
        }
        .invoice-pkg-summary-kv {
            width: 100%;
            max-width: 420px;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        .invoice-pkg-summary-kv td {
            padding: 1px 6px 1px 0;
            vertical-align: top;
            line-height: 1.3;
        }
        .invoice-pkg-summary-kv .inv-pkg-lbl {
            text-align: left;
            font-weight: normal;
            white-space: nowrap;
        }
        .invoice-pkg-summary-kv .inv-pkg-val {
            text-align: left;
            font-weight: bold;
        }
        .invoice-pkg-summary-dim {
            width: 100%;
            max-width: 420px;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin-top: 2px;
            margin-bottom: 8px;
        }
        .invoice-pkg-summary-dim td {
            padding: 1px 6px 1px 0;
            vertical-align: top;
            line-height: 1.3;
        }
        .invoice-pkg-summary-dim .inv-pkg-dim-legend {
            font-weight: normal;
        }
        .invoice-pkg-summary-dim .inv-pkg-dim-line {
            font-weight: bold;
        }
        .invoice-pkg-summary-dim .inv-pkg-dim-spacer {
            width: 1px;
        }

        /* Keep line items + totals + invoice footer + declaration on one PDF page (last page) */
        .invoice-pdf-page-break {
            page-break-before: always;
            break-before: page;
            height: 0;
            margin: 0;
            padding: 0;
            font-size: 0;
            line-height: 0;
        }
        .invoice-pdf-last-page-block {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* FOC page: nested table spans HSN + Description + Ref. Article columns */
        table.main.invoice-items-table.invoice-foc-page-table > tbody > tr.invoice-foc-embed-row > td {
            border-style: solid !important;
            border-color: #000 !important;
            border-width: 0 !important;
            border-left: 1px solid #000 !important;
            border-top: none !important;
            border-bottom: none !important;
            border-right: none !important;
            vertical-align: top;
        }
        table.main.invoice-items-table.invoice-foc-page-table > tbody > tr.invoice-foc-embed-row > td:last-child {
            border-right: 1px solid #000 !important;
        }
        /* Filler rows under FOC stretch the middle section toward the page footer */
        table.main.invoice-items-table.invoice-foc-page-table > tbody > tr.invoice-blank-line-row > td {
            height: 18px;
            min-height: 18px;
            border-style: solid !important;
            border-color: #000 !important;
            border-top: none !important;
            border-bottom: none !important;
            border-left: 1px solid #000 !important;
            border-right: 1px solid #000 !important;
        }
        /* Blank row between Brought Forward and the FOC table — verticals only, no bottom rule */
        table.main.invoice-items-table.invoice-foc-page-table > tbody > tr.invoice-foc-pre-embed-blank > td {
            height: 18px;
            min-height: 18px;
            border-top: none !important;
            border-bottom: none !important;
        }
        table.main.invoice-items-table .invoice-foc-embed-cell {
            padding: 0 !important;
            vertical-align: top;
            overflow: visible;
        }
        table.main.invoice-items-table.invoice-foc-page-table > tbody > tr.invoice-foc-embed-row + tr.invoice-blank-line-row + tr.invoice-foc-embed-row > td.invoice-foc-embed-cell {
            padding-top: 2px !important;
        }
        .invoice-foc-overlay-wrap {
            width: 100%;
            box-sizing: border-box;
        }
        .invoice-foc-overlay-inner {
            width: 100%;
            box-sizing: border-box;
        }
        table.main.invoice-items-table .invoice-foc-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif;
            font-size: 5.5pt;
            border: 2px solid #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table.main.invoice-items-table .invoice-foc-table th,
        table.main.invoice-items-table .invoice-foc-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            vertical-align: middle;
        }
        table.main.invoice-items-table .invoice-foc-title-row td {
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 5.75pt;
            letter-spacing: 0.2px;
            line-height: 1.2;
        }
        table.main.invoice-items-table .invoice-foc-head-row th {
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 5.25pt;
            line-height: 1.15;
        }
        table.main.invoice-items-table .invoice-foc-body .foc-col-code {
            text-align: center;
            font-size: 5.5pt;
        }
        table.main.invoice-items-table .invoice-foc-body .foc-col-desc {
            text-align: left;
            text-transform: none;
            font-size: 5.5pt;
            line-height: 1.25;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.main.invoice-items-table .invoice-foc-body .foc-col-be {
            text-align: center;
            font-size: 5.5pt;
        }
        table.main.invoice-items-table .invoice-foc-body .foc-col-qty,
        table.main.invoice-items-table .invoice-foc-body .foc-col-units,
        table.main.invoice-items-table .invoice-foc-body .foc-col-rate,
        table.main.invoice-items-table .invoice-foc-body .foc-col-amt {
            text-align: right;
            white-space: nowrap;
            font-size: 5.5pt;
        }
        table.main.invoice-items-table .invoice-foc-body .foc-col-units {
            text-align: center;
        }
        table.main.invoice-items-table .invoice-foc-bf-row .foc-col-desc {
            font-weight: bold;
        }
        table.main.invoice-items-table .invoice-foc-yellow-row td.foc-yellow-cell {
            background-color: #ffff00;
            font-weight: bold;
            text-align: left;
            font-size: 5.5pt;
            line-height: 1.25;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table.main.invoice-items-table .invoice-foc-total-row td {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 5.5pt;
            border-bottom: 0.5px solid #999 !important;
        }
        table.main.invoice-items-table .invoice-foc-total-row .foc-total-label {
            text-align: center;
        }
        table.main.invoice-items-table .invoice-foc-total-row .foc-total-qty,
        table.main.invoice-items-table .invoice-foc-total-row .foc-total-amt {
            text-align: right;
        }
        table.main.invoice-items-table .invoice-foc-disclaimer-row td {
            font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif;
            font-size: 5.75pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.2;
            border-top: 0.5px solid #999 !important;
            border-bottom: 2px solid #000 !important;
            border-left: 2px solid #000 !important;
            border-right: 2px solid #000 !important;
            padding: 3px 3px;
        }
    </style>
</head>
<body>
    @foreach($invoicePdfChunks as $chunk)
        @if(!empty($chunk['pageBreakBefore']))
            <div class="invoice-pdf-page-break">&nbsp;</div>
        @endif
        @if(!empty($chunk['isDocumentLast']))
            <div class="invoice-pdf-last-page-block">
        @endif
        @include('pdf.invoice-export.header', ['pageLabel' => $chunk['pageLabel']])
        @include('pdf.invoice-export.middle', [
            'printRows' => $chunk['rows'],
            'showMiddleFooter' => !empty($chunk['showMiddleFooter']),
            'showPageSubtotal' => !empty($chunk['showPageSubtotal']),
            'broughtForward' => $chunk['broughtForward'] ?? null,
            'broughtForwardLabel' => $chunk['broughtForwardLabel'] ?? null,
            'pageSubtotal' => $chunk['pageSubtotal'] ?? null,
        ])
        @include('pdf.invoice-export.footer', [
            'showPackageSummary' => !empty($chunk['isDocumentLast']),
            'customerReference' => $chunk['customerReference'] ?? '',
        ])
        @if(!empty($chunk['isDocumentLast']))
            </div>
        @endif
    @endforeach
    @foreach($focPdfChunks ?? [] as $focChunk)
        <div class="invoice-pdf-page-break">&nbsp;</div>
        @if(!empty($focChunk['isDocumentLast']))
            <div class="invoice-pdf-last-page-block">
        @endif
        @include('pdf.invoice-export.header', ['pageLabel' => $focChunk['pageLabel'] ?? ''])
        @include('pdf.invoice-export.middle-foc', [
            'focPrintRows' => $focChunk['printRows'] ?? [],
            'broughtForward' => $focChunk['broughtForward'] ?? null,
            'broughtForwardLabel' => $focChunk['broughtForwardLabel'] ?? null,
            'showMiddleFooter' => !empty($focChunk['showMiddleFooter']),
            'showPageSubtotal' => !empty($focChunk['showPageSubtotal']),
            'pageSubtotal' => $focChunk['pageSubtotal'] ?? null,
        ])
        @include('pdf.invoice-export.footer', [
            'customerReference' => $focChunk['customerReference'] ?? '',
            'showPackageSummary' => !empty($focChunk['isDocumentLast']),
        ])
        @if(!empty($focChunk['isDocumentLast']))
            </div>
        @endif
    @endforeach
</body>
</html>
