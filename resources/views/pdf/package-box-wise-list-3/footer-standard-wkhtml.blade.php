<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Tahoma, Verdana, Arial, sans-serif;
            font-size: 8pt;
            letter-spacing: 0.5pt;
        }
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
    </style>
    <script>
        function subst() {
            var vars = {};
            var query_strings_from_url = document.location.search.substring(1).split('&');
            for (var i = 0; i < query_strings_from_url.length; i++) {
                var temp_var = query_strings_from_url[i].split('=', 2);
                vars[temp_var[0]] = decodeURIComponent(temp_var[1]);
            }
            var css_selector_classes = ['page', 'frompage', 'topage', 'webpage', 'section', 'subsection', 'date', 'isodate', 'time', 'title', 'doctitle', 'sitepage', 'sitepages'];
            for (var k = 0; k < css_selector_classes.length; k++) {
                var key = css_selector_classes[k];
                var element = document.getElementsByClassName(key);
                for (var j = 0; j < element.length; ++j) {
                    element[j].textContent = vars[key] || '';
                }
            }
            var el = document.getElementById('std-footer');
            var p0 = document.getElementsByClassName('page')[0];
            var t0 = document.getElementsByClassName('topage')[0];
            var p = p0 ? parseInt(p0.textContent, 10) : parseInt(vars['page'], 10);
            var t = t0 ? parseInt(t0.textContent, 10) : parseInt(vars['topage'], 10);
            if (el && !isNaN(p) && !isNaN(t) && p === t) {
                el.style.display = 'none';
            } else if (el) {
                el.style.display = 'block';
            }
        }
    </script>
</head>
<body onload="subst()">
    <div id="std-footer" style="display: block;">
        <span class="page" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"></span>
        <span class="topage" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"></span>
        @include('pdf.package-box-wise-list-3.footer-standard-inner')
    </div>
</body>
</html>
