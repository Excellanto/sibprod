<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Production Slip</title>
    <style>
        /* Page Setup */
        @page {
            size: A4;
            margin: 0;
        }
        @media print {
            body { margin: 0; -webkit-print-color-adjust: exact; }
            .page { margin: 0; width: 95%; height: 340mm; }
            * { page-break-inside: avoid; page-break-after: avoid; page-break-before: avoid; }
            table { page-break-inside: avoid; }
            tr { page-break-inside: avoid; }
            div { page-break-inside: avoid; }
        }

        /* General Reset */
        html, body {
            margin: 0;
            padding: 1%;
            width: 100%;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            color: #000;
            box-sizing: border-box;
            background: #fff;
        }

        .page {
            width: 100%; /* Full A4 width */
            padding: 1mm;
            margin: 0;
            position: relative;
            background: white;
            min-height: 350mm; /* Full A4 height */
            overflow: hidden;
        }

        /* Typography */
        h1 {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin: 0 0 1mm 0;
            text-transform: uppercase;
        }
        .header-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 2mm;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
        }

        td {
            border: 0.75pt solid #000;
            overflow: hidden;
            word-wrap: break-word;
            padding:3px;
            line-height:1.3;
            vertical-align: top;
        }
    
        /* Field Styling */
        .label {
            font-weight: bold;
            font-size: 12pt;
            display: inline-block;
        }
        .value {
            font-size: 11pt;
            font-weight: normal;
            display: inline-block;
        }
        .value-large {
            font-size: 11pt;
             display: inline-block;
            font-weight: bold;
        }

        /* Specific Utilities */
        .no-border-top { border-top: none; }
        .no-border-bottom { border-bottom: none; }
        .section-gap {
            height: 6mm;
            border-bottom: 1pt dashed #999;
            margin-bottom: 6mm;
            position: relative;
        }
        .section-gap::after {
            content: '✂';
            position: absolute;
            right: 0;
            bottom: -6pt;
            font-size: 11pt;
            background: white;
            padding-left: 5px;
        }

        /* Image Placeholder */
        .img-box {
            height: 35mm;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f0f0f0;
            color: #666;
            font-size: 8pt;
            border: 1px dashed #ccc;
            margin-top: 2pt;
        }
    </style>
</head>
<body>

<div class="page">
    <h1>SIBRAMA NEWAGE</h1>
    <div class="header-title">Production Slip</div>

<table style="width: 100%; margin: 1% 0; border-collapse: collapse; border:none;">
    <tr>
        <td style="width: 33%; font-size: 12px; vertical-align: top;  border: none;">
        <span class="value">   {{ $printNumber == 1 ? "Original Copy" : "DUPLICATE (No. $printNumber)" }}</span>
        </td>
        <td style="width: 33%; font-size: 12px; vertical-align: top; text-align: center;  border: none;">
        <span class="value">  <strong>Client Model: </strong> {{ $clientModel ?? '-' }} </span>
        </td>
        <td style="width: 34%; font-size: 12px; vertical-align: top; text-align: right;  border: none;">
           <span class="value"><strong> Client Name: </strong> {{ $clientName ?? '-' }}</span> </td>
    </tr>
    <tr>
        <td style="width: 33%; font-size: 12px; vertical-align: top; border: none; text-align: left;">
            <span class="value"><strong>OEM Ref: </strong>{{ $oemReference ?? '-' }}</span>
        </td>
        <td style="width: 33%; border: none;"></td>
        <td style="width: 34%; border: none;"></td>
    </tr>
</table>

    <table>
        <tr>
            <td width="33%">
                <span class="label">Print Date:</span>
                <span class="value">{{ $printDate }}</span>
            </td>
            <td width="34%">
                <span class="label">Ref No.:</span>
                <span class="value">{{ $refNumber ?? '-' }}</span>
            </td>
            <td width="33%">
                 <span class="label">{{ $deliveryDateLabel ?? 'Delivery Date' }}:</span>
                <span class="value">{{ $deliveryDate ?? '-' }}</span>
            </td>
        </tr>
    </table>

    <table class="no-border-top">
        <tr>
            <td width="33%">
                  <span class="label">Order id:</span>
                <span class="value">{{ $orderId ?? '-' }}</span>
            </td>
          
            <td width="40%">
                <span class="label">Ref Order.:</span>
                <span class="value">{{ $refOrder ?? '-' }}</span>
            </td>
            <td width="27%">
                <span class="label">Ps No.:</span>
                <span class="value">{{ $psNumber ?? '' }}</span>
            </td>
        </tr>

        <tr>
            <td colspan="4">
               
            <span class="label" style="display: inline-block; width:10%; vertical-align:top;">Special:</span>
                <span class="value" style="display: inline-block; width:60%;">
    @if(!empty($special))
        @foreach($special as $part)
            <div style="line-height: 1.2;">{{ $part }}</div>
        @endforeach
    @endif
    @if(empty($special)) *** @endif
