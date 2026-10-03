import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ command }) => ({
    // Base relatif saat build: aplikasi dilayani dari subfolder
    // (/asata-production/public), dan halaman Inertia kini dimuat per-kebutuhan.
    // Dengan base bawaan "/build/", chunk dinamis dicari di akar domain → 404.
    // Relatif = dicari di sebelah file JS yang memuatnya, benar di path mana pun.
    base: command === 'build' ? './' : undefined,
    plugins: [
        laravel({
            // app.js melayani halaman Blade lama, inertia.jsx melayani halaman
            // React. Keduanya sengaja hidup berdampingan selama migrasi
            // bertahap — halaman yang belum dipindahkan tetap berfungsi.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/inertia.jsx',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
}));
