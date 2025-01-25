// https://nuxt.com/docs/api/configuration/nuxt-config

export default defineNuxtConfig({
  ssr: false, // Disable Server-Side Rendering, ensuring the app is fully client-side


  app: {
    baseURL: '/', // Match your WordPress admin page URL
    buildAssetsDir: '_nuxt/', // Keep the default assets directory
    cdnURL: '/wp-content/plugins/bsr-shop-manager/dist/public/', // Prepend plugin path to assets
  },

  runtimeConfig: {
    public: {
      baseUrl: process.env.LOCAL_DEV_URL || '',
      wooConsumerKey: process.env.WOO_CONSUMER_KEY || '',
      wooConsumerSecret: process.env.WOO_CONSUMER_SECRET || '',
    },
  },

  modules: ['@nuxt/ui', '@pinia/nuxt'],

  css: ['~/assets/css/main.css'],

  nitro: {
    preset: 'static',
    output: {
      dir: '../dist'
    }
  },
  vite: {
    build: {
      rollupOptions: {
        output: {
          entryFileNames: '_nuxt/entry.[hash].js', // Prefixed entry file
          chunkFileNames: '_nuxt/chunk-[hash].js', // Prefixed chunks
          assetFileNames: (assetInfo) => {
            if (assetInfo.name?.endsWith('.css')) {
              // Add hash to CSS files
              return '_nuxt/[name].[hash][extname]';
            }
            // Default for other assets
            return '_nuxt/[name].[hash][extname]';
          },
        },
      },
    },
  },

  experimental: {
    appManifest: false
  },

  compatibilityDate: '2024-11-01',

  devtools: { enabled: true }
})