# FlexiConvert Installation Guide

## System Requirements

### PHP Requirements
- PHP 8.1 or higher
- Required PHP extensions:
  - `php-gd` (image processing)
  - `php-zip` (archive handling)
  - `php-xml` (XML processing)
  - `php-mbstring` (multibyte string support)
  - `php-curl` (HTTP requests)
  - `php-fileinfo` (file type detection)
  - `php-imagick` (advanced image processing)

### System Dependencies

#### LibreOffice (Recommended for Office conversions)
**Windows:**
```bash
# Download and install LibreOffice from https://www.libreoffice.org/
# Default installation path: C:\Program Files\LibreOffice\program\soffice.exe
```

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install libreoffice
```

**CentOS/RHEL:**
```bash
sudo yum install libreoffice
```

**macOS:**
```bash
brew install --cask libreoffice
```

#### Ghostscript (Recommended for PDF compression)
**Windows:**
```bash
# Download from https://www.ghostscript.com/download/gsdnld.html
# Install to default location or set GHOSTSCRIPT_PATH in .env
```

**Ubuntu/Debian:**
```bash
sudo apt install ghostscript
```

**CentOS/RHEL:**
```bash
sudo yum install ghostscript
```

**macOS:**
```bash
brew install ghostscript
```

### Database
- MySQL 8.0+ or MariaDB 10.3+
- PostgreSQL 13+ (alternative)
- SQLite 3.8+ (development only)

## Installation Steps

### 1. Clone Repository
```bash
git clone <repository-url> flexiconvert
cd flexiconvert
```

### 2. Install PHP Dependencies
```bash
composer install
```

### 3. Install Node.js Dependencies
```bash
npm install
```

### 4. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

### 5. Configure Database
Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flexiconvert
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 6. Configure PDF Tools
Edit `.env` file for PDF tools settings:
```env
# Office to PDF conversion engine
ENGINE_OFFICE_TO_PDF=libreoffice

# Optional: Override executable paths
# LIBREOFFICE_PATH=/usr/bin/libreoffice
# GHOSTSCRIPT_PATH=/usr/bin/gs

# File cleanup retention (hours)
CLEANUP_RETENTION_HOURS=24

# Logging
LOG_CONVERSIONS=true
LOG_PERFORMANCE_METRICS=true

# File size limits (50MB default)
MAX_FILE_SIZE=52428800
MAX_TOTAL_SIZE=104857600

# Processing timeouts
LIBREOFFICE_TIMEOUT=60
GHOSTSCRIPT_TIMEOUT=60

# Default compression quality
DEFAULT_COMPRESSION_QUALITY=medium
```

### 7. Run Database Migrations
```bash
php artisan migrate
```

### 8. Create Storage Directories
```bash
php artisan storage:link
mkdir -p storage/app/pdf-tools/uploads
mkdir -p storage/app/pdf-tools/outputs
chmod -R 775 storage/app/pdf-tools
```

### 9. Install Conversion Packages
Run the provided installation scripts:

**Windows:**
```bash
./install-php-packages.bat
./install-conversion-tools.bat
```

**Linux/macOS:**
```bash
composer require phpoffice/phpword phpoffice/phpspreadsheet phpoffice/phppresentation
composer require mpdf/mpdf dompdf/dompdf tecnickcom/tcpdf
composer require smalot/pdfparser setasign/fpdi symfony/process
```

### 10. Build Frontend Assets
```bash
npm run build
```

### 11. Configure Queue Worker (Production)
```bash
# Add to supervisor or systemd
php artisan queue:work --daemon
```

### 12. Schedule File Cleanup (Production)
Add to crontab:
```bash
# Clean up old files daily at 2 AM
0 2 * * * cd /path/to/flexiconvert && php artisan files:cleanup
```

## Verification

### Test LibreOffice Integration
```bash
php artisan tinker
```
```php
$helper = new App\Http\Controllers\PDFToolsHelperMethods();
$libreoffice = $helper->findLibreOffice();
echo $libreoffice ? "LibreOffice found: $libreoffice" : "LibreOffice not found";
```

### Test Ghostscript Integration
```bash
php artisan tinker
```
```php
$helper = new App\Http\Controllers\PDFToolsHelperMethods();
$ghostscript = $helper->findGhostscript();
echo $ghostscript ? "Ghostscript found: $ghostscript" : "Ghostscript not found";
```

### Run Tests
```bash
php artisan test --filter PDFToolsTest
```

## Production Deployment

### 1. Optimize Application
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

### 2. Set Production Environment
```env
APP_ENV=production
APP_DEBUG=false
```

### 3. Configure Web Server

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name flexiconvert.example.com;
    root /var/www/flexiconvert/public;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    
    index index.php;
    
    charset utf-8;
    
    # Increase upload limits for large files
    client_max_body_size 100M;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Increase timeout for file processing
        fastcgi_read_timeout 300;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName flexiconvert.example.com
    DocumentRoot /var/www/flexiconvert/public
    
    # Increase upload limits
    php_value upload_max_filesize 100M
    php_value post_max_size 100M
    php_value max_execution_time 300
    
    <Directory /var/www/flexiconvert/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 4. Set File Permissions
```bash
sudo chown -R www-data:www-data /var/www/flexiconvert
sudo chmod -R 755 /var/www/flexiconvert
sudo chmod -R 775 /var/www/flexiconvert/storage
sudo chmod -R 775 /var/www/flexiconvert/bootstrap/cache
```

### 5. Configure SSL (Recommended)
```bash
# Using Certbot for Let's Encrypt
sudo certbot --nginx -d flexiconvert.example.com
```

## Monitoring and Maintenance

### Log Files
- Application logs: `storage/logs/laravel.log`
- Conversion logs: Check for PDF tools specific entries
- Web server logs: `/var/log/nginx/` or `/var/log/apache2/`

### Performance Monitoring
```bash
# Monitor queue jobs
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Monitor file cleanup
php artisan files:cleanup --dry-run
```

### Health Checks
```bash
# Check system dependencies
php artisan tinker
```
```php
// Test LibreOffice
$lo = (new App\Http\Controllers\PDFToolsHelperMethods())->findLibreOffice();
echo $lo ? "✓ LibreOffice: $lo" : "✗ LibreOffice not found";

// Test Ghostscript  
$gs = (new App\Http\Controllers\PDFToolsHelperMethods())->findGhostscript();
echo $gs ? "✓ Ghostscript: $gs" : "✗ Ghostscript not found";

// Test storage
echo Storage::exists('pdf-tools') ? "✓ Storage accessible" : "✗ Storage not accessible";
```

## Troubleshooting

### Common Issues

#### LibreOffice Not Found
```bash
# Check installation
which libreoffice
# or
which soffice

# Set custom path in .env
LIBREOFFICE_PATH=/custom/path/to/soffice
```

#### Ghostscript Not Found
```bash
# Check installation
which gs

# Set custom path in .env
GHOSTSCRIPT_PATH=/custom/path/to/gs
```

#### Permission Errors
```bash
# Fix storage permissions
sudo chmod -R 775 storage/
sudo chown -R www-data:www-data storage/
```

#### Memory Limits
Edit `php.ini`:
```ini
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 100M
post_max_size = 100M
```

#### Queue Not Processing
```bash
# Check queue worker status
sudo supervisorctl status flexiconvert-worker

# Restart queue worker
php artisan queue:restart
```

### Performance Optimization

#### Enable OpCache
Edit `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
```

#### Configure Redis (Optional)
```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

#### Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_pdf_processings_user_created ON pdf_processings(user_id, created_at);
CREATE INDEX idx_pdf_processings_status ON pdf_processings(status);
```

## Security Considerations

1. **File Upload Security**
   - Validate file types and MIME types
   - Scan uploaded files for malware
   - Limit file sizes appropriately

2. **Access Control**
   - Implement proper authentication
   - Use CSRF protection
   - Validate user permissions

3. **File Storage**
   - Store files outside web root
   - Use UUID-based filenames
   - Implement file cleanup policies

4. **System Security**
   - Keep LibreOffice and Ghostscript updated
   - Run with minimal privileges
   - Monitor system resources

## Support

For technical support or questions:
- Check application logs in `storage/logs/`
- Review this documentation
- Test with provided PHPUnit tests
- Monitor system resource usage during conversions
