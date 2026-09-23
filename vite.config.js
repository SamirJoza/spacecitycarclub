// vite.config.js
import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import laravel from 'laravel-vite-plugin'
import { wordpressPlugin, wordpressThemeJson } from '@roots/vite-plugin'
import fg from 'fast-glob'

// Auto-discover blocks
const blockJs  = fg.sync('resources/js/blocks/*.js',  { dot: false })
const blockCss = fg.sync('resources/css/blocks/*.css', { dot: false })

export default defineConfig({
  // This base should point at where WP serves your built assets.
  // Keep it consistent with how you reference files in PHP.
  base: '/wp-content/themes/spacecitycarclub/public/build/',

  plugins: [
    tailwindcss(),

    // Include globals + editor + all blocks
    laravel({
      input: [
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/editor.css',
        'resources/js/editor.js',
        ...blockJs,
        ...blockCss,
      ],
      refresh: true,
    }),

    wordpressPlugin(),

    // Optional: generate theme.json from Tailwind tokens
    wordpressThemeJson({
      disableTailwindColors: false,
      disableTailwindFonts: false,
      disableTailwindFontSizes: false,
    }),
  ],

  // Helpful aliases (unchanged)
  resolve: {
    alias: {
      '@scripts': '/resources/js',
      '@styles': '/resources/css',
      '@fonts': '/resources/fonts',
      '@images': '/resources/images',
    },
  },

  // Control how Vite/Rollup names the emitted files
  build: {
    rollupOptions: {
      output: {
        // JS entry files
        entryFileNames: (chunk) => {
          const name = chunk.name
          if (name.startsWith('resources/js/blocks/')) {
            const stem = name.replace('resources/js/blocks/', '').replace(/\.js$/, '')
            return `blocks/${stem}/${stem}-[hash].js`
          }
          // Everything else (global app/editor)
          return 'assets/[name]-[hash].js'
        },

        // CSS + other assets
        assetFileNames: (asset) => {
          const src = asset.name || ''
          // Per-block CSS that you authored as standalone files
          if (src.includes('resources/css/blocks/')) {
            const stem = src.replace('resources/css/blocks/', '').replace(/\.css$/, '')
            return `blocks/${stem}/${stem}-[hash][extname]`
          }

          // Images/fonts imported from a block JS entry — drop them next to the block
          if (src.includes('resources/js/blocks/')) {
            const stem = src.replace('resources/js/blocks/', '').split('/')[0].replace(/\.js$/, '')
            return `blocks/${stem}/assets/[name]-[hash][extname]`
          }

          // Default for global assets
          return 'assets/[name]-[hash][extname]'
        },

        // Shared chunks (vendor, etc.) stay global for better caching
        chunkFileNames: 'assets/[name]-[hash].js',
      },
    },
  },

  // If you’re proxying through a local domain (e.g., Valet/LocalWP),
  // you can uncomment and tune this HMR block:
  // server: {
  //   host: '0.0.0.0',
  //   strictPort: true,
  //   port: 5173,
  //   hmr: {
  //     host: 'your.localdomain.test',
  //     protocol: 'wss',
  //   },
  // },
})
