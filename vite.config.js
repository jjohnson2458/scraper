import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    root: 'resources/js',
    build: {
        outDir: '../../public/assets/js',
        emptyOutDir: false,
        rollupOptions: {
            input: {
                app: 'resources/js/app.jsx',
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            },
        },
    },
    server: {
        origin: 'http://localhost:5173',
    },
});