</span>
            </td>
        </tr>

        <tr>
            <td width="35%">
                <span class="label">Lining:</span>
                <span class="value">{{ $lining ?? '-' }}</span>
            </td>
            <td width="35%">
                <span class="label">Leather:</span>
                <span class="value">{{ $leather ?? '-' }}</span>
            </td>
            <td width="30%">
                <span class="label">Colour:</span>
                <span class="value">{{ $colour ?? '-' }}</span>
            </td>
        </tr>

        <tr>
        <td width="25%">
                <span class="label">Quantity:</span>
                <span class="value">{{ $quantity ?? '-' }}</span>
            </td>
            <td width="25%">
                <span class="label">Width:</span>
                <span class="value">{{ $width ?? '-' }}</span>
            </td>
            <td width="50%">
                <span class="label">Strap Length:</span>
                <div style="display: flex; justify-content: space-between; width: 80%;">
                    <span><strong>Long:</strong> {{ $strapLengthLong ?? '-' }}</span>
                    <span><strong>Short:</strong> {{ $strapLengthShort ?? '-' }}</span>
                </div>
            </td>
        </tr>

        <tr>
            <td colspan="4">
            <div style="white-space:nowrap; font-size:0;">
   

    <span style="display:inline-block; margin-right:15mm; font-size:12px; padding-right: 20px; ">
        <span class="label">Buckle Type:</span>
        <span class="value">{{ $buckle ?? '---' }}</span>
    </span>

    <span style="display:inline-block; margin-right:15mm; font-size:12px; padding-left: 4px; border-left: 1px solid black;">
        <span class="label">Width:</span>
        <span class="value">{{ $buckleWidth ?? '' }}</span>
    </span>

    <span style="display:inline-block; margin-right:15mm; font-size:12px; padding-left: 4px; border-left: 1px solid black;">
        <span class="label">Gold:</span>
        <span class="value">{{ $gold ?? '0' }}</span>
    </span>

    <span style="display:inline-block; font-size:12px; padding-left: 4px; border-left: 1px solid black;">
        <span class="label">Silver:</span>
        <span class="value">{{ $silver ?? '0' }}</span>
    </span>
</div>

            </td>
        </tr>

        <tr>
          
                <table>
        <td width="55%" >
               <span class="label" style="display: inline-block; width:20%; vertical-align:top;">Design:</span>
            
                <span style="display:inline-block;">
                    {{ $design ?? '-' }}<br>
                    {{ $thickness ?? '-' }}
                </span>
           
            </td>
            <td width="45%">
                                <span style="display:block;">
                <span class="label" style="display:inline-block;vertical-align:top;">Loop Size: </span>
                <span>{{ $loopSize ?? '-' }}</span>
                </span>

            
                <span style="display:block;display: flex; gap: 10mm;">
                        <span style="display:inline-block; margin-right:15mm;"><strong>Big: </strong>{{ $bigLoop ?? '-' }}</span>
                        <span ><strong>Small: </strong>{{ $smallLoop ?? '0' }}</span>
                </span>
            </td>      
          
            </table>
        </tr>


        <tr>
            <table>
           <td width="55%">
           <div style="white-space:nowrap; font-size:11px;">

<!-- Header -->
<div style="display:block;">
    <div style="display:inline-block; width:65%; vertical-align:top;">
        <span class="label">Thread Colour:</span>
    </div>
    <div style="display:inline-block; width:35%; vertical-align:top;">
        <span class="label">Thread Size:</span>
    </div>
</div>

