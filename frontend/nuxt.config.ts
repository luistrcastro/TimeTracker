import vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'

export default defineNuxtConfig({
  compatibilityDate: '2025-01-01',

  future: {
    compatibilityVersion: 4,
  },

  ssr: false,

  nitro: {
    preset: 'static',
  },

  experimental: {
    viteEnvironmentApi: true,
  },

  components: {
    dirs: [{ path: '~/components', pathPrefix: false }],
  },

  modules: [
    '@pinia/nuxt',
    'pinia-plugin-persistedstate/nuxt',
    (_options, nuxt) => {
      nuxt.hooks.hook('vite:extendConfig', (config) => {
        config.plugins?.push(vuetify({ autoImport: true }))
      })
    },
  ],

  vite: {
    vue: {
      template: {
        transformAssetUrls,
      },
    },
  },

  build: {
    transpile: ['vuetify'],
  },

  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8020',
      appName: 'Time Tracker',
      appVersion: '3.0.0',
    },
  },

  app: {
    head: {
      title: 'Time Tracker',
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
    },
  },
})
