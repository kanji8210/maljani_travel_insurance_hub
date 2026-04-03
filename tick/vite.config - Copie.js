import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

const WP_GRAPHQL = 'https://mtj.ivk.mybluehost.me/website_e48ea083';

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/graphql': {
        target: WP_GRAPHQL,
        changeOrigin: true,
        secure: false,
        timeout: 60000,
        proxyTimeout: 60000,
      },
      '/wp-json': {
        target: WP_GRAPHQL,
        changeOrigin: true,
        secure: false,
        timeout: 60000,
        proxyTimeout: 60000,
      },
    },
  },
})
