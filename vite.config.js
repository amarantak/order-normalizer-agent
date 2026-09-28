import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue'; // NUEVO: le enseña a Vite a compilar archivos .vue

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        // NUEVO: plugin de Vue, con la configuración que recomienda Laravel
        vue({
            template: {
                transformAssetUrls: {
                    base: null,             // deja que el plugin de Laravel resuelva las rutas de assets
                    includeAbsolute: false, // no reescribir URLs absolutas como /images/logo.png
                },
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});