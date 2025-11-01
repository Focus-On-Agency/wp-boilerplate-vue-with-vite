import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import path from 'node:path'
import postcssUrl from 'postcss-url'
import vue from '@vitejs/plugin-vue'
import liveReload from 'vite-plugin-live-reload'
import { viteStaticCopy } from 'vite-plugin-static-copy'

export default defineConfig(({ mode }) => {
  const isProd = mode === 'production'

  return {
    plugins: [
      tailwindcss(),
      vue(),
      // Live reload solo in dev
      !isProd && liveReload(`${__dirname}/**/*.php`),
      // Copia solo i font necessari (woff/woff2) in prod
      viteStaticCopy({
        targets: [
          {
            src: [
              'node_modules/primeicons/fonts/*.woff2',
              'node_modules/primeicons/fonts/*.woff'
            ],
            dest: 'fonts'
          }
        ]
      })
    ].filter(Boolean),

    css: {
      // Assicurati che la purge di Tailwind sia configurata correttamente nel tailwind.config.js
      postcss: {
        plugins: [
          postcssUrl({
            url: (asset) => {
              // Riscrivi solo i font di primeicons
              if (asset.url.includes('primeicons/fonts')) {
                const filename = asset.url.split('/').pop()
                // in dev puntiamo all’assets del plugin (comodo per hot reload)
                if (!isProd) {
                  return `/wp-content/plugins/wp-restaurant-reservations-and-takeaways/assets/fonts/${filename}`
                }
                // in prod riferimenti relativi (abbiamo copiato i file in assets/fonts)
                return `../fonts/${filename}`
              }
              return asset.url
            },
          }),
        ],
      },
    },

    build: {
      manifest: 'manifest.json',
      outDir: 'assets',
      assetsDir: '',
      emptyOutDir: true,

      // Hash dei file in prod per caching forte (usa il manifest in PHP per risolvere i path)
      sourcemap: false,
      minify: 'esbuild',
      cssMinify: 'esbuild',
      modulePreload: false, // in WP evita preloads extra non utili
      assetsInlineLimit: 2048, // evita di inlinare asset troppo grandi

      rollupOptions: {
        input: [
          'resources/js/admin/main.js',
          'resources/js/frontend/main.js'
        ],
        output: {
          // nomi con hash per caching; gestisci con il manifest in PHP
          chunkFileNames: 'js/[name]-[hash].js',
          entryFileNames: 'js/[name]-[hash].js',
          assetFileNames: ({ name }) => {
            if (/\.css$/.test(name ?? '')) return 'css/[name]-[hash][extname]'
            return name && name.includes('primeicons') ? 'fonts/[name]-[hash][extname]' : '[name]-[hash][extname]'
          },
          manualChunks(id, { getModuleInfo }) {
            // Splitting mirato per massimizzare cache tra admin/front
            if (id.includes('node_modules')) {
              if (id.includes('/vue/')) return 'vendor-vue'
              if (id.includes('/primevue/')) return 'vendor-primevue'
              if (id.includes('/chart.js/')) return 'vendor-chartjs'
              if (id.includes('moment') || id.includes('dayjs')) return 'vendor-date'
              if (id.includes('@primeuix')) return 'vendor-primeuix'
              return 'vendor'
            }
          },
        },
        treeshake: {
          moduleSideEffects: 'no-external',
          propertyReadSideEffects: false,
          tryCatchDeoptimization: false,
          unknownGlobalSideEffects: false,
        },
      },

      // ESBuild “aggressivo”
      target: 'es2019',
      // Rimuove console/debugger in prod
      // (se ti serve in prod, togli "console")
      esbuild: {
        drop: ['console', 'debugger'],
        legalComments: 'none'
      }
    },

    define: {
      // Ottimizza Vue 3.2.x in produzione
      __VUE_OPTIONS_API__: true,
      __VUE_PROD_DEVTOOLS__: false,
      'process.env.NODE_ENV': JSON.stringify(isProd ? 'production' : 'development'),
    },

    resolve: {
      alias: {
        'vue': 'vue/dist/vue.esm-bundler.js',
        '@': path.resolve(__dirname, 'resources/js'),
        '@admin': path.resolve(__dirname, 'resources/js/admin'),
        '@frontend': path.resolve(__dirname, 'resources/js/frontend'),
      },
    },

    server: {
      host: '0.0.0.0',
      port: 5173,
      strictPort: true,
      origin: `${process.env.DDEV_PRIMARY_URL.replace(/:\d+$/, '')}:5173`,
      cors: {
        origin: /https?:\/\/([A-Za-z0-9\-\.]+)?(\.ddev\.site)(?::\d+)?$/,
      },
    }
  }
})