<!-- Top -->
<div style="display:block;">
    <div style="display:inline-block; width:80%; vertical-align:top;">
        <span class="value"><strong>Top: </strong>&nbsp; &nbsp;{{ $threadColourTop ?? '-' }}</span>
    </div>
    <div style="display:inline-block; width:20%; vertical-align:top;">
        <span class="value">{{ $threadSizeTop ?? '' }}</span>
    </div>
</div>

<!-- Bottom -->
<div style="display:block;">
    <div style="display:inline-block; width:80%; vertical-align:top;">
        <span class="value"><strong>Bottom: </strong>&nbsp; &nbsp;{{ $threadColourBottom ?? '-' }}</span>
    </div>
    <div style="display:inline-block; width:20%; vertical-align:top;">
        <span class="value">{{ $threadSizeBottom ?? '' }}</span>
    </div>
</div>

</div>



               
            </td>
            <td width="45%">
                            <table>
                                <tr style="border-bottom:1px solid #000;">
                                    <td style="border:none; border-collapse:collapse;">
                                <span class="label">Varnish:</span>
                                <span class="value">{{ $varnish ?? '-' }}</span>
                                </td>
                                </tr>
                                <tr>
                                <td style="border:none; border-collapse:collapse;">
                                <span class="label">Reinforcement:</span>
                                <span class="value">{{ $reinforcement ?? '-' }}</span>
                                </td>
                            </tr>
                                </table>
            </td>
            </table>
        </tr>
        <tr style="border:none; border-collapse:collapse;">
            <table>
            <td colspan="4" >
                <span class="label" style="display: inline-block; width:15%; vertical-align:top;">Stamping:</span>
            
                <span style="display:inline-block; ">
                <strong>Short:</strong> {{ $stampingShort ?? '-' }}<br>
                <strong>Long:</strong> {{ $stampingLong ?? '-' }}
                </span>
        
            </td>
            </table>
        </tr>
        <tr style="border:none; border-collapse:collapse;">
            <table>
            <td colspan="4" >
                <span class="label" style="display: inline-block; width:10%; vertical-align:top;">Notes:</span>
            
                <span style="display:inline-block; ">
                @if(!empty($notes))
                        @foreach($notes as $note)
                            <div style="line-height: 1.2;">{{ $note }}</div>
                        @endforeach
                    @endif
                    @if(empty($notes)) *** @endif
               
                </span>
        
            </td>
            </table>
        </tr>
   

        <tr style="border:none; border-collapse:collapse;">
        <table>
            <td colspan="4">
                <span class="label">Picture:</span>
                @if(isset($picturePath) && $picturePath)
                    <div style="text-align:center; padding: 5pt;">
                        <img src="{{ $picturePath }}" style="max-height: 60mm; object-fit:contain; width: 100%; height: auto;" alt="Strap Picture">
                    </div>
                    <div style="margin-top: 4pt; text-align: center; font-size: 12pt;">
                        {{ $pictureFileName ?? 'No picture' }}
                    </div>
                @else
                    <div class="img-box">
                        [Technical Diagram Placeholder]
                    </div>
                @endif
             
            </td>
            </table>
        </tr>

        <tr>
            <table>
            <td colspan="4">
                <span class="label">First Cutting Tool:</span>
                <span class="value">{{ $firstCuttingTool ?? '---' }}</span>
            </td>
            </table>
        </tr>
        <tr>
            <table>
            <td colspan="4">
                <span class="label">Inside Cutting Tool:</span>
                <span class="value">{{ $insideCuttingTool ?? '---' }}</span>
            </td>
            </table>
        </tr>
    </table>

    <div class="section-gap"></div>


    <table style="width: 100%; margin: 1% 0; border-collapse: collapse; border:none;">
    <tr>
        <td style="width: 33%; font-size: 12px; vertical-align: top;  border: none; text-align: left;">
        <span class="value">   {{ $printNumber == 1 ? "Original Copy" : "DUPLICATE (No. $printNumber)" }}</span>
        </td>
        <td style="width: 33%; font-size: 12px; vertical-align: top; text-align: center;  border: none;">
        <span class="value">  <strong>Client Model: </strong>{{ $clientModel ?? '-' }} </span>
        </td>
        <td style="width: 34%; font-size: 12px; vertical-align: top; text-align: right;  border: none;">
           <span class="value"> <strong>Client Name: </strong>{{ $clientName ?? '-' }}</span> </td>
    </tr>
    <tr>
        <td style="width: 33%; font-size: 12px; vertical-align: top; border: none; text-align: left;">
            <span class="value"><strong>OEM Ref: </strong>{{ $oemReference ?? '-' }}</span>
        </td>
        <td style="width: 33%; border: none;"></td>
        <td style="width: 34%; border: none;"></td>
    </tr>
