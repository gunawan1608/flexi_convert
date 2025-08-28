# FFmpeg Installation Guide for Windows

## Overview
FFmpeg is required for audio and video conversion in FlexiConvert. Without FFmpeg, the system will only copy files without actual format conversion.

## Installation Steps

### Method 1: Direct Download (Recommended)

1. **Download FFmpeg**
   - Go to https://ffmpeg.org/download.html
   - Click "Windows" → "Windows builds by BtbN"
   - Download the latest release (ffmpeg-master-latest-win64-gpl.zip)

2. **Extract Files**
   - Extract the downloaded ZIP file
   - Copy the extracted folder to `C:\ffmpeg\`
   - The structure should be: `C:\ffmpeg\bin\ffmpeg.exe`

3. **Add to System PATH**
   - Press `Win + R`, type `sysdm.cpl`, press Enter
   - Click "Environment Variables"
   - Under "System Variables", find and select "Path"
   - Click "Edit" → "New"
   - Add: `C:\ffmpeg\bin`
   - Click "OK" on all dialogs

4. **Verify Installation**
   - Open Command Prompt (cmd)
   - Type: `ffmpeg -version`
   - You should see FFmpeg version information

### Method 2: Using Chocolatey

```powershell
# Install Chocolatey first (if not installed)
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))

# Install FFmpeg
choco install ffmpeg
```

### Method 3: Using Winget

```powershell
winget install Gyan.FFmpeg
```

## Restart Required

After installation, restart your web server:
- Stop Laravel development server (`Ctrl+C`)
- Restart with: `php artisan serve`

## Verification

Test audio conversion in FlexiConvert:
1. Upload an MP3 file
2. Convert to AAC format
3. Check that the output is actually in AAC format (not just renamed MP3)

## Troubleshooting

**FFmpeg not found after installation:**
- Verify PATH contains `C:\ffmpeg\bin`
- Restart Command Prompt/PowerShell
- Restart web server

**Permission errors:**
- Run Command Prompt as Administrator
- Check antivirus software isn't blocking FFmpeg

**Audio conversion still fails:**
- Check Laravel logs for specific error messages
- Ensure input file is not corrupted
- Try with a smaller test file first

## Supported Conversions

With FFmpeg installed, FlexiConvert supports:
- **Audio**: MP3 ↔ AAC, WAV, FLAC, OGG
- **Video**: MP4 ↔ AVI, MKV, MOV, WebM
- **Video to GIF**: Any video format to animated GIF
- **Audio Enhancement**: Compression, noise reduction

## File Size Limits

- Audio files: 100MB maximum
- Video files: 500MB maximum
- Processing time varies based on file size and conversion complexity
