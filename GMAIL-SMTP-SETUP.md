# Setup Gmail SMTP untuk FlexiConvert

## 📧 Konfigurasi yang Diperlukan di .env

Berikut adalah konfigurasi Gmail SMTP yang perlu Anda masukkan ke file `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-gmail@gmail.com
MAIL_PASSWORD=your-16-digit-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="your-gmail@gmail.com"
MAIL_FROM_NAME="FlexiConvert"
```

## 🔧 Langkah-langkah Setup Gmail SMTP

### 1. Persiapan Akun Gmail
- Pastikan Anda memiliki akun Gmail yang aktif
- Akun Gmail harus memiliki 2-Factor Authentication (2FA) yang aktif

### 2. Aktifkan 2-Factor Authentication
1. Buka [Google Account Security](https://myaccount.google.com/security)
2. Di bagian "Signing in to Google", klik **2-Step Verification**
3. Ikuti petunjuk untuk mengaktifkan 2FA (gunakan SMS atau Google Authenticator)

### 3. Generate App Password
1. Setelah 2FA aktif, kembali ke [Google Account Security](https://myaccount.google.com/security)
2. Di bagian "Signing in to Google", klik **2-Step Verification**
3. Scroll ke bawah dan klik **App passwords**
4. Pilih **Mail** sebagai app dan **Other (Custom name)** sebagai device
5. Masukkan nama: **FlexiConvert Laravel**
6. Klik **Generate**
7. **SIMPAN** 16-digit password yang muncul (contoh: `abcd efgh ijkl mnop`)

### 4. Setup File .env
1. Copy file `.env.example` ke `.env`:
   ```bash
   cp .env.example .env
   ```

2. Edit file `.env` dan ganti nilai berikut:
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=email-anda@gmail.com
   MAIL_PASSWORD=abcd efgh ijkl mnop
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS="email-anda@gmail.com"
   MAIL_FROM_NAME="FlexiConvert"
   ```

   **⚠️ PENTING:**
   - Ganti `email-anda@gmail.com` dengan Gmail Anda yang sebenarnya
   - Ganti `abcd efgh ijkl mnop` dengan App Password 16-digit yang Anda generate
   - **JANGAN** gunakan password Gmail biasa, harus App Password!

### 5. Setup Queue untuk Email (Opsional tapi Direkomendasikan)
1. Pastikan queue connection di `.env`:
   ```env
   QUEUE_CONNECTION=database
   ```

2. Buat table untuk queue:
   ```bash
   php artisan queue:table
   php artisan migrate
   ```

3. Jalankan queue worker:
   ```bash
   php artisan queue:work
   ```

## 🧪 Testing Email Configuration

### Test 1: Artisan Tinker
```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\Mail;

Mail::raw('Test email dari FlexiConvert', function ($message) {
    $message->to('email-tujuan@gmail.com')
            ->subject('Test Email FlexiConvert');
});
```

### Test 2: Register User Baru
1. Buka aplikasi di browser
2. Register dengan email yang valid
3. Cek inbox email untuk verification link
4. Klik link verifikasi
5. Cek inbox lagi untuk welcome email

## ⚠️ Troubleshooting

### Error: "Authentication failed"
- Pastikan 2FA aktif di Google Account
- Pastikan menggunakan App Password, bukan password biasa
- Cek kembali username dan app password di `.env`

### Error: "Connection timeout"
- Cek koneksi internet
- Pastikan firewall tidak memblokir port 587
- Coba ganti `MAIL_PORT=587` ke `MAIL_PORT=465` dan `MAIL_ENCRYPTION=ssl`

### Email tidak terkirim
- Cek log Laravel: `storage/logs/laravel.log`
- Pastikan queue worker berjalan jika menggunakan queue
- Cek spam folder di email tujuan

### Gmail Security Warning
- Jika muncul notifikasi "Less secure app", abaikan karena kita menggunakan App Password
- App Password sudah aman dan direkomendasikan oleh Google

## 🔒 Keamanan

### DO's:
- ✅ Gunakan App Password, bukan password Gmail biasa
- ✅ Simpan App Password di `.env` (tidak di-commit ke Git)
- ✅ Aktifkan 2FA di Google Account
- ✅ Gunakan email yang valid dan aktif

### DON'Ts:
- ❌ Jangan commit file `.env` ke Git
- ❌ Jangan share App Password dengan orang lain
- ❌ Jangan gunakan password Gmail biasa
- ❌ Jangan disable 2FA setelah generate App Password

## 📝 Catatan Penting

1. **App Password hanya bisa dilihat sekali** saat generate, jadi simpan baik-baik
2. **Jika lupa App Password**, hapus yang lama dan generate yang baru
3. **Satu App Password bisa digunakan untuk multiple aplikasi** Laravel
4. **Gmail SMTP gratis** dengan limit 500 email per hari untuk personal use
5. **Untuk production**, pertimbangkan menggunakan service seperti SendGrid, Mailgun, atau SES

## 🚀 Langkah Selanjutnya

Setelah setup selesai:
1. Test email verification dengan register user baru
2. Cek apakah welcome email terkirim setelah verifikasi
3. Monitor log untuk memastikan tidak ada error
4. Setup monitoring untuk email delivery rate
5. Pertimbangkan setup queue worker sebagai service untuk production