</table>    

    

    <table class="no-border-top">
        <tr>
            <td width="33%">
                <span class="label">Print Date:</span>
                <span class="value">{{ $printDate }}</span>
            </td>
            <td width="34%">
                <span class="label">Ref No.:</span>
                <span class="value">{{ $refNumber ?? '-' }}</span>
            </td>
            <td width="33%">
                 <span class="label">{{ $deliveryDateLabel ?? 'Delivery Date' }}:</span>
                <span class="value">{{ $deliveryDate ?? '-' }}</span>
            </td>
        </tr>

        <tr>
            <td width="33%">
                <span class="label">Order ID:</span>
                <span class="value">{{ $orderId ?? '-' }}</span>
            </td>
            <td width="34%">
                <span class="label">Ref Order.:</span>
                <span class="value">{{ $refOrder ?? '-' }}</span>
            </td>
            <td width="33%">
                 <span class="label">PS No:</span>
                <span class="value">{{ $psNumber ?? '' }}</span>
            </td>
        </tr>

        <tr>
            <td colspan="4">
                <span class="label">Quantity:</span>
                <span class="value">{{ $quantity ?? '-' }}</span>
            </td>
        </tr>
        <tr>
            <td width="33%">
                <span class="label">Thread Colour:</span>
                {{ $threadColourTop ?? '-' }}
            </td>
            <td width="33%">
                <span class="label">Leather:</span>
                <span class="value">{{ $leather ?? '-' }}</span>
            </td>
            
            <td width="33%">
                <span class="label">Colour:</span>
                <span class="value">{{ $colour ?? '-' }}</span>
            </td>
        </tr>

        <tr>
             <td width="33%">
                            <table>
                                <tr style="border-bottom:1px solid #000;">
                                    <td style="border:none; border-collapse:collapse;">
                                <span class="label">Loop Cutting:</span>
                                <span class="value">{{ $loopCuttingTool ?? '---' }}</span>
                                </td>
                                </tr>
                                <tr>
                                <td style="border:none; border-collapse:collapse;">
                                <span class="label">Loop Folding:</span>
                                <span class="value">{{ $loopFoldingTool ?? '---' }}</span>
                                </td>
                            </tr>
                                </table>
                
            </td>
            <td width="33%">
                            <table>
                                <tr>
                                    <td style="border:none; border-collapse:collapse;">
                                <span class="label">Strap Width:</span>
                                <span class="value">{{ $width ?? '' }}</span>
                                </td>
                                </tr>
                                <tr>
                                <td style="border:none; border-collapse:collapse;">
                                <span class="label">Buckle Width:</span>
                                <span class="value">{{ $buckleWidth ?? '' }}</span>
                                </td>
                            </tr>
                                </table>
                
            </td>
             <td width="33%">
             <span style="display:block;">
                <span class="label" style="display:inline-block;vertical-align:top;">Loop Size: </span>
                <span>{{ $loopSize ?? '-' }}</span>
                </span>

                
                <span style="display:block;">Big: {{ $bigLoop ?? '-' }}</span>
                        <span style="display:block;">Small: {{ $smallLoop ?? '0' }}</span>
                </span>
            
            </td>
        </tr>       
        <tr>
            <td width="40%">
                <span class="label">Remark:</span>
               
            </td>
            <td width="40%" >
               <span class="label" style="display: inline-block; width:25%; vertical-align:top;">Design:</span>
            
                <span style="display:inline-block; width:70%;">
                    {{ $design ?? '-' }}<br>
                    {{ $thickness ?? '-' }}
                </span>
           
            </td>
            
            <td width="20%">
                <span class="label">Varnish:</span>
                <span class="value">{{ $varnish ?? '-' }}</span>
            </td>
        </tr>
    </table>
</div>
</body>
</html>