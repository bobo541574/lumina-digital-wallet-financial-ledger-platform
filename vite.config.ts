import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig(({ mode }) => {
    const isProduction = mode === 'production';

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.ts'],
                refresh: true,
            }),
            vue({
                template: {
                    compilerOptions: {
                        isCustomElement: (tag) => tag.startsWith('iconify-'),
                    },
                },
            }),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            },
        },
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
        build: {
            target: 'es2022',
            // Source maps are off in production by default; opt in with
            // VITE_BUILD_SOURCEMAP=true when uploading them to an error tracker.
            sourcemap: process.env.VITE_BUILD_SOURCEMAP === 'true',
            // Strip console/debugger output from production bundles. Vite 8
            // minifies with the Oxc minifier, so this is configured through the
            // rolldown output options rather than the deprecated `esbuild` key.
            rolldownOptions: {
                output: {
                    minify: isProduction
                        ? {
                              compress: {
                                  dropConsole: true,
                                  dropDebugger: true,
                              },
                          }
                        : undefined,
                },
            },
        },
    };
});