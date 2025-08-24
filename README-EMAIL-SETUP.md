# Setup Email Verification untuk FlexiConvert

## Konfigurasi Email

Untuk mengaktifkan pengiriman email verifikasi yang sesungguhnya, ikuti langkah-langkah berikut:

### 1. Setup Environment Variables

Copy file `.env.example` ke `.env` dan konfigurasikan email settings:

```bash
cp .env.example .env
```

### 2. Pilih Provider Email

#### Option A: Gmail SMTP (Recommended untuk development)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="your-email@gmail.com"
MAIL_FROM_NAME="FlexiConvert"
```

**Cara mendapatkan App Password untuk Gmail:**
1. Buka Google Account settings
2. Security → 2-Step Verification
3. App passwords → Generate new app password
4. Gunakan password yang dihasilkan di `MAIL_PASSWORD`

#### Option B: Mailtrap (Untuk testing)
```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@flexiconvert.com"
MAIL_FROM_NAME="FlexiConvert"
```

#### Option C: Local development (logs only)
```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@flexiconvert.com"
MAIL_FROM_NAME="FlexiConvert"
```

### 3. Setup Queue untuk Email

Untuk performa yang lebih baik, setup queue untuk mengirim email:

```bash
php artisan queue:table
php artisan migrate
```

Jalankan queue worker:
```bash
php artisan queue:work
```

### 4. Test Email Configuration

Test apakah email bisa dikirim:

```bash
php artisan tinker
```

```php
Mail::raw('Test email', function ($message) {
    $message->to('test@example.com')->subject('Test Email');
});
```

## Fitur Email yang Diimplementasikan

### 1. Email Verifikasi Custom
- Email verifikasi dalam bahasa Indonesia
- Design yang menarik dan profesional
- Link verifikasi dengan expiry 60 menit

### 2. Welcome Email
- Dikirim otomatis setelah email diverifikasi
- Berisi informasi tentang fitur-fitur FlexiConvert
- Design responsive dan modern

### 3. Security Features
- Rate limiting untuk email verification
- Signed URLs untuk keamanan
- Throttling untuk mencegah spam

## Troubleshooting

### Email tidak terkirim
1. Cek konfigurasi SMTP di `.env`
2. Pastikan firewall tidak memblokir port 587
3. Cek log Laravel: `storage/logs/laravel.log`

### Gmail SMTP Error
1. Pastikan 2FA aktif di Google Account
2. Gunakan App Password, bukan password biasa
3. Aktifkan "Less secure app access" jika diperlukan

### Queue tidak berjalan
1. Pastikan queue driver di `.env`: `QUEUE_CONNECTION=database`
2. Jalankan migration untuk queue table
3. Start queue worker: `php artisan queue:work`

## Testing

Untuk testing email verification flow:
1. Register user baru
2. Cek email yang diterima
3. Klik link verifikasi
4. Pastikan welcome email diterima setelah verifikasi

## Production Deployment

Untuk production, gunakan:
- Email service provider seperti SendGrid, Mailgun, atau SES
- Setup proper queue worker dengan supervisor
- Monitor email delivery rates
- Setup email analytics dan tracking
