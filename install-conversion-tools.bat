@echo off
echo Installing conversion tools for FlexiConvert...
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% == 0 (
    echo Running as administrator - Good!
) else (
    echo Please run this script as administrator
    pause
    exit /b 1
)

echo.
echo === Installing LibreOffice (for Office document conversion) ===
echo Downloading LibreOffice...

REM Create temp directory
if not exist "%TEMP%\flexiconvert-install" mkdir "%TEMP%\flexiconvert-install"
cd /d "%TEMP%\flexiconvert-install"

REM Download LibreOffice
powershell -Command "Invoke-WebRequest -Uri 'https://download.libreoffice.org/libreoffice/stable/7.6.4/win/x86_64/LibreOffice_7.6.4_Win_x64.msi' -OutFile 'LibreOffice.msi'"

if exist "LibreOffice.msi" (
    echo Installing LibreOffice...
    msiexec /i "LibreOffice.msi" /quiet /norestart
    echo LibreOffice installed successfully!
) else (
    echo Failed to download LibreOffice. Please install manually from https://www.libreoffice.org/
)

echo.
echo === Installing Poppler (for PDF to image conversion) ===
echo Downloading Poppler...

REM Download Poppler
powershell -Command "Invoke-WebRequest -Uri 'https://github.com/oschwartz10612/poppler-windows/releases/download/v23.01.0-0/Release-23.01.0-0.zip' -OutFile 'poppler.zip'"

if exist "poppler.zip" (
    echo Extracting Poppler...
    powershell -Command "Expand-Archive -Path 'poppler.zip' -DestinationPath 'C:\Program Files\poppler' -Force"
    
    REM Add to PATH
    setx PATH "%PATH%;C:\Program Files\poppler\Library\bin" /M
    echo Poppler installed successfully!
) else (
    echo Failed to download Poppler. Please install manually.
)

echo.
echo === Installing wkhtmltopdf (for HTML to PDF conversion) ===
echo Downloading wkhtmltopdf...

REM Download wkhtmltopdf
powershell -Command "Invoke-WebRequest -Uri 'https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-2/wkhtmltox-0.12.6.1-2.msvc2019-win64.exe' -OutFile 'wkhtmltopdf.exe'"

if exist "wkhtmltopdf.exe" (
    echo Installing wkhtmltopdf...
    wkhtmltopdf.exe /S
    echo wkhtmltopdf installed successfully!
) else (
    echo Failed to download wkhtmltopdf. Please install manually.
)

echo.
echo === Installing ImageMagick (for image conversion) ===
echo Downloading ImageMagick...

REM Download ImageMagick
powershell -Command "Invoke-WebRequest -Uri 'https://imagemagick.org/archive/binaries/ImageMagick-7.1.1-21-Q16-HDRI-x64-dll.exe' -OutFile 'imagemagick.exe'"

if exist "imagemagick.exe" (
    echo Installing ImageMagick...
    imagemagick.exe /SILENT
    echo ImageMagick installed successfully!
) else (
    echo Failed to download ImageMagick. Please install manually.
)

echo.
echo === Installing Ghostscript (for PDF processing) ===
echo Downloading Ghostscript...

REM Download Ghostscript
powershell -Command "Invoke-WebRequest -Uri 'https://github.com/ArtifexSoftware/ghostpdl-downloads/releases/download/gs10021/gs10021w64.exe' -OutFile 'ghostscript.exe'"

if exist "ghostscript.exe" (
    echo Installing Ghostscript...
    ghostscript.exe /S
    echo Ghostscript installed successfully!
) else (
    echo Failed to download Ghostscript. Please install manually.
)

echo.
echo === Installation Complete ===
echo All conversion tools have been installed!
echo Please restart your command prompt or reboot your system for PATH changes to take effect.
echo.
echo Installed tools:
echo - LibreOffice: For Word/PowerPoint/Excel to PDF conversion
echo - Poppler: For PDF to image conversion
echo - wkhtmltopdf: For HTML to PDF conversion
echo - ImageMagick: For image processing and conversion
echo - Ghostscript: For PDF processing and compression
echo.

REM Cleanup
cd /d "%USERPROFILE%"
rmdir /s /q "%TEMP%\flexiconvert-install"

echo Installation script completed!
pause
