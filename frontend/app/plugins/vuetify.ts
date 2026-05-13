import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import { createVuetify } from 'vuetify'

export default defineNuxtPlugin((app) => {
  const vuetify = createVuetify({
    theme: {
      defaultTheme: 'light',
      themes: {
        light: {
          colors: {
            primary: '#4f5af0',
            secondary: '#6b7280',
            surface: '#ffffff',
            background: '#f9fafb',
          },
        },
        dark: {
          colors: {
            primary: '#5b6af5',
            secondary: '#9ca3af',
            surface: '#1e2130',
            background: '#13151f',
          },
        },
      },
    },
  })
  app.vueApp.use(vuetify)
})
