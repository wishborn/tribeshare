import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

export default defineConfig({
    publicDir: false,
    define: { 'process.env.NODE_ENV': JSON.stringify('production') },
    plugins: [react(), tailwindcss()],
    build: {
        outDir: 'spikes/pass-0/dist',
        lib: {
            entry: 'spikes/pass-0/surface.tsx',
            name: 'PassZero',
            formats: ['iife'],
            fileName: () => 'surface.js',
            cssFileName: 'surface',
        },
    },
});
