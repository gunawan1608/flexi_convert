@echo off
echo Installing PHP packages for FlexiConvert conversion functionality...
echo.

echo Installing PDF processing packages...
composer require dompdf/dompdf
composer require setasign/fpdi
composer require setasign/fpdf

echo.
echo Installing Office document packages...
composer require phpoffice/phpword
composer require phpoffice/phpspreadsheet  
composer require phpoffice/phppresentation

echo.
echo Installing PDF parsing package...
composer require smalot/pdfparser

echo.
echo Installing image processing (optional - requires ImageMagick extension)...
echo Note: ImageMagick PHP extension must be installed separately
echo For Windows: Download from https://windows.php.net/downloads/pecl/releases/imagick/
echo For Linux: sudo apt-get install php-imagick

echo.
echo Installing additional utility packages...
composer require intervention/image

echo.
echo Installation complete!
echo.
echo Required PHP extensions:
echo - zip (for creating ZIP archives)
echo - gd or imagick (for image processing)
echo - fileinfo (for MIME type detection)
echo.
echo Optional software for enhanced conversions:
echo - LibreOffice (for high-quality Office to PDF conversion)
echo - Ghostscript (for PDF compression and optimization)
echo.
pause
