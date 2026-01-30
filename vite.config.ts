import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

const webEnabled = process.env.VITE_WEB_APP_ENABLED === 'true';

export default defineConfig({
    plugins: [
        laravel({
            input: [webEnabled ? 'resources/js/app.ts' : 'resources/js/empty.ts'],
            ssr: webEnabled ? 'resources/js/ssr.ts' : false,
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
