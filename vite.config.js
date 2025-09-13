import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    build: {
        outDir: 'dist', // <-- tambah ini
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/document-converter.js',
                'resources/js/image-converter.js',
                'resources/js/audio-converter.js',
                'resources/js/video-converter.js',
                'resources/js/profile.js',
                'resources/js/history.js',
            ],
            refresh: true,
        }),
        react({
            jsxImportSource: 'react',
            babel: {
                plugins: []
            }
        }),
        tailwindcss(),
    ],
});
