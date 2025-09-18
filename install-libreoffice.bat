@echo off
echo ========================================
echo FlexiConvert - LibreOffice Installation
echo ========================================
echo.

echo This script will help you install LibreOffice for high-quality PDF conversions
echo LibreOffice provides the best PDF to Word conversion quality (similar to iLovePDF)
echo.

echo Step 1: Download LibreOffice
echo Please download LibreOffice from: https://www.libreoffice.org/download/download/
echo Choose "LibreOffice Fresh" for Windows (x64)
echo.

echo Step 2: Install LibreOffice
echo - Run the downloaded installer
echo - Use default installation settings
echo - Make sure to install to: C:\Program Files\LibreOffice
echo.

echo Step 3: Verify Installation
echo After installation, LibreOffice should be available at:
echo C:\Program Files\LibreOffice\program\soffice.exe
echo.

echo Step 4: Test LibreOffice Command Line
echo Open Command Prompt and run:
echo "C:\Program Files\LibreOffice\program\soffice.exe" --version
echo.

echo Step 5: Optional - Add to PATH
echo To use LibreOffice from anywhere, add this to your PATH:
echo C:\Program Files\LibreOffice\program
echo.

echo ========================================
echo Additional Tools for Better Conversion
echo ========================================
echo.

echo For even better PDF processing, you can also install:
echo.
echo 1. Poppler Utils (for pdftotext and pdfimages):
echo    Download from: https://github.com/oschwartz10612/poppler-windows/releases
echo    Extract to C:\poppler and add C:\poppler\bin to PATH
echo.
echo 2. Ghostscript (for PDF compression):
echo    Download from: https://www.ghostscript.com/download/gsdnld.html
echo    Install to default location
echo.

echo ========================================
echo Testing Your Installation
echo ========================================
echo.

echo After installing LibreOffice, test the conversion by:
echo 1. Upload a PDF file in FlexiConvert
echo 2. Use "PDF to Word" tool
echo 3. Check the log file for LibreOffice success messages
echo.

echo Log file location: storage/logs/laravel.log
echo Look for: "LibreOffice PDF to Word conversion successful"
echo.

pause
