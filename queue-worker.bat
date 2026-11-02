@echo off
cd /d D:\WinNMP\WWW\sibrama
:loop
"D:\WinNMP\bin\PHP\64bit-php-8.3.20\php.exe" artisan queue:work database --queue=reports,default --sleep=3 --tries=1 --timeout=3600 --max-time=3600 --memory=1024 >> storage\logs\queue-stdout.log 2>&1
timeout /t 5 /nobreak >nul
goto loop
