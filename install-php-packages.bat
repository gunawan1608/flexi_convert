@echo off
echo Installing PHP packages for document conversion...
echo.

echo Installing PhpOffice packages...
composer require phpoffice/phpword --no-interaction
composer require phpoffice/phpspreadsheet --no-interaction

echo Installing PDF processing packages...
composer require smalot/pdfparser --no-interaction
composer require mpdf/mpdf --no-interaction
composer require dompdf/dompdf --no-interaction

echo Installing additional conversion tools...
composer require tecnickcom/tcpdf --no-interaction

echo.
echo Installation complete!
echo.
echo Optimizing Laravel...
php artisan optimize:clear
php artisan config:cache
php artisan route:cache

echo.
echo All packages installed successfully!
pause
