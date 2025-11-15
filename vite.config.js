import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/blade.css'],
            refresh: ['resources/views/**/*.blade.php'],
        }),
        tailwindcss(),
    ],
});
