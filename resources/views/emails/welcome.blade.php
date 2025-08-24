<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang di FlexiConvert</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Instrument Sans', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <div class="max-w-2xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6 text-center">
                <h1 class="text-3xl font-bold text-white">FlexiConvert</h1>
                <p class="text-blue-100 mt-2">Platform Konversi File Terpercaya</p>
            </div>

            <!-- Content -->
            <div class="px-8 py-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Selamat Datang, {{ $user->name }}!</h2>
                
                <p class="text-gray-600 mb-4">Terima kasih telah bergabung dengan FlexiConvert! Kami sangat senang Anda menjadi bagian dari komunitas kami.</p>
                
                <p class="text-gray-600 mb-6">Email Anda telah berhasil diverifikasi dan akun Anda sekarang aktif. Anda dapat mulai menggunakan semua fitur yang tersedia di platform kami.</p>

                <!-- Features Section -->
                <div class="bg-gray-50 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Apa yang bisa Anda lakukan dengan FlexiConvert:</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700">Konversi dokumen (PDF, Word, Excel, PowerPoint)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700">Konversi gambar (JPG, PNG, GIF, WebP, dan lainnya)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700">Konversi audio (MP3, WAV, FLAC, AAC)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700">Konversi video (MP4, AVI, MOV, WebM)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700">Proses batch untuk multiple file</span>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700">Keamanan dan privasi terjamin</span>
                        </div>
                    </div>
                </div>

                <p class="text-gray-600 mb-6">Mulai konversi file Anda sekarang dengan mengklik tombol di bawah ini:</p>
                
                <!-- CTA Button -->
                <div class="text-center mb-6">
                    <a href="{{ route('dashboard') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition duration-200 shadow-lg hover:shadow-xl">
                        Mulai Konversi
                    </a>
                </div>

                <p class="text-gray-600">Jika Anda memiliki pertanyaan atau membutuhkan bantuan, jangan ragu untuk menghubungi tim support kami.</p>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-4 border-t border-gray-200">
                <div class="text-center text-sm text-gray-500 space-y-1">
                    <p>Email ini dikirim secara otomatis. Mohon jangan membalas email ini.</p>
                    <p>&copy; {{ date('Y') }} FlexiConvert. Semua hak dilindungi.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